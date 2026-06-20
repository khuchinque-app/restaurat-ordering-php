<?php
require_once dirname(__DIR__) . '/db.php';

function log_activity(
    array  $user,
    string $action,
    string $entityType = '',
    string $entityId   = '',
    string $details    = '',
    string $restaurantName = ''
): void {
    try {
        $id  = new_id();
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        $rName = $restaurantName;
        if (!$rName && !empty($user['restaurantId'])) {
            $r = db_fetch('SELECT name FROM Restaurant WHERE id = ?', [$user['restaurantId']]);
            $rName = $r['name'] ?? '';
        }
        db_execute(
            'INSERT INTO AdminActivity
             (id, userId, userName, userEmail, userRole, restaurantId, restaurantName,
              action, entityType, entityId, details, ipAddress, createdAt)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,datetime("now"))',
            [
                $id,
                $user['id'],
                $user['name']  ?? '',
                $user['email'] ?? '',
                $user['role']  ?? '',
                $user['restaurantId'] ?? null,
                $rName,
                $action,
                $entityType,
                $entityId,
                $details,
                $ip,
            ]
        );
    } catch (Throwable) {
        // Never break the main request due to logging failure
    }
}
