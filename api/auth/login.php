<?php
require_once dirname(__DIR__, 2) . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Method not allowed');

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) json_error(400, 'Email and password are required');

$result = auth_login($email, $password);
if ($result === false) json_error(401, 'Invalid email or password');

json_ok($result);
