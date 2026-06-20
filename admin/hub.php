<?php
$page_title = 'Admin Hub';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/activity.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new_pass) {
            $error = 'All fields are required.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_pass !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $user = db_fetch('SELECT password FROM User WHERE id = ?', [$current_user['id']]);
            if (!$user || !password_verify($current, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                db_execute('UPDATE User SET password = ?, updatedAt = datetime("now") WHERE id = ?',
                    [password_hash($new_pass, PASSWORD_DEFAULT), $current_user['id']]);
                log_activity($current_user, 'CHANGE_PASSWORD', 'User', $current_user['id'], 'Admin changed own password');
                $message = '✅ Password changed successfully!';
            }
        }
    }
}

// Restaurant info
$restaurant = get_restaurant();
$rid = $restaurant['id'] ?? null;
$today = date('Y-m-d');
$stats = [
    'today_orders' => $rid ? (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND createdAt >= ?', [$rid, $today . ' 00:00:00'])['n'] ?? 0) : 0,
    'pending' => $rid ? (int)(db_fetch('SELECT COUNT(*) AS n FROM "Order" WHERE restaurantId = ? AND status = "PENDING"', [$rid])['n'] ?? 0) : 0,
    'today_revenue' => $rid ? (float)(db_fetch('SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) AS n FROM "Order" WHERE restaurantId = ? AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY") AND createdAt >= ?', [$rid, $today . ' 00:00:00'])['n'] ?? 0) : 0,
    'total_staff' => (int)(db_fetch('SELECT COUNT(*) AS n FROM User WHERE restaurantId = ? AND role IN ("ADMIN","MANAGER")', [$rid])['n'] ?? 0),
];
?>

<div style="max-width:800px">
    <!-- Welcome Card -->
    <div class="card" style="margin-bottom:1.5rem">
        <div style="display:flex;align-items:center;gap:1.25rem">
            <div style="width:64px;height:64px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:2rem">
                👤
            </div>
            <div>
                <h2 style="font-size:1.3rem;font-weight:700;margin:0">Welcome, <?= htmlspecialchars($current_user['name']) ?></h2>
                <p style="color:#6b7280;margin:.25rem 0 0;font-size:.9rem">
                    <?= htmlspecialchars($restaurant['name'] ?? 'Restaurant') ?> &bull;
                    <span style="color:#059669;font-weight:600">ADMIN</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7">📦</div>
            <div>
                <div class="stat-label">Today's Orders</div>
                <div class="stat-value"><?= $stats['today_orders'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7">⏳</div>
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe">💰</div>
            <div>
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value">$<?= number_format($stats['today_revenue'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h2>🔒 Change Password</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="hub.php" style="max-width:400px">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" class="form-control" minlength="6" required>
                <small style="color:#6b7280">Minimum 6 characters</small>
            </div>
            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>

    <!-- Account Info -->
    <div class="card" style="margin-top:1.25rem">
        <div class="card-header"><h2>📋 Account Info</h2></div>
        <table class="table" style="max-width:400px">
            <tr><td style="font-weight:600;width:120px">Email</td><td><?= htmlspecialchars($current_user['email']) ?></td></tr>
            <tr><td style="font-weight:600">Name</td><td><?= htmlspecialchars($current_user['name']) ?></td></tr>
            <tr><td style="font-weight:600">Role</td><td><span class="badge badge-success">ADMIN</span></td></tr>
            <tr><td style="font-weight:600">Restaurant</td><td><?= htmlspecialchars($restaurant['name'] ?? 'N/A') ?></td></tr>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
