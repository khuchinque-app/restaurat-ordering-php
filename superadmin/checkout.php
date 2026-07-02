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
.checkout-toolbar { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:1.25rem; }
.checkout-toolbar label { font-size:.78rem; font-weight:600; display:block; margin-bottom:.2rem; color:#6b7280; }
.checkout-toolbar select, .checkout-toolbar input { padding:.4rem .65rem; border:1px solid #d1d5db; border-radius:6px; font-size:.875rem; }

.order-stream { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:1rem; }

.stream-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.1rem; position:relative; transition:box-shadow .2s; }
.stream-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
.stream-card.pending   { border-left:4px solid #f59e0b; }
.stream-card.confirmed { border-left:4px solid #3b82f6; }
.stream-card.preparing { border-left:4px solid #8b5cf6; }
.stream-card.ready     { border-left:4px solid #10b981; }
.stream-card.completed { border-left:4px solid #6b7280; }
.stream-card.cancelled { border-left:4px solid #ef4444; }

.sc-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.7rem; }
.sc-order-num { font-weight:700; font-size:1rem; }
.sc-time { font-size:.75rem; color:#94a3b8; }
.sc-customer { font-size:.875rem; color:#64748b; margin-bottom:.6rem; }
.sc-items { display:flex; flex-direction:column; gap:.25rem; margin-bottom:.7rem; }
.sc-item { display:flex; justify-content:space-between; font-size:.85rem; padding:.2rem 0; border-bottom:1px dashed #f1f5f9; }
.sc-notes { font-size:.78rem; color:#94a3b8; font-style:italic; margin-bottom:.5rem; padding:.4rem .6rem; background:#f8fafc; border-radius:6px; }
.sc-footer { display:flex; justify-content:space-between; align-items:center; padding-top:.5rem; border-top:1px solid #f1f5f9; }
.sc-total { font-weight:700; font-size:1.05rem; color:#111827; }
.sc-actions { display:flex; gap:.4rem; flex-wrap:wrap; }

.stat-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .85rem; border-radius:9999px; font-size:.85rem; font-weight:600; }
.stat-pill.pending   { background:#fef3c7; color:#92400e; }
.stat-pill.preparing { background:#ede9fe; color:#5b21b6; }
.stat-pill.ready     { background:#dcfce7; color:#166534; }

@keyframes fadeIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
.stream-card { animation: fadeIn .3s ease; }
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
    <div style="margin-left:auto;display:flex;gap:.6rem;align-self:center">
        <span class="stat-pill pending">⏳ <?= $pending_count ?> Pending</span>
        <span class="stat-pill preparing">🔥 <?= $preparing_count ?> Preparing</span>
        <span class="stat-pill ready">✅ <?= $ready_count ?> Ready</span>
    </div>
</div>

<!-- Order Stream -->
<?php if (empty($orders)): ?>
<div style="text-align:center;padding:4rem;color:#94a3b8;font-size:1rem">
    <div style="font-size:3rem;margin-bottom:1rem">📦</div>
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
    <?php
    $tel_setting = db_fetch('SELECT settingValue FROM RestaurantSetting WHERE restaurantId = ? AND settingKey = ?', [$rid, 'telegram_url']);
    $tel_url = $tel_setting['settingValue'] ?? '';
    if ($tel_url): ?>
    <div style="font-size:.78rem;margin-bottom:.4rem">
        📱 <a href="<?= htmlspecialchars($tel_url) ?>" target="_blank" style="color:#0088cc;font-weight:600">Forward to Official Telegram →</a>
    </div>
    <?php endif; ?>
    <!-- Ongkir / Shipping Fee -->
    <div class="sc-ongkir" style="display:flex;justify-content:space-between;align-items:center;font-size:.85rem;padding:.3rem 0;border-top:1px dashed #f1f5f9;margin-top:.3rem">
        <span>🚚 Ongkir (Shipping)</span>
        <span style="display:flex;align-items:center;gap:.4rem">
            <span id="ongkir-display-<?= htmlspecialchars($ord['id']) ?>"><?= (float)$ord['shippingFee'] > 0 ? '$'.number_format((float)$ord['shippingFee'],2).' · '.number_format((float)$ord['shippingFee']*4000).' KHR' : '—' ?></span>
            <button onclick="editOngkir('<?= htmlspecialchars($ord['id']) ?>')" style="background:none;border:none;cursor:pointer;font-size:.75rem;color:#7c3aed;padding:2px 6px;border-radius:4px" title="Edit ongkir">✏️</button>
        </span>
    </div>
    <div id="ongkir-form-<?= htmlspecialchars($ord['id']) ?>" style="display:none;margin-top:.3rem">
        <div style="display:flex;gap:.4rem;align-items:center">
            <input type="number" id="ongkir-input-<?= htmlspecialchars($ord['id']) ?>" value="<?= (float)$ord['shippingFee'] > 0 ? (float)$ord['shippingFee'] : '1.50' ?>" step="0.25" min="0" style="width:80px;padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem;text-align:center">
            <span style="font-size:.75rem;color:#64748b">USD</span>
            <button onclick="saveOngkir('<?= htmlspecialchars($ord['id']) ?>')" class="btn btn-sm btn-primary">Save</button>
            <button onclick="cancelOngkir('<?= htmlspecialchars($ord['id']) ?>')" class="btn btn-sm btn-ghost">Cancel</button>
        </div>
    </div>
    <?php if ($payment): ?>
    <div style="font-size:.75rem;color:#64748b;margin-bottom:.4rem">
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
// ── Order Notification System ────────────────────
let knownOrders = new Set();
document.querySelectorAll('.stream-card').forEach(card => {
    const num = card.querySelector('.sc-order-num')?.textContent || '';
    if (num) knownOrders.add(num);
});
let notifSoundEnabled = true;

function playOrderSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        // Ding-dong sound
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(523, ctx.currentTime);
        osc.frequency.setValueAtTime(659, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
    } catch(e) {}
}

function showOrderToast(title, body) {
    const existing = document.querySelector('.order-toast-overlay');
    if (existing) existing.remove();
    const overlay = document.createElement('div');
    overlay.className = 'order-toast-overlay';
    overlay.innerHTML = `<div class="order-toast show"><button class="order-toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button><div class="order-toast-title">${title}</div><div class="order-toast-body">${body}</div></div>`;
    document.body.appendChild(overlay);
    if (notifSoundEnabled) playOrderSound();
    setTimeout(() => { try { overlay.remove(); } catch(e) {} }, 10000);
}

// Inject toast CSS
const style = document.createElement('style');
style.textContent = '.order-toast-overlay{position:fixed;top:0;left:0;right:0;z-index:9999;display:flex;justify-content:center;padding-top:1rem;pointer-events:none}.order-toast{background:#1e293b;color:#fff;padding:1rem 1.5rem;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.3);max-width:480px;width:100%;transform:translateY(-120%);transition:transform .4s cubic-bezier(.34,1.56,.64,1);pointer-events:auto;border-left:4px solid #f59e0b}.order-toast.show{transform:translateY(0)}.order-toast-title{font-weight:700;font-size:1rem;margin-bottom:.25rem}.order-toast-body{font-size:.85rem;color:#cbd5e1}.order-toast-close{float:right;background:none;border:none;color:#64748b;cursor:pointer;font-size:1.2rem;padding:0 .2rem}.order-toast-close:hover{color:#fff}@keyframes orderPulse{0%{box-shadow:0 0 0 0 rgba(245,158,11,0.5)}70%{box-shadow:0 0 0 12px rgba(245,158,11,0)}100%{box-shadow:0 0 0 0 rgba(245,158,11,0)}}.order-toast{animation:orderPulse 1.5s infinite}';
document.head.appendChild(style);

// Poll for new orders via API
async function pollOrders() {
    try {
        const r = await fetch('/api/orders/index.php?limit=10&status=PENDING', {credentials:'include'});
        const d = await r.json();
        if (!d.success || !d.data.orders) return;
        d.data.orders.forEach(order => {
            if (!knownOrders.has(order.orderNumber)) {
                knownOrders.add(order.orderNumber);
                showOrderToast('🆕 New Order #' + order.orderNumber, 
                    order.customerName + ' — $' + parseFloat(order.totalAmount).toFixed(2) + ' (' + order.itemCount + ' items)');
            }
        });
    } catch(e) {}
}

// Sound toggle
const topbar = document.querySelector('.sa-topbar-right');
if (topbar) {
    const btn = document.createElement('button');
    btn.id = 'soundToggle';
    btn.innerHTML = '🔔';
    btn.title = 'Toggle notification sound';
    btn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:1.1rem;margin-left:.5rem;padding:2px 6px;border-radius:6px';
    btn.onclick = function() {
        notifSoundEnabled = !notifSoundEnabled;
        this.innerHTML = notifSoundEnabled ? '🔔' : '🔕';
        this.style.opacity = notifSoundEnabled ? '1' : '.4';
    };
    topbar.appendChild(btn);
}

// Poll every 10s
pollOrders();
setInterval(pollOrders, 10000);

// ── Ongkir (Shipping Fee) Editing ────────────────
function editOngkir(orderId) {
    document.getElementById('ongkir-display-' + orderId).parentElement.style.display = 'none';
    document.getElementById('ongkir-form-' + orderId).style.display = '';
}
function cancelOngkir(orderId) {
    document.getElementById('ongkir-form-' + orderId).style.display = 'none';
    document.getElementById('ongkir-display-' + orderId).parentElement.style.display = '';
}
async function saveOngkir(orderId) {
    const input = document.getElementById('ongkir-input-' + orderId);
    const fee = parseFloat(input.value);
    if (isNaN(fee) || fee < 0) { showToast('Enter a valid shipping fee', false); return; }
    try {
        const res = await fetch('/api/orders/shipping.php', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({orderId, shippingFee: fee})
        });
        const d = await res.json();
        if (d.success) { location.reload(); }
        else { alert(d.error || 'Failed'); }
    } catch(e) { alert('Network error'); }
}

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

// Auto-refresh every 10 seconds for active orders — DISABLED when API polling is active
// The API pollOrders() + setInterval handles new order detection without full page reload
// const hasActive = <?= json_encode((bool)array_filter($orders, fn($o) => in_array($o['status'], ['PENDING','CONFIRMED','PREPARING']))) ?>;
// if (hasActive) setTimeout(() => location.reload(), 10000);
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
