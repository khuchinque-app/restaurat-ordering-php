<?php
/**
 * Notification API for admin & superadmin panels.
 *
 * GET  /api/notifications/index.php       — returns unread notifications for the current user
 * GET  /api/notifications/index.php?all=1 — returns all recent notifications
 * PUT  /api/notifications/index.php?id=X  — marks a notification as read
 * PUT  /api/notifications/index.php?all=1 — marks ALL notifications as read for current user
 */
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $show_all = !empty($_GET['all']);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

    if ($show_all) {
        $notifications = db_query(
            'SELECT id, orderId, type, title, message, isRead, createdAt
             FROM Notification
             WHERE userId = ?
             ORDER BY createdAt DESC
             LIMIT ?',
            [$user['id'], $limit]
        );
    } else {
        $notifications = db_query(
            'SELECT id, orderId, type, title, message, isRead, createdAt
             FROM Notification
             WHERE userId = ? AND isRead = 0
             ORDER BY createdAt DESC
             LIMIT ?',
            [$user['id'], $limit]
        );
    }

    $unread_count = (int)(db_fetch(
        'SELECT COUNT(*) AS n FROM Notification WHERE userId = ? AND isRead = 0',
        [$user['id']]
    )['n'] ?? 0);

    json_ok([
        'notifications' => $notifications,
        'unreadCount'   => $unread_count,
    ]);
}

if ($method === 'PUT') {
    $id = $_GET['id'] ?? '';

    if ($id === 'all') {
        // Mark all as read
        db_execute(
            'UPDATE Notification SET isRead = 1 WHERE userId = ? AND isRead = 0',
            [$user['id']]
        );
        json_ok(null, 'All notifications marked as read');
    } elseif ($id) {
        // Mark single notification as read
        db_execute(
            'UPDATE Notification SET isRead = 1 WHERE id = ? AND userId = ?',
            [$id, $user['id']]
        );
        json_ok(null, 'Notification marked as read');
    } else {
        json_error(400, 'Notification ID required');
    }
}

json_error(405, 'Method not allowed');
