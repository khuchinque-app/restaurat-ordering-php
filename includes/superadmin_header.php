<?php
require_once dirname(__DIR__) . '/auth.php';
session_init();
$current_user = get_auth_user();
if (!$current_user || $current_user['role'] !== 'SUPERADMIN') {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}
$current_path = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Superadmin') ?> &mdash; Superadmin Panel</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/superadmin.css">
</head>
<body class="sa-body">
<div class="sa-layout">

<aside class="sa-sidebar">
    <div class="sa-logo">&#9733; Superadmin</div>
    <nav class="sa-nav">
        <a href="<?= APP_URL ?>/superadmin/index.php"         class="<?= strpos($current_path, 'superadmin/index') !== false ? 'active' : '' ?>">&#128202; Dashboard <span id="badgeDashboard" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/checkout.php"      class="<?= strpos($current_path, 'superadmin/checkout') !== false ? 'active' : '' ?>">&#128230; Live Orders <span id="badgeOrders" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/menu.php"          class="<?= strpos($current_path, 'superadmin/menu') !== false ? 'active' : '' ?>">&#127860; Menu <span id="badgeMenu" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/stock.php"         class="<?= strpos($current_path, 'superadmin/stock') !== false ? 'active' : '' ?>">&#128230; Stock <span id="badgeStock" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/users.php"         class="<?= strpos($current_path, 'superadmin/users') !== false ? 'active' : '' ?>">&#128100; Users</a>
        <a href="<?= APP_URL ?>/superadmin/restaurants.php"   class="<?= strpos($current_path, 'superadmin/restaurants') !== false ? 'active' : '' ?>">&#127974; Restaurants</a>
        <hr>
        <a href="<?= APP_URL ?>/superadmin/accounting.php"    class="<?= strpos($current_path, 'superadmin/accounting') !== false ? 'active' : '' ?>">&#128176; Accounting</a>
        <a href="<?= APP_URL ?>/superadmin/activity.php"      class="<?= strpos($current_path, 'superadmin/activity') !== false ? 'active' : '' ?>">&#128203; History</a>
        <a href="<?= APP_URL ?>/superadmin/chat.php"          class="<?= strpos($current_path, 'superadmin/chat') !== false ? 'active' : '' ?>" id="chatNavLink">&#128172; Chat <span id="badgeChat" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/activity.php"      class="<?= strpos($current_path, 'superadmin/activity') !== false ? 'active' : '' ?>" id="notifNavLink">&#128276; Alerts <span id="badgeAlerts" class="nav-badge" style="display:none">0</span></a>
        <a href="<?= APP_URL ?>/superadmin/settings.php"      class="<?= strpos($current_path, 'superadmin/settings') !== false ? 'active' : '' ?>">&#9881; Settings</a>
        <hr>
        <?php require_once dirname(__DIR__) . '/includes/storefronts.php'; render_storefront_nav(); ?>
        <hr>
        <div class="sidebar-section-label" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin:.3rem 0 .2rem;padding-left:.2rem">Cashier-Outstore</div>
        <a href="<?= APP_URL ?>/aseng/cashier/" target="_blank" rel="noopener" title="Open Aseng Cashier">&#128179; Aseng Cashier &#8599;</a>
        <a href="<?= APP_URL ?>/tittil/cashier/" target="_blank" rel="noopener" title="Open Tittil Cashier">&#128179; Tittil Cashier &#8599;</a>
        <hr>
        <a href="<?= APP_URL ?>/logout.php">&#128682; Logout</a>
    </nav>
    <div class="sa-user">
        <?= htmlspecialchars($current_user['name']) ?><br>
        <small>SUPERADMIN</small>
    </div>
</aside>

<div class="sa-main">
    <header class="sa-topbar">
        <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        <div class="sa-topbar-right">
            <span>&#128100; <?= htmlspecialchars($current_user['email']) ?></span>
        </div>
    </header>
    <div class="sa-content">
<style>
.nav-badge { display:inline-block; background:#ef4444; color:#fff; border-radius:10px; font-size:.65rem; font-weight:700; padding:.05rem .4rem; margin-left:.2rem; vertical-align:middle; line-height:1.4; min-width:18px; text-align:center; }
.nav-badge.orange { background:#f59e0b; }
.nav-badge.green { background:#10b981; }
</style>
<script>
// ── Unified unread badge poll ──────────────────────
(function pollAllBadges() {
    fetch('<?= APP_URL ?>/api/unread-counts.php', {credentials:'include'})
        .then(r => r.json()).then(d => {
            if (!d.success) return;
            const data = d.data;
            
            // Helper: set badge
            // Detect current page to hide its badge
            const pageMap = {
                badgeDashboard: 'index',
                badgeOrders: 'checkout',
                badgeChat: 'chat',
                badgeAlerts: 'activity',
                badgeStock: 'stock',
                badgeMenu: 'menu'
            };
            const currentPath = window.location.pathname;
            
            function setBadge(id, count, cls) {
                const el = document.getElementById(id);
                if (!el) return;
                // Hide badge if user is already ON that page
                const relatedPath = pageMap[id];
                if (relatedPath && currentPath.includes(relatedPath)) {
                    el.style.display = 'none';
                    return;
                }
                if (count > 0) {
                    el.textContent = count;
                    el.style.display = 'inline-block';
                    if (cls) el.className = 'nav-badge ' + cls;
                    else el.className = 'nav-badge';
                } else {
                    el.style.display = 'none';
                }
            }
            
            setBadge('badgeDashboard', data.total_unread);
            setBadge('badgeOrders', data.pending_orders, 'orange');
            setBadge('badgeChat', data.unread_customer_chat);
            setBadge('badgeAlerts', data.unread_notifs);
            
            // Stock: sum of low stock + out of stock
            const stockIssues = data.low_stock + data.out_of_stock;
            setBadge('badgeStock', stockIssues, 'orange');
            setBadge('badgeMenu', data.low_stock, 'orange');
            
            // Browser notification for new chat messages
            const lastChat = sessionStorage.getItem('lastChatUnread') || '0';
            if (data.unread_customer_chat > parseInt(lastChat) && data.unread_customer_chat > 0) {
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('💬 New Chat Message', { body: data.unread_customer_chat + ' unread message(s)', icon: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💬</text></svg>' });
                }
            }
            sessionStorage.setItem('lastChatUnread', String(data.unread_customer_chat));
        }).catch(()=>{});
    setTimeout(pollAllBadges, 10000);
})();
// Request browser notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

// Presence heartbeat — ping every 30 seconds
(function heartbeat() {
    fetch('<?= APP_URL ?>/api/staff/presence.php', {method:'POST', credentials:'include'})
        .catch(()=>{});
    setTimeout(heartbeat, 30000);
})();
</script>
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
