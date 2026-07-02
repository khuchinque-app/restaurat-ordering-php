<?php
/**
 * Shipping Fee API — update shipping fee (ongkir) for an order.
 * PUT /api/orders/shipping.php — update shippingFee
 */
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = require_auth();
if (!in_array($user['role'], ['ADMIN', 'SUPERADMIN'])) {
    json_error(403, 'Admin access required');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = $body['orderId'] ?? '';
    $shippingFee = $body['shippingFee'] ?? null;

    if (!$orderId) json_error(400, 'Order ID required');
    if ($shippingFee === null || !is_numeric($shippingFee) || $shippingFee < 0) {
        json_error(400, 'Valid shipping fee required');
    }

    $order = db_fetch('SELECT * FROM "Order" WHERE id = ?', [$orderId]);
    if (!$order) json_error(404, 'Order not found');

    db_execute(
        'UPDATE "Order" SET shippingFee = ?, updatedAt = datetime("now") WHERE id = ?',
        [(string)$shippingFee, $orderId]
    );

    $updated = db_fetch('SELECT * FROM "Order" WHERE id = ?', [$orderId]);
    json_ok($updated);
}

json_error(405, 'Method not allowed');
