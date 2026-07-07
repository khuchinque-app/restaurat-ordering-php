<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Dashboard';
include dirname(__DIR__) . '/includes/superadmin_header.php';

// System-wide stats
$total_restaurants = (int)(db_fetch('SELECT COUNT(*) AS n FROM Restaurant')['n'] ?? 0);
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
     ORDER BY r.createdAt DESC'
);

// Resolve storefront folders and cashier paths
$storefront_root = dirname(__DIR__);
foreach ($restaurants as &$rr) {
    $rr['_folder'] = null;
    $folder_candidates = [$rr['slug'], $rr['slug'] . '_restaurant', $rr['slug'] . '_house'];
    foreach ($folder_candidates as $candidate) {
        if (is_dir($storefront_root . '/' . $candidate)) {
            $rr['_folder'] = $candidate;
            break;
        }
    }
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

// Offline Cashier transactions — pull from both cashier DBs
$cashier_orders = [];
$cashier_dbs = [
    'aseng'  => __DIR__ . '/../aseng/cashier/database.db',
    'tittil' => __DIR__ . '/../tittil/cashier/database.db',
];
foreach ($cashier_dbs as $store => $dbPath) {
    if (!file_exists($dbPath)) continue;
    try {
        $cdb = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rows = $cdb->query("SELECT id, customer_name, total, payment_method, created_at FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $row['_store'] = $store;
            $cashier_orders[] = $row;
        }
    } catch (Throwable $e) {}
}
// Sort merged cashier orders by date desc
usort($cashier_orders, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
$cashier_orders = array_slice($cashier_orders, 0, 8);
?>

<style>
/* ---- Dashboard-specific: Sections ---- */
.nav-section { margin-bottom: var(--space-6); }
.nav-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .85rem 1.1rem;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    font-weight: 700;
    font-size: 1rem;
}
.nav-section-header.storefronts {
    background: linear-gradient(135deg, #1e40af, var(--color-blue));
    color: #fff;
}
.nav-section-header.cashier {
    background: linear-gradient(135deg, #7c3aed, var(--color-purple));
    color: #fff;
}
.nav-section-body {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-top: none;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    padding: .75rem 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
}

/* ---- Storefront / Cashier Links ---- */
.storefront-link {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem .85rem;
    background: var(--color-blue-soft);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: var(--radius-md);
    color: var(--color-blue);
    font-weight: 600;
    font-size: .88rem;
    text-decoration: none;
    transition: var(--transition-base);
}
.storefront-link:hover {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.4);
    color: #60a5fa;
    text-decoration: none;
}
.cashier-link {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem .85rem;
    background: var(--color-purple-soft);
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: var(--radius-md);
    color: var(--color-purple);
    font-weight: 600;
    font-size: .88rem;
    text-decoration: none;
    transition: var(--transition-base);
}
.cashier-link:hover {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.4);
    color: #a78bfa;
    text-decoration: none;
}

/* ---- Live Orders Banner ---- */
.live-orders-banner {
    display: flex;
    gap: .75rem;
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
}
.live-orders-banner a {
    flex: 1;
    min-width: 160px;
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: #fff;
    border-radius: var(--radius-lg);
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(220, 38, 38, .3);
    transition: transform .15s, box-shadow .15s;
}
.live-orders-banner a:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(220, 38, 38, .4);
}
.live-orders-banner .pending-badge {
    background: #fff;
    color: #dc2626;
    padding: .15rem .5rem;
    border-radius: 9999px;
    font-size: .78rem;
    font-weight: 600;
}

/* ---- Info Banner ---- */
.info-banner {
    background: var(--color-blue-soft);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: var(--radius-lg);
    padding: var(--space-4) var(--space-5);
    margin-bottom: var(--space-6);
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    font-size: .88rem;
    color: #93c5fd;
    line-height: 1.5;
}
.info-banner strong { font-size: .95rem; color: var(--color-blue); }
.info-banner .badge-storefront {
    display: inline-block;
    background: var(--color-blue);
    color: #fff;
    padding: .1rem .5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: .78rem;
    margin-top: .25rem;
}
.info-banner .badge-cashier {
    display: inline-block;
    background: var(--color-purple);
    color: #fff;
    padding: .1rem .5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: .78rem;
    margin-top: .1rem;
}

/* ---- Delete All Button ---- */
.btn-delete-all {
    background: rgba(255, 255, 255, .15);
    border: 1px solid rgba(255, 255, 255, .25);
    color: #fff;
    padding: .3rem .7rem;
    border-radius: var(--radius-sm);
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-base);
}
.btn-delete-all:hover { background: rgba(239, 68, 68, .7); border-color: rgba(239, 68, 68, .8); }

/* ---- Responsive ---- */
@media (max-width: 1024px) {
    .live-orders-banner a { min-width: 100%; }
}
@media (max-width: 640px) {
    .nav-section-header { flex-direction: column; gap: var(--space-2); align-items: flex-start; }
}
</style>

<!-- Live Orders Banner -->
<div class="live-orders-banner">
    <a href="checkout.php">
        📦 Live Orders
        <?php if ($pending_orders > 0): ?>
        <span class="pending-badge"><?= $pending_orders ?> pending</span>
        <?php endif; ?>
    </a>
</div>

<!-- Stats Grid -->
<div class="sa-stats">
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:var(--color-purple-soft)">🏨</div>
        <div>
            <div class="sa-stat-label">Restaurants</div>
            <div class="sa-stat-value"><?= $total_restaurants ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:var(--color-blue-soft)">👤</div>
        <div>
            <div class="sa-stat-label">Customers</div>
            <div class="sa-stat-value"><?= $total_users ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:rgba(250,204,21,0.1)">📦</div>
        <div>
            <div class="sa-stat-label">Total Orders</div>
            <div class="sa-stat-value"><?= $total_orders ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:var(--color-green-soft)">💰</div>
        <div>
            <div class="sa-stat-label">Total Revenue</div>
            <div class="sa-stat-value">$<?= number_format($total_revenue, 0) ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:var(--color-danger-soft)">📅</div>
        <div>
            <div class="sa-stat-label">Today Orders</div>
            <div class="sa-stat-value"><?= $today_orders ?></div>
        </div>
    </div>
    <div class="sa-stat">
        <div class="sa-stat-icon" style="background:var(--color-green-soft)">✅</div>
        <div>
            <div class="sa-stat-label">Today Revenue</div>
            <div class="sa-stat-value">$<?= number_format($today_revenue, 2) ?></div>
        </div>
    </div>
</div>

<!-- Storefront Orders -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>🏪 Storefront Orders</h2>
        <a href="checkout.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <table class="table">
        <thead><tr><th>#Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Status</th><th>Total</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($recent_orders as $o): ?>
        <tr>
            <td><strong>#<?= htmlspecialchars($o['orderNumber']) ?></strong></td>
            <td><?= htmlspecialchars($o['restaurantName']) ?></td>
            <td style="font-size:.82rem;color:#64748b">—</td>
            <td style="font-size:.82rem;color:#64748b"><?= $o['itemCount'] ?? '—' ?></td>
            <td><span class="status-badge status-<?= strtolower($o['status']) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
            <td><strong>$<?= number_format((float)$o['totalAmount'], 2) ?></strong></td>
            <td style="font-size:.78rem;color:var(--color-text-muted);white-space:nowrap"><?= date('H:i', strtotime($o['createdAt'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent_orders)): ?>
            <tr><td colspan="7" style="color:var(--color-text-muted);text-align:center;padding:2rem">No orders yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Storefronts Section -->
<div class="nav-section">
    <div class="nav-section-header storefronts">
        <div>
            <span style="font-size:1.1rem">🏪 Storefronts</span>
            <div style="font-size:.75rem;font-weight:400;opacity:.85;margin-top:.1rem">Online ordering pages visible to customers</div>
        </div>
    </div>
    <div class="nav-section-body">
        <?php
        $has_storefront = false;
        foreach ($restaurants as $r):
            if ($r['_folder']):
                $has_storefront = true;
        ?>
        <a href="/<?= htmlspecialchars($r['_folder']) ?>/" target="_blank" class="storefront-link">
            🍽 <?= htmlspecialchars($r['name']) ?> ↗
        </a>
        <?php endif; endforeach; ?>
        <?php if (!$has_storefront): ?>
        <span style="color:var(--color-text-muted);font-size:.9rem;padding:.3rem 0">No storefronts deployed yet.</span>
        <?php endif; ?>
    </div>
</div>

<!-- Offline Cashier -->
<div class="sa-card" style="margin-top:1.25rem">
    <div class="sa-card-header">
        <h2>💳 Offline Cashier</h2>
    </div>
    <table class="table">
        <thead><tr><th>Customer</th><th>Store</th><th>Method</th><th>Items</th><th>Total</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($cashier_orders as $co): ?>
        <tr>
            <td><strong><?= htmlspecialchars($co['customer_name'] ?? 'Walk-in') ?></strong></td>
            <td style="text-transform:capitalize"><?= htmlspecialchars($co['_store']) ?></td>
            <td><?= htmlspecialchars($co['payment_method'] ?? '—') ?></td>
            <td style="font-size:.82rem;color:#64748b">—</td>
            <td><strong>$<?= number_format((float)($co['total'] ?? 0), 2) ?></strong></td>
            <td style="font-size:.78rem;color:var(--color-text-muted);white-space:nowrap"><?= isset($co['created_at']) ? date('H:i', strtotime($co['created_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($cashier_orders)): ?>
            <tr><td colspan="6" style="color:var(--color-text-muted);text-align:center;padding:2rem">No cashier transactions yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Cashier-Outstore Section -->
<div class="nav-section">
    <div class="nav-section-header cashier">
        <div>
            <span style="font-size:1.1rem">💳 Cashier-Outstore</span>
            <div style="font-size:.75rem;font-weight:400;opacity:.85;margin-top:.1rem">For in-person / offline sales (admin panels)</div>
        </div>
    </div>
    <div class="nav-section-body">
        <?php
        $has_cashier = false;
        foreach ($restaurants as $r):
            if ($r['_folder']):
                $has_cashier = true;
        ?>
        <a href="/<?= htmlspecialchars($r['_folder']) ?>/cashier/" target="_blank" class="cashier-link">
            💳 <?= htmlspecialchars($r['name']) ?> Cashier ↗
        </a>
        <?php endif; endforeach; ?>
        <?php if (!$has_cashier): ?>
        <span style="color:var(--color-text-muted);font-size:.9rem;padding:.3rem 0">No cashier links available.</span>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Activity -->
<div class="sa-card" style="margin-top:var(--space-6)">
    <div class="sa-card-header">
        <h2>📋 Recent Activity</h2>
        <a href="activity.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if (empty($recent_activity)): ?>
    <div style="text-align:center; padding:2rem; color:var(--color-text-muted)">No activity recorded yet.</div>
    <?php else: ?>
    <table class="table">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>Store</th></tr></thead>
        <tbody>
        <?php foreach ($recent_activity as $a): ?>
        <?php
            $color = '#6b7280';
            if (str_contains($a['action'], 'DELETE') || str_contains($a['action'], 'CANCEL')) $color = 'var(--color-danger)';
            elseif (str_contains($a['action'], 'CREATE') || str_contains($a['action'], 'CONFIRMED')) $color = 'var(--color-green)';
            elseif (str_contains($a['action'], 'UPDATE') || str_contains($a['action'], 'STOCK') || str_contains($a['action'], '→')) $color = '#f59e0b';
            elseif ($a['action'] === 'LOGIN') $color = '#6366f1';
        ?>
        <tr>
            <td style="font-size:.8rem;color:var(--color-text-muted);white-space:nowrap">
                <?= date('H:i:s', strtotime($a['createdAt'])) ?><br>
                <span><?= date('M d', strtotime($a['createdAt'])) ?></span>
            </td>
            <td>
                <strong style="font-size:.85rem"><?= htmlspecialchars($a['userName'] ?? '—') ?></strong><br>
                <small style="font-size:.7rem;color:var(--color-text-muted)"><?= htmlspecialchars($a['userRole'] ?? '') ?></small>
            </td>
            <td>
                <span style="font-size:.75rem;font-weight:700;color:<?= $color ?>;background:<?= $color ?>18;padding:.15rem .45rem;border-radius:var(--radius-sm)">
                    <?= htmlspecialchars($a['action']) ?>
                </span>
            </td>
            <td style="font-size:.83rem;max-width:200px;word-break:break-word"><?= htmlspecialchars($a['details'] ?? '') ?></td>
            <td style="font-size:.8rem;color:var(--color-text-muted)"><?= htmlspecialchars($a['restaurantName'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function confirmDeleteAll(type) {
    const label = type === 'storefronts' ? 'all storefront folders' : 'all cashier data';
    if (!confirm(`⚠️ Are you sure you want to delete ${label}? This action cannot be undone.`)) return;
    fetch('/api/superadmin/delete_all.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: type })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { alert('Deleted successfully.'); location.reload(); }
        else alert(d.error || 'Failed to delete');
    })
    .catch(() => alert('Network error'));
}
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
