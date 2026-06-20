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
        // Fetch by conversation (orderId, senderId, or senderName for guests)
        $conversation = db_fetch('SELECT * FROM CustomerChat WHERE id = ?', [$chat_id]);
        if ($conversation) {
            if ($conversation['orderId']) {
                $where[] = 'orderId = ?';
                $params[] = $conversation['orderId'];
            } elseif ($conversation['senderId']) {
                $where[] = 'senderId = ?';
                $params[] = $conversation['senderId'];
            } else {
                // Guest customer — use senderName since senderId is null
                $where[] = 'senderName = ?';
                $params[] = $conversation['senderName'];
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
        $where[] = 'createdAt > ?';
        $params[] = $since;
    }

    $w    = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $msgs = db_query(
        "SELECT id, chatType, orderId, restaurantId, senderId, senderName, senderRole, message, isRead, createdAt
         FROM CustomerChat $w ORDER BY createdAt ASC LIMIT $limit",
        $params
    );

    json_ok(['messages' => $msgs]);
}

// POST — send a message
if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($body['message'] ?? '');
    $chat_id = $body['chat_id'] ?? '';
    $order_id = $body['order_id'] ?? null;

    if (!$message) json_error(400, 'Message cannot be empty');
    if (strlen($message) > 2000) json_error(400, 'Message too long');

    $user = get_auth_user();
    $sender_id   = $user['id'] ?? null;
    $sender_name = $user['name'] ?? ($body['customer_name'] ?? 'Guest');
    $sender_role = $user['role'] ?? 'CUSTOMER';
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

json_error(405, 'Method not allowed');
