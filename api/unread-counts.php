<?php
/**
 * Unified unread counts for all sidebar notification badges.
 * Returns counts for every section in one call.
 */
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/db.php';

$user = require_auth();

$is_super = $user['role'] === 'SUPERADMIN';
$rid = $user['restaurantId'] ?? null;

// ── Pending orders ────────────────────────────────
$pending_orders = (int)(db_fetch("SELECT COUNT(*) AS n FROM \"Order\" WHERE status = 'PENDING'")['n'] ?? 0);

// ── Unread customer chat ──────────────────────────
$unread_customer_chat = (int)(db_fetch(
    "SELECT COUNT(*) AS n FROM CustomerChat WHERE isRead = 0 AND senderRole NOT IN ('ADMIN','SUPERADMIN')"
)['n'] ?? 0);

// ── Unread staff/admin chat ───────────────────────
$unread_staff_chat = (int)(db_fetch(
    'SELECT COUNT(*) AS n FROM StaffChat WHERE senderId != ? AND isRead = 0',
    [$user['id']]
)['n'] ?? 0);

// ── Low stock items ────────────────────────────────
$low_stock = (int)(db_fetch(
    "SELECT COUNT(*) AS n FROM MenuItem WHERE stockQuantity IS NOT NULL AND stockQuantity <= lowStockThreshold AND stockQuantity > 0 AND isAvailable = 1"
)['n'] ?? 0);

// ── Out of stock items ─────────────────────────────
$out_of_stock = (int)(db_fetch(
    "SELECT COUNT(*) AS n FROM MenuItem WHERE isAvailable = 0"
)['n'] ?? 0);

// ── Unread notifications ──────────────────────────
$unread_notifs = (int)(db_fetch(
    'SELECT COUNT(*) AS n FROM Notification WHERE userId = ? AND isRead = 0',
    [$user['id']]
)['n'] ?? 0);

json_ok([
    'pending_orders'    => $pending_orders,
    'unread_customer_chat' => $unread_customer_chat + $unread_staff_chat, // total chat unread
    'unread_staff_chat' => $unread_staff_chat,
    'low_stock'         => $low_stock,
    'out_of_stock'      => $out_of_stock,
    'unread_notifs'     => $unread_notifs,
    'total_unread'      => $pending_orders + $unread_customer_chat + $unread_staff_chat + $low_stock + $out_of_stock,
]);
