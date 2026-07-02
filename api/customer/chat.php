<?php
/**
 * Customer Chat API — handles messages between customers and staff.
 * GET:  Fetch messages for a chat (by chat_id or customer identifier)
 * POST: Send a message (from customer or staff)
 */
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

session_init();
$method = $_SERVER['REQUEST_METHOD'];

// Parse request body for all methods
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// GET — fetch messages
if ($method === 'GET') {
    $chat_id = $_GET['chat_id'] ?? '';
    $since   = $_GET['since'] ?? null;
    $sender  = $_GET['sender'] ?? '';
    $restaurant_slug = $_GET['restaurant'] ?? '';
    $limit   = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $params  = [];
    $where   = [];

    if ($chat_id) {
        // Fetch by conversation — find ALL messages including admin replies
        $conversation = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$chat_id]);
        if ($conversation) {
            if ($conversation['orderId']) {
                // Order conversations: match by orderId (both customer + admin msgs share it)
                $where[] = 'orderId = ?';
                $params[] = $conversation['orderId'];
            } else {
                // Support conversations: match by restaurantId (catches both customer + admin replies)
                $where[] = 'restaurantId = ?';
                $params[] = $conversation['restaurantId'];
            }
        }
    }

    if ($sender) {
        // Filter by sender name so customers only see their own conversation
        $where[] = 'senderName = ?';
        $params[] = $sender;
    }

    if ($restaurant_slug) {
        $r = get_restaurant($restaurant_slug);
        if ($r) {
            $where[] = 'restaurantId = ?';
            $params[] = $r['id'];
        }
    }

    if ($since) {
        $where[] = 'createdAt >= ?';
        $params[] = $since;
    }

    $w    = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $msgs = db_query(
        "SELECT id, chatType, orderId, restaurantId, senderId, senderName, senderRole, message, isRead, createdAt
         FROM CustomerChat $w ORDER BY createdAt ASC LIMIT $limit",
        $params
    );

    // Mark messages as read when admin views a specific conversation
    if ($chat_id && $msgs) {
        $conversation = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$chat_id]);
        if ($conversation) {
            if ($conversation['orderId']) {
                db_execute('UPDATE CustomerChat SET isRead = 1 WHERE orderId = ?',
                    [$conversation['orderId']]);
            } elseif ($conversation['senderId']) {
                db_execute('UPDATE CustomerChat SET isRead = 1 WHERE senderId = ?',
                    [$conversation['senderId']]);
            } else {
                db_execute('UPDATE CustomerChat SET isRead = 1 WHERE senderName = ? AND senderId IS NULL',
                    [$conversation['senderName']]);
            }
        }
    }

    json_ok(['messages' => $msgs]);
}

// POST — send a message
if ($method === 'POST') {
    $message = trim($body['message'] ?? '');
    $chat_id = $body['chat_id'] ?? '';
    $order_id = $body['order_id'] ?? null;

    if (!$message) json_error(400, 'Message cannot be empty');
    if (strlen($message) > 2000) json_error(400, 'Message too long');

    $user = get_auth_user();
    $sender_id   = $user['id'] ?? null;
    // If customer_name is provided in body, use it (storefront chat bypasses session)
    $sender_name = !empty($body['customer_name']) ? $body['customer_name'] : ($user['name'] ?? 'Guest');
    $sender_role = !empty($body['customer_name']) ? 'CUSTOMER' : ($user['role'] ?? 'CUSTOMER');
    $restaurant_id = $body['restaurant_id'] ?? null;

    // If replying to existing conversation, get its restaurant/order info
    if ($chat_id) {
        $conversation = db_fetch('SELECT orderId, restaurantId FROM CustomerChat WHERE id = ?', [$chat_id]);
        if ($conversation) {
            $order_id = $order_id ?? $conversation['orderId'];
            $restaurant_id = $restaurant_id ?? $conversation['restaurantId'];
        }
    }

    // Resolve restaurant from order if not set
    if ($order_id && !$restaurant_id) {
        $order = db_fetch('SELECT restaurantId FROM "Order" WHERE id = ?', [$order_id]);
        $restaurant_id = $order['restaurantId'] ?? null;
    }

    if (!$restaurant_id) json_error(400, 'restaurant_id is required');

    $id = new_id();
    db_execute(
        'INSERT INTO CustomerChat (id, chatType, orderId, restaurantId, senderId, senderName, senderRole, message, isRead, createdAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, datetime("now"))',
        [$id, $order_id ? 'ORDER' : 'SUPPORT', $order_id, $restaurant_id, $sender_id, $sender_name, $sender_role, $message]
    );

    $msg = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$id]);
    json_ok($msg);
}

// ── PUT: Edit a message ─────────────────────────────────
if ($method === 'PUT') {
    $user = get_auth_user();
    if (!$user || !in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
        json_error(403, 'Admin access required');
    }

    $edit_id = $body['id'] ?? '';
    $new_message = trim($body['message'] ?? '');
    if (!$edit_id) json_error(400, 'Message ID required');
    if (!$new_message) json_error(400, 'Message cannot be empty');
    if (strlen($new_message) > 2000) json_error(400, 'Message too long');

    $msg = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$edit_id]);
    if (!$msg) json_error(404, 'Message not found');

    if ($user['role'] !== 'SUPERADMIN' && $msg['senderId'] !== $user['id']) {
        json_error(403, 'You can only edit your own messages');
    }

    db_execute('UPDATE CustomerChat SET message = ?, createdAt = createdAt WHERE id = ?', [$new_message, $edit_id]);
    $updated = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$edit_id]);
    json_ok($updated);
}

// ── DELETE: Delete conversation ──────────────────────────
if ($method === 'DELETE') {
    $user = get_auth_user();
    if (!$user || !in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
        json_error(403, 'Admin access required');
    }

    $delete_sender = $_GET['sender'] ?? $body['sender_name'] ?? '';
    $delete_order  = $_GET['order_id'] ?? $body['order_id'] ?? '';
    $delete_restaurant = $_GET['restaurant_id'] ?? $body['restaurant_id'] ?? '';
    $delete_id = $_GET['id'] ?? $body['id'] ?? '';

    // Delete a single message by ID
    if ($delete_id) {
        $msg = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$delete_id]);
        if (!$msg) json_error(404, 'Message not found');
        // Superadmin can delete any; admin can delete own
        if ($user['role'] !== 'SUPERADMIN' && $msg['senderId'] !== $user['id']) {
            json_error(403, 'You can only delete your own messages');
        }
        db_execute('DELETE FROM CustomerChat WHERE id = ?', [$delete_id]);
        json_ok(['deleted' => true, 'type' => 'message']);
    }

    if ($delete_sender && $delete_restaurant) {
        db_execute(
            'DELETE FROM CustomerChat WHERE senderName = ? AND restaurantId = ?',
            [$delete_sender, $delete_restaurant]
        );
        // Also clear presence
        db_execute(
            'DELETE FROM CustomerPresence WHERE senderName = ? AND restaurantId = ?',
            [$delete_sender, $delete_restaurant]
        );
        json_ok(['deleted' => true, 'type' => 'conversation']);
    }

    if ($delete_order) {
        db_execute('DELETE FROM CustomerChat WHERE orderId = ?', [$delete_order]);
        json_ok(['deleted' => true, 'type' => 'order']);
    }

    json_error(400, 'sender_name+restaurant_id or order_id required');
}

json_error(405, 'Method not allowed');
