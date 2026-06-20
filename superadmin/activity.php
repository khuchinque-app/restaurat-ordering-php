<?php
$page_title = 'Activity History';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

// Filters
$filter_user       = $_GET['user']       ?? '';
$filter_action     = $_GET['action']     ?? '';
$filter_restaurant = $_GET['restaurant'] ?? '';
$filter_date_from  = $_GET['date_from']  ?? '';
$filter_date_to    = $_GET['date_to']    ?? '';
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$skip  = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($filter_user) {
    $where[]  = 'aa.userId = ?';
    $params[] = $filter_user;
}
if ($filter_action) {
    $where[]  = 'aa.action LIKE ?';
    $params[] = '%' . $filter_action . '%';
}
if ($filter_restaurant) {
    $where[]  = 'aa.restaurantName = ?';
    $params[] = $filter_restaurant;
}
if ($filter_date_from) {
    $where[]  = 'aa.createdAt >= ?';
    $params[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to) {
    $where[]  = 'aa.createdAt <= ?';
    $params[] = $filter_date_to . ' 23:59:59';
}

$w = implode(' AND ', $where);

$total = (int)(db_fetch("SELECT COUNT(*) AS n FROM AdminActivity aa WHERE $w", $params)['n'] ?? 0);
$logs  = db_query(
    "SELECT aa.*, u.name AS userName, u.email AS userEmail, u.role AS userRole
     FROM AdminActivity aa
     LEFT JOIN User u ON u.id = aa.userId
     WHERE $w
     ORDER BY aa.createdAt DESC
     LIMIT $limit OFFSET $skip",
    $params
);

$total_pages = (int)ceil($total / $limit);

// For filter dropdowns
$staff_users  = db_query('SELECT id, name, email, role FROM User WHERE role IN ("ADMIN","MANAGER","SUPERADMIN") ORDER BY name');
$restaurants  = db_query('SELECT DISTINCT restaurantName FROM AdminActivity WHERE restaurantName IS NOT NULL AND restaurantName != "" ORDER BY restaurantName');
$action_types = db_query('SELECT DISTINCT action FROM AdminActivity ORDER BY action');

function action_color(string $action): string {
    if (str_contains($action, 'DELETE') || str_contains($action, 'CANCEL')) return '#ef4444';
    if (str_contains($action, 'CREATE') || str_contains($action, 'CONFIRMED')) return '#10b981';
    if (str_contains($action, 'UPDATE') || str_contains($action, 'STOCK') || str_contains($action, '→')) return '#f59e0b';
    if ($action === 'LOGIN') return '#6366f1';
    return '#6b7280';
}
?>

<!-- Filters -->
<form method="GET" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Staff User</label>
        <select name="user" class="form-control" style="min-width:160px">
            <option value="">All Staff</option>
            <?php foreach ($staff_users as $u): ?>
            <option value="<?= htmlspecialchars($u['id']) ?>" <?= $filter_user === $u['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Action</label>
        <select name="action" class="form-control" style="min-width:180px">
            <option value="">All Actions</option>
            <?php foreach ($action_types as $a): ?>
            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filter_action === $a['action'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['action']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Restaurant</label>
        <select name="restaurant" class="form-control" style="min-width:150px">
            <option value="">All Restaurants</option>
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['restaurantName']) ?>" <?= $filter_restaurant === $r['restaurantName'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['restaurantName']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
    </div>
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
    </div>
    <div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($filter_user || $filter_action || $filter_restaurant || $filter_date_from || $filter_date_to): ?>
        <a href="activity.php" class="btn btn-outline" style="margin-left:.5rem">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="sa-card">
    <div class="sa-card-header">
        <h2>&#128203; Activity Log</h2>
        <div style="display:flex;align-items:center;gap:.75rem">
            <span style="font-size:.85rem;color:#6b7280"><?= number_format($total) ?> event<?= $total !== 1 ? 's' : '' ?></span>
            <button onclick="exportCSV()" class="btn btn-sm btn-outline">📥 Export CSV</button>
            <button onclick="window.print()" class="btn btn-sm btn-outline">🖨 Print</button>
        </div>
    </div>

    <?php if (empty($logs)): ?>
    <div style="text-align:center; padding:2rem; color:#94a3b8">No activity found with these filters.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Details</th>
                <th>Restaurant</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td style="white-space:nowrap;font-size:.82rem;color:#6b7280">
                <?= date('M d, Y', strtotime($log['createdAt'])) ?><br>
                <strong><?= date('H:i:s', strtotime($log['createdAt'])) ?></strong>
            </td>
            <td>
                <strong><?= htmlspecialchars($log['userName'] ?? '—') ?></strong><br>
                <small style="font-size:.72rem;color:#94a3b8"><?= htmlspecialchars($log['userRole'] ?? '') ?></small>
            </td>
            <td>
                <span style="display:inline-block; font-size:.78rem; font-weight:700; padding:.18rem .55rem; border-radius:4px; color:<?= action_color($log['action']) ?>; background:<?= action_color($log['action']) ?>18">
                    <?= htmlspecialchars($log['action']) ?>
                </span>
            </td>
            <td style="font-size:.82rem">
                <?php if ($log['entityType']): ?>
                <span style="color:#64748b"><?= htmlspecialchars($log['entityType']) ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:.85rem; max-width:260px; word-break:break-word">
                <?= htmlspecialchars($log['details'] ?? '') ?>
            </td>
            <td style="font-size:.82rem;color:#6b7280">
                <?= htmlspecialchars($log['restaurantName'] ?? '') ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex; gap:.4rem; justify-content:center; padding:1rem; flex-wrap:wrap">
        <?php
        $base_params = array_filter(['user'=>$filter_user,'action'=>$filter_action,'restaurant'=>$filter_restaurant,'date_from'=>$filter_date_from,'date_to'=>$filter_date_to]);
        for ($p = 1; $p <= $total_pages; $p++):
            $params_str = http_build_query(array_merge($base_params, ['page' => $p]));
        ?>
        <a href="activity.php?<?= $params_str ?>" style="padding:.3rem .7rem; border-radius:5px; font-size:.82rem; text-decoration:none;
            <?= $p === $page ? 'background:#7c3aed; color:#fff;' : 'background:#f1f5f9; color:#374151;' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function exportCSV() {
    const rows = [['Time','User','Role','Action','Entity','Details','Restaurant','IP']];
    <?php foreach ($logs as $log): ?>
    rows.push([
        '<?= addslashes(date('Y-m-d H:i:s', strtotime($log['createdAt']))) ?>',
        '<?= addslashes($log['userName'] ?? '') ?>',
        '<?= addslashes($log['userRole'] ?? '') ?>',
        '<?= addslashes($log['action']) ?>',
        '<?= addslashes($log['entityType'] ?? '') ?>',
        '<?= addslashes($log['details'] ?? '') ?>',
        '<?= addslashes($log['restaurantName'] ?? '') ?>',
        '<?= addslashes($log['ipAddress'] ?? '') ?>'
    ]);
    <?php endforeach; ?>
    const csv = rows.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'activity-log-<?= date('Y-m-d') ?>.csv';
    a.click();
}
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
