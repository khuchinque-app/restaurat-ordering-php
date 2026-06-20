<?php
require_once dirname(__DIR__, 2) . '/auth.php';
session_init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error(405, 'Method not allowed');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$order_id = $body['orderId'] ?? '';
if (!$order_id) json_error(400, 'orderId required');

if (!isset($_SESSION['order_ids'])) $_SESSION['order_ids'] = [];
if (!in_array($order_id, $_SESSION['order_ids'])) {
    array_unshift($_SESSION['order_ids'], $order_id);
    $_SESSION['order_ids'] = array_slice($_SESSION['order_ids'], 0, 30);
}

json_ok(null, 'Tracked');
