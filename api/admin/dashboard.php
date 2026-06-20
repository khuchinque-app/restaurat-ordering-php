<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$user = require_admin();
$slug = $_GET['restaurant'] ?? DEFAULT_RESTAURANT_SLUG;

$restaurant = get_restaurant($slug);
if (!$restaurant) json_error(404, 'Restaurant not found');

$rid = $restaurant['id'];
$today = date('Y-m-d') . ' 00:00:00';

$today_orders   = (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND createdAt >= ?', [$rid, $today])['n'] ?? 0);
$pending_orders = (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND status = "PENDING"', [$rid])['n'] ?? 0);
$total_revenue  = db_fetch(
    'SELECT COALESCE(SUM(CAST(totalAmount AS REAL)), 0) AS n FROM "Order" WHERE restaurantId = ? AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")',
    [$rid]
)['n'] ?? 0;
$today_revenue  = db_fetch(
    'SELECT COALESCE(SUM(CAST(totalAmount AS REAL)), 0) AS n FROM "Order" WHERE restaurantId = ? AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY") AND createdAt >= ?',
    [$rid, $today]
)['n'] ?? 0;

$low_stock_items = db_query(
    'SELECT id, name, stockQuantity, lowStockThreshold FROM MenuItem WHERE restaurantId = ? AND stockQuantity IS NOT NULL AND stockQuantity <= lowStockThreshold AND isAvailable = 1 ORDER BY stockQuantity ASC LIMIT 10',
    [$rid]
);

$recent_orders = db_query(
    'SELECT o.id, o.orderNumber, o.status, o.totalAmount, o.customerName, o.createdAt
     FROM "Order" o WHERE o.restaurantId = ? ORDER BY o.createdAt DESC LIMIT 10',
    [$rid]
);

json_ok([
    'restaurant'    => ['id' => $restaurant['id'], 'name' => $restaurant['name']],
    'stats'         => [
        'todayOrders'   => $today_orders,
        'pendingOrders' => $pending_orders,
        'totalRevenue'  => number_format((float)$total_revenue, 2),
        'todayRevenue'  => number_format((float)$today_revenue, 2),
    ],
    'lowStockItems' => $low_stock_items,
    'recentOrders'  => $recent_orders,
]);
