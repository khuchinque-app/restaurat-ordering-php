<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = get_auth_user();
if (!$user || $user['role'] !== 'SUPERADMIN') json_error(403, 'Superadmin access required');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $restaurants = db_query(
        'SELECT r.*, (SELECT COUNT(*) FROM "Order" WHERE restaurantId = r.id) AS totalOrders FROM Restaurant r ORDER BY r.createdAt DESC'
    );
    json_ok($restaurants);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($body['name'] ?? '');
    $slug = trim($body['slug'] ?? '');
    if (!$name || !$slug) json_error(400, 'Name and slug are required');
    $exists = db_fetch('SELECT id FROM Restaurant WHERE slug = ?', [$slug]);
    if ($exists) json_error(409, 'Slug already taken');
    $id = new_id();
    db_execute(
        'INSERT INTO Restaurant (id, name, slug, description, isActive, createdAt, updatedAt) VALUES (?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
        [$id, $name, $slug, $body['description'] ?? null]
    );
    json_ok(db_fetch('SELECT * FROM Restaurant WHERE id = ?', [$id]));
}

if ($method === 'PUT') {
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Restaurant ID required');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = []; $params = [];
    foreach (['name', 'description', 'slug'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (array_key_exists('isActive', $body)) { $fields[] = 'isActive = ?'; $params[] = $body['isActive'] ? 1 : 0; }
    if (empty($fields)) json_error(400, 'Nothing to update');
    $fields[] = 'updatedAt = datetime("now")';
    $params[]  = $id;
    db_execute('UPDATE Restaurant SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    json_ok(db_fetch('SELECT * FROM Restaurant WHERE id = ?', [$id]));
}

json_error(405, 'Method not allowed');
