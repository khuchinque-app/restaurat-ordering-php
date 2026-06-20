<?php
$page_title = 'Accounting';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

$restaurants = db_query('SELECT id, name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name');
$slug = $_GET['restaurant'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$KHR_RATE = 4000;

// Overall stats
$where_all = ['o.status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")', 'o.createdAt >= ?', 'o.createdAt <= ?'];
$params_all = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($slug) {
    $r = get_restaurant($slug);
    if ($r) { $where_all[] = 'o.restaurantId = ?'; $params_all[] = $r['id']; }
}
$w_all = 'WHERE ' . implode(' AND ', $where_all);

$totals = db_fetch("SELECT COUNT(*) AS totalOrders, COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS totalRevenue FROM \"Order\" o $w_all", $params_all);

// By restaurant
$by_restaurant = db_query(
    "SELECT r.name, r.slug, COUNT(o.id) AS orders, COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS revenue
     FROM \"Order\" o JOIN Restaurant r ON r.id = o.restaurantId
     $w_all
     GROUP BY r.id ORDER BY revenue DESC",
    $params_all
);

// By status
$by_status = db_query(
    "SELECT o.status, COUNT(*) AS n, COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS revenue
     FROM \"Order\" o $w_all GROUP BY o.status ORDER BY n DESC",
    $params_all
);

// Payment methods (from Payment table if exists)
$payment_methods = [];
try {
    $payment_where = ["p.status = 'VERIFIED'", "o.createdAt >= ?", "o.createdAt <= ?"];
    $payment_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    if (!empty($slug) && isset($r) && !empty($r['id'])) {
        $payment_where[] = 'o.restaurantId = ?';
        $payment_params[] = $r['id'];
    }
    $payment_w = 'WHERE ' . implode(' AND ', $payment_where);
    $payment_methods = db_query(
        "SELECT p.method, p.currency, COUNT(*) AS n, COALESCE(SUM(CAST(p.amount AS REAL)),0) AS total
                 FROM Payment p
                 JOIN \"Order\" o ON o.id = p.orderId
                 $payment_w
                 GROUP BY p.method, p.currency ORDER BY total DESC",
        $payment_params
    );
} catch (Throwable $e) { /* Payment table may not exist */ }

// Daily revenue
$daily = db_query(
    "SELECT date(o.createdAt) AS day, COUNT(*) AS orders, COALESCE(SUM(CAST(o.totalAmount AS REAL)),0) AS revenue
     FROM \"Order\" o $w_all GROUP BY date(o.createdAt) ORDER BY day ASC",
    $params_all
);

// Top items
$top_items = $slug ? db_query(
    "SELECT mi.name, SUM(oi.quantity) AS totalQty, SUM(CAST(oi.totalPrice AS REAL)) AS totalRevenue
     FROM OrderItem oi
     JOIN \"Order\" o ON o.id = oi.orderId
     JOIN MenuItem mi ON mi.id = oi.menuItemId
     WHERE o.restaurantId = ? AND o.status IN ('COMPLETED','READY','OUT_FOR_DELIVERY')
       AND o.createdAt >= ? AND o.createdAt <= ?
     GROUP BY oi.menuItemId ORDER BY totalRevenue DESC LIMIT 10",
    [$r['id'] ?? '', $date_from . ' 00:00:00', $date_to . ' 23:59:59']
) : [];
?>

<style>
.acct-stats { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:1rem; margin-bottom:1.5rem; }
.acct-stat { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; }
.acct-stat .label { font-size:.75rem; color:#64748b; text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
.acct-stat .value { font-size:1.6rem; font-weight:800; margin-top:.2rem; }
.acct-stat .sub { font-size:.78rem; color:#94a3b8; margin-top:.15rem; }
.acct-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem; }
.daily-table { max-height:400px; overflow-y:auto; }
</style>

<!-- Filters -->
<form method="GET" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin-bottom:1.5rem">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Restaurant</label>
        <select name="restaurant" class="form-control" style="min-width:160px">
            <option value="">All Restaurants</option>
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>" <?= $slug === $r['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
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
        <div class="value" style="color:#10b981">$<?= number_format((float)$totals['totalRevenue'], 2) ?></div>
        <div class="sub">≈ <?= number_format((float)$totals['totalRevenue'] * $KHR_RATE) ?> KHR</div>
    </div>
    <div class="acct-stat">
        <div class="label">Total Orders</div>
        <div class="value"><?= number_format((int)$totals['totalOrders']) ?></div>
        <div class="sub">Avg $<?= $totals['totalOrders'] > 0 ? number_format((float)$totals['totalRevenue'] / (int)$totals['totalOrders'], 2) : '0.00' ?>/order</div>
    </div>
    <?php
    $usd_total = 0; $khr_total = 0;
    foreach ($payment_methods as $pm) {
        if ($pm['currency'] === 'USD') $usd_total += (float)$pm['total'];
        else $khr_total += (float)$pm['total'];
    }
    ?>
    <div class="acct-stat">
        <div class="label">USD Revenue</div>
        <div class="value" style="color:#3b82f6">$<?= number_format($usd_total, 2) ?></div>
        <div class="sub">ABA / USD Cash</div>
    </div>
    <div class="acct-stat">
        <div class="label">KHR Revenue</div>
        <div class="value" style="color:#f59e0b">៛<?= number_format($khr_total) ?></div>
        <div class="sub">≈ $<?= number_format($khr_total / $KHR_RATE, 2) ?> USD</div>
    </div>
</div>

<div class="acct-grid">
    <!-- Revenue by Restaurant -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>📊 Revenue by Restaurant</h2></div>
        <table class="table">
            <thead><tr><th>Restaurant</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($by_restaurant as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= (int)$row['orders'] ?></td>
                <td><strong>$<?= number_format((float)$row['revenue'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($by_restaurant)): ?>
            <tr><td colspan="3" class="text-muted" style="text-align:center">No data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Payment Methods -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>💳 Payment Methods</h2></div>
        <?php if (empty($payment_methods)): ?>
        <div style="text-align:center;padding:2rem;color:#94a3b8">
            No payment records yet.<br><small>Payments will appear here once integrated.</small>
        </div>
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
</div>

<!-- Orders by Status -->
<div class="sa-card" style="margin-bottom:1.25rem">
    <div class="sa-card-header"><h2>📋 Orders by Status</h2></div>
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

<div class="acct-grid">
    <!-- Daily Revenue -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>📅 Daily Revenue</h2></div>
        <div class="daily-table">
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
            <tr><td colspan="3" class="text-muted" style="text-align:center">No data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Top Items -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>🏆 Top Selling Items</h2></div>
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

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
