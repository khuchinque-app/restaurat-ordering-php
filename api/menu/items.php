<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/activity.php';

$method = $_SERVER['REQUEST_METHOD'];
$slug   = $_GET['restaurant'] ?? DEFAULT_RESTAURANT_SLUG;

$restaurant = get_restaurant($slug);
if (!$restaurant) json_error(404, 'Restaurant not found');

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $item = db_fetch(
            'SELECT mi.*, c.name AS categoryName FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId WHERE mi.id = ?',
            [$_GET['id']]
        );
        if (!$item) json_error(404, 'Item not found');
        json_ok($item);
    }

    $where  = ['mi.restaurantId = ?'];
    $params = [$restaurant['id']];

    if (!empty($_GET['categoryId'])) { $where[] = 'mi.categoryId = ?'; $params[] = $_GET['categoryId']; }
    if (!empty($_GET['available']))  { $where[] = 'mi.isAvailable = 1'; }
    if (!empty($_GET['search'])) {
        $where[]  = '(mi.name LIKE ? OR mi.description LIKE ?)';
        $q = '%' . $_GET['search'] . '%';
        $params[] = $q;
        $params[] = $q;
    }

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $skip  = ($page - 1) * $limit;

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $total = (int)(db_fetch("SELECT COUNT(*) AS n FROM MenuItem mi $where_sql", $params)['n'] ?? 0);
    $items = db_query(
        "SELECT mi.*, c.name AS categoryName
         FROM MenuItem mi
         JOIN Category c ON c.id = mi.categoryId
         $where_sql
         ORDER BY mi.name ASC
         LIMIT $limit OFFSET $skip",
        $params
    );

    json_ok([
        'items'      => $items,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => (int)ceil($total / $limit)],
    ]);
}

if ($method === 'POST') {
    $user = require_admin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($body['name'] ?? '');
    if (!$name || !isset($body['price']) || empty($body['categoryId'])) {
        json_error(400, 'name, price, and categoryId are required');
    }
    $id = new_id();
    db_execute(
        'INSERT INTO MenuItem (id, name, description, price, image, isAvailable, preparationTime, notes,
          stockQuantity, lowStockThreshold, categoryId, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, datetime("now"), datetime("now"))',
        [
            $id, $name, $body['description'] ?? null, (string)$body['price'], $body['image'] ?? null,
            (int)($body['preparationTime'] ?? 15), $body['notes'] ?? null,
            isset($body['stockQuantity']) ? (int)$body['stockQuantity'] : null,
            (int)($body['lowStockThreshold'] ?? 5),
            $body['categoryId'], $restaurant['id'],
        ]
    );
    $created = db_fetch('SELECT mi.*, c.name AS categoryName FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId WHERE mi.id = ?', [$id]);
    log_activity($user, 'CREATE_MENU_ITEM', 'MenuItem', $id, "Created \"{$body['name']}\"");
    notify_all_admins(
        'MENU_UPDATE',
        'New Menu Item: ' . $body['name'],
        $user['name'] . ' added "' . $body['name'] . '" to ' . $restaurant['name'] . ' menu — $' . number_format((float)$body['price'], 2)
    );
    json_ok($created);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $user = require_admin();
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Item ID required');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = [];
    $params = [];
    $updatable = ['name', 'description', 'price', 'image', 'preparationTime', 'notes', 'stockQuantity', 'lowStockThreshold', 'categoryId'];
    foreach ($updatable as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (array_key_exists('isAvailable', $body)) {
        $fields[] = 'isAvailable = ?';
        $params[] = $body['isAvailable'] ? 1 : 0;
    }
    if (empty($fields)) json_error(400, 'Nothing to update');
    $fields[]  = 'updatedAt = datetime("now")';
    $params[]  = $id;
    db_execute('UPDATE MenuItem SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    $updated = db_fetch('SELECT mi.*, c.name AS categoryName FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId WHERE mi.id = ?', [$id]);
    log_activity($user, 'UPDATE_MENU_ITEM', 'MenuItem', $id, "Updated \"{$updated['name']}\"");
    notify_all_admins(
        'MENU_UPDATE',
        'Menu Item Updated: ' . $updated['name'],
        $user['name'] . ' updated "' . $updated['name'] . '" on ' . $restaurant['name'] . ' menu'
    );
    json_ok($updated);
}

if ($method === 'DELETE') {
    $user = require_admin();
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Item ID required');
    $item = db_fetch('SELECT name FROM MenuItem WHERE id = ?', [$id]);
    $item_name = $item['name'] ?? 'Unknown';
    db_execute('DELETE FROM MenuItem WHERE id = ?', [$id]);
    log_activity($user, 'DELETE_MENU_ITEM', 'MenuItem', $id, "Deleted \"{$item_name}\"");
    notify_all_admins(
        'MENU_UPDATE',
        'Menu Item Deleted: ' . $item_name,
        $user['name'] . ' deleted "' . $item_name . '" from ' . $restaurant['name'] . ' menu'
    );
    json_ok(null, 'Menu item deleted');
}

json_error(405, 'Method not allowed');
