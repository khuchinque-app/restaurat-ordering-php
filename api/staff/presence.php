<?php
/**
 * Staff presence (online/offline) tracking API.
 *
 * POST /api/staff/presence.php  — heartbeat: updates current user's lastSeenAt
 * GET  /api/staff/presence.php  — returns all staff users with online status
 *
 * A user is considered "online" if their lastSeenAt is within the last 60 seconds.
 */
require_once dirname(__DIR__, 2) . '/auth.php';

$user = require_auth();
if (!in_array($user['role'], ['ADMIN', 'MANAGER', 'SUPERADMIN'])) {
    json_error(403, 'Staff access required');
}

$method = $_SERVER['REQUEST_METHOD'];

// Heartbeat — update this user's last-seen timestamp
if ($method === 'POST') {
    db_execute(
        'INSERT INTO UserPresence (userId, lastSeenAt)
         VALUES (?, datetime(\'now\'))
         ON CONFLICT(userId) DO UPDATE SET lastSeenAt = datetime(\'now\')',
        [$user['id']]
    );
    json_ok(['status' => 'online']);
}

// GET — list all staff with online/offline status
if ($method === 'GET') {
    // Consider users online if seen within the last 60 seconds
    $staff = db_query(
        "SELECT u.id, u.name, u.role, u.restaurantId,
                up.lastSeenAt,
                CASE WHEN up.lastSeenAt >= datetime('now', '-60 seconds') THEN 1 ELSE 0 END AS isOnline
         FROM User u
         LEFT JOIN UserPresence up ON up.userId = u.id
         WHERE u.role IN ('ADMIN', 'MANAGER', 'SUPERADMIN')
           AND u.isActive = 1
         ORDER BY isOnline DESC, u.name ASC"
    );

    json_ok(['staff' => $staff]);
}

json_error(405, 'Method not allowed');
