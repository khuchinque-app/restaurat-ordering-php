<?php
require_once dirname(__DIR__) . '/auth.php';
$current_user = require_admin(true);
$current_path = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin') ?> &mdash; Admin Panel</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-layout">
<aside class="admin-sidebar">
    <div class="sidebar-logo">&#127860; Admin</div>
    <nav class="sidebar-nav">
        <a href="<?= APP_URL ?>/admin/index.php"     class="<?= strpos($current_path, 'admin/index') !== false ? 'active' : '' ?>">&#128202; Dashboard</a>
        <a href="<?= APP_URL ?>/admin/hub.php"       class="<?= strpos($current_path, 'admin/hub') !== false ? 'active' : '' ?>">👤 Hub</a>
        <a href="<?= APP_URL ?>/admin/checkout.php"   class="<?= strpos($current_path, 'admin/checkout') !== false ? 'active' : '' ?>">&#128230; Live Orders</a>
        <a href="<?= APP_URL ?>/admin/menu.php"       class="<?= strpos($current_path, 'admin/menu') !== false ? 'active' : '' ?>">&#127860; Menu</a>
        <a href="<?= APP_URL ?>/admin/stock.php"      class="<?= strpos($current_path, 'admin/stock') !== false ? 'active' : '' ?>">&#128230; Stock</a>
        <a href="<?= APP_URL ?>/admin/accounting.php"  class="<?= strpos($current_path, 'admin/accounting') !== false ? 'active' : '' ?>">&#128176; Accounting</a>
        <a href="<?= APP_URL ?>/admin/settings.php"    class="<?= strpos($current_path, 'admin/settings') !== false ? 'active' : '' ?>">&#9881; Settings</a>
        <a href="<?= APP_URL ?>/admin/chat.php"        class="<?= strpos($current_path, 'admin/chat') !== false ? 'active' : '' ?>" id="chatNavLink">&#128172; Chat <span id="chatUnreadBadge" style="display:none;background:#ef4444;color:#fff;border-radius:10px;font-size:.7rem;padding:.05rem .4rem;margin-left:.2rem">0</span></a>
        <a href="<?= APP_URL ?>/admin/orders.php"       class="<?= strpos($current_path, 'admin/orders') !== false ? 'active' : '' ?>" id="notifNavLink">&#128276; Alerts <span id="notifUnreadBadge" style="display:none;background:#ef4444;color:#fff;border-radius:10px;font-size:.7rem;padding:.05rem .4rem;margin-left:.2rem">0</span></a>
        <hr>
        <?php require_once dirname(__DIR__) . '/includes/storefronts.php'; render_storefront_nav(); ?>
        <div class="sidebar-section-label" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;opacity:.6;margin:.3rem 0 .2rem;padding-left:.2rem">Cashier-Outstore</div>
        <a href="<?= APP_URL ?>/aseng/cashier/" target="_blank" rel="noopener" title="Open Aseng Cashier">&#128179; Aseng Cashier &#8599;</a>
        <a href="<?= APP_URL ?>/tittil/cashier/" target="_blank" rel="noopener" title="Open Tittil Cashier">&#128179; Tittil Cashier &#8599;</a>
        <a href="<?= APP_URL ?>/logout.php">&#128682; Logout</a>
    </nav>
    <div class="sidebar-user">
        <?= htmlspecialchars($current_user['name']) ?><br>
        <small><?= htmlspecialchars($current_user['role']) ?></small>
    </div>
</aside>
<div class="admin-main">
<header class="admin-topbar">
    <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
</header>
<script>
// Unread chat badge poll
(function pollUnread() {
    fetch('<?= APP_URL ?>/api/staff/chat.php?limit=1', {credentials:'include'})
        .then(r => r.json()).then(d => {
            if (d.success) {
                const n = d.data.unread || 0;
                const b = document.getElementById('chatUnreadBadge');
                if (b) { b.textContent = n; b.style.display = n > 0 ? 'inline' : 'none'; }
            }
        }).catch(()=>{});
    setTimeout(pollUnread, 15000);
})();

// Unread notification badge poll
(function pollNotifs() {
    fetch('<?= APP_URL ?>/api/notifications/index.php', {credentials:'include'})
        .then(r => r.json()).then(d => {
            if (d.success) {
                const n = d.data.unreadCount || 0;
                const b = document.getElementById('notifUnreadBadge');
                if (b) { 
                    b.textContent = n; 
                    b.style.display = n > 0 ? 'inline' : 'none';
                    // Click badge to mark all as read
                    b.onclick = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        await fetch('<?= APP_URL ?>/api/notifications/index.php?all=1', {method:'PUT', credentials:'include'});
                        b.style.display = 'none';
                        b.textContent = '0';
                        sessionStorage.setItem('notifCount', '0');
                    };
                }
                // Show browser notification for new orders
                if (n > 0) {
                    const last = parseInt(sessionStorage.getItem('notifCount') || '0');
                    if (last > 0 && n > last && d.data.notifications && d.data.notifications[0]) {
                        const notif = d.data.notifications[0];
                        if ('Notification' in window && Notification.permission === 'granted') {
                            new Notification(notif.title, { body: notif.message, icon: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔔</text></svg>' });
                        }
                    }
                    sessionStorage.setItem('notifCount', String(n));
                } else {
                    sessionStorage.setItem('notifCount', '0');
                }
            }
        }).catch(()=>{});
    setTimeout(pollNotifs, 10000);
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
<div class="admin-content">
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
