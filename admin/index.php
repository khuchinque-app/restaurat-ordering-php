<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Dashboard';
include dirname(__DIR__) . '/includes/admin_header.php';

$restaurant = !empty($current_user['restaurantId'])
    ? db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']])
    : null;
$rid = $restaurant['id'] ?? null;
$today = date('Y-m-d') . ' 00:00:00';

$today_orders   = $rid ? (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND createdAt >= ?', [$rid, $today])['n'] ?? 0) : 0;
$pending_orders = $rid ? (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND status = "PENDING"', [$rid])['n'] ?? 0) : 0;
$today_revenue  = $rid ? (float)(db_fetch('SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS n FROM "Order" WHERE restaurantId = ? AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY") AND createdAt >= ?', [$rid, $today])['n'] ?? 0) : 0;
$total_revenue  = $rid ? (float)(db_fetch('SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS n FROM "Order" WHERE restaurantId = ? AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")', [$rid])['n'] ?? 0) : 0;

$low_stock = $rid ? db_query('SELECT id, name, stockQuantity, lowStockThreshold FROM MenuItem WHERE restaurantId = ? AND stockQuantity IS NOT NULL AND stockQuantity <= lowStockThreshold ORDER BY stockQuantity ASC LIMIT 8', [$rid]) : [];
$recent_orders = $rid ? db_query('SELECT id, orderNumber, status, totalAmount, customerName, createdAt FROM "Order" WHERE restaurantId = ? ORDER BY createdAt DESC LIMIT 10', [$rid]) : [];
?>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe">&#128230;</div>
        <div>
            <div class="stat-label">Today's Orders</div>
            <div class="stat-value"><?= $today_orders ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7">&#128178;</div>
        <div>
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value">$<?= number_format($today_revenue, 2) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7">&#9200;</div>
        <div>
            <div class="stat-label">Pending Orders</div>
            <div class="stat-value"><?= $pending_orders ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe">&#128200;</div>
        <div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">$<?= number_format($total_revenue, 2) ?></div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Orders</h2>
            <a href="orders.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recent_orders)): ?>
            <p class="empty-state">No orders yet.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>#Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recent_orders as $order): ?>
            <tr>
                <td><a href="orders.php?id=<?= htmlspecialchars($order['id']) ?>">#<?= htmlspecialchars($order['orderNumber']) ?></a></td>
                <td><?= htmlspecialchars($order['customerName'] ?? 'Guest') ?></td>
                <td><span class="status-badge status-<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                <td>$<?= number_format((float)$order['totalAmount'], 2) ?></td>
                <td><?= date('H:i', strtotime($order['createdAt'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Low Stock -->
    <div class="card">
        <div class="card-header">
            <h2>&#9888; Low Stock Alert</h2>
            <a href="stock.php" class="btn btn-sm btn-outline">Manage Stock</a>
        </div>
        <?php if (empty($low_stock)): ?>
            <p class="empty-state">All stock levels are healthy.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Item</th><th>Stock</th><th>Threshold</th></tr></thead>
            <tbody>
            <?php foreach ($low_stock as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td class="<?= $item['stockQuantity'] == 0 ? 'text-danger' : 'text-warn' ?>"><?= (int)$item['stockQuantity'] ?></td>
                <td><?= (int)$item['lowStockThreshold'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>setTimeout(() => location.reload(), 30000);</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
