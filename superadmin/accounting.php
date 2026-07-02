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

// Payment methods — include online (Payment table) + offline (cashier databases)
$payment_methods = [];
$cashier_dir = dirname(__DIR__);

// Online payments from Payment table
try {
    $payment_where = ["p.status = 'VERIFIED'", "o.createdAt >= ?", "o.createdAt <= ?"];
    $payment_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    if (!empty($slug) && isset($r) && !empty($r['id'])) {
        $payment_where[] = 'o.restaurantId = ?';
        $payment_params[] = $r['id'];
    }
    $payment_w = 'WHERE ' . implode(' AND ', $payment_where);
    $online_payments = db_query(
        "SELECT p.method, p.currency, COUNT(*) AS n, COALESCE(SUM(CAST(p.amount AS REAL)),0) AS total
         FROM Payment p JOIN \"Order\" o ON o.id = p.orderId
         $payment_w GROUP BY p.method, p.currency ORDER BY total DESC",
        $payment_params
    );
    foreach ($online_payments as $pm) {
        $payment_methods[] = $pm;
    }
} catch (Throwable $e) {}

// Offline payments from cashier databases
$cashier_dbs = [
    'aseng' => 'aseng/cashier/database.db',
    'tittil' => 'tittil/cashier/database.db',
];
$cashier_labels = ['aseng' => 'Aseng', 'tittil' => 'Tittil'];

foreach ($cashier_dbs as $slug_id => $db_rel) {
    $db_path = $cashier_dir . '/' . $db_rel;
    if (!is_file($db_path)) continue;
    if ($slug && $slug !== $slug_id) continue;
    
    try {
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Cash payments (no "A" marked = cash)
        $cash = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS total FROM finished_orders WHERE (payment_type IS NULL OR payment_type = '' OR payment_type = 'CASH') AND created_at >= '$date_from 00:00:00' AND created_at <= '$date_to 23:59:59'")->fetch(PDO::FETCH_ASSOC);
        if ($cash && (int)$cash['n'] > 0) {
            $payment_methods[] = [
                'method' => 'CASH (Offline)',
                'currency' => 'KHR',
                'n' => (int)$cash['n'],
                'total' => number_format((float)$cash['total'] / $KHR_RATE, 2),
                'khr_raw' => (float)$cash['total'],
                'slug' => $slug_id,
            ];
        }
        
        // ABA payments
        $aba = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS total FROM finished_orders WHERE payment_type = 'ABA' AND created_at >= '$date_from 00:00:00' AND created_at <= '$date_to 23:59:59'")->fetch(PDO::FETCH_ASSOC);
        if ($aba && (int)$aba['n'] > 0) {
            $payment_methods[] = [
                'method' => 'ABA (Offline)',
                'currency' => 'KHR',
                'n' => (int)$aba['n'],
                'total' => number_format((float)$aba['total'] / $KHR_RATE, 2),
                'khr_raw' => (float)$aba['total'],
                'slug' => $slug_id,
            ];
        }
    } catch (Throwable $e) {}
}

// Calculate combined stats
$cash_total_khr = 0;
$aba_total_khr = 0;
$online_total_usd = 0;
foreach ($payment_methods as $pm) {
    if ($pm['method'] === 'CASH' || $pm['method'] === 'CASH (Offline)') {
        $cash_total_khr += isset($pm['khr_raw']) ? $pm['khr_raw'] : ((float)$pm['total'] * $KHR_RATE);
    }
    if ($pm['method'] === 'ABA' || $pm['method'] === 'ABA (Offline)') {
        $aba_total_khr += isset($pm['khr_raw']) ? $pm['khr_raw'] : ((float)$pm['total'] * $KHR_RATE);
    }
}

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
        <div class="label">💵 Cash Revenue</div>
        <div class="value" style="color:#10b981">៛<?= number_format($cash_total_khr) ?></div>
        <div class="sub">≈ $<?= number_format($cash_total_khr / $KHR_RATE, 2) ?> USD</div>
    </div>
    <div class="acct-stat">
        <div class="label">💳 ABA Revenue</div>
        <div class="value" style="color:#3b82f6">៛<?= number_format($aba_total_khr) ?></div>
        <div class="sub">≈ $<?= number_format($aba_total_khr / $KHR_RATE, 2) ?> USD</div>
    </div>
</div>

<!-- Origin Breakdown -->
<div class="acct-stats" style="margin-top:.5rem">
    <div class="acct-stat" style="border-left:4px solid #6366f1">
        <div class="label">🌐 Online Revenue</div>
        <div class="value" style="color:#6366f1">$<?= number_format((float)$totals['totalRevenue'], 2) ?></div>
        <div class="sub">From web orders (<?= (int)$totals['totalOrders'] ?> orders)</div>
    </div>
    <div class="acct-stat" style="border-left:4px solid #f59e0b">
        <div class="label">🏪 Offline (Cashier) Revenue</div>
        <div class="value" style="color:#f59e0b">៛<?= number_format($cash_total_khr + $aba_total_khr) ?></div>
        <div class="sub">≈ $<?= number_format(($cash_total_khr + $aba_total_khr) / $KHR_RATE, 2) ?> USD — Cash/ABA</div>
    </div>
</div>

<div class="acct-grid">
    <!-- Revenue by Restaurant -->
    <div class="sa-card">
        <div class="sa-card-header"><h2>📊 Revenue by Restaurant</h2></div>
        <table class="table">
            <thead><tr><th>Restaurant</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php 
            // Add offline cashier data per restaurant
            $rest_total_online = [];
            $rest_total_offline_khr = [];
            foreach ($by_restaurant as $row) {
                $rest_total_online[$row['slug']] = (float)$row['revenue'];
                $rest_total_offline_khr[$row['slug']] = 0;
            }
            foreach ($payment_methods as $pm) {
                if (isset($pm['slug']) && isset($rest_total_offline_khr[$pm['slug']])) {
                    $rest_total_offline_khr[$pm['slug']] += isset($pm['khr_raw']) ? (float)$pm['khr_raw'] : 0;
                }
            }
            ?>
            <?php foreach ($by_restaurant as $row): 
                $off_khr = $rest_total_offline_khr[$row['slug']] ?? 0;
                $off_usd = $off_khr / $KHR_RATE;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= (int)$row['orders'] ?></td>
                <td>
                    <span style="display:inline-block;padding:.1rem .4rem;border-radius:4px;font-size:.7rem;font-weight:700;background:#ede9fe;color:#6366f1">🌐 $<?= number_format((float)$row['revenue'], 2) ?></span>
                    <?php if ($off_usd > 0): ?>
                    <span style="display:inline-block;padding:.1rem .4rem;border-radius:4px;font-size:.7rem;font-weight:700;background:#fef3c7;color:#f59e0b">🏪 $<?= number_format($off_usd, 2) ?></span>
                    <?php endif; ?>
                </td>
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
        <div style="text-align:center;padding:2rem;color:#94a3b8">No payment records found.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Origin</th><th>Method</th><th>Count</th><th>Total (USD)</th><th>Total (KHR)</th></tr></thead>
            <tbody>
            <?php 
            $grand_n = 0; $grand_usd = 0; $grand_khr = 0;
            foreach ($payment_methods as $pm): 
                $is_offline = strpos($pm['method'] ?? '', 'Offline') !== false;
                $origin_color = $is_offline ? '#f59e0b' : '#6366f1';
                $origin_bg = $is_offline ? '#fef3c7' : '#ede9fe';
                $origin_label = $is_offline ? '🏪 Offline' : '🌐 Online';
                $khr_amt = isset($pm['khr_raw']) ? (float)$pm['khr_raw'] : ((float)$pm['total'] * $KHR_RATE);
                $usd_amt = (float)$pm['total'];
                $grand_n += (int)$pm['n'];
                $grand_usd += $usd_amt;
                $grand_khr += $khr_amt;
            ?>
            <tr>
                <td><span style="display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.7rem;font-weight:700;background:<?= $origin_bg ?>;color:<?= $origin_color ?>"><?= $origin_label ?></span></td>
                <td><strong><?= htmlspecialchars($pm['method']) ?></strong></td>
                <td><?= (int)$pm['n'] ?></td>
                <td><strong>$<?= number_format($usd_amt, 2) ?></strong></td>
                <td>៛<?= number_format($khr_amt) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr style="font-weight:700;background:#f8fafc">
                <td colspan="2">TOTAL</td>
                <td><?= $grand_n ?></td>
                <td><strong>$<?= number_format($grand_usd, 2) ?></strong></td>
                <td>៛<?= number_format($grand_khr) ?></td>
            </tr>
            </tfoot>
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
