<?php
require_once dirname(__DIR__) . '/db.php';
$page_title = 'Orders';
include dirname(__DIR__) . '/includes/admin_header.php';

$restaurant = !empty($current_user['restaurantId'])
    ? db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']])
    : null;
$rid = $restaurant['id'] ?? null;

$status_filter = $_GET['status'] ?? '';
$valid_statuses = ['PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'];

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$skip  = ($page - 1) * $limit;

$where  = $rid ? ['o.restaurantId = ?'] : [];
$params = $rid ? [$rid] : [];

if ($status_filter && in_array($status_filter, $valid_statuses)) {
    $where[]  = 'o.status = ?';
    $params[] = $status_filter;
}

$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total  = $rid ? (int)(db_fetch("SELECT COUNT(*) AS n FROM \"Order\" o $w", $params)['n'] ?? 0) : 0;
$orders = $rid ? db_query(
    "SELECT o.id, o.orderNumber, o.status, o.totalAmount, o.itemCount, o.customerName, o.customerPhone, o.notes, o.createdAt
     FROM \"Order\" o $w ORDER BY o.createdAt DESC LIMIT $limit OFFSET $skip",
    $params
) : [];

$total_pages = (int)ceil($total / $limit);

// Fetch items per order
$order_items = [];
foreach ($orders as $ord) {
    $order_items[$ord['id']] = db_query(
        'SELECT oi.quantity, oi.totalPrice, mi.name AS itemName FROM OrderItem oi JOIN MenuItem mi ON mi.id = oi.menuItemId WHERE oi.orderId = ?',
        [$ord['id']]
    );
}
?>

<!-- Filters -->
<div class="filter-bar">
    <?php foreach (array_merge([''], $valid_statuses) as $s): ?>
        <a href="orders.php<?= $s ? '?status=' . urlencode($s) : '' ?>"
           class="btn btn-sm <?= $status_filter === $s ? 'btn-primary' : 'btn-outline' ?>">
           <?= $s ?: 'All' ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="orders-admin-list" id="orders-list">
<?php if (empty($orders)): ?>
    <div class="empty-state">No orders found.</div>
<?php else: foreach ($orders as $ord): ?>
<article class="order-admin-card card" id="order-row-<?= htmlspecialchars($ord['id']) ?>">
    <div class="order-admin-header">
        <div>
            <strong>#<?= htmlspecialchars($ord['orderNumber']) ?></strong>
            <span class="text-muted ml-2"><?= htmlspecialchars($ord['customerName'] ?? 'Guest') ?></span>
            <?php if ($ord['customerPhone']): ?>
                <span class="text-muted"> &bull; <?= htmlspecialchars($ord['customerPhone']) ?></span>
            <?php endif; ?>
        </div>
        <div class="order-admin-meta">
            <span class="status-badge status-<?= strtolower($ord['status']) ?>"><?= htmlspecialchars($ord['status']) ?></span>
            <strong>$<?= number_format((float)$ord['totalAmount'], 2) ?></strong>
            <span class="text-muted"><?= date('M d H:i', strtotime($ord['createdAt'])) ?></span>
        </div>
    </div>

    <div class="order-admin-items">
        <?php foreach ($order_items[$ord['id']] as $oi): ?>
            <span class="order-item-chip"><?= (int)$oi['quantity'] ?>&times; <?= htmlspecialchars($oi['itemName']) ?></span>
        <?php endforeach; ?>
        <?php if ($ord['notes']): ?>
            <div class="order-notes"><em>Note: <?= htmlspecialchars($ord['notes']) ?></em></div>
        <?php endif; ?>
    </div>

    <?php
    $transitions = [
        'PENDING'          => 'CONFIRMED',
        'CONFIRMED'        => 'PREPARING',
        'PREPARING'        => 'READY',
        'READY'            => 'COMPLETED',
        'OUT_FOR_DELIVERY' => 'COMPLETED',
    ];
    $next = $transitions[$ord['status']] ?? null;
    ?>
    <?php if ($next): ?>
    <div class="order-admin-actions">
        <button class="btn btn-primary btn-sm update-status-btn"
                data-order-id="<?= htmlspecialchars($ord['id']) ?>"
                data-status="<?= htmlspecialchars($next) ?>">
            Mark as <?= htmlspecialchars($next) ?>
        </button>
        <?php if (in_array($ord['status'], ['PENDING','CONFIRMED'])): ?>
        <button class="btn btn-danger btn-sm update-status-btn"
                data-order-id="<?= htmlspecialchars($ord['id']) ?>"
                data-status="CANCELLED">
            Cancel
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</article>
<?php endforeach; endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="orders.php?page=<?= $p ?><?= $status_filter ? '&status=' . urlencode($status_filter) : '' ?>"
           class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.update-status-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const orderId = btn.dataset.orderId;
        const status  = btn.dataset.status;
        btn.disabled  = true;
        btn.textContent = 'Updating...';
        try {
            const res = await fetch('/api/orders/index.php?id=' + orderId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Failed to update order');
        } catch (e) {
            alert('Network error');
            btn.disabled = false;
        }
    });
});

// Auto-refresh if there are active orders
const hasPending = <?= json_encode((bool)array_filter($orders, fn($o) => in_array($o['status'], ['PENDING','CONFIRMED','PREPARING']))) ?>;
if (hasPending) setTimeout(() => location.reload(), 20000);
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
