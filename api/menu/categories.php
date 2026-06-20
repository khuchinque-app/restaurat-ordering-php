<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$slug   = $_GET['restaurant'] ?? DEFAULT_RESTAURANT_SLUG;

$restaurant = get_restaurant($slug);
if (!$restaurant) json_error(404, 'Restaurant not found');

if ($method === 'GET') {
    $cats = db_query(
        'SELECT c.*, COUNT(mi.id) AS itemCount
         FROM Category c
         LEFT JOIN MenuItem mi ON mi.categoryId = c.id AND mi.isAvailable = 1
         WHERE c.restaurantId = ? AND c.isActive = 1
         GROUP BY c.id
         ORDER BY c.sortOrder ASC',
        [$restaurant['id']]
    );
    json_ok($cats);
}

if ($method === 'POST') {
    $user = require_admin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($body['name'] ?? '');
    $slug_val = trim($body['slug'] ?? strtolower(str_replace(' ', '-', $name)));
    if (!$name) json_error(400, 'Name is required');

    $exists = db_fetch('SELECT id FROM Category WHERE slug = ? AND restaurantId = ?', [$slug_val, $restaurant['id']]);
    if ($exists) json_error(409, 'Category with this slug already exists');

    $id = new_id();
    db_execute(
        'INSERT INTO Category (id, name, slug, description, sortOrder, isActive, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, 1, ?, datetime("now"), datetime("now"))',
        [$id, $name, $slug_val, $body['description'] ?? null, (int)($body['sortOrder'] ?? 0), $restaurant['id']]
    );
    $cat = db_fetch('SELECT * FROM Category WHERE id = ?', [$id]);
    notify_all_admins(
        'MENU_UPDATE',
        'New Category: ' . $name,
        $user['name'] . ' added category "' . $name . '" to ' . $restaurant['name']
    );
    json_ok($cat);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $user = require_admin();
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Category ID required');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = [];
    $params = [];
    foreach (['name', 'description', 'sortOrder'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (array_key_exists('isActive', $body)) {
        $fields[] = 'isActive = ?';
        $params[] = $body['isActive'] ? 1 : 0;
    }
    if (empty($fields)) json_error(400, 'Nothing to update');
    $fields[]  = 'updatedAt = datetime("now")';
    $params[]  = $id;
    db_execute('UPDATE Category SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    $updated_cat = db_fetch('SELECT * FROM Category WHERE id = ?', [$id]);
    notify_all_admins(
        'MENU_UPDATE',
        'Category Updated: ' . $updated_cat['name'],
        $user['name'] . ' updated category "' . $updated_cat['name'] . '" on ' . $restaurant['name']
    );
    json_ok($updated_cat);
}

if ($method === 'DELETE') {
    $user = require_admin();
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Category ID required');
    $has_items = db_fetch('SELECT id FROM MenuItem WHERE categoryId = ? LIMIT 1', [$id]);
    if ($has_items) json_error(400, 'Cannot delete category with menu items');
    $cat_name = 'Unknown';
    $cat_to_delete = db_fetch('SELECT name FROM Category WHERE id = ?', [$id]);
    if ($cat_to_delete) $cat_name = $cat_to_delete['name'];
    db_execute('DELETE FROM Category WHERE id = ?', [$id]);
    notify_all_admins(
        'MENU_UPDATE',
        'Category Deleted: ' . $cat_name,
        $user['name'] . ' deleted category "' . $cat_name . '" from ' . $restaurant['name']
    );
    json_ok(null, 'Category deleted');
}

json_error(405, 'Method not allowed');
