<?php
$page_title = 'Accounting';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';

// Use the admin's assigned restaurant; fall back to default if not set
if (!empty($current_user['restaurantId'])) {
    $restaurant = db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']]);
} else {
    $restaurant = get_restaurant();
}
$rid = $restaurant['id'] ?? null;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$KHR_RATE = 4000;

$where = $rid ? ['o.restaurantId = ?', 'o.status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")', 'o.createdAt >= ?', 'o.createdAt <= ?'] : ['1=0'];
$params = $rid ? [$rid, $date_from . ' 00:00:00', $date_to . ' 23:59:59'] : [];
$w = 'WHERE ' . implode(' AND ', $where);

$totals = db_fetch("SELECT COUNT(*) AS totalOrders, COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS totalRevenue FROM \"Order\" o $w", $params);

$by_status = db_query(
    "SELECT o.status, COUNT(*) AS n, COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS revenue
     FROM \"Order\" o $w GROUP BY o.status ORDER BY n DESC",
    $params
);

$daily = db_query(
    "SELECT date(o.createdAt) AS day, COUNT(*) AS orders, COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS revenue
     FROM \"Order\" o $w GROUP BY date(o.createdAt) ORDER BY day ASC",
    $params
);

$top_items = $rid ? db_query(
    "SELECT mi.name, SUM(oi.quantity) AS totalQty, SUM(CAST(oi.totalPrice AS REAL)) AS totalRevenue
     FROM OrderItem oi JOIN \"Order\" o ON o.id = oi.orderId JOIN MenuItem mi ON mi.id = oi.menuItemId
     WHERE o.restaurantId = ? AND o.status IN ('COMPLETED','READY','OUT_FOR_DELIVERY')
       AND o.createdAt >= ? AND o.createdAt <= ?
     GROUP BY oi.menuItemId ORDER BY totalRevenue DESC LIMIT 10",
    [$rid, $date_from . ' 00:00:00', $date_to . ' 23:59:59']
) : [];

$payment_methods = [];
try {
    $payment_methods = db_query(
        "SELECT p.method, p.currency, COUNT(*) AS n, COALESCE(SUM(CAST(p.amount AS REAL)),0) AS total
         FROM Payment p JOIN \"Order\" o ON o.id = p.orderId
         WHERE p.status = 'VERIFIED' AND o.restaurantId = ? AND o.createdAt >= ? AND o.createdAt <= ?
         GROUP BY p.method, p.currency ORDER BY total DESC",
        [$rid, $date_from . ' 00:00:00', $date_to . ' 23:59:59']
    );
} catch (Throwable $e) {}
?>

<style>
.acct-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem}
.acct-stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem}
.acct-stat .label{font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.acct-stat .value{font-size:1.6rem;font-weight:800;margin-top:.2rem}
.acct-stat .sub{font-size:.78rem;color:#94a3b8;margin-top:.15rem}
.acct-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem}
</style>

<!-- Filters -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-bottom:1.5rem">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
</form>

<!-- Stats -->
<div class="acct-stats">
    <div class="acct-stat">
        <div class="label">Total Revenue</div>
        <div class="value" style="color:#059669">$<?= number_format((float)$totals['totalRevenue'], 2) ?></div>
        <div class="sub">≈ <?= number_format((float)$totals['totalRevenue'] * $KHR_RATE) ?> KHR</div>
    </div>
    <div class="acct-stat">
        <div class="label">Total Orders</div>
        <div class="value"><?= number_format((int)$totals['totalOrders']) ?></div>
        <div class="sub">Avg $<?= $totals['totalOrders'] > 0 ? number_format((float)$totals['totalRevenue'] / (int)$totals['totalOrders'], 2) : '0.00' ?>/order</div>
    </div>
</div>

<div class="acct-grid">
    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><h2>💳 Payment Methods</h2></div>
        <?php if (empty($payment_methods)): ?>
        <div style="text-align:center;padding:2rem;color:#94a3b8">No payment records yet.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Method</th><th>Currency</th><th>Count</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($payment_methods as $pm): ?>
            <tr>
                <td><?= htmlspecialchars($pm['method']) ?></td>
                <td><?= htmlspecialchars($pm['currency']) ?></td>
                <td><?= (int)$pm['n'] ?></td>
                <td><strong><?= $pm['currency'] === 'KHR' ? '៛' : '$' ?><?= number_format((float)$pm['total'], $pm['currency'] === 'KHR' ? 0 : 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Orders by Status -->
    <div class="card">
        <div class="card-header"><h2>📋 Orders by Status</h2></div>
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
            <?php if (empty($by_status)): ?>
            <tr><td colspan="3" style="text-align:center;color:#94a3b8">No data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="acct-grid">
    <!-- Daily Revenue -->
    <div class="card">
        <div class="card-header"><h2>📅 Daily Revenue</h2></div>
        <div style="max-height:400px;overflow-y:auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($daily as $row): ?>
            <tr>
                <td><?= date('D, M d', strtotime($row['day'])) ?></td>
                <td><?= (int)$row['orders'] ?></td>
                <td><strong>$<?= number_format((float)$row['revenue'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($daily)): ?>
            <tr><td colspan="3" style="text-align:center;color:#94a3b8">No data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Top Items -->
    <div class="card">
        <div class="card-header"><h2>🏆 Top Selling Items</h2></div>
        <?php if (empty($top_items)): ?>
        <div style="text-align:center;padding:2rem;color:#94a3b8">No item data yet.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Item</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($top_items as $ti): ?>
            <tr>
                <td><?= htmlspecialchars($ti['name']) ?></td>
                <td><?= (int)$ti['totalQty'] ?></td>
                <td><strong>$<?= number_format((float)$ti['totalRevenue'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
