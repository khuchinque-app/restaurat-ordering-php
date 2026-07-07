<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/activity.php';

$user = get_auth_user();
if (!$user || !in_array($user['role'], ['SUPERADMIN', 'ADMIN'])) {
    json_error(403, 'Admin access required');
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') json_error(405, 'Method not allowed');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $body['type'] ?? '';

// Supported types: 'storefronts', 'cashier'
if (!in_array($type, ['storefronts', 'cashier'])) {
    json_error(400, 'Invalid type. Use "storefronts" or "cashier"');
}

try {
    if ($type === 'storefronts') {
        // Reset all storefront settings / inactive restaurants
        db_execute('UPDATE Restaurant SET isActive = 1, updatedAt = datetime("now") WHERE isActive = 0');
    } elseif ($type === 'cashier') {
        // Clear all orders and related data
        $rids = db_query('SELECT id FROM Restaurant');
        foreach ($rids as $r) {
            $rid = $r['id'];
            db_transaction(function($db) use ($rid) {
                $db->prepare("DELETE FROM OrderItem WHERE orderId IN (SELECT id FROM \"Order\" WHERE restaurantId = ?)")->execute([$rid]);
                $db->prepare("DELETE FROM Payment WHERE orderId IN (SELECT id FROM \"Order\" WHERE restaurantId = ?)")->execute([$rid]);
                $db->prepare("DELETE FROM Notification WHERE orderId IN (SELECT id FROM \"Order\" WHERE restaurantId = ?)")->execute([$rid]);
                $db->prepare("DELETE FROM ChatRoom WHERE orderId IN (SELECT id FROM \"Order\" WHERE restaurantId = ?)")->execute([$rid]);
                $db->prepare("DELETE FROM \"Order\" WHERE restaurantId = ?")->execute([$rid]);
            });
        }
    }

    log_activity($user, 'DELETE_ALL', 'System', '', "Deleted all $type data");
    json_ok(['message' => "All $type data cleared successfully"]);
} catch (Exception $e) {
    json_error(500, 'Failed: ' . $e->getMessage());
}
