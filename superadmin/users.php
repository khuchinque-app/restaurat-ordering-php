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
    "SELECT u.id, u.email, u.name, u.role, u.isActive, u.createdAt, r.name AS restaurantName
     FROM User u LEFT JOIN Restaurant r ON r.id = u.restaurantId $w ORDER BY u.createdAt DESC",
    $params
);
?>

<!-- Create Account Form -->
<div class="sa-card" style="margin-bottom:1.25rem">
    <div class="sa-card-header">
        <h2>&#43; Create Account</h2>
        <button class="btn btn-sm btn-outline" id="toggle-form-btn">Show Form</button>
    </div>
    <div id="create-form-wrap" style="display:none; padding:1.25rem; border-top:1px solid #e2e8f0">
        <form id="create-user-form" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem; align-items:end">
            <div class="form-group" style="margin:0">
                <label>Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. John Smith">
            </div>
            <div class="form-group" style="margin:0">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="user@email.com">
            </div>
            <div class="form-group" style="margin:0">
                <label>Password *</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
            </div>
            <div class="form-group" style="margin:0">
                <label>Role *</label>
                <select name="role" class="form-control" required id="role-select">
                    <option value="SUPERADMIN">SUPERADMIN</option>
                    <option value="ADMIN" selected>ADMIN</option>
                    <option value="MANAGER">MANAGER</option>
                    <option value="CUSTOMER">CUSTOMER</option>
                </select>
            </div>
            <div class="form-group" style="margin:0" id="restaurant-field">
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
                <button type="button" class="btn btn-ghost" id="cancel-form-btn">Cancel</button>
            </div>
        </form>
        <div id="create-error" style="color:#dc2626;font-size:.875rem;margin-top:.5rem;display:none"></div>
    </div>
</div>

<!-- Users Table -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>All Users</h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach (['', 'SUPERADMIN','ADMIN','MANAGER','CUSTOMER'] as $role): ?>
            <a href="users.php<?= $role ? '?role=' . $role : '' ?>"
               class="btn btn-sm <?= $role_filter === $role ? 'btn-primary' : 'btn-outline' ?>">
               <?= $role ?: 'All' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Restaurant</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr id="user-row-<?= htmlspecialchars($u['id']) ?>">
            <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="status-badge" style="background:#ede9fe;color:#4c1d95"><?= htmlspecialchars($u['role']) ?></span></td>
            <td><?= htmlspecialchars($u['restaurantName'] ?? '&mdash;') ?></td>
            <td><span class="<?= $u['isActive'] ? 'restaurant-active' : 'restaurant-inactive' ?>"><?= $u['isActive'] ? 'Active' : 'Inactive' ?></span></td>
            <td><?= date('M d, Y', strtotime($u['createdAt'])) ?></td>
            <td>
                <?php if ($u['role'] !== 'SUPERADMIN' || $current_user['id'] !== $u['id']): ?>
                <button class="btn btn-sm btn-outline toggle-user-btn"
                        data-id="<?= htmlspecialchars($u['id']) ?>"
                        data-active="<?= $u['isActive'] ?>">
                    <?= $u['isActive'] ? 'Disable' : 'Enable' ?>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;padding:2rem" class="text-muted">No users found</td></tr>
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
        if (data.success) {
            location.reload();
        } else {
            errEl.textContent = data.error || 'Failed to create account';
            errEl.style.display = 'block';
        }
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
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
