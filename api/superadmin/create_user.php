<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = get_auth_user();
if (!$user || $user['role'] !== 'SUPERADMIN') json_error(403, 'Superadmin access required');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Method not allowed');

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$name  = trim($body['name']  ?? '');
$email = trim($body['email'] ?? '');
$pass  = $body['password']   ?? '';
$role  = $body['role']       ?? 'CUSTOMER';
$rid   = $body['restaurantId'] ?? null;

$allowed_roles = ['SUPERADMIN', 'ADMIN', 'MANAGER', 'CASHIER', 'CUSTOMER'];
if (!$name || !$email || !$pass)               json_error(400, 'Name, email, and password are required');
if (strlen($pass) < 6)                         json_error(400, 'Password must be at least 6 characters');
if (!in_array($role, $allowed_roles))          json_error(400, 'Invalid role');

$existing = db_fetch('SELECT id FROM User WHERE email = ?', [$email]);
if ($existing) json_error(409, 'Email already registered');

if ($rid) {
    $restaurant = db_fetch('SELECT id FROM Restaurant WHERE id = ?', [$rid]);
    if (!$restaurant) json_error(404, 'Restaurant not found');
}

$id = new_id();
db_execute(
    'INSERT INTO User (id, email, password, name, role, restaurantId, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$id, $email, password_hash($pass, PASSWORD_DEFAULT), $name, $role, $rid ?: null]
);

json_ok([
    'id'    => $id,
    'email' => $email,
    'name'  => $name,
    'role'  => $role,
]);
