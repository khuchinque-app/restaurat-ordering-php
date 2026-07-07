<?php
$page_title = 'Live Orders';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

$restaurants = db_query('SELECT id, name, slug FROM Restaurant WHERE isActive = 1 ORDER BY name');
$slug = $_GET['restaurant'] ?? ($restaurants[0]['slug'] ?? null);
$filter = $_GET['status'] ?? '';

$restaurant = $slug ? get_restaurant($slug) : null;
$rid = $restaurant['id'] ?? null;

$where = $rid ? ['o.restaurantId = ?'] : ['1=0'];
$params = $rid ? [$rid] : [];

if ($filter && in_array($filter, ['PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'])) {
    $where[] = 'o.status = ?';
    $params[] = $filter;
}

$w = 'WHERE ' . implode(' AND ', $where);
$orders = db_query(
    "SELECT o.*, r.name AS restaurantName
     FROM \"Order\" o
     JOIN Restaurant r ON r.id = o.restaurantId
     $w ORDER BY o.createdAt DESC LIMIT 100",
    $params
);

$order_items = [];
foreach ($orders as $ord) {
    $order_items[$ord['id']] = db_query(
        'SELECT oi.quantity, oi.unitPrice, oi.totalPrice, oi.notes, mi.name AS itemName
         FROM OrderItem oi JOIN MenuItem mi ON mi.id = oi.menuItemId WHERE oi.orderId = ?',
        [$ord['id']]
    );
}

// Payment info
$order_payments = [];
foreach ($orders as $ord) {
    $order_payments[$ord['id']] = db_fetch(
        'SELECT * FROM Payment WHERE orderId = ? ORDER BY createdAt DESC LIMIT 1',
        [$ord['id']]
    );
}

$pending_count = 0;
$preparing_count = 0;
$ready_count = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'PENDING') $pending_count++;
    if ($o['status'] === 'PREPARING') $preparing_count++;
    if ($o['status'] === 'READY') $ready_count++;
}
?>

<style>
/* ---- Toolbar ---- */
.checkout-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: flex-end;
    margin-bottom: 1.25rem;
}
.checkout-toolbar label {
    font-size: .78rem;
    font-weight: 600;
    display: block;
    margin-bottom: .2rem;
    color: #6b7280;
}
.checkout-toolbar select,
.checkout-toolbar input {
    padding: .4rem .65rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: .875rem;
}
.checkout-toolbar-right {
    margin-left: auto;
    display: flex;
    gap: .6rem;
    align-self: center;
}

/* ---- Order Stream Grid ---- */
.order-stream {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1rem;
}

/* ---- Stream Card ---- */
.stream-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 1.1rem;
    position: relative;
    transition: box-shadow .2s;
    animation: fadeIn .3s ease;
}
.stream-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.2); }
.stream-card.pending   { border-left: 4px solid #f59e0b; }
.stream-card.confirmed { border-left: 4px solid #3b82f6; }
.stream-card.preparing { border-left: 4px solid #8b5cf6; }
.stream-card.ready     { border-left: 4px solid #4CAF50; }
.stream-card.completed { border-left: 4px solid #6b7280; }
.stream-card.cancelled { border-left: 4px solid #ef4444; }

/* ---- Card Inner Sections ---- */
.sc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: .7rem;
}
.sc-order-num { font-weight: 700; font-size: 1rem; color: var(--color-text-primary); }
.sc-time { font-size: .75rem; color: var(--color-text-muted); }
.sc-customer { font-size: .875rem; color: var(--color-text-secondary); margin-bottom: .6rem; }
.sc-items {
    display: flex;
    flex-direction: column;
    gap: .25rem;
    margin-bottom: .7rem;
}
.sc-item {
    display: flex;
    justify-content: space-between;
    font-size: .85rem;
    padding: .2rem 0;
    border-bottom: 1px dashed var(--color-border);
}
.sc-notes {
    font-size: .78rem;
    color: var(--color-text-muted);
    font-style: italic;
    margin-bottom: .5rem;
    padding: .4rem .6rem;
    background: var(--color-surface-raised);
    border-radius: 6px;
}
.sc-payment {
    font-size: .75rem;
    color: var(--color-text-secondary);
    margin-bottom: .4rem;
}
.sc-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: .5rem;
    border-top: 1px solid var(--color-border);
}
.sc-total { font-weight: 700; font-size: 1.05rem; color: var(--color-text-primary); }
.sc-actions { display: flex; gap: .4rem; flex-wrap: wrap; }

/* ---- Stat Pills ---- */
.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .85rem;
    border-radius: 9999px;
    font-size: .85rem;
    font-weight: 600;
}
.stat-pill.pending   { background: #fef3c7; color: #92400e; }
.stat-pill.preparing { background: #ede9fe; color: #5b21b6; }
.stat-pill.ready     { background: #dcfce7; color: #166534; }

/* ---- Delete All Button ---- */
.btn-delete-orders {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .45rem 1rem;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    color: #dc2626;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
}
.btn-delete-orders:hover {
    background: #fee2e2;
    border-color: #ef4444;
    color: #b91c1c;
}

/* ---- Empty State ---- */
.empty-state {
    text-align: center;
    padding: 4rem;
    color: #94a3b8;
    font-size: 1rem;
}
.empty-state-icon { font-size: 3rem; margin-bottom: 1rem; }

/* ---- Animation ---- */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Toolbar -->
<div class="checkout-toolbar">
    <div>
        <label>Restaurant</label>
        <select onchange="location.href='checkout.php?restaurant='+this.value+'&filter=<?= urlencode($filter) ?>'">
            <?php foreach ($restaurants as $r): ?>
            <option value="<?= htmlspecialchars($r['slug']) ?>" <?= $slug === $r['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Status</label>
        <div style="display:flex;gap:.4rem">
            <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
            <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>&status=PENDING" class="btn btn-sm <?= $filter === 'PENDING' ? 'btn-primary' : 'btn-outline' ?>">⏳ Pending</a>
            <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>&status=CONFIRMED" class="btn btn-sm <?= $filter === 'CONFIRMED' ? 'btn-primary' : 'btn-outline' ?>">✓ Confirmed</a>
            <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>&status=PREPARING" class="btn btn-sm <?= $filter === 'PREPARING' ? 'btn-primary' : 'btn-outline' ?>">🔥 Preparing</a>
            <a href="checkout.php?restaurant=<?= urlencode($slug ?? '') ?>&status=READY" class="btn btn-sm <?= $filter === 'READY' ? 'btn-primary' : 'btn-outline' ?>">✅ Ready</a>
        </div>
    </div>
    <div class="checkout-toolbar-right">
        <?php if (!empty($orders)): ?>
        <button class="btn-delete-orders" onclick="confirmDeleteAllOrders()">🗑 Delete All Orders</button>
        <?php endif; ?>
        <span class="stat-pill pending">⏳ <?= $pending_count ?> Pending</span>
        <span class="stat-pill preparing">🔥 <?= $preparing_count ?> Preparing</span>
        <span class="stat-pill ready">✅ <?= $ready_count ?> Ready</span>
    </div>
</div>

<!-- Order Stream -->
<?php if (empty($orders)): ?>
<div class="empty-state">
    <div class="empty-state-icon">📦</div>
    No orders found for this restaurant.
</div>
<?php else: ?>
<div class="order-stream" id="order-stream">
<?php foreach ($orders as $ord):
    $status_class = strtolower($ord['status']);
    $payment = $order_payments[$ord['id']] ?? null;
    $transitions = [
        'PENDING'          => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED'        => ['PREPARING', 'CANCELLED'],
        'PREPARING'        => ['READY', 'CANCELLED'],
        'READY'            => ['COMPLETED'],
        'OUT_FOR_DELIVERY' => ['COMPLETED'],
    ];
    $next_options = $transitions[$ord['status']] ?? [];
?>
<div class="stream-card <?= $status_class ?>" id="order-<?= htmlspecialchars($ord['id']) ?>">
    <div class="sc-header">
        <div>
            <div class="sc-order-num">#<?= htmlspecialchars($ord['orderNumber']) ?></div>
            <div class="sc-time"><?= date('M d, H:i', strtotime($ord['createdAt'])) ?></div>
        </div>
        <span class="status-badge status-<?= $status_class ?>"><?= htmlspecialchars($ord['status']) ?></span>
    </div>
    <div class="sc-customer">
        👤 <?= htmlspecialchars($ord['customerName'] ?? 'Guest') ?>
        <?php if (!empty($ord['customerPhone'])): ?>
            &bull; 📞 <?= htmlspecialchars($ord['customerPhone']) ?>
        <?php endif; ?>
    </div>
    <div class="sc-items">
        <?php foreach ($order_items[$ord['id']] as $oi): ?>
        <div class="sc-item">
            <span><?= (int)$oi['quantity'] ?>× <?= htmlspecialchars($oi['itemName']) ?></span>
            <span>$<?= number_format((float)$oi['totalPrice'], 2) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($ord['notes'])): ?>
    <div class="sc-notes">📝 <?= htmlspecialchars($ord['notes']) ?></div>
    <?php endif; ?>
    <?php if ($payment): ?>
    <div class="sc-payment">
        💳 <?= htmlspecialchars($payment['method']) ?> — <?= htmlspecialchars($payment['currency']) ?>
        <?= $payment['khrAmount'] ? '/ ' . number_format((float)$payment['khrAmount']) . ' KHR' : '' ?>
        <span class="badge <?= $payment['status'] === 'VERIFIED' ? 'badge-success' : 'badge-warn' ?>"><?= htmlspecialchars($payment['status']) ?></span>
    </div>
    <?php endif; ?>
    <div class="sc-footer">
        <div class="sc-total">$<?= number_format((float)$ord['totalAmount'], 2) ?></div>
        <div class="sc-actions">
            <?php foreach ($next_options as $next): ?>
            <button class="btn btn-sm <?= $next === 'CANCELLED' ? 'btn-danger' : 'btn-primary' ?> update-status-btn"
                    data-order-id="<?= htmlspecialchars($ord['id']) ?>"
                    data-status="<?= htmlspecialchars($next) ?>">
                <?= $next === 'CANCELLED' ? '✕ Cancel' : '→ ' . $next ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const CHECKOUT_SLUG = <?= json_encode($slug ?? '') ?>;

document.querySelectorAll('.update-status-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const orderId = btn.dataset.orderId;
        const status  = btn.dataset.status;
        btn.disabled = true;
        btn.textContent = '...';
        try {
            const res = await fetch('/api/orders/index.php?id=' + orderId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Failed');
        } catch (e) {
            alert('Network error');
            btn.disabled = false;
        }
    });
});

function confirmDeleteAllOrders() {
    if (!CHECKOUT_SLUG) {
        alert('No restaurant selected.');
        return;
    }
    const count = <?= count($orders) ?>;
    if (!confirm(`⚠️ Are you sure you want to delete ALL ${count} orders for this restaurant? This cannot be undone.`)) return;

    const btn = document.querySelector('.btn-delete-orders');
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    fetch('/api/orders/index.php?all=1&restaurant=' + CHECKOUT_SLUG, {
        method: 'DELETE',
        credentials: 'include'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            // Reset notification badges before reload
            const chatBadge = document.getElementById('chatUnreadBadge');
            const notifBadge = document.getElementById('notifUnreadBadge');
            if (chatBadge) chatBadge.style.display = 'none';
            if (notifBadge) notifBadge.style.display = 'none';
            location.reload();
        }
        else { alert(d.error || 'Failed to delete orders'); btn.disabled = false; btn.innerHTML = '🗑 Delete All Orders'; }
    })
    .catch(() => { alert('Network error'); btn.disabled = false; btn.innerHTML = '🗑 Delete All Orders'; });
}

// Auto-refresh every 10 seconds for active orders
const hasActive = <?= json_encode((bool)array_filter($orders, fn($o) => in_array($o['status'], ['PENDING','CONFIRMED','PREPARING']))) ?>;
if (hasActive) setTimeout(() => location.reload(), 10000);

// Auto-scroll to latest order button (bottom-right fixed)
const scrollBtn = document.createElement('button');
scrollBtn.innerHTML = '⬇';
scrollBtn.title = 'Scroll to latest order';
scrollBtn.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;width:42px;height:42px;border-radius:50%;background:#3b82f6;color:#fff;border:none;font-size:18px;cursor:pointer;box-shadow:0 4px 16px rgba(59,130,246,.4);transition:transform .2s;display:flex;align-items:center;justify-content:center';
scrollBtn.onmouseover = () => scrollBtn.style.transform = 'scale(1.1)';
scrollBtn.onmouseout = () => scrollBtn.style.transform = 'scale(1)';
scrollBtn.onclick = () => {
  const cards = document.querySelectorAll('.stream-card');
  if (cards.length) {
    cards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    cards[0].style.boxShadow = '0 0 0 3px #3b82f6';
    setTimeout(() => cards[0].style.boxShadow = '', 2000);
  }
};
document.body.appendChild(scrollBtn);
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
