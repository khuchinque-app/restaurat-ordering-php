<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/activity.php';

$user   = require_admin();
$method = $_SERVER['REQUEST_METHOD'];
$slug   = $_GET['restaurant'] ?? DEFAULT_RESTAURANT_SLUG;

$restaurant = db_fetch('SELECT * FROM Restaurant WHERE slug = ?', [$slug]);
if (!$restaurant) json_error(404, 'Restaurant not found');
$rid = $restaurant['id'];

// DELETE all stock (reset all to 0)
if ($method === 'DELETE') {
    if (!in_array($user['role'], ['SUPERADMIN', 'ADMIN'])) json_error(403, 'Admin access required');
    db_execute('UPDATE MenuItem SET stockQuantity = 0, isAvailable = 0, updatedAt = datetime("now") WHERE restaurantId = ?', [$rid]);
    log_activity($user, 'STOCK RESET ALL', 'MenuItem', '', "All stock reset to 0 for {$restaurant['name']}");
    json_ok(null, 'All stock reset to 0');
}

if ($method === 'GET') {
    $where  = ['mi.restaurantId = ?'];
    $params = [$rid];
    if (!empty($_GET['lowStock']))   { $where[] = 'mi.stockQuantity IS NOT NULL AND mi.stockQuantity > 0 AND mi.stockQuantity <= mi.lowStockThreshold'; }
    if (!empty($_GET['outOfStock'])) { $where[] = 'mi.stockQuantity IS NOT NULL AND mi.stockQuantity = 0'; }

    $w = 'WHERE ' . implode(' AND ', $where);
    $items = db_query(
        "SELECT mi.id, mi.name, mi.stockQuantity, mi.lowStockThreshold, mi.isAvailable, c.name AS categoryName
         FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId $w ORDER BY mi.stockQuantity ASC",
        $params
    );
    json_ok($items);
}

if ($method === 'POST') {
    if ($user['role'] !== 'SUPERADMIN') json_error(403, 'Only Superadmin can adjust stock');
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $menu_item_id = $body['menuItemId'] ?? '';
    $quantity    = (int)($body['quantity'] ?? 0);
    $type        = $body['type'] ?? 'INCREASE';
    $reason      = $body['reason'] ?? null;

    if (!$menu_item_id || $quantity <= 0) json_error(400, 'menuItemId and quantity > 0 required');

    $item = db_fetch('SELECT * FROM MenuItem WHERE id = ?', [$menu_item_id]);
    if (!$item) json_error(404, 'Menu item not found');

    $current = (int)($item['stockQuantity'] ?? 0);
    if ($type === 'INCREASE' || $type === 'ADD') {
        $new_qty = $current + $quantity;
    } elseif ($type === 'DECREASE' || $type === 'REMOVE') {
        $new_qty = max(0, $current - $quantity);
    } else {
        $new_qty = $quantity; // SET
    }

    db_execute(
        'UPDATE MenuItem SET stockQuantity = ?, isAvailable = ?, updatedAt = datetime("now") WHERE id = ?',
        [$new_qty, $new_qty > 0 ? 1 : 0, $menu_item_id]
    );

    $log_id  = new_id();
    $log_qty = ($type === 'DECREASE' || $type === 'REMOVE') ? -$quantity : $quantity;
    try {
        db_execute(
            'INSERT INTO StockLog (id, menuItemId, quantity, type, reason, createdAt)
             VALUES (?, ?, ?, ?, ?, datetime("now"))',
            [$log_id, $menu_item_id, $log_qty, $type, $reason]
        );
    } catch (PDOException $e) {
        // StockLog table may not exist in all installations — continue without logging
    }

    if ($new_qty <= (int)$item['lowStockThreshold']) {
        $alert = db_fetch('SELECT id FROM StockAlert WHERE menuItemId = ? AND isResolved = 0', [$menu_item_id]);
        if (!$alert) {
            $alert_id = new_id();
            db_execute(
                'INSERT INTO StockAlert (id, menuItemId, threshold, currentStock, isResolved, restaurantId)
                 VALUES (?, ?, ?, ?, 0, ?)',
                [$alert_id, $menu_item_id, (int)$item['lowStockThreshold'], $new_qty, $rid]
            );
        }
    }

    log_activity($user, "STOCK_ADJUST ($type)", 'MenuItem', $menu_item_id, "\"{$item['name']}\": $current → $new_qty");
    json_ok(['newQuantity' => $new_qty]);
}

json_error(405, 'Method not allowed');
