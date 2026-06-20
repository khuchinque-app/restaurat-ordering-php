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
.stock-toolbar { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:1.25rem; }
.stock-toolbar label { font-size:.78rem; font-weight:600; display:block; margin-bottom:.2rem; color:#6b7280; }
.stock-toolbar select, .stock-toolbar input { padding:.4rem .65rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; }

.stock-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:.85rem; }

.stock-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1rem 1.1rem; display:flex; flex-direction:column; gap:.6rem; }
.stock-card.status-out  { border-left:4px solid #ef4444; }
.stock-card.status-low  { border-left:4px solid #f59e0b; }
.stock-card.status-ok   { border-left:4px solid #10b981; }
.stock-card.status-none { border-left:4px solid #d1d5db; }

.sc-name { font-weight:700; font-size:.95rem; color:#1e293b; }
.sc-cat  { font-size:.75rem; color:#94a3b8; margin-top:.05rem; }
.sc-qty  { font-size:2rem; font-weight:800; color:#1e293b; line-height:1; }
.sc-qty small { font-size:.7rem; font-weight:400; color:#94a3b8; display:block; margin-top:.1rem; }

.sc-badge { display:inline-block; font-size:.7rem; font-weight:700; padding:.15rem .5rem; border-radius:4px; }
.sc-badge.out  { background:#fee2e2; color:#dc2626; }
.sc-badge.low  { background:#fef3c7; color:#d97706; }
.sc-badge.ok   { background:#d1fae5; color:#065f46; }
.sc-badge.none { background:#f1f5f9; color:#64748b; }

.sc-controls { display:flex; gap:.4rem; align-items:center; margin-top:.25rem; }
.sc-controls input[type=number] {
    width:70px; padding:.4rem .5rem; border:1px solid #d1d5db; border-radius:6px;
    font-size:.9rem; text-align:center; font-weight:600;
}
.sc-controls input[type=number]:focus { outline:none; border-color:#7c3aed; }
.sc-btn-save { flex:1; padding:.4rem .6rem; background:#7c3aed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.85rem; font-weight:600; }
.sc-btn-save:hover { background:#6d28d9; }
.sc-btn-save:disabled { opacity:.5; cursor:default; }
.sc-stepper { display:flex; flex-direction:column; gap:1px; }
.sc-stepper button { width:22px; height:18px; background:#f1f5f9; border:1px solid #e2e8f0; cursor:pointer; font-size:.7rem; line-height:1; padding:0; border-radius:3px; }
.sc-stepper button:hover { background:#e2e8f0; }
.sc-saved { color:#10b981; font-size:.78rem; font-weight:600; display:none; }
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
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter=" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter=low" class="btn btn-sm <?= $filter === 'low' ? 'btn-primary' : 'btn-outline' ?>">&#9888; Low</a>
            <a href="stock.php?restaurant=<?= urlencode($slug ?? '') ?>&filter=out" class="btn btn-sm <?= $filter === 'out' ? 'btn-primary' : 'btn-outline' ?>">&#10005; Out</a>
        </div>
    </div>
    <div style="margin-left:auto; font-size:.85rem; color:#6b7280; align-self:center">
        <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
        &nbsp;&mdash;&nbsp; <?= htmlspecialchars($restaurant['name'] ?? '') ?>
    </div>
</div>

<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;padding:.6rem 1.1rem;border-radius:8px;font-size:.85rem;font-weight:600;display:none;z-index:9999"></div>

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
<div class="stock-card status-<?= $status ?>">
    <div>
        <div class="sc-name"><?= htmlspecialchars($item['name']) ?></div>
        <div class="sc-cat"><?= htmlspecialchars($item['categoryName']) ?></div>
    </div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between">
        <div>
            <div class="sc-qty" id="qty-display-<?= $item['id'] ?>">
                <?= $has_stock ? $qty : '&infin;' ?>
                <small>current stock</small>
            </div>
        </div>
        <span class="sc-badge <?= $status ?>"><?= $badge_labels[$status] ?></span>
    </div>
    <?php if ($has_stock): ?>
    <div class="sc-controls">
        <div class="sc-stepper">
            <button onclick="stepQty('<?= $item['id'] ?>',1)" title="Increase">&#9650;</button>
            <button onclick="stepQty('<?= $item['id'] ?>',-1)" title="Decrease">&#9660;</button>
        </div>
        <input type="number" id="qty-input-<?= $item['id'] ?>"
               value="<?= $qty ?>" min="0"
               onkeydown="if(event.key==='Enter') saveStock('<?= $item['id'] ?>')">
        <button class="sc-btn-save" id="save-<?= $item['id'] ?>" onclick="saveStock('<?= $item['id'] ?>')">Save</button>
        <span class="sc-saved" id="saved-<?= $item['id'] ?>">&#10003; Saved</span>
    </div>
    <div style="font-size:.73rem;color:#94a3b8">Threshold: <?= $threshold ?> &nbsp;|&nbsp; Type a qty &amp; press Save or Enter</div>
    <?php else: ?>
    <div style="font-size:.8rem;color:#94a3b8">This item has unlimited stock (no tracking).</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const STOCK_SLUG = <?= json_encode($slug ?? '') ?>;

function stepQty(id, delta) {
    const input = document.getElementById('qty-input-' + id);
    input.value = Math.max(0, (parseInt(input.value, 10) || 0) + delta);
}

function showToast(msg, ok) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = ok ? '#10b981' : '#ef4444';
    t.style.color = '#fff';
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 2500);
}

async function saveStock(id) {
    const input = document.getElementById('qty-input-' + id);
    const btn   = document.getElementById('save-' + id);
    const saved = document.getElementById('saved-' + id);
    const qty   = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 0) { showToast('Enter a valid quantity (0 or more)', false); return; }

    btn.disabled = true;
    try {
        const res = await fetch('/api/admin/stock.php?restaurant=' + STOCK_SLUG, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({menuItemId: id, quantity: qty, type: 'SET'})
        });
        const data = await res.json();
        if (data.success) {
            const newQty = data.data.newQuantity;
            document.getElementById('qty-display-' + id).innerHTML = newQty + '<small>current stock</small>';
            input.value = newQty;
            saved.style.display = 'inline';
            setTimeout(() => saved.style.display = 'none', 2000);
            showToast('Stock updated to ' + newQty, true);
        } else {
            showToast(data.error || 'Failed', false);
        }
    } catch(e) {
        showToast('Network error', false);
    }
    btn.disabled = false;
}
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
