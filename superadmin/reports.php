<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Reports';
include dirname(__DIR__) . '/includes/superadmin_header.php';

// Revenue per restaurant
$by_restaurant = db_query(
    'SELECT r.name, r.slug,
            COUNT(o.id) AS totalOrders,
            COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS totalRevenue,
            COUNT(CASE WHEN o.status="COMPLETED" THEN 1 END) AS completed,
            COUNT(CASE WHEN o.status="CANCELLED" THEN 1 END) AS cancelled
     FROM Restaurant r
     LEFT JOIN "Order" o ON o.restaurantId = r.id
     GROUP BY r.id
     ORDER BY totalRevenue DESC'
);

// Orders by status (all)
$by_status = db_query(
    'SELECT status, COUNT(*) AS n, COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS revenue
     FROM "Order" GROUP BY status ORDER BY n DESC'
);

// Recent 7 days orders
$seven_days = db_query(
    'SELECT date(createdAt) AS day, COUNT(*) AS orders, COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS revenue
     FROM "Order"
     WHERE createdAt >= date("now", "-7 days")
     GROUP BY date(createdAt)
     ORDER BY day ASC'
);
?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem">

    <!-- Revenue by Restaurant -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>&#128200; Revenue by Restaurant</h2></div>
        <table class="table">
            <thead><tr><th>Restaurant</th><th>Orders</th><th>Completed</th><th>Cancelled</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($by_restaurant as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= (int)$row['totalOrders'] ?></td>
                <td class="text-success"><?= (int)$row['completed'] ?></td>
                <td class="text-danger"><?= (int)$row['cancelled'] ?></td>
                <td><strong>$<?= number_format((float)$row['totalRevenue'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Orders by Status -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>&#128202; Orders by Status</h2></div>
        <table class="table">
            <thead><tr><th>Status</th><th>Count</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($by_status as $row): ?>
            <tr>
                <td><span class="status-badge status-<?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td><?= (int)$row['n'] ?></td>
                <td>$<?= number_format((float)$row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Last 7 Days -->
<div class="sa-card">
    <div class="sa-card-header"><h2>&#128197; Last 7 Days</h2></div>
    <table class="table">
        <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php if (empty($seven_days)): ?>
            <tr><td colspan="3" style="text-align:center;padding:2rem" class="text-muted">No data yet</td></tr>
        <?php else: foreach ($seven_days as $row): ?>
        <tr>
            <td><?= date('D, M d', strtotime($row['day'])) ?></td>
            <td><?= (int)$row['orders'] ?></td>
            <td>$<?= number_format((float)$row['revenue'], 2) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
