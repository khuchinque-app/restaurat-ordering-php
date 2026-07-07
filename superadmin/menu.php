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

<style>
/* ---- Bulk Upload ---- */
.bulk-upload-area {
    margin-bottom: 1.25rem;
}
.bulk-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2.5rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fafafa;
}
.bulk-dropzone:hover,
.bulk-dropzone.drag-over {
    border-color: #7c3aed;
    background: #f5f3ff;
}
.bulk-dropzone-icon { font-size: 2.5rem; margin-bottom: .5rem; }
.bulk-dropzone-text { font-size: 1rem; font-weight: 600; color: #374151; }
.bulk-dropzone-hint { font-size: .8rem; color: #9ca3af; margin-top: .25rem; }

.bulk-progress-wrap { margin-top: 1rem; }
.bulk-progress-bar-bg {
    width: 100%;
    height: 22px;
    background: #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}
.bulk-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed, #a855f7);
    border-radius: 8px;
    transition: width .3s ease;
    width: 0%;
}
.bulk-progress-text {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    font-weight: 700;
    color: #374151;
}
.bulk-results {
    margin-top: .75rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
    max-height: 200px;
    overflow-y: auto;
}
.bulk-result-item {
    font-size: .82rem;
    padding: .25rem .5rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: .35rem;
}
.bulk-result-item.success { background: #dcfce7; color: #166534; }
.bulk-result-item.error { background: #fee2e2; color: #991b1b; }

/* ---- Modal image dropzone ---- */
.modal-dropzone {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #f9fafb;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.modal-dropzone:hover,
.modal-dropzone.drag-over {
    border-color: #7c3aed;
    background: #f5f3ff;
}
.modal-dropzone-text { font-size: .85rem; color: #6b7280; }
.modal-dropzone-text strong { color: #374151; }

/* ---- Inline Editable Cells ---- */
.editable-cell {
    position: relative;
    cursor: pointer;
    min-height: 28px;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all .15s ease;
    display: inline-block;
    min-width: 40px;
}
.editable-cell:hover {
    background: #f0fdf4;
    box-shadow: inset 0 0 0 1.5px #86efac;
}
.editable-cell:hover::after {
    content: ' ✏️';
    font-size: .7rem;
    opacity: 0.5;
}
/* Name & desc cells fill the whole td */
.name-cell,
.desc-cell {
    display: block;
    width: 100%;
}
.name-cell strong {
    display: block;
}
.desc-cell {
    margin-top: 2px;
}
.editable-cell.editing {
    background: #fff;
    box-shadow: inset 0 0 0 2px #22c55e;
    cursor: text;
    padding: 0;
}
.editable-cell.editing:hover::after {
    content: none;
}
.editable-cell .inline-input {
    border: none;
    outline: none;
    background: transparent;
    font: inherit;
    color: inherit;
    width: 100%;
    padding: 2px 6px;
    min-width: 30px;
    box-sizing: border-box;
}
.editable-cell .inline-input.name-input { min-width: 120px; }
.editable-cell .inline-input.price-input { min-width: 60px; }
.editable-cell .inline-input.stock-input { min-width: 50px; }
.editable-cell .inline-input.desc-input {
    min-width: 150px;
    font-size: .78rem;
    color: #64748b;
}

/* Editable stock with null (∞) */
.stock-null-toggle {
    color: #94a3b8;
    font-style: italic;
    cursor: pointer;
    font-size: .82rem;
}

/* Toggle buttons — bigger air */
.toggle-item-btn,
.toggle-cat-btn {
    min-width: 44px;
    font-weight: 700 !important;
    letter-spacing: .03em;
}

/* Spinner for saving */
.editable-cell.saving {
    opacity: 0.6;
    pointer-events: none;
}
.editable-cell.saving::after {
    content: ' ⏳';
    font-size: .7rem;
    opacity: 1;
}
.editable-cell.saved-flash {
    animation: savedFlash .6s ease;
}
@keyframes savedFlash {
    0%   { background: #bbf7d0; box-shadow: inset 0 0 0 2px #22c55e; }
    100% { background: transparent; box-shadow: none; }
}
.editable-cell.error-flash {
    animation: errorFlash .6s ease;
}
@keyframes errorFlash {
    0%   { background: #fecaca; box-shadow: inset 0 0 0 2px #ef4444; }
    100% { background: transparent; box-shadow: none; }
}

/* ---- Live Image Thumbnails ---- */
.item-thumb-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}
.item-thumb {
    width: 52px;
    height: 52px;
    border-radius: 6px;
    object-fit: cover;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all .15s ease;
    display: block;
}
.item-thumb:hover {
    border-color: #7c3aed;
    box-shadow: 0 0 0 2px rgba(124,58,237,.2);
    transform: scale(1.05);
}
.item-thumb-placeholder {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px 8px;
    border: 1.5px dashed #d1d5db;
    border-radius: 6px;
    transition: all .15s ease;
    margin-top: 2px;
}
.item-thumb-placeholder:hover {
    border-color: #7c3aed;
    color: #7c3aed;
    background: #f5f3ff;
}

/* ---- Toast ---- */
.inline-toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    padding: .6rem 1.2rem;
    border-radius: 8px;
    font-size: .85rem;
    font-weight: 600;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    opacity: 0;
    transform: translateY(10px);
    transition: all .25s ease;
    pointer-events: none;
}
.inline-toast.show {
    opacity: 1;
    transform: translateY(0);
}
.inline-toast.success { background: #166534; color: #fff; }
.inline-toast.error { background: #991b1b; color: #fff; }
</style>

<!-- Restaurant Selector -->
<div class="menu-toolbar" style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
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
    <div style="margin-left:auto;align-self:end;padding-bottom:.4rem">
        <button class="btn btn-outline btn-sm" id="bulk-upload-btn">📸 Bulk Upload Images</button>
    </div>
</div>

<!-- Bulk Upload Area (hidden by default) -->
<div class="bulk-upload-area" id="bulk-upload-area" style="display:none">
    <div class="sa-card">
        <div class="sa-card-header">
            <h2>📸 Bulk Upload Images</h2>
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('bulk-upload-area').style.display='none'">✕ Close</button>
        </div>
        <div class="sa-card-body">
            <div style="display:flex;gap:1rem;align-items:center;margin-bottom:.85rem;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <label style="font-size:.78rem;font-weight:600;display:block;margin-bottom:.25rem;color:#374151">📁 Default Category for New Items</label>
                    <select id="bulk-default-category" class="form-control" style="width:100%;font-size:.85rem">
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="font-size:.8rem;color:#6b7280;align-self:flex-end;padding-bottom:.3rem">
                    Items will be created with price $0.00 — edit later.
                </div>
            </div>
            <div class="bulk-dropzone" id="bulk-dropzone">
                <div class="bulk-dropzone-icon">📁</div>
                <div class="bulk-dropzone-text">Drop images here or click to select</div>
                <div class="bulk-dropzone-hint">JPEG, PNG, GIF, WebP — multiple files supported, max 5MB each</div>
            </div>
            <input type="file" id="bulk-file-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none">

            <div class="bulk-progress-wrap" id="bulk-progress-wrap" style="display:none">
                <div class="bulk-progress-bar-bg">
                    <div class="bulk-progress-fill" id="bulk-progress-fill"></div>
                    <div class="bulk-progress-text" id="bulk-progress-text">0 / 0</div>
                </div>
                <div class="bulk-results" id="bulk-results"></div>
            </div>
        </div>
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
        <thead><tr>
            <th>Name / Description</th>
            <th>Category</th>
            <th style="width:90px">Price</th>
            <th style="width:70px">Stock</th>
            <th style="width:58px">Avail</th>
            <th style="width:70px">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
        <tr id="item-<?= htmlspecialchars($item['id']) ?>"
            data-id="<?= htmlspecialchars($item['id']) ?>"
            data-category="<?= htmlspecialchars($item['categoryId']) ?>"
            data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>">
            <td>
                <div class="editable-cell name-cell" data-field="name" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['name']) ?>">
                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                </div>
                <?php if ($item['description']): ?>
                <br>
                <div class="editable-cell desc-cell" data-field="description" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['description']) ?>" style="font-size:.78rem;color:#64748b">
                    <?= htmlspecialchars(mb_strimwidth($item['description'], 0, 55, '...')) ?>
                </div>
                <?php endif; ?>
                <div class="item-thumb-wrap" data-item-id="<?= htmlspecialchars($item['id']) ?>">
                    <?php if ($item['image']): ?>
                    <img class="item-thumb" src="<?= htmlspecialchars($item['image']) ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>"
                         title="Click to change image"
                         onclick="inlineImageUpload(this)">
                    <?php else: ?>
                    <span class="item-thumb-placeholder" onclick="inlineImageUpload(this)">📷 Add image</span>
                    <?php endif; ?>
                </div>
                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="display:none" class="inline-image-input"
                       data-item-id="<?= htmlspecialchars($item['id']) ?>">
            </td>
            <td><?= htmlspecialchars($item['categoryName']) ?></td>
            <td>
                <div class="editable-cell price-cell" data-field="price" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['price']) ?>">
                    $<?= number_format((float)$item['price'], 2) ?>
                </div>
            </td>
            <td class="<?= $item['stockQuantity'] !== null && $item['stockQuantity'] <= $item['lowStockThreshold'] ? 'text-warn' : '' ?>">
                <div class="editable-cell stock-cell" data-field="stockQuantity" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['stockQuantity'] ?? '') ?>">
                    <?= $item['stockQuantity'] !== null ? (int)$item['stockQuantity'] : '∞' ?>
                </div>
            </td>
            <td>
                <button class="btn btn-sm btn-outline toggle-item-btn" data-id="<?= htmlspecialchars($item['id']) ?>" data-available="<?= $item['isAvailable'] ?>">
                    <?= $item['isAvailable'] ? 'On' : 'Off' ?>
                </button>
            </td>
            <td>
                <button class="btn btn-sm btn-outline edit-item-btn" data-item='<?= htmlspecialchars(json_encode($item)) ?>'>✎</button>
                <button class="btn btn-sm btn-danger delete-item-btn" data-id="<?= htmlspecialchars($item['id']) ?>">✕</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8">No items. Add some!</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

</div>

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
                    <div style="display:flex;gap:.75rem;align-items:start;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px">
                            <div id="image-preview-wrap" style="display:none;margin-bottom:.5rem">
                                <img id="image-preview" src="" alt="Preview" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover">
                                <button type="button" class="btn btn-sm btn-danger" onclick="clearImagePreview()" style="margin-top:.3rem">✕ Remove</button>
                            </div>
                            <div class="modal-dropzone" id="modal-dropzone">
                                <div class="modal-dropzone-text">
                                    <strong>Click or drop</strong> an image here<br>
                                    <span style="font-size:.75rem">JPG, PNG, GIF, WebP • Max 5MB</span>
                                </div>
                            </div>
                            <input type="file" id="item-image-file" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
                        </div>
                    </div>
                    <div style="margin-top:.5rem">
                        <input type="url" name="image" id="item-image" class="form-control" placeholder="Or paste image URL (https://...)" style="font-size:.85rem">
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

<!-- Inline Toast -->
<div class="inline-toast" id="inline-toast"></div>

<script>
const RESTAURANT_SLUG = <?= json_encode($slug ?? '') ?>;

/* ===== Inline Editing ===== */
let currentEdit = null;

document.querySelectorAll('.editable-cell').forEach(cell => {
    cell.addEventListener('click', function(e) {
        // Don't start edit if clicking inside an already-editing input
        if (this.classList.contains('editing')) return;
        startInlineEdit(this);
    });
});

function startInlineEdit(cell) {
    // Close any existing edit
    if (currentEdit) cancelInlineEdit(currentEdit);

    const field = cell.dataset.field;
    const id = cell.dataset.id;
    const currentVal = cell.dataset.value;
    cell.classList.add('editing');

    let input;
    if (field === 'stockQuantity') {
        // Stock: allow null (∞) via special handling
        input = document.createElement('input');
        input.type = 'number';
        input.className = 'inline-input stock-input';
        input.min = '0';
        input.step = '1';
        input.placeholder = '∞ (unlimited)';
        if (currentVal !== '') input.value = currentVal;
    } else if (field === 'price') {
        input = document.createElement('input');
        input.type = 'number';
        input.className = 'inline-input price-input';
        input.step = '0.01';
        input.min = '0';
        input.value = currentVal;
    } else if (field === 'description') {
        input = document.createElement('input');
        input.type = 'text';
        input.className = 'inline-input desc-input';
        input.value = currentVal;
    } else {
        // name
        input = document.createElement('input');
        input.type = 'text';
        input.className = 'inline-input name-input';
        input.value = currentVal;
    }

    // Store original display text
    cell.dataset.displayText = cell.innerText;
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    currentEdit = cell;

    // Save handlers
    input.addEventListener('blur', () => saveInlineEdit(cell));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { e.preventDefault(); cancelInlineEdit(cell); }
    });

    // Special: if stock, allow clearing to null by deleting value
    if (field === 'stockQuantity') {
        input.addEventListener('input', () => {
            if (input.value === '') {
                input.placeholder = '∞ (unlimited)';
            }
        });
    }
}

function cancelInlineEdit(cell) {
    cell.classList.remove('editing');
    const displayText = cell.dataset.displayText || cell.innerText;
    cell.innerHTML = displayText;
    if (currentEdit === cell) currentEdit = null;
}

async function saveInlineEdit(cell) {
    const input = cell.querySelector('.inline-input');
    if (!input) { cell.classList.remove('editing'); return; }

    const field = cell.dataset.field;
    const id = cell.dataset.id;

    let newValue;
    if (field === 'stockQuantity') {
        newValue = input.value !== '' ? input.value : null;
    } else if (field === 'price') {
        newValue = input.value || '0';
    } else {
        newValue = input.value.trim();
    }

    const oldValue = cell.dataset.value;
    const strNew = newValue !== null ? String(newValue) : '';
    const strOld = String(oldValue);

    // No change? Cancel edit
    if (strNew === strOld && field !== 'stockQuantity') {
        cancelInlineEdit(cell);
        return;
    }
    if (field === 'stockQuantity' && strNew === strOld) {
        cancelInlineEdit(cell);
        return;
    }

    cell.classList.add('saving');

    try {
        const body = {};
        body[field] = newValue;

        const res = await fetch(`/api/menu/items.php?id=${id}&restaurant=${RESTAURANT_SLUG}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();

        if (data.success) {
            // Update display
            cell.dataset.value = newValue !== null ? String(newValue) : '';
            cell.classList.remove('editing', 'saving');

            let display;
            if (field === 'price') {
                display = '$' + parseFloat(newValue || 0).toFixed(2);
            } else if (field === 'stockQuantity') {
                display = newValue !== null ? String(parseInt(newValue)) : '∞';
            } else {
                display = newValue;
            }
            cell.innerHTML = display;

            cell.classList.remove('saved-flash');
            void cell.offsetWidth;
            cell.classList.add('saved-flash');

            showToast('Saved ✓', 'success');
        } else {
            throw new Error(data.error || 'Save failed');
        }
    } catch (err) {
        cell.classList.remove('saving');
        cell.classList.remove('error-flash');
        void cell.offsetWidth;
        cell.classList.add('error-flash');
        cancelInlineEdit(cell);
        showToast('✕ ' + (err.message || 'Failed to save'), 'error');
    }

    if (currentEdit === cell) currentEdit = null;
}

function showToast(msg, type) {
    const toast = document.getElementById('inline-toast');
    toast.textContent = msg;
    toast.className = 'inline-toast ' + type + ' show';
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => toast.classList.remove('show'), 2000);
}

/* ===== Inline Image Upload on Thumbnails ===== */
function inlineImageUpload(el) {
    // Find the hidden file input for this item
    const itemId = el.closest('.item-thumb-wrap')?.dataset?.itemId;
    if (!itemId) return;
    const input = document.querySelector(`.inline-image-input[data-item-id="${itemId}"]`);
    if (!input) return;
    input.click();
}

document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('inline-image-input')) return;
    const input = e.target;
    const file = input.files?.[0];
    if (!file) return;

    const itemId = input.dataset.itemId;
    const wrap = document.querySelector(`.item-thumb-wrap[data-item-id="${itemId}"]`);
    if (!wrap) return;

    // Show uploading state
    wrap.innerHTML = '<span style="font-size:.75rem;color:#7c3aed">⏳ uploading...</span>';

    const formData = new FormData();
    formData.append('image', file);

    fetch('/api/menu/upload.php', { method: 'POST', credentials: 'include', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Upload failed');
            const imageUrl = data.data?.url || data.data?.path || '';

            // Update the item via PUT
            return fetch(`/api/menu/items.php?id=${itemId}&restaurant=${RESTAURANT_SLUG}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: imageUrl })
            }).then(r => r.json()).then(updateData => {
                if (!updateData.success) throw new Error(updateData.error || 'Update failed');

                // Replace with live thumbnail
                wrap.innerHTML = `<img class="item-thumb" src="${imageUrl}" alt="" title="Click to change image" onclick="inlineImageUpload(this)">`;
                showToast('📸 Image saved', 'success');
            });
        })
        .catch(err => {
            showToast('✕ ' + err.message, 'error');
            // Restore original state — reload to be safe
            location.reload();
        });
}, false);

/* ===== Bulk Upload ===== */
const bulkDropzone = document.getElementById('bulk-dropzone');
const bulkFileInput = document.getElementById('bulk-file-input');
const bulkProgressWrap = document.getElementById('bulk-progress-wrap');
const bulkProgressFill = document.getElementById('bulk-progress-fill');
const bulkProgressText = document.getElementById('bulk-progress-text');
const bulkResults = document.getElementById('bulk-results');

document.getElementById('bulk-upload-btn')?.addEventListener('click', () => {
    const area = document.getElementById('bulk-upload-area');
    area.style.display = area.style.display === 'none' ? '' : 'none';
});

bulkDropzone?.addEventListener('click', () => bulkFileInput.click());

bulkDropzone?.addEventListener('dragover', (e) => { e.preventDefault(); bulkDropzone.classList.add('drag-over'); });
bulkDropzone?.addEventListener('dragleave', () => bulkDropzone.classList.remove('drag-over'));
bulkDropzone?.addEventListener('drop', (e) => {
    e.preventDefault();
    bulkDropzone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        bulkFileInput.files = e.dataTransfer.files;
        handleBulkUpload(e.dataTransfer.files);
    }
});

bulkFileInput?.addEventListener('change', function() {
    if (this.files.length) handleBulkUpload(this.files);
});

async function handleBulkUpload(files) {
    const total = files.length;
    let success = 0, failed = 0;
    bulkProgressWrap.style.display = '';
    bulkResults.innerHTML = '';
    updateBulkProgress(0, total);

    const catSelect = document.getElementById('bulk-default-category');
    const defaultCategoryId = catSelect ? catSelect.value : '';

    if (!defaultCategoryId) {
        const msg = document.createElement('div');
        msg.className = 'bulk-result-item error';
        msg.textContent = '❌ Please select a default category first.';
        bulkResults.appendChild(msg);
        return;
    }

    for (let i = 0; i < total; i++) {
        const file = files[i];
        const item = document.createElement('div');
        item.className = 'bulk-result-item';
        item.textContent = `📤 Uploading ${file.name}...`;
        bulkResults.appendChild(item);
        bulkResults.scrollTop = bulkResults.scrollHeight;

        try {
            item.textContent = `📤 Uploading image for ${file.name}...`;
            const fd = new FormData();
            fd.append('image', file);
            const uploadRes = await fetch('/api/menu/upload.php', { method: 'POST', credentials: 'include', body: fd });
            const uploadData = await uploadRes.json();

            if (!uploadData.success) {
                failed++;
                item.className = 'bulk-result-item error';
                item.textContent = `❌ ${file.name}: Upload failed — ${uploadData.error || 'Unknown error'}`;
                updateBulkProgress(i + 1, total);
                continue;
            }

            item.textContent = `📝 Creating menu item for ${file.name}...`;

            let itemName = file.name.replace(/\.[^.]+$/, '');
            itemName = itemName.replace(/[-_]+/g, ' ');
            itemName = itemName.replace(/\b\w/g, c => c.toUpperCase());
            itemName = itemName.trim() || 'Untitled';

            const imageUrl = uploadData.data?.url || uploadData.data?.path || '';
            const body = {
                name: itemName,
                categoryId: defaultCategoryId,
                price: 0,
                stockQuantity: null,
                lowStockThreshold: 5,
                preparationTime: 15,
                description: null,
                image: imageUrl
            };

            const createRes = await fetch(`/api/menu/items.php?restaurant=${RESTAURANT_SLUG}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const createData = await createRes.json();

            if (createData.success) {
                success++;
                item.className = 'bulk-result-item success';
                item.textContent = `✅ ${itemName} — created (image: ${file.name})`;
            } else {
                failed++;
                item.className = 'bulk-result-item error';
                item.textContent = `❌ ${file.name}: Create failed — ${createData.error || 'Unknown error'}`;
            }
        } catch (err) {
            failed++;
            item.className = 'bulk-result-item error';
            item.textContent = `❌ ${file.name}: Network error — ${err.message}`;
        }
        updateBulkProgress(i + 1, total);
    }

    const summary = document.createElement('div');
    summary.style.cssText = 'font-weight:700;font-size:.95rem;padding:.5rem 0;border-top:2px solid #e5e7eb;margin-top:.5rem';
    if (failed === 0) {
        summary.style.color = '#166534';
        summary.textContent = `✅ All done! ${success} item${success !== 1 ? 's' : ''} created successfully.`;
    } else {
        summary.style.color = failed > 0 ? '#991b1b' : '#166534';
        summary.textContent = `✅ ${success} created, ❌ ${failed} failed`;
    }
    bulkResults.appendChild(summary);

    setTimeout(() => location.reload(), 2000);
}

function updateBulkProgress(done, total) {
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    bulkProgressFill.style.width = pct + '%';
    bulkProgressText.textContent = `${done} / ${total}`;
}

/* ===== Modal Dropzone ===== */
const modalDropzone = document.getElementById('modal-dropzone');
const itemImageFile = document.getElementById('item-image-file');

modalDropzone?.addEventListener('click', () => itemImageFile.click());
modalDropzone?.addEventListener('dragover', (e) => { e.preventDefault(); modalDropzone.classList.add('drag-over'); });
modalDropzone?.addEventListener('dragleave', () => modalDropzone.classList.remove('drag-over'));
modalDropzone?.addEventListener('drop', (e) => {
    e.preventDefault();
    modalDropzone.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) {
        itemImageFile.files = e.dataTransfer.files;
        itemImageFile.dispatchEvent(new Event('change'));
    }
});

/* ===== Category CRUD ===== */
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

/* ===== Item Modal ===== */
const modal = document.getElementById('item-modal');

document.getElementById('add-item-btn')?.addEventListener('click', () => openModal(null));
document.getElementById('close-modal')?.addEventListener('click', () => modal.style.display = 'none');
document.getElementById('cancel-item-btn')?.addEventListener('click', () => modal.style.display = 'none');
document.getElementById('modal-backdrop')?.addEventListener('click', () => modal.style.display = 'none');

document.querySelectorAll('.edit-item-btn').forEach(btn => {
    btn.addEventListener('click', () => { try { openModal(JSON.parse(btn.dataset.item)); } catch {} });
});

/* ===== Image Upload ===== */
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

/* ===== Save Item ===== */
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

/* ===== Toggle + Delete items ===== */
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

/* ===== Search ===== */
document.getElementById('item-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#items-table tbody tr').forEach(row => {
        row.style.display = !q || (row.dataset.name||'').includes(q) ? '' : 'none';
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
