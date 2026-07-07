<?php
$page_title = 'Stock Management';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

$restaurants = db_query('SELECT id, name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name');

$slug = $_GET['restaurant'] ?? ($restaurants[0]['slug'] ?? null);
$filter = $_GET['filter'] ?? '';

$restaurant = $slug ? get_restaurant($slug) : null;
$rid = $restaurant['id'] ?? null;

$where  = $rid ? ['mi.restaurantId = ?'] : ['1=0'];
$params = $rid ? [$rid] : [];
if ($filter === 'low') { $where[] = 'mi.stockQuantity IS NOT NULL AND mi.stockQuantity > 0 AND mi.stockQuantity <= mi.lowStockThreshold'; }
if ($filter === 'out') { $where[] = 'mi.stockQuantity IS NOT NULL AND mi.stockQuantity = 0'; }

$w = 'WHERE ' . implode(' AND ', $where);
$items = db_query(
    "SELECT mi.id, mi.name, mi.stockQuantity, mi.lowStockThreshold, mi.isAvailable, c.name AS categoryName
     FROM MenuItem mi JOIN Category c ON c.id = mi.categoryId $w ORDER BY c.name ASC, mi.name ASC",
    $params
);
?>
<style>
.stock-toolbar {
    display: flex; flex-wrap: wrap; gap: .75rem;
    align-items: flex-end; margin-bottom: 1.25rem;
}
.stock-toolbar label {
    font-size: .78rem; font-weight: 600;
    display: block; margin-bottom: .25rem; color: #6b7280;
}
.stock-toolbar select, .stock-toolbar input {
    padding: .45rem .7rem; border: 1px solid #d1d5db;
    border-radius: 6px; font-size: .875rem;
}
.stock-toolbar select:focus, .stock-toolbar input:focus {
    outline: none; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124,58,237,.15);
}

.stock-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: .85rem;
}

.stock-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .6rem;
    transition: box-shadow .2s;
}
.stock-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.stock-card.status-out  { border-left: 4px solid #ef4444; }
.stock-card.status-low  { border-left: 4px solid #f59e0b; }
.stock-card.status-ok   { border-left: 4px solid #10b981; }
.stock-card.status-none { border-left: 4px solid #d1d5db; }

.sc-name { font-weight: 700; font-size: .95rem; color: #1e293b; }
.sc-cat  { font-size: .75rem; color: #94a3b8; margin-top: .05rem; }
.sc-qty  { font-size: 2rem; font-weight: 800; color: #1e293b; line-height: 1; }
.sc-qty small { font-size: .7rem; font-weight: 400; color: #94a3b8; display: block; margin-top: .1rem; }

.sc-badge {
    display: inline-block; font-size: .7rem; font-weight: 700;
    padding: .2rem .55rem; border-radius: 9999px;
}
.sc-badge.out  { background: #fee2e2; color: #dc2626; }
.sc-badge.low  { background: #fef3c7; color: #d97706; }
.sc-badge.ok   { background: #d1fae5; color: #065f46; }
.sc-badge.none { background: #f1f5f9; color: #64748b; }

.sc-controls {
    display: flex; gap: .4rem; align-items: center; margin-top: .25rem;
}
.sc-controls input[type=number] {
    width: 72px; padding: .4rem .5rem; border: 1px solid #d1d5db;
    border-radius: 6px; font-size: .9rem; text-align: center; font-weight: 600;
}
.sc-controls input[type=number]:focus {
    outline: none; border-color: #7c3aed;
}

.sc-stepper {
    display: flex; flex-direction: column; gap: 1px;
}
.sc-stepper button {
    width: 24px; height: 19px; background: #f1f5f9;
    border: 1px solid #e2e8f0; cursor: pointer;
    font-size: .65rem; line-height: 1; padding: 0;
    border-radius: 3px; transition: background .15s;
    display: flex; align-items: center; justify-content: center;
}
.sc-stepper button:hover { background: #e2e8f0; }

.btn-delete-all {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .45rem .85rem; background: #fff; color: #ef4444;
    border: 1.5px solid #ef4444; border-radius: 8px;
    font-size: .82rem; font-weight: 600; cursor: pointer;
    transition: all .15s;
}
.btn-delete-all:hover {
    background: #fef2f2; border-color: #dc2626;
}

/* ===== Inline Editable ===== */
.sc-name.editable-cell {
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    transition: all .15s ease;
    display: inline-block;
}
.sc-name.editable-cell:hover {
    background: #f0fdf4;
    box-shadow: inset 0 0 0 1.5px #86efac;
}
.sc-name.editable-cell:hover::after {
    content: ' ✏️';
    font-size: .65rem;
    opacity: 0.5;
}
.sc-name.editable-cell.editing {
    background: #fff;
    box-shadow: inset 0 0 0 2px #22c55e;
    cursor: text;
    padding: 0;
}
.sc-name.editable-cell.editing:hover::after {
    content: none;
}

/* Stock qty editable */
.sc-qty.editable-cell {
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 6px;
    transition: all .15s ease;
    display: inline-block;
}
.sc-qty.editable-cell:hover {
    background: #f0fdf4;
    box-shadow: inset 0 0 0 1.5px #86efac;
}
.sc-qty.editable-cell:hover::after {
    content: ' ✏️';
    font-size: .7rem;
    opacity: 0.5;
}
.sc-qty.editable-cell.editing {
    padding: 0;
    background: #fff;
    box-shadow: inset 0 0 0 2px #22c55e;
    cursor: text;
}
.sc-qty.editable-cell.editing:hover::after {
    content: none;
}
.sc-qty.editable-cell .inline-input {
    border: none; outline: none; background: transparent;
    font: inherit; color: inherit; width: 100%;
    padding: 2px 6px; min-width: 50px; box-sizing: border-box;
    font-size: 1.8rem; font-weight: 800;
}
.sc-qty.editable-cell.saving { opacity: 0.6; pointer-events: none; }
.sc-qty.editable-cell.saving::after { content: ' ⏳'; font-size: .7rem; opacity: 1; }
.sc-qty.editable-cell.saved-flash {
    animation: stockSavedFlash .6s ease;
}
@keyframes stockSavedFlash {
    0%   { background: #bbf7d0; box-shadow: inset 0 0 0 2px #22c55e; }
    100% { background: transparent; box-shadow: none; }
}
.sc-qty.editable-cell.error-flash {
    animation: stockErrorFlash .6s ease;
}
@keyframes stockErrorFlash {
    0%   { background: #fecaca; box-shadow: inset 0 0 0 2px #ef4444; }
    100% { background: transparent; box-shadow: none; }
}

/* Name edit input */
.sc-name.editable-cell .inline-input {
    border: none; outline: none; background: transparent;
    font: inherit; color: inherit; width: 100%;
    padding: 2px 6px; box-sizing: border-box;
    font-weight: 700;
}

/* Toast */
.stock-toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    padding: .6rem 1.2rem; border-radius: 8px;
    font-size: .85rem; font-weight: 600;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    opacity: 0; transform: translateY(10px);
    transition: all .25s ease;
    pointer-events: none;
}
.stock-toast.show { opacity: 1; transform: translateY(0); }
.stock-toast.success { background: #166534; color: #fff; }
.stock-toast.error { background: #991b1b; color: #fff; }
</style>

<!-- Toolbar -->
<div class="stock-toolbar">
    <div>
        <label>Restaurant</label>
        <select onchange="location.href='stock.php?restaurant='+this.value+'&filter=<?= urlencode($filter) ?>'">
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>" <?= $slug === $r['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Show</label>
        <div style="display:flex;gap:.4rem">
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter="
               class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter=low"
               class="btn btn-sm <?= $filter === 'low' ? 'btn-primary' : 'btn-outline' ?>">⚠ Low</a>
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter=out"
               class="btn btn-sm <?= $filter === 'out' ? 'btn-primary' : 'btn-outline' ?>">✕ Out</a>
        </div>
    </div>
    <div style="margin-left:auto;display:flex;gap:.75rem;align-items:center">
        <span style="font-size:.85rem;color:#6b7280">
            <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
            &mdash; <?= htmlspecialchars($restaurant['name'] ?? '') ?>
        </span>
        <?php if (!empty($items)): ?>
        <button class="btn-delete-all" onclick="resetAllStock()">🗑 Reset All Stock</button>
        <?php endif; ?>
    </div>
</div>

<!-- Stock cards grid -->
<?php if (empty($items)): ?>
<div style="text-align:center;padding:3rem;color:#94a3b8;font-size:1rem">No items found.</div>
<?php else: ?>
<div class="stock-grid">
<?php foreach ($items as $item):
    $has_stock = $item['stockQuantity'] !== null;
    $qty = (int)($item['stockQuantity'] ?? 0);
    $threshold = (int)($item['lowStockThreshold'] ?? 5);
    $status = !$has_stock ? 'none' : ($qty === 0 ? 'out' : ($qty <= $threshold ? 'low' : 'ok'));
    $badge_labels = ['none'=>'Unlimited','out'=>'Out of Stock','low'=>'Low Stock','ok'=>'OK'];
?>
<div class="stock-card status-<?= $status ?>" id="sc-<?= $item['id'] ?>">
    <div>
        <div class="sc-name editable-cell" data-field="name" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['name']) ?>">
            <?= htmlspecialchars($item['name']) ?>
        </div>
        <div class="sc-cat"><?= htmlspecialchars($item['categoryName']) ?></div>
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between">
        <div>
            <div class="sc-qty editable-cell" data-field="stockQuantity" data-id="<?= htmlspecialchars($item['id']) ?>" data-value="<?= htmlspecialchars($item['stockQuantity'] ?? '') ?>">
                <?= $has_stock ? $qty : '∞' ?>
                <small>current stock</small>
            </div>
        </div>
        <span class="sc-badge <?= $status ?>"><?= $badge_labels[$status] ?></span>
    </div>
    <?php if ($has_stock): ?>
    <div class="sc-controls">
        <div class="sc-stepper">
            <button onclick="stepAndSave('<?= $item['id'] ?>',1)" title="Increase">▲</button>
            <button onclick="stepAndSave('<?= $item['id'] ?>',-1)" title="Decrease">▼</button>
        </div>
        <input type="number" id="qty-input-<?= $item['id'] ?>"
               value="<?= $qty ?>" min="0"
               onkeydown="if(event.key==='Enter') inlineSaveStock('<?= $item['id'] ?>')"
               onchange="inlineSaveStock('<?= $item['id'] ?>')">
    </div>
    <div style="font-size:.73rem;color:#94a3b8">Threshold: <?= $threshold ?> &nbsp;|&nbsp; Click number to edit, use steppers, or type</div>
    <div id="last-updated-<?= $item['id'] ?>" style="font-size:.7rem;color:#94a3b8;margin-top:.15rem"></div>
    <?php else: ?>
    <div style="font-size:.8rem;color:#94a3b8">Click ∞ to set a stock limit and start tracking.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Inline Toast -->
<div class="stock-toast" id="stock-toast"></div>

<script>
const STOCK_SLUG = <?= json_encode($slug ?? '') ?>;
let currentEdit = null;

/* ===== Toast ===== */
function stockToast(msg, type) {
    const t = document.getElementById('stock-toast');
    t.textContent = msg;
    t.className = 'stock-toast ' + type + ' show';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2500);
}

/* ===== Inline Edit: Name ===== */
document.querySelectorAll('.sc-name.editable-cell').forEach(cell => {
    cell.addEventListener('click', function(e) {
        if (this.classList.contains('editing')) return;
        if (currentEdit) cancelEdit(currentEdit);
        const val = this.dataset.value;
        this.classList.add('editing');
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'inline-input';
        input.value = val;
        this.dataset.displayText = this.innerText;
        this.innerHTML = '';
        this.appendChild(input);
        input.focus();
        input.select();
        currentEdit = this;
        input.addEventListener('blur', () => saveNameEdit(this.closest('.editable-cell')));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { e.preventDefault(); cancelEdit(this.closest('.editable-cell')); }
        });
    });
});

async function saveNameEdit(cell) {
    const input = cell.querySelector('.inline-input');
    if (!input) { cell.classList.remove('editing'); return; }
    const newVal = input.value.trim();
    if (!newVal || newVal === cell.dataset.value) { cancelEdit(cell); return; }
    cell.classList.add('saving');
    try {
        const res = await fetch(`/api/menu/items.php?id=${cell.dataset.id}&restaurant=${STOCK_SLUG}`, {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({name: newVal})
        });
        const d = await res.json();
        if (!d.success) throw new Error(d.error || 'Failed');
        cell.dataset.value = newVal;
        cell.classList.remove('editing', 'saving');
        cell.innerHTML = newVal;
        stockToast('Renamed ✓', 'success');
    } catch(err) {
        cell.classList.remove('saving');
        stockToast('✕ ' + (err.message || 'Failed'), 'error');
        cancelEdit(cell);
    }
    if (currentEdit === cell) currentEdit = null;
}

/* ===== Inline Edit: Stock Quantity ===== */
document.querySelectorAll('.sc-qty.editable-cell').forEach(cell => {
    cell.addEventListener('click', function(e) {
        if (this.classList.contains('editing')) return;
        // Don't start edit if clicking on the <small> text
        if (e.target.tagName === 'SMALL') return;
        if (currentEdit) cancelEdit(currentEdit);
        const id = this.dataset.id;
        const currentVal = this.dataset.value;
        this.classList.add('editing');
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'inline-input';
        input.min = '0';
        input.step = '1';
        input.placeholder = '∞ (unlimited)';
        if (currentVal !== '') input.value = currentVal;
        this.dataset.displayText = this.innerHTML;
        // Clear but preserve the <small> child
        const small = this.querySelector('small');
        this.innerHTML = '';
        this.appendChild(input);
        if (small) this.appendChild(small);
        // Recalculate height — small breaks layout, hide it during edit
        if (small) small.style.display = 'none';
        input.focus();
        input.select();
        currentEdit = this;
        input.addEventListener('blur', () => saveStockEdit(this));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { e.preventDefault(); cancelEdit(this); }
        });
    });
});

async function saveStockEdit(cell) {
    const input = cell.querySelector('.inline-input');
    if (!input) { cell.classList.remove('editing'); return; }
    let newVal = input.value !== '' ? input.value : null;
    const oldVal = cell.dataset.value;
    const strNew = newVal !== null ? String(newVal) : '';
    if (strNew === String(oldVal)) { cancelEdit(cell); return; }
    cell.classList.add('saving');
    try {
        const qty = newVal !== null ? parseInt(newVal) : null;
        const res = await fetch('/api/admin/stock.php?restaurant=' + STOCK_SLUG, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({menuItemId: cell.dataset.id, quantity: qty, type: 'SET'})
        });
        const d = await res.json();
        if (!d.success) throw new Error(d.error || 'Failed');
        const finalQty = d.data?.newQuantity;
        cell.dataset.value = finalQty !== null && finalQty !== undefined ? String(finalQty) : '';
        cell.classList.remove('editing', 'saving');
        const small = cell.querySelector('small');
        cell.innerHTML = '';
        if (finalQty !== null && finalQty !== undefined) {
            cell.appendChild(document.createTextNode(String(finalQty)));
        } else {
            cell.appendChild(document.createTextNode('∞'));
        }
        if (small) { small.style.display = 'block'; cell.appendChild(small); }
        // Update the input box too
        const qtyInput = document.getElementById('qty-input-' + cell.dataset.id);
        if (qtyInput) qtyInput.value = finalQty !== null && finalQty !== undefined ? finalQty : '';
        // Update card status
        updateCardStatus(cell.dataset.id);
        stockToast('Stock updated ✓', 'success');
    } catch(err) {
        cell.classList.remove('saving');
        stockToast('✕ ' + (err.message || 'Failed'), 'error');
        cancelEdit(cell);
    }
    if (currentEdit === cell) currentEdit = null;
}

function cancelEdit(cell) {
    if (!cell) return;
    cell.classList.remove('editing', 'saving');
    const displayText = cell.dataset.displayText || cell.innerText;
    cell.innerHTML = displayText;
    // Restore small if it was hidden
    const small = cell.querySelector('small');
    if (small) small.style.display = 'block';
    if (currentEdit === cell) currentEdit = null;
}

/* ===== Steppers: auto-save ===== */
async function stepAndSave(id, delta) {
    const input = document.getElementById('qty-input-' + id);
    const current = parseInt(input.value, 10) || 0;
    const newVal = Math.max(0, current + delta);
    input.value = newVal;
    await inlineSaveStock(id);
}

/* ===== Manual input auto-save ===== */
async function inlineSaveStock(id) {
    const input = document.getElementById('qty-input-' + id);
    const qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 0) { stockToast('Enter 0 or more', 'error'); return; }
    input.disabled = true;
    try {
        const res = await fetch('/api/admin/stock.php?restaurant=' + STOCK_SLUG, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({menuItemId: id, quantity: qty, type: 'SET'})
        });
        const d = await res.json();
        if (d.success) {
            const newQty = d.data.newQuantity;
            input.value = newQty;
            // Update the big display
            const display = document.querySelector(`.sc-qty.editable-cell[data-id="${id}"]`);
            if (display) {
                display.dataset.value = String(newQty);
                const small = display.querySelector('small');
                display.innerHTML = '';
                display.appendChild(document.createTextNode(String(newQty)));
                if (small) display.appendChild(small);
            }
            updateCardStatus(id);
            stockToast('Stock updated to ' + newQty, 'success');
        } else {
            stockToast(d.error || 'Failed', 'error');
        }
    } catch(e) {
        stockToast('Network error', 'error');
    }
    input.disabled = false;
}

/* ===== Update card border/badge after stock change ===== */
function updateCardStatus(id) {
    const card = document.getElementById('sc-' + id);
    if (!card) return;
    const display = card.querySelector('.sc-qty.editable-cell');
    const badge = card.querySelector('.sc-badge');
    if (!display || !badge) return;
    const hasStock = display.dataset.value !== '';
    const qty = hasStock ? parseInt(display.dataset.value) : 0;
    const status = !hasStock ? 'none' : (qty === 0 ? 'out' : (qty <= 5 ? 'low' : 'ok'));
    const labels = {none:'Unlimited', out:'Out of Stock', low:'Low Stock', ok:'OK'};
    card.className = card.className.replace(/status-\S+/g, '') + ' stock-card status-' + status;
    badge.className = 'sc-badge ' + status;
    badge.textContent = labels[status];
    // Show/hide controls
    const controls = card.querySelector('.sc-controls');
    const unlimitedMsg = card.querySelector('.stock-card > div:last-child');
    if (controls) controls.style.display = hasStock ? '' : 'none';
    if (unlimitedMsg && !controls) {
        // Hmm, simpler: just let page flow
    }
}

/* ===== Reset all stock ===== */
async function resetAllStock() {
    if (!confirm('⚠ Reset ALL stock to 0 for this restaurant?\nThis cannot be undone.')) return;
    try {
        const res = await fetch('/api/admin/stock.php?restaurant=' + STOCK_SLUG, { method: 'DELETE' });
        const d = await res.json();
        if (d.success) { stockToast('All stock reset to 0', 'success'); setTimeout(() => location.reload(), 1000); }
        else { stockToast(d.error || 'Failed', 'error'); }
    } catch(e) { stockToast('Network error', 'error'); }
}
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
