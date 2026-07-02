<?php
/**
 * Customer Presence API — heartbeat endpoint for storefront chat
 * GET:  Check who's online (returns active customers)
 * POST: Ping heartbeat (customer is active)
 * DELETE: Clear presence (customer closed chat)
 */
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$sender_name   = $_GET['sender'] ?? $body['sender_name'] ?? '';
$restaurant_id = $_GET['restaurant_id'] ?? $body['restaurant_id'] ?? '';
$restaurant_slug = $_GET['restaurant'] ?? $body['restaurant'] ?? '';

// Resolve restaurant slug → id
if ($restaurant_slug && !$restaurant_id) {
    $r = db_fetch('SELECT id FROM Restaurant WHERE slug = ?', [$restaurant_slug]);
    $restaurant_id = $r['id'] ?? null;
}

// ── POST: Heartbeat (customer is online) ─────────────────
if ($method === 'POST') {
    if (!$sender_name || !$restaurant_id) {
        json_error(400, 'sender_name and restaurant_id required');
    }

    // Upsert presence
    $existing = db_fetch(
        'SELECT senderName FROM CustomerPresence WHERE senderName = ? AND restaurantId = ?',
        [$sender_name, $restaurant_id]
    );

    if ($existing) {
        db_execute(
            'UPDATE CustomerPresence SET lastSeenAt = datetime("now"), chatOpen = 1 WHERE senderName = ? AND restaurantId = ?',
            [$sender_name, $restaurant_id]
        );
    } else {
        db_execute(
            'INSERT INTO CustomerPresence (senderName, restaurantId, lastSeenAt, chatOpen) VALUES (?, ?, datetime("now"), 1)',
            [$sender_name, $restaurant_id]
        );
    }

    json_ok(['status' => 'online']);
}

// ── DELETE: Clear presence (chat closed) ────────────────
if ($method === 'DELETE') {
    if (!$sender_name || !$restaurant_id) {
        json_error(400, 'sender_name and restaurant_id required');
    }

    db_execute(
        'UPDATE CustomerPresence SET chatOpen = 0, lastSeenAt = datetime("now") WHERE senderName = ? AND restaurantId = ?',
        [$sender_name, $restaurant_id]
    );

    json_ok(['status' => 'offline']);
}

// ── GET: Check online status ─────────────────────────────
if ($method === 'GET') {
    $online_threshold = 15; // seconds — if heartbeat within 15s, they're online

    if ($sender_name && $restaurant_id) {
        // Check specific customer
        $p = db_fetch(
            "SELECT senderName, lastSeenAt, chatOpen,
                    CASE WHEN CAST(julianday('now') - julianday(lastSeenAt) AS REAL) * 86400 < ? THEN 1 ELSE 0 END AS isOnline
             FROM CustomerPresence WHERE senderName = ? AND restaurantId = ?",
            [$online_threshold, $sender_name, $restaurant_id]
        );
        json_ok($p ?: ['senderName' => $sender_name, 'isOnline' => 0, 'chatOpen' => 0]);
    }

    // Get all online customers for a restaurant
    if ($restaurant_id) {
        $online = db_query(
            "SELECT senderName, lastSeenAt, chatOpen FROM CustomerPresence
             WHERE restaurantId = ? AND CAST(julianday('now') - julianday(lastSeenAt) AS REAL) * 86400 < ?
             ORDER BY lastSeenAt DESC",
            [$restaurant_id, $online_threshold]
        );
        json_ok(['online' => $online]);
    }

    // Get ALL online customers across all restaurants
    if (!empty($_GET['all'])) {
        $online = db_query(
            "SELECT cp.senderName, cp.lastSeenAt, cp.chatOpen, r.slug AS restaurantSlug, r.name AS restaurantName
             FROM CustomerPresence cp
             JOIN Restaurant r ON r.id = cp.restaurantId
             WHERE CAST(julianday('now') - julianday(cp.lastSeenAt) AS REAL) * 86400 < ?
             ORDER BY cp.lastSeenAt DESC",
            [$online_threshold]
        );
        json_ok(['online' => $online]);
    }

    json_error(400, 'sender+restaurant_id or restaurant parameter required');
}

json_error(405, 'Method not allowed');
