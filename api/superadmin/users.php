<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = get_auth_user();
if (!$user || $user['role'] !== 'SUPERADMIN') json_error(403, 'Superadmin access required');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PUT') {
    $id   = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'User ID required');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Protect superadmin accounts from being disabled
    $target = db_fetch('SELECT role FROM User WHERE id = ?', [$id]);
    if ($target && $target['role'] === 'SUPERADMIN') json_error(403, 'Cannot modify superadmin accounts');

    $fields = []; $params = [];
    if (array_key_exists('isActive', $body)) { $fields[] = 'isActive = ?'; $params[] = $body['isActive'] ? 1 : 0; }
    if (array_key_exists('role', $body))     { $fields[] = 'role = ?';     $params[] = $body['role']; }
    if (empty($fields)) json_error(400, 'Nothing to update');
    $fields[] = 'updatedAt = datetime("now")';
    $params[]  = $id;
    db_execute('UPDATE User SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    json_ok(db_fetch('SELECT id, email, name, role, isActive FROM User WHERE id = ?', [$id]));
}

json_error(405, 'Method not allowed');
