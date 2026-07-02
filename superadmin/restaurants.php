<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Restaurants';
include dirname(__DIR__) . '/includes/superadmin_header.php';

$restaurants = db_query(
    'SELECT r.*,
            (SELECT COUNT(*) FROM "Order" WHERE restaurantId = r.id) AS totalOrders,
            (SELECT COALESCE(SUM(CAST(totalAmount AS REAL)),0) FROM "Order" WHERE restaurantId = r.id AND status IN ("COMPLETED","READY","OUT_FOR_DELIVERY")) AS totalRevenue,
            (SELECT COUNT(*) FROM User WHERE restaurantId = r.id AND role = "CUSTOMER") AS customers,
            (SELECT COUNT(*) FROM User WHERE restaurantId = r.id AND role IN ("ADMIN","MANAGER")) AS admins
     FROM Restaurant r
     WHERE r.isActive = 1
     ORDER BY r.createdAt DESC'
);
?>

<div class="sa-card">
    <div class="sa-card-header">
        <h2>All Restaurants</h2>
        <button class="btn btn-primary btn-sm" id="add-restaurant-btn">+ New Restaurant</button>
    </div>

    <!-- Add Restaurant Form -->
    <div id="add-restaurant-form" style="display:none; padding:1rem; border-bottom:1px solid #e2e8f0; background:#f8fafc">
        <form id="restaurant-form" style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem">
            <div class="form-group" style="margin:0">
                <label>Restaurant Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Burger House">
            </div>
            <div class="form-group" style="margin:0">
                <label>Slug * (URL-safe)</label>
                <input type="text" name="slug" class="form-control" required placeholder="e.g. burger-house">
            </div>
            <div class="form-group" style="margin:0; grid-column:1/-1">
                <label>Description</label>
                <input type="text" name="description" class="form-control" placeholder="Short description">
            </div>
            <div style="grid-column:1/-1; display:flex; gap:.5rem; justify-content:flex-end">
                <button type="button" class="btn btn-ghost btn-sm" id="cancel-restaurant-btn">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Restaurant</button>
            </div>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr><th>Name / Slug</th><th>Customers</th><th>Orders</th><th>Revenue</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($restaurants as $r): ?>
        <tr id="restaurant-row-<?= htmlspecialchars($r['id']) ?>">
            <td>
                <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                <small class="text-muted">slug: <?= htmlspecialchars($r['slug']) ?></small>
            </td>
            <td><?= (int)$r['customers'] ?></td>
            <td><?= (int)$r['totalOrders'] ?></td>
            <td>$<?= number_format((float)$r['totalRevenue'], 2) ?></td>
            <td>
                <span class="<?= $r['isActive'] ? 'restaurant-active' : 'restaurant-inactive' ?>">
                    <?= $r['isActive'] ? 'Active' : 'Inactive' ?>
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline toggle-restaurant-btn"
                        data-id="<?= htmlspecialchars($r['id']) ?>"
                        data-active="<?= $r['isActive'] ?>">
                    <?= $r['isActive'] ? 'Disable' : 'Enable' ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($restaurants)): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;padding:2rem">No restaurants yet</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('add-restaurant-btn')?.addEventListener('click', () => {
    document.getElementById('add-restaurant-form').style.display = '';
});
document.getElementById('cancel-restaurant-btn')?.addEventListener('click', () => {
    document.getElementById('add-restaurant-form').style.display = 'none';
});

// Auto-fill slug from name
document.querySelector('[name="name"]')?.addEventListener('input', function() {
    const slugField = document.querySelector('[name="slug"]');
    if (!slugField._touched) {
        slugField.value = this.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    }
});
document.querySelector('[name="slug"]')?.addEventListener('input', function() { this._touched = true; });

document.getElementById('restaurant-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const data = {
        name:        form.name.value.trim(),
        slug:        form.slug.value.trim(),
        description: form.description.value.trim() || null,
    };
    try {
        const res  = await fetch('/api/superadmin/restaurants.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) location.reload();
        else alert(json.error || 'Failed to create restaurant');
    } catch { alert('Network error'); }
});

document.querySelectorAll('.toggle-restaurant-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id     = btn.dataset.id;
        const active = btn.dataset.active === '1' ? 0 : 1;
        const res    = await fetch(`/api/superadmin/restaurants.php?id=${id}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ isActive: active })
        });
        const json = await res.json();
        if (json.success) location.reload();
        else alert(json.error);
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
