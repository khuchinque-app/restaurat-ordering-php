<?php
$page_title = 'Menu Management';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

$restaurants = db_query('SELECT id, name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name');
$slug = $_GET['restaurant'] ?? ($restaurants[0]['slug'] ?? null);
$restaurant = $slug ? get_restaurant($slug) : null;
$rid = $restaurant['id'] ?? null;

$categories = $rid ? db_query('SELECT * FROM Category WHERE restaurantId = ? ORDER BY sortOrder ASC', [$rid]) : [];
$items = $rid ? db_query(
    'SELECT mi.*, c.name AS categoryName FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId WHERE mi.restaurantId = ? ORDER BY c.sortOrder ASC, mi.name ASC',
    [$rid]
) : [];
?>

<!-- Restaurant Selector -->
<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
    <div>
        <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:.25rem">Restaurant</label>
        <select onchange="location.href='menu.php?restaurant='+this.value" class="form-control" style="min-width:200px">
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>" <?= $slug === $r['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="font-size:.85rem;color:#64748b;align-self:end;padding-bottom:.4rem">
        <?= count($items) ?> items &bull; <?= count($categories) ?> categories
    </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:1.25rem">

<!-- Categories Panel -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>📁 Categories</h2>
        <button class="btn btn-primary btn-sm" id="add-cat-btn">+ Add</button>
    </div>

    <div id="add-cat-form" style="display:none;padding:.75rem;border-bottom:1px solid #e2e8f0;background:#f8fafc">
        <form id="cat-form" style="display:flex;gap:.5rem;align-items:end">
            <input type="text" name="name" class="form-control" placeholder="Name" required style="flex:1">
            <input type="number" name="sortOrder" class="form-control" placeholder="Sort" value="0" style="width:60px">
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('add-cat-form').style.display='none'">✕</button>
        </form>
    </div>

    <div style="max-height:calc(100vh - 300px);overflow-y:auto">
    <?php foreach ($categories as $cat):
        $item_count = (int)(db_fetch('SELECT COUNT(*) AS n FROM MenuItem WHERE categoryId = ?', [$cat['id']])['n'] ?? 0);
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem .75rem;border-bottom:1px solid #f1f5f9" id="cat-<?= htmlspecialchars($cat['id']) ?>">
        <div>
            <strong style="font-size:.9rem"><?= htmlspecialchars($cat['name']) ?></strong>
            <span style="font-size:.75rem;color:#94a3b8">(<?= $item_count ?>)</span>
        </div>
        <div style="display:flex;gap:.3rem">
            <button class="btn btn-sm btn-outline toggle-cat-btn" data-id="<?= htmlspecialchars($cat['id']) ?>" data-active="<?= $cat['isActive'] ?>">
                <?= $cat['isActive'] ? 'On' : 'Off' ?>
            </button>
            <?php if ($item_count === 0): ?>
            <button class="btn btn-sm btn-danger delete-cat-btn" data-id="<?= htmlspecialchars($cat['id']) ?>">✕</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($categories)): ?>
    <div style="text-align:center;padding:1.5rem;color:#94a3b8;font-size:.85rem">No categories yet</div>
    <?php endif; ?>
    </div>
</div>

<!-- Menu Items Panel -->
<div class="sa-card">
    <div class="sa-card-header">
        <h2>🍽 Menu Items</h2>
        <div style="display:flex;gap:.5rem">
            <input type="search" id="item-search" class="form-control" placeholder="Search..." style="width:180px;font-size:.85rem">
            <button class="btn btn-primary btn-sm" id="add-item-btn">+ Add Item</button>
        </div>
    </div>

    <div style="overflow-x:auto">
    <table class="table" id="items-table">
        <thead><tr><th style="width:50px">Img</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Avail</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
        <tr id="item-<?= htmlspecialchars($item['id']) ?>"
            data-category="<?= htmlspecialchars($item['categoryId']) ?>"
            data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>">
            <td style="text-align:center;vertical-align:middle">
                <?php if ($item['image']): ?>
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="" style="width:40px;height:40px;border-radius:6px;object-fit:cover;border:1px solid #e5e7eb">
                <?php else: ?>
                <span style="font-size:.8rem;color:#cbd5e1">📷</span>
                <?php endif; ?>
            </td>
            <td>
                <strong><?= htmlspecialchars($item['name']) ?></strong>
                <?php if ($item['description']): ?><br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 50, '...')) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($item['categoryName']) ?></td>
            <td>$<?= number_format((float)$item['price'], 2) ?> <small style="color:#94a3b8">· <?= number_format((float)$item['price'] * 4000) ?> KHR</small></td>
            <td class="<?= $item['stockQuantity'] !== null && $item['stockQuantity'] <= $item['lowStockThreshold'] ? 'text-warn' : '' ?>">
                <?= $item['stockQuantity'] !== null ? (int)$item['stockQuantity'] : '∞' ?>
            </td>
            <td>
                <button class="btn btn-sm btn-outline toggle-item-btn" data-id="<?= htmlspecialchars($item['id']) ?>" data-available="<?= $item['isAvailable'] ?>">
                    <?= $item['isAvailable'] ? 'On' : 'Off' ?>
                </button>
            </td>
            <td>
                <button class="btn btn-sm btn-outline edit-item-btn" data-item='<?= htmlspecialchars(json_encode($item)) ?>'>Edit</button>
                <button class="btn btn-sm btn-danger delete-item-btn" data-id="<?= htmlspecialchars($item['id']) ?>">✕</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8">No items. Add some!</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

</div>

<style>
.fp-gallery-toggle { cursor:pointer; color:#7c3aed; font-size:.82rem; text-decoration:underline; margin-top:.25rem; display:inline-block; }
.fp-gallery-toggle:hover { color:#6d28d9; }
.fp-gallery { display:none; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; max-height:240px; overflow-y:auto; padding:.5rem; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; }
.fp-gallery.open { display:flex; }
.fp-gallery img { width:56px; height:56px; object-fit:cover; border-radius:6px; border:2px solid transparent; cursor:pointer; transition:all .15s; }
.fp-gallery img:hover { border-color:#7c3aed; transform:scale(1.1); }
.fp-gallery img.selected { border-color:#7c3aed; box-shadow:0 0 0 2px rgba(124,58,237,0.3); }
</style>
<!-- Add/Edit Item Modal -->
<div class="modal" id="item-modal" style="display:none">
    <div class="modal-backdrop" id="modal-backdrop"></div>
    <div class="modal-dialog card">
        <div class="card-header">
            <h2 id="modal-title">Add Menu Item</h2>
            <button class="btn btn-ghost btn-sm" id="close-modal">&times;</button>
        </div>
        <form id="item-form">
            <input type="hidden" id="item-id" name="id">
            <div class="form-grid">
                <div class="form-group"><label>Name *</label><input type="text" name="name" id="item-name" class="form-control" required></div>
                <div class="form-group"><label>Category *</label>
                    <select name="categoryId" id="item-category" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Price *</label><input type="number" name="price" id="item-price" class="form-control" step="0.01" min="0" required></div>
                <div class="form-group"><label>Stock</label><input type="number" name="stockQuantity" id="item-stock" class="form-control" min="0" placeholder="Empty = unlimited"></div>
                <div class="form-group"><label>Low Stock Threshold</label><input type="number" name="lowStockThreshold" id="item-threshold" class="form-control" min="0" value="5"></div>
                <div class="form-group"><label>Prep Time (min)</label><input type="number" name="preparationTime" id="item-prep" class="form-control" min="0" value="15"></div>
                <div class="form-group form-full"><label>Description</label><textarea name="description" id="item-desc" class="form-control" rows="2"></textarea></div>
                <div class="form-group form-full">
                    <label>📸 Menu Image</label>
                    <div style="display:flex;gap:.75rem;align-items:start">
                        <div style="flex:1">
                            <div id="image-preview-wrap" style="display:none;margin-bottom:.5rem">
                                <img id="image-preview" src="" alt="Preview" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover">
                                <button type="button" class="btn btn-sm btn-danger" onclick="clearImagePreview()" style="margin-top:.3rem">✕ Remove</button>
                            </div>
                            <input type="file" id="item-image-file" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control" style="font-size:.85rem">
                            <small style="color:#6b7280;display:block;margin-top:.25rem">JPG, PNG, GIF, WebP • Max 5MB</small>
                        </div>
                    </div>
                    <div style="margin-top:.5rem">
                        <input type="url" name="image" id="item-image" class="form-control" placeholder="Or paste image URL (https://...)" style="font-size:.85rem">
                    </div>
                    <div style="margin-top:.3rem">
                        <a class="fp-gallery-toggle" onclick="toggleGallery()" id="galleryToggle">📂 Pick from Foodpanda images</a>
                        <div class="fp-gallery" id="fpGallery"></div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" id="cancel-item-btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-item-btn">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
const RESTAURANT_SLUG = <?= json_encode($slug ?? '') ?>;

// Category CRUD
document.getElementById('add-cat-btn')?.addEventListener('click', () => {
    document.getElementById('add-cat-form').style.display = '';
});

document.getElementById('cat-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const name = f.name.value.trim();
    const slug = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    const res = await fetch(`/api/menu/categories.php?restaurant=${RESTAURANT_SLUG}`, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({name, slug, sortOrder: parseInt(f.sortOrder.value)||0})
    });
    const d = await res.json();
    if (d.success) location.reload(); else alert(d.error || 'Failed');
});

document.querySelectorAll('.toggle-cat-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const active = btn.dataset.active === '1' ? 0 : 1;
        const res = await fetch(`/api/menu/categories.php?id=${btn.dataset.id}&restaurant=${RESTAURANT_SLUG}`, {
            method: 'PUT', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({isActive: active})
        });
        if ((await res.json()).success) location.reload();
    });
});

document.querySelectorAll('.delete-cat-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this category?')) return;
        const res = await fetch(`/api/menu/categories.php?id=${btn.dataset.id}&restaurant=${RESTAURANT_SLUG}`, {method:'DELETE'});
        if ((await res.json()).success) document.getElementById('cat-'+btn.dataset.id)?.remove();
        else alert('Cannot delete category with items');
    });
});

// Item Modal
const modal = document.getElementById('item-modal');

document.getElementById('add-item-btn')?.addEventListener('click', () => openModal(null));
document.getElementById('close-modal')?.addEventListener('click', () => modal.style.display = 'none');
document.getElementById('cancel-item-btn')?.addEventListener('click', () => modal.style.display = 'none');
document.getElementById('modal-backdrop')?.addEventListener('click', () => modal.style.display = 'none');

document.querySelectorAll('.edit-item-btn').forEach(btn => {
    btn.addEventListener('click', () => { try { openModal(JSON.parse(btn.dataset.item)); } catch {} });
});

let pendingImageUrl = '';

function clearImagePreview() {
    document.getElementById('image-preview-wrap').style.display = 'none';
    document.getElementById('image-preview').src = '';
    document.getElementById('item-image-file').value = '';
    document.getElementById('item-image').value = '';
    pendingImageUrl = '';
}

document.getElementById('item-image-file')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const wrap = document.getElementById('image-preview-wrap');
    const img = document.getElementById('image-preview');
    img.src = URL.createObjectURL(file);
    wrap.style.display = '';
    const saveBtn = document.getElementById('save-item-btn');
    saveBtn.disabled = true; saveBtn.textContent = 'Uploading...';
    const formData = new FormData();
    formData.append('image', file);
    try {
        const r = await fetch('/api/menu/upload.php', { method: 'POST', credentials: 'include', body: formData });
        const d = await r.json();
        if (d.success) {
            pendingImageUrl = d.data.url;
            document.getElementById('item-image').value = d.data.url;
            saveBtn.textContent = 'Upload Done ✓';
            setTimeout(() => { saveBtn.textContent = 'Save Item'; saveBtn.disabled = false; }, 1000);
        } else {
            alert(d.error || 'Upload failed');
            saveBtn.textContent = 'Save Item'; saveBtn.disabled = false;
        }
    } catch (err) {
        alert('Upload failed: ' + err.message);
        saveBtn.textContent = 'Save Item'; saveBtn.disabled = false;
    }
});

document.getElementById('item-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const saveBtn = document.getElementById('save-item-btn');
    saveBtn.disabled = true; saveBtn.textContent = 'Saving...';
    const id = document.getElementById('item-id').value;
    const stockVal = document.getElementById('item-stock').value;
    const body = {
        name: document.getElementById('item-name').value.trim(),
        categoryId: document.getElementById('item-category').value,
        price: document.getElementById('item-price').value,
        stockQuantity: stockVal !== '' ? parseInt(stockVal) : null,
        lowStockThreshold: parseInt(document.getElementById('item-threshold').value) || 5,
        preparationTime: parseInt(document.getElementById('item-prep').value) || 15,
        description: document.getElementById('item-desc').value.trim() || null,
        image: document.getElementById('item-image').value.trim() || null,
    };
    const url = id ? `/api/menu/items.php?id=${id}&restaurant=${RESTAURANT_SLUG}` : `/api/menu/items.php?restaurant=${RESTAURANT_SLUG}`;
    const res = await fetch(url, { method: id ? 'PUT' : 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const d = await res.json();
    if (d.success) location.reload(); else alert(d.error || 'Failed');
    saveBtn.disabled = false; saveBtn.textContent = 'Save Item';
});

function openModal(item) {
    document.getElementById('modal-title').textContent = item ? 'Edit Menu Item' : 'Add Menu Item';
    document.getElementById('item-id').value = item?.id || '';
    document.getElementById('item-name').value = item?.name || '';
    document.getElementById('item-category').value = item?.categoryId || '';
    document.getElementById('item-price').value = item?.price || '';
    document.getElementById('item-stock').value = item?.stockQuantity ?? '';
    document.getElementById('item-threshold').value = item?.lowStockThreshold || 5;
    document.getElementById('item-prep').value = item?.preparationTime || 15;
    document.getElementById('item-desc').value = item?.description || '';
    document.getElementById('item-image').value = item?.image || '';
    clearImagePreview();
    if (item?.image) {
        document.getElementById('image-preview').src = item.image;
        document.getElementById('image-preview-wrap').style.display = '';
    }
    modal.style.display = '';
}

// Toggle + Delete items
document.querySelectorAll('.toggle-item-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const avail = btn.dataset.available === '1' ? 0 : 1;
        const res = await fetch(`/api/menu/items.php?id=${btn.dataset.id}&restaurant=${RESTAURANT_SLUG}`, {
            method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({isAvailable:avail})
        });
        if ((await res.json()).success) location.reload();
    });
});

document.querySelectorAll('.delete-item-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this item?')) return;
        const res = await fetch(`/api/menu/items.php?id=${btn.dataset.id}&restaurant=${RESTAURANT_SLUG}`, {method:'DELETE'});
        if ((await res.json()).success) document.getElementById('item-'+btn.dataset.id)?.remove();
    });
});

// Search
document.getElementById('item-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#items-table tbody tr').forEach(row => {
        row.style.display = !q || (row.dataset.name||'').includes(q) ? '' : 'none';
    });
});
// ── Foodpanda Image Gallery ──────────────────────────
const ASSET_IMG_DIRS = {
    'aseng': '/aseng/asset-img/',
    'tittil': '/tittil/asset-img/'
};

function toggleGallery() {
    const gallery = document.getElementById('fpGallery');
    const toggle = document.getElementById('galleryToggle');
    if (gallery.classList.contains('open')) {
        gallery.classList.remove('open');
        gallery.innerHTML = '';
        toggle.textContent = '📂 Pick from Foodpanda images';
        return;
    }
    const slug = document.querySelector('select[onchange*="menu.php"]')?.value || RESTAURANT_SLUG;
    const dir = ASSET_IMG_DIRS[slug];
    if (!dir) { alert('No Foodpanda images for this restaurant'); return; }
    
    gallery.innerHTML = '<div style="width:100%;font-size:.8rem;color:#94a3b8;padding:.3rem">Loading...</div>';
    gallery.classList.add('open');
    toggle.textContent = '📂 Close gallery';
    
    // Fetch the asset-img directory listing (the README lists files)
    // Since we can't list dirs from JS, we use a known list
    const knownCounts = { 'aseng': 144, 'tittil': 51 };
    const count = knownCounts[slug] || 0;
    gallery.innerHTML = '';
    
    // Generate gallery from known file pattern
    if (slug === 'aseng') {
        const pids = [3930501,3930502,3930503,3930504,3930505,3930506,3930519,3930521,3930523,3930526,3930527,3930528,3930529,3930531,3930532,3930533,3930534,3930535,3930536,3930537,3930538,3930539,3930540,3930541,3930542,3930543,3930544,3930545,3930546,3930547,3930548,3930549,3930550,3930551,3930552,3930553,3930554,3930555,3930556,3930557,3930558,3930559,3930560,3930561,3930562,3930563,3930564,3930565,3930566,3930567,3930568,3930569,3930570,3930581,3930582,3930583,3930584,3930585,3930586,3930587,3930588,3930589,3930590,3930591,3930592,3930593,3930594,3930595,3930596,3930597,3930598,3930599];
        pids.forEach(pid => {
            const url = dir + 'fp_' + pid + '.jpg';
            const img = document.createElement('img');
            img.src = url;
            img.loading = 'lazy';
            img.title = 'fp_' + pid;
            img.onclick = function() {
                document.getElementById('item-image').value = url;
                document.getElementById('image-preview').src = url;
                document.getElementById('image-preview-wrap').style.display = '';
                document.querySelectorAll('#fpGallery img').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
            };
            img.onerror = function() { this.style.display = 'none'; };
            gallery.appendChild(img);
        });
    } else if (slug === 'tittil') {
        for (let pid = 3930600; pid <= 3930649; pid++) {
            const url = dir + 'fp_' + pid + '.jpg';
            const img = document.createElement('img');
            img.src = url;
            img.loading = 'lazy';
            img.title = 'fp_' + pid;
            img.onclick = function() {
                document.getElementById('item-image').value = url;
                document.getElementById('image-preview').src = url;
                document.getElementById('image-preview-wrap').style.display = '';
                document.querySelectorAll('#fpGallery img').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
            };
            img.onerror = function() { this.style.display = 'none'; };
            gallery.appendChild(img);
        }
    }
    
    if (!gallery.children.length) {
        gallery.innerHTML = '<div style="width:100%;font-size:.8rem;color:#94a3b8;padding:.3rem">No images found</div>';
    }
}

</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
