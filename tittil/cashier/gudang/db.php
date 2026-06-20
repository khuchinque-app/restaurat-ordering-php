<?php
// =============================================================================
// DATABASE CONNECTION - Shared PDO for all PHP pages
// =============================================================================
// This file provides a shared database connection for stock.php, history.php, etc.
// =============================================================================

$db_path = __DIR__ . '/database.db';

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}