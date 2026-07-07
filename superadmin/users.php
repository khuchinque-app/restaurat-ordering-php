<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Users';
include dirname(__DIR__) . '/includes/superadmin_header.php';

$restaurants = db_query('SELECT id, name FROM Restaurant WHERE isActive = 1 ORDER BY name ASC');
$role_filter = $_GET['role'] ?? '';
$where  = [];
$params = [];
if ($role_filter) { $where[] = 'u.role = ?'; $params[] = $role_filter; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = db_query(
    "SELECT u.id, u.email, u.name, u.role, u.isActive, u.createdAt, r.name AS restaurantName,
            up.lastSeenAt,
            (SELECT COUNT(*) FROM CustomerChat cc WHERE cc.restaurantId = u.restaurantId AND cc.senderRole NOT IN ('ADMIN','SUPERADMIN') AND cc.isRead = 0) AS unreadChats,
            (SELECT COUNT(*) FROM \"Order\" o WHERE o.restaurantId = u.restaurantId AND o.status = 'PENDING') AS pendingOrders
     FROM User u LEFT JOIN Restaurant r ON r.id = u.restaurantId
     LEFT JOIN UserPresence up ON up.userId = u.id $w ORDER BY u.createdAt DESC",
    $params
);

// Alarm: users with unread chats or pending orders
$alarms = [];
foreach ($users as $u) {
    if (($u['unreadChats'] ?? 0) > 0 || ($u['pendingOrders'] ?? 0) > 0) {
        $alarms[] = $u;
    }
}
?>

<style>
.user-row { transition: background .15s; }
.user-row:hover { background: var(--color-surface-hover); }
.user-row td { padding: .85rem .75rem !important; vertical-align: middle; }

.role-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .25rem .7rem; border-radius: 9999px;
    font-size: .75rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
}
.role-badge.superadmin { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
.role-badge.admin { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
.role-badge.manager { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.role-badge.customer { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

.user-name-cell { display: flex; flex-direction: column; }
.user-name-cell strong { font-size: .9rem; color: #1e293b; }
.user-name-cell small { font-size: .75rem; color: #94a3b8; }

.status-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .65rem; border-radius: 9999px; font-size: .75rem; font-weight: 600;
}
.status-pill.active { background: #dcfce7; color: #166534; }
.status-pill.inactive { background: #fee2e2; color: #dc2626; }

.activity-time { font-size: .75rem; color: #64748b; white-space: nowrap; }
.activity-time.recent { color: #059669; font-weight: 600; }
.activity-time.stale { color: #dc2626; font-weight: 600; }

.alarm-box {
    background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px;
    padding: .6rem 1rem; margin-bottom: 1.25rem; font-size: .78rem;
}
.alarm-box h3 { color: #dc2626; font-size: .82rem; margin-bottom: .2rem; }
.alarm-box ul { list-style: none; padding: 0; margin: 0; }
.alarm-box li { font-size: .75rem; padding: .15rem 0; color: #7f1d1d; display: flex; align-items: center; gap: .4rem; }

#create-form-wrap { background: #fafbfc; border-radius: 0 0 10px 10px; }
.actions-cell { display: flex; gap: .4rem; align-items: center; }
.resto-cell { font-size: .82rem; color: #64748b; }
.role-filter-btn { font-size: .75rem !important; padding: .3rem .65rem !important; }
</style>

<!-- Alarm Section -->
<?php if (!empty($alarms)): ?>
<div class="alarm-box">
    <h3>🚨 Attention Required — Missed / Unreplied</h3>
    <ul>
    <?php foreach ($alarms as $a): ?>
        <li>
            ⚠ <strong><?= htmlspecialchars($a['name'] ?? 'Unknown') ?></strong>
            (<?= htmlspecialchars($a['restaurantName'] ?? '—') ?>)
            <?php if (($a['unreadChats'] ?? 0) > 0): ?>
                — <?= $a['unreadChats'] ?> unread customer chat<?= $a['unreadChats'] > 1 ? 's' : '' ?>
            <?php endif; ?>
            <?php if (($a['pendingOrders'] ?? 0) > 0): ?>
                — <?= $a['pendingOrders'] ?> pending order<?= $a['pendingOrders'] > 1 ? 's' : '' ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Create Account Form -->
<div class="sa-card" style="margin-bottom:1.25rem">
    <div class="sa-card-header">
        <h2>👤 Create Account</h2>
        <button class="btn btn-sm btn-outline" id="toggle-form-btn">Show Form</button>
    </div>
    <div id="create-form-wrap" style="display:none; padding:1.25rem; border-top:1px solid #e2e8f0">
        <form id="create-user-form" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.85rem; align-items:end">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. John Smith">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="user@email.com">
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" class="form-control" required id="role-select">
                    <option value="SUPERADMIN">SUPERADMIN</option>
                    <option value="ADMIN" selected>ADMIN</option>
                    <option value="MANAGER">MANAGER</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                </select>
            </div>
            <div class="form-group" id="restaurant-field">
                <label>Restaurant</label>
                <select name="restaurantId" class="form-control" id="restaurant-select">
                    <option value="">— None / Superadmin —</option>
                    <?php foreach ($restaurants as $r): ?>
                    <option value="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center">
                <button type="submit" class="btn btn-primary">Create Account</button>
                <button type="button" class="btn btn-outline btn-sm" id="cancel-form-btn">Cancel</button>
            </div>
        </form>
        <div id="create-error" style="color:#dc2626;font-size:.875rem;margin-top:.5rem;display:none"></div>
    </div>
</div>

<!-- Users Table -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>👥 All Users</h2>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
            <?php foreach (['', 'SUPERADMIN','ADMIN','MANAGER','CUSTOMER'] as $role): ?>
            <a href="users.php<?= $role ? '?role=' . $role : '' ?>"
               class="btn btn-sm role-filter-btn <?= $role_filter === $role ? 'btn-primary' : 'btn-outline' ?>">
               <?= $role ?: 'All' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Restaurant</th><th>Status</th><th>Last Active</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
            $role_class = strtolower($u['role']);
            $lastSeen = $u['lastSeenAt'] ?? null;
            $activeClass = 'recent';
            $activeLabel = '—';
            if ($lastSeen) {
                $diff = time() - strtotime($lastSeen);
                if ($diff < 300) { $activeLabel = 'Now'; $activeClass = 'recent'; }
                elseif ($diff < 3600) { $activeLabel = floor($diff/60) . 'm ago'; $activeClass = 'recent'; }
                elseif ($diff < 86400) { $activeLabel = floor($diff/3600) . 'h ' . floor(($diff%3600)/60) . 'm ago'; $activeClass = ($diff < 43200) ? 'recent' : 'stale'; }
                else { $activeLabel = floor($diff/86400) . 'd ago'; $activeClass = 'stale'; }
            }
        ?>
        <tr class="user-row" id="user-row-<?= htmlspecialchars($u['id']) ?>">
            <td>
                <div class="user-name-cell">
                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                    <small>#<?= htmlspecialchars($u['id']) ?></small>
                </div>
            </td>
            <td style="font-size:.85rem;color:#64748b"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="role-badge <?= $role_class ?>"><?= htmlspecialchars($u['role']) ?></span></td>
            <td class="resto-cell"><?= htmlspecialchars($u['restaurantName'] ?? '—') ?></td>
            <td><span class="status-pill <?= $u['isActive'] ? 'active' : 'inactive' ?>">● <?= $u['isActive'] ? 'Active' : 'Inactive' ?></span></td>
            <td><span class="activity-time <?= $activeClass ?>"><?= $activeLabel ?></span></td>
            <td style="font-size:.8rem;color:#94a3b8;white-space:nowrap"><?= date('M d, Y', strtotime($u['createdAt'])) ?></td>
            <td>
                <div class="actions-cell">
                <?php if ($current_user['role'] === 'SUPERADMIN'): ?>
                <?php if ($u['role'] !== 'SUPERADMIN' || $current_user['id'] !== $u['id']): ?>
                <button class="btn btn-sm <?= $u['isActive'] ? 'btn-outline' : 'btn-primary' ?> toggle-user-btn"
                        data-id="<?= htmlspecialchars($u['id']) ?>"
                        data-active="<?= $u['isActive'] ?>">
                    <?= $u['isActive'] ? 'Disable' : 'Enable' ?>
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-danger delete-user-btn"
                        data-id="<?= htmlspecialchars($u['id']) ?>"
                        data-name="<?= htmlspecialchars($u['name']) ?>">🗑</button>
                <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8">No users found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Toggle form
document.getElementById('toggle-form-btn').addEventListener('click', function() {
    const wrap = document.getElementById('create-form-wrap');
    const shown = wrap.style.display !== 'none';
    wrap.style.display = shown ? 'none' : '';
    this.textContent  = shown ? 'Show Form' : 'Hide Form';
});
document.getElementById('cancel-form-btn')?.addEventListener('click', () => {
    document.getElementById('create-form-wrap').style.display = 'none';
    document.getElementById('toggle-form-btn').textContent = 'Show Form';
});

// Hide restaurant field for SUPERADMIN role
document.getElementById('role-select')?.addEventListener('change', function() {
    document.getElementById('restaurant-field').style.opacity = this.value === 'SUPERADMIN' ? '.4' : '1';
    if (this.value === 'SUPERADMIN') document.getElementById('restaurant-select').value = '';
});

// Submit create account
document.getElementById('create-user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('create-error');
    errEl.style.display = 'none';
    const f = e.target;
    const body = {
        name:         f.name.value.trim(),
        email:        f.email.value.trim(),
        password:     f.password.value,
        role:         f.role.value,
        restaurantId: f.restaurantId.value || null,
    };
    try {
        const res  = await fetch('/api/superadmin/create_user.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) location.reload();
        else { errEl.textContent = data.error || 'Failed to create account'; errEl.style.display = 'block'; }
    } catch { errEl.textContent = 'Network error'; errEl.style.display = 'block'; }
});

// Toggle user active
document.querySelectorAll('.toggle-user-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id     = btn.dataset.id;
        const active = btn.dataset.active === '1' ? 0 : 1;
        const res    = await fetch(`/api/superadmin/users.php?id=${id}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ isActive: active })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error);
    });
});

// Delete user
document.querySelectorAll('.delete-user-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const name = btn.dataset.name;
        if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
        const res = await fetch(`/api/superadmin/users.php?id=${btn.dataset.id}`, {
            method: 'DELETE', headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Failed to delete');
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
