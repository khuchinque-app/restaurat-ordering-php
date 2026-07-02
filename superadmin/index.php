<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Dashboard';
include dirname(__DIR__) . '/includes/superadmin_header.php';

// System-wide stats
$total_restaurants = (int)(db_fetch('SELECT COUNT(*) AS n FROM Restaurant WHERE isActive = 1')['n'] ?? 0);
$active_restaurants = (int)(db_fetch('SELECT COUNT(*) AS n FROM Restaurant WHERE isActive = 1')['n'] ?? 0);
$total_users   = (int)(db_fetch('SELECT COUNT(*) AS n FROM User WHERE role = "CUSTOMER"')['n'] ?? 0);
$total_admins  = (int)(db_fetch('SELECT COUNT(*) AS n FROM User WHERE role IN ("ADMIN","SUPERADMIN")')['n'] ?? 0);
$total_orders  = (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order"')['n'] ?? 0);
$total_revenue = (float)(db_fetch('SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS n FROM "Order" WHERE status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")')['n'] ?? 0);

$today = date('Y-m-d') . ' 00:00:00';
$today_orders  = (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE createdAt >= ?', [$today])['n'] ?? 0);
$today_revenue = (float)(db_fetch('SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS n FROM "Order" WHERE status IN ("COMPLETED","READY","OUT_FOR_DELIVERY") AND createdAt >= ?', [$today])['n'] ?? 0);

$pending_orders = (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE status = "PENDING"')['n'] ?? 0);

// Restaurants list with per-restaurant stats + storefront folder
$restaurants = db_query(
    'SELECT r.id, r.name, r.slug, r.isActive, r.createdAt,
            (SELECT COUNT(*) FROM "Order" WHERE restaurantId = r.id) AS totalOrders,
            (SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) FROM "Order" WHERE restaurantId = r.id AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")) AS totalRevenue,
            (SELECT COUNT(*) FROM User WHERE restaurantId = r.id AND role = "CUSTOMER") AS customerCount
     FROM Restaurant r
     WHERE r.isActive = 1
     ORDER BY r.createdAt DESC'
);

// Resolve storefront folders
foreach ($restaurants as &$rr) {
    $rr['_folder'] = storefront_folder($rr['slug']);
}
unset($rr);

// Recent orders across all restaurants
$recent_orders = db_query(
    'SELECT o.id, o.orderNumber, o.status, o.totalAmount, o.createdAt, r.name AS restaurantName
     FROM "Order" o JOIN Restaurant r ON r.id = o.restaurantId
     ORDER BY o.createdAt DESC LIMIT 8'
);

// Recent activity
$recent_activity = db_query(
    'SELECT aa.action, aa.entityType, aa.details, aa.restaurantName, aa.createdAt,
            u.name AS userName, u.role AS userRole
     FROM AdminActivity aa LEFT JOIN User u ON u.id = aa.userId
     ORDER BY aa.createdAt DESC LIMIT 10'
);
?>

<!-- Quick Restaurant Buttons -->
<div style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="checkout.php" style="flex:1;min-width:160px;display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:1rem;box-shadow:0 2px 8px rgba(249,115,22,.3);transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(249,115,22,.4)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(249,115,22,.3)'">
        📦 Live Orders
        <?php if ($pending_orders > 0): ?>
        <span style="background:#fff;color:#ea580c;padding:.15rem .5rem;border-radius:9999px;font-size:.78rem"><?= $pending_orders ?> pending</span>
        <?php endif; ?>
    </a>
    <?php foreach ($restaurants as $r): ?>
    <?php if ($r['_folder']): ?>
    <a href="/<?= htmlspecialchars($r['_folder']) ?>/" target="_blank" style="min-width:140px;display:flex;align-items:center;gap:.5rem;padding:.85rem 1.1rem;background:#fff;border:2px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#111827;font-weight:600;font-size:.9rem;transition:all .15s" onmouseover="this.style.borderColor='#7c3aed';this.style.boxShadow='0 2px 8px rgba(124,58,237,.15)'" onmouseout="this.style.borderColor='#e5e7eb';this.style.boxShadow=''">
        🍽 <?= htmlspecialchars($r['name']) ?> ↗
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Stats -->
<div class="sa-stats">
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#ede9fe">&#127974;</div>
        <div>
            <div class="sa-stat-label">Restaurants</div>
            <div class="sa-stat-value"><?= $total_restaurants ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#dbeafe">&#128100;</div>
        <div>
            <div class="sa-stat-label">Customers</div>
            <div class="sa-stat-value"><?= $total_users ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#fef9c3">&#128230;</div>
        <div>
            <div class="sa-stat-label">Total Orders</div>
            <div class="sa-stat-value"><?= $total_orders ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#dcfce7">&#128178;</div>
        <div>
            <div class="sa-stat-label">Total Revenue</div>
            <div class="sa-stat-value">$<?= number_format($total_revenue, 0) ?></div>
            <div class="sa-stat-sub" style="font-size:.7rem;color:#94a3b8">≈ <?= number_format($total_revenue * 4000) ?> KHR</div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#fce7f3">&#128197;</div>
        <div>
            <div class="sa-stat-label">Today Orders</div>
            <div class="sa-stat-value"><?= $today_orders ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:#ecfdf5">&#9989;</div>
        <div>
            <div class="sa-stat-label">Today Revenue</div>
            <div class="sa-stat-value">$<?= number_format($today_revenue, 2) ?></div>
            <div class="sa-stat-sub" style="font-size:.7rem;color:#94a3b8">≈ <?= number_format($today_revenue * 4000) ?> KHR</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem">

<!-- Restaurants Overview -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>&#127974; Restaurants</h2>
        <a href="restaurants.php" class="btn btn-sm btn-outline">Manage All</a>
    </div>
    <table class="table">
        <thead><tr><th>Name</th><th>Orders</th><th>Revenue</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($restaurants as $r): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($r['slug']) ?></small>
            </td>
            <td><?= (int)$r['totalOrders'] ?></td>
            <td>$<?= number_format((float)$r['totalRevenue'], 2) ?></td>
            <td>
                <span class="<?= $r['isActive'] ? 'restaurant-active' : 'restaurant-inactive' ?>">
                    <?= $r['isActive'] ? 'Active' : 'Inactive' ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($restaurants)): ?>
            <tr><td colspan="4" class="text-muted" style="text-align:center;padding:1rem">No restaurants yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent Orders -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>&#128230; Recent Orders</h2>
    </div>
    <table class="table">
        <thead><tr><th>#Order</th><th>Restaurant</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($recent_orders as $o): ?>
        <tr>
            <td><strong>#<?= htmlspecialchars($o['orderNumber']) ?></strong><br><small class="text-muted"><?= date('M d H:i', strtotime($o['createdAt'])) ?></small></td>
            <td><?= htmlspecialchars($o['restaurantName']) ?></td>
            <td><span class="status-badge status-<?= strtolower($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
            <td>$<?= number_format((float)$o['totalAmount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_orders)): ?>
            <tr><td colspan="4" class="text-muted" style="text-align:center;padding:1rem">No orders yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<!-- Recent Activity -->
<div class="sa-card" style="margin-top:1.25rem">
    <div class="sa-card-header">
        <h2>&#128203; Recent Activity</h2>
        <a href="activity.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if (empty($recent_activity)): ?>
    <div style="text-align:center; padding:1.5rem; color:#94a3b8">No activity recorded yet.</div>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>Restaurant</th></tr></thead>
        <tbody>
        <?php foreach ($recent_activity as $a): ?>
        <?php
            $color = '#6b7280';
            if (str_contains($a['action'], 'DELETE') || str_contains($a['action'], 'CANCEL')) $color = '#ef4444';
            elseif (str_contains($a['action'], 'CREATE') || str_contains($a['action'], 'CONFIRMED')) $color = '#10b981';
            elseif (str_contains($a['action'], 'UPDATE') || str_contains($a['action'], 'STOCK') || str_contains($a['action'], '→')) $color = '#f59e0b';
            elseif ($a['action'] === 'LOGIN') $color = '#6366f1';
        ?>
        <tr>
            <td style="font-size:.8rem;color:#6b7280;white-space:nowrap">
                <?= date('H:i:s', strtotime($a['createdAt'])) ?><br>
                <span><?= date('M d', strtotime($a['createdAt'])) ?></span>
            </td>
            <td>
                <strong style="font-size:.85rem"><?= htmlspecialchars($a['userName'] ?? '—') ?></strong><br>
                <small style="font-size:.7rem;color:#94a3b8"><?= htmlspecialchars($a['userRole'] ?? '') ?></small>
            </td>
            <td>
                <span style="font-size:.75rem;font-weight:700;color:<?= $color ?>;background:<?= $color ?>18;padding:.15rem .45rem;border-radius:4px">
                    <?= htmlspecialchars($a['action']) ?>
                </span>
            </td>
            <td style="font-size:.83rem;max-width:200px;word-break:break-word"><?= htmlspecialchars($a['details'] ?? '') ?></td>
            <td style="font-size:.8rem;color:#6b7280"><?= htmlspecialchars($a['restaurantName'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
