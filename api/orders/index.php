<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/activity.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = get_auth_user();

    if (!empty($_GET['id'])) {
        $order = db_fetch(
            'SELECT o.*, r.name AS restaurantName FROM "Order" o
             JOIN Restaurant r ON r.id = o.restaurantId WHERE o.id = ?',
            [$_GET['id']]
        );
        if (!$order) json_error(404, 'Order not found');
        $order['items'] = db_query(
            'SELECT oi.*, mi.name AS itemName FROM OrderItem oi JOIN MenuItem mi ON mi.id = oi.menuItemId WHERE oi.orderId = ?',
            [$order['id']]
        );
        json_ok($order);
    }

    $where  = [];
    $params = [];

    if ($user && $user['role'] === 'CUSTOMER') {
        $where[]  = 'o.customerId = ?';
        $params[] = $user['id'];
    } elseif (!empty($_GET['restaurantSlug'])) {
        $r = get_restaurant($_GET['restaurantSlug']);
        if ($r) { $where[] = 'o.restaurantId = ?'; $params[] = $r['id']; }
    }

    if (!empty($_GET['status'])) { $where[] = 'o.status = ?'; $params[] = $_GET['status']; }

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $skip  = ($page - 1) * $limit;
    $w     = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int)(db_fetch("SELECT COUNT(*) AS n FROM \"Order\" o $w", $params)['n'] ?? 0);
    $orders = db_query(
        "SELECT o.*, r.name AS restaurantName FROM \"Order\" o
         JOIN Restaurant r ON r.id = o.restaurantId
         $w ORDER BY o.createdAt DESC LIMIT $limit OFFSET $skip",
        $params
    );

    json_ok([
        'orders'     => $orders,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => (int)ceil($total / $limit)],
    ]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $restaurant_id = $body['restaurantId'] ?? null;
    if (!$restaurant_id) json_error(400, 'restaurantId is required');

    $restaurant = db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$restaurant_id]);
    if (!$restaurant) json_error(404, 'Restaurant not found');

    $items = $body['items'] ?? [];
    if (empty($items)) json_error(400, 'Order must have at least one item');

    $subtotal    = 0;
    $item_count  = 0;
    $order_items = [];

    foreach ($items as $item) {
        $menu_item = db_fetch('SELECT * FROM MenuItem WHERE id = ? AND isAvailable = 1', [$item['menuItemId']]);
        if (!$menu_item) json_error(400, "Item {$item['menuItemId']} is not available");

        if ($menu_item['stockQuantity'] !== null && (int)$menu_item['stockQuantity'] < (int)$item['quantity']) {
            json_error(400, "Insufficient stock for {$menu_item['name']}");
        }

        $modifiers_total = 0;
        if (!empty($item['modifiers'])) {
            foreach ($item['modifiers'] as $mod) {
                $modifiers_total += (float)($mod['priceAdjustment'] ?? 0);
            }
        }
        $unit_price  = (float)$menu_item['price'] + $modifiers_total;
        $total_price = $unit_price * (int)$item['quantity'];
        $subtotal += $total_price;
        $item_count   += (int)$item['quantity'];

        $order_items[] = [
            'menuItemId'  => $item['menuItemId'],
            'quantity'    => (int)$item['quantity'],
            'unitPrice'   => (string)$unit_price,
            'totalPrice'  => (string)$total_price,
            'notes'       => $item['notes'] ?? null,
            'modifiers'   => !empty($item['modifiers']) ? json_encode($item['modifiers']) : null,
        ];
    }

    $current_user = get_auth_user();
    $order_id     = new_id();
    $order_number = new_order_number();

    // Include tax in the stored total to match what the customer sees
    $tax_amount    = round($subtotal * TAX_RATE, 2);
    $total_amount  = round($subtotal + $tax_amount, 2);

    db_transaction(function ($db) use (
        $order_id, $order_number, $total_amount, $item_count, $body, $current_user,
        $restaurant_id, $order_items
    ) {
        $db->prepare(
            'INSERT INTO "Order" (id, orderNumber, status, totalAmount, itemCount, notes,
              customerName, customerPhone, customerId, restaurantId, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([
            $order_id, $order_number, 'PENDING',
            (string)$total_amount, $item_count,
            $body['notes'] ?? null,
            $body['customerName'] ?? ($current_user['name'] ?? null),
            $body['customerPhone'] ?? null,
            $current_user['id'] ?? null,
            $restaurant_id,
        ]);

        foreach ($order_items as $oi) {
            $item_id = new_id();
            $db->prepare(
                'INSERT INTO OrderItem (id, orderId, menuItemId, quantity, unitPrice, totalPrice, notes, modifiers, createdAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))'
            )->execute([$item_id, $order_id, $oi['menuItemId'], $oi['quantity'], $oi['unitPrice'], $oi['totalPrice'], $oi['notes'], $oi['modifiers']]);

            // Decrement stock
            $db->prepare(
                'UPDATE MenuItem SET stockQuantity = MAX(0, stockQuantity - ?),
                  isAvailable = CASE WHEN stockQuantity - ? <= 0 THEN 0 ELSE 1 END,
                  updatedAt = datetime("now")
                 WHERE id = ? AND stockQuantity IS NOT NULL'
            )->execute([$oi['quantity'], $oi['quantity'], $oi['menuItemId']]);
        }

        // Create chat room
        $chat_id = new_id();
        $db->prepare('INSERT INTO ChatRoom (id, orderId, type, createdAt, updatedAt) VALUES (?, ?, "ORDER", datetime("now"), datetime("now"))')->execute([$chat_id, $order_id]);
    });

    $order = db_fetch(
        'SELECT o.*, r.name AS restaurantName FROM "Order" o JOIN Restaurant r ON r.id = o.restaurantId WHERE o.id = ?',
        [$order_id]
    );
    $order['items'] = db_query(
        'SELECT oi.*, mi.name AS itemName FROM OrderItem oi JOIN MenuItem mi ON mi.id = oi.menuItemId WHERE oi.orderId = ?',
        [$order_id]
    );

    // Notify all admins + superadmins about the new order
    notify_all_admins(
        'NEW_ORDER',
        'New Order #' . $order_number,
        'New order #' . $order_number . ' from ' . ($body['customerName'] ?? 'Guest') . ' — $' . number_format((float)$total_amount, 2) . ' (' . $item_count . ' items, incl. tax) — ' . $restaurant['name'],
        $order_id
    );

    // Check for items that are now below low stock threshold
    foreach ($order_items as $oi) {
        $menu_item = db_fetch(
            'SELECT name, stockQuantity, lowStockThreshold FROM MenuItem WHERE id = ?',
            [$oi['menuItemId']]
        );
        if ($menu_item && $menu_item['stockQuantity'] !== null && (int)$menu_item['stockQuantity'] <= (int)$menu_item['lowStockThreshold']) {
            notify_all_admins(
                'STOCK_ALERT',
                '⚠ Low Stock: ' . $menu_item['name'],
                '"' . $menu_item['name'] . '" at ' . $restaurant['name'] . ' has only ' . (int)$menu_item['stockQuantity'] . ' left (threshold: ' . (int)$menu_item['lowStockThreshold'] . ')'
            );
        }
    }

    json_response(['success' => true, 'data' => $order], 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $user  = require_auth();
    $id    = $_GET['id'] ?? '';
    if (!$id) json_error(400, 'Order ID required');
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = $body['status'] ?? '';

    $valid_statuses = ['PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'];
    if (!in_array($status, $valid_statuses)) json_error(400, 'Invalid status');

    $order = db_fetch('SELECT * FROM "Order" WHERE id = ?', [$id]);
    if (!$order) json_error(404, 'Order not found');

    $transitions = [
        'PENDING'          => ['CONFIRMED', 'CANCELLED'],
        'CONFIRMED'        => ['PREPARING', 'CANCELLED'],
        'PREPARING'        => ['READY', 'CANCELLED'],
        'READY'            => ['OUT_FOR_DELIVERY', 'COMPLETED'],
        'OUT_FOR_DELIVERY' => ['COMPLETED'],
        'COMPLETED'        => [],
        'CANCELLED'        => [],
    ];

    if (!in_array($status, $transitions[$order['status']] ?? [])) {
        json_error(400, "Cannot transition from {$order['status']} to $status");
    }

    $completed_at = $status === 'COMPLETED' ? ', completedAt = datetime("now")' : '';
    db_execute(
        "UPDATE \"Order\" SET status = ?, updatedAt = datetime(\"now\") $completed_at WHERE id = ?",
        [$status, $id]
    );

    // Notify customer
    if ($order['customerId']) {
        $notif_id = new_id();
        db_execute(
            'INSERT INTO Notification (id, userId, orderId, type, title, message, isRead, createdAt)
             VALUES (?, ?, ?, "ORDER_STATUS", "Order Status Updated", ?, 0, datetime("now"))',
            [$notif_id, $order['customerId'], $id, "Your order #{$order['orderNumber']} is now $status"]
        );
    }

    $updated = db_fetch('SELECT * FROM "Order" WHERE id = ?', [$id]);
    log_activity($user, "ORDER_STATUS → $status", 'Order', $id, "#{$order['orderNumber']} changed to $status");

    // Notify all admins + superadmins about the status change
    notify_all_admins(
        'ORDER_STATUS',
        'Order #' . $order['orderNumber'] . ' → ' . $status,
        'Order #' . $order['orderNumber'] . ' status changed to ' . $status,
        $id
    );

    json_ok($updated);
}

json_error(405, 'Method not allowed');
