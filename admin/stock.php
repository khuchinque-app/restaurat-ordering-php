<?php
$page_title = 'Stock Overview';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';

$restaurant = !empty($current_user['restaurantId'])
    ? db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']])
    : null;
$rid = $restaurant['id'] ?? null;
$slug = $restaurant['slug'] ?? '';

$filter = $_GET['filter'] ?? '';
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
</style>

<div class="stock-toolbar">
    <div>
        <label>Show</label>
        <div style="display:flex;gap:.4rem">
            <a href="stock.php?filter=" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="stock.php?filter=low" class="btn btn-sm <?= $filter === 'low' ? 'btn-primary' : 'btn-outline' ?>">&#9888; Low</a>
            <a href="stock.php?filter=out" class="btn btn-sm <?= $filter === 'out' ? 'btn-primary' : 'btn-outline' ?>">&#10005; Out</a>
        </div>
    </div>
    <div style="margin-left:auto; font-size:.85rem; color:#6b7280; align-self:center">
        <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
        &nbsp;&mdash;&nbsp; <?= htmlspecialchars($restaurant['name'] ?? '') ?>
        <span style="color:#94a3b8;font-size:.75rem;margin-left:.5rem">(read-only view)</span>
    </div>
</div>

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
            <div class="sc-qty">
                <?= $has_stock ? $qty : '&infin;' ?>
                <small>current stock</small>
            </div>
        </div>
        <span class="sc-badge <?= $status ?>"><?= $badge_labels[$status] ?></span>
    </div>
    <?php if ($has_stock): ?>
    <div style="font-size:.73rem;color:#94a3b8">Threshold: <?= $threshold ?></div>
    <?php else: ?>
    <div style="font-size:.8rem;color:#94a3b8">Unlimited (no tracking)</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
