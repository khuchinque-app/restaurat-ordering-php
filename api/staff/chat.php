<?php
require_once dirname(__DIR__, 2) . '/auth.php';

$user = require_auth();
if (!in_array($user['role'], ['ADMIN', 'MANAGER', 'SUPERADMIN'])) {
    json_error(403, 'Admin access required');
}

$method = $_SERVER['REQUEST_METHOD'];

// Parse request body for all methods
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $since  = $_GET['since'] ?? null;   // ISO datetime — fetch only newer messages
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $params = [];
    $where  = [];

    if ($since) {
        $where[]  = 'createdAt > ?';
        $params[] = $since;
    }

    $w    = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $msgs = db_query(
        "SELECT id, senderId, senderName, senderRole, message, isRead, createdAt
         FROM StaffChat $w ORDER BY createdAt ASC LIMIT $limit",
        $params
    );

    // Mark as read for this user (messages not sent by them)
    db_execute(
        "UPDATE StaffChat SET isRead = 1
         WHERE senderId != ? AND isRead = 0",
        [$user['id']]
    );

    // Unread count (messages sent by others, not yet read)
    $unread = (int)(db_fetch(
        "SELECT COUNT(*) AS n FROM StaffChat WHERE senderId != ? AND isRead = 0",
        [$user['id']]
    )['n'] ?? 0);

    json_ok(['messages' => $msgs, 'unread' => $unread]);
}

if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($body['message'] ?? '');
    if (!$message) json_error(400, 'Message cannot be empty');
    if (strlen($message) > 1000) json_error(400, 'Message too long (max 1000 chars)');

    $id = new_id();
    db_execute(
        'INSERT INTO StaffChat (id, senderId, senderName, senderRole, message, isRead, createdAt)
         VALUES (?, ?, ?, ?, ?, 0, datetime("now"))',
        [$id, $user['id'], $user['name'], $user['role'], $message]
    );

    $msg = db_fetch('SELECT * FROM StaffChat WHERE id = ?', [$id]);
    json_ok($msg);
}

// ── DELETE: Delete a staff chat message ──────────────
if ($method === 'DELETE') {
    $msg_id = $_GET['id'] ?? $body['id'] ?? '';
    if (!$msg_id) json_error(400, 'Message ID required');

    $msg = db_fetch('SELECT * FROM StaffChat WHERE id = ?', [$msg_id]);
    if (!$msg) json_error(404, 'Message not found');

    // Superadmin can delete any message; admin can only delete their own
    if ($user['role'] !== 'SUPERADMIN' && $msg['senderId'] !== $user['id']) {
        json_error(403, 'You can only delete your own messages');
    }

    db_execute('DELETE FROM StaffChat WHERE id = ?', [$msg_id]);
    json_ok(['deleted' => true]);
}

json_error(405, 'Method not allowed');
