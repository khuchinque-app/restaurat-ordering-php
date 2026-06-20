<?php
/**
 * db.php - Database connection (SQLite)
 * Place this in the SAME directory as your main login/index.php (parent of cashier subfolder)
 * or adjust paths.
 * The cashier/history/stock assume require '../db.php' so this should be one level up.
 */

$dbFile = __DIR__ . '/database.db';

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Create common tables if not exist (idempotent)
    $pdo->exec("CREATE TABLE IF NOT EXISTS finished_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT UNIQUE,
        customer_name TEXT,
        address TEXT,
        total REAL,
        payment_type TEXT,
        plain_text TEXT,
        html_content TEXT,
        is_checked INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no TEXT,
        customer_name TEXT,
        address TEXT,
        notes TEXT,
        total REAL,
        payment_type TEXT,
        location TEXT,
        plain_text TEXT,
        html_content TEXT,
        is_checked INTEGER DEFAULT 0,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Don't die on include, let pages handle
    // error_log("DB error: " . $e->getMessage());
}

// Optional helper used by index.php wrapper
if (!function_exists('updateOnlineStatus')) {
    function updateOnlineStatus($pdo, $username, $isOnline = true) {
        // Stub: implement real logic if you have a users/online table
        // e.g. $pdo->prepare("UPDATE users SET last_seen = ?, is_online = ? WHERE username = ?")
        //     ->execute([date('Y-m-d H:i:s'), $isOnline ? 1 : 0, $username]);
    }
}
?>