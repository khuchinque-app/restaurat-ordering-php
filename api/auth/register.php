<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Method not allowed');

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$name     = trim($body['name'] ?? '');
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';
$phone    = trim($body['phone'] ?? '');

if (!$name || !$email || !$password) json_error(400, 'Name, email, and password are required');
if (strlen($password) < 6)           json_error(400, 'Password must be at least 6 characters');

$existing = db_fetch('SELECT id FROM User WHERE email = ?', [$email]);
if ($existing) json_error(409, 'Email already registered');

$restaurant = get_restaurant($body['restaurantSlug'] ?? null);
$id = new_id();
db_execute(
    'INSERT INTO User (id, email, password, name, phone, role, restaurantId, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$id, $email, password_hash($password, PASSWORD_DEFAULT), $name, $phone ?: null, 'CUSTOMER', $restaurant['id'] ?? null]
);

$result = auth_login($email, $password);
json_ok($result, 'Registration successful');
