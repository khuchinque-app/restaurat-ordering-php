<?php
/**
 * api.php - Server-side persistence API for ASENG CASHIER
 * Located alongside script.js for coordinated access.
 * Uses the existing SQLite database via ../../db.php
 * 
 * Endpoints:
 *   GET  ?action=load_state       - Load full app state JSON
 *   POST ?action=save_state       - Save full app state JSON
 *   GET  ?action=load_order_no    - Load current order number + reset date
 *   POST ?action=save_order_no    - Save current order number + reset date
 *   GET  ?action=load_driver_names - Load all driver names
 *   POST ?action=save_driver_name  - Save a specific driver name
 */

require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'save_state':        saveState();          break;
        case 'load_state':        loadState();          break;
        case 'save_order_no':     saveOrderNo();        break;
        case 'load_order_no':     loadOrderNo();        break;
        case 'save_driver_name':  saveDriverName();     break;
        case 'load_driver_names': loadDriverNames();    break;
        case 'add_menu_item':     addMenuItem();        break;
        case 'report_deleted_order': reportDeletedOrder(); break;
        case 'save_finished_order': saveFinishedOrder(); break;
        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function ensureTable() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_state (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function saveState() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $data  = $input['data'] ?? '';
    ensureTable();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO app_state (key, value, updated_at) VALUES ('app_data', ?, datetime('now'))");
    $stmt->execute([$data]);
    echo json_encode(['ok' => true]);
}

function loadState() {
    global $pdo;
    ensureTable();
    $stmt = $pdo->prepare("SELECT value FROM app_state WHERE key = 'app_data'");
    $stmt->execute();
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'data' => $row ? $row['value'] : null]);
}

function saveOrderNo() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    ensureTable();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO app_state (key, value, updated_at) VALUES ('order_no', ?, datetime('now'))");
    $stmt->execute([json_encode([
        'order_no'        => $input['order_no'] ?? 0,
        'last_reset_date' => $input['last_reset_date'] ?? ''
    ])]);
    echo json_encode(['ok' => true]);
}

function loadOrderNo() {
    global $pdo;
    ensureTable();
    $stmt = $pdo->prepare("SELECT value FROM app_state WHERE key = 'order_no'");
    $stmt->execute();
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'data' => $row ? json_decode($row['value'], true) : null]);
}

function saveDriverName() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $driver = $input['driver'] ?? '';
    $name   = $input['name']   ?? '';
    if (!$driver) {
        echo json_encode(['ok' => false, 'error' => 'Driver name is required']);
        return;
    }
    ensureTable();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO app_state (key, value, updated_at) VALUES ('driver_name_' || ?, ?, datetime('now'))");
    $stmt->execute([$driver, $name]);
    echo json_encode(['ok' => true]);
}

function loadDriverNames() {
    global $pdo;
    ensureTable();
    $stmt = $pdo->prepare("SELECT key, value FROM app_state WHERE key LIKE 'driver_name_%'");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $names = [];
    foreach ($rows as $row) {
        $driverKey = str_replace('driver_name_', '', $row['key']);
        $names[$driverKey] = $row['value'];
    }
    echo json_encode(['ok' => true, 'data' => $names]);
}

// ── Add menu item to menu.txt ───────────────────────────────────────────────
function addMenuItem() {
    $input = json_decode(file_get_contents('php://input'), true);
    $name  = $input['name'] ?? '';
    $price = $input['price'] ?? 0;

    if (!$name || !$price) {
        echo json_encode(['ok' => false, 'error' => 'Name and price are required']);
        return;
    }

    $line = sprintf("%s %dk\n", $name, round($price / 1000));
    $menuFile = __DIR__ . '/../../menu.txt';
    $written = @file_put_contents($menuFile, $line, FILE_APPEND | LOCK_EX);

    if ($written === false) {
        echo json_encode(['ok' => false, 'error' => 'Failed to write to menu.txt']);
        return;
    }

    echo json_encode(['ok' => true]);
}

// ── Report finished order (saves to finished_orders for accounting) ─────
function saveFinishedOrder() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $orderNo        = $input['orderNo'] ?? $input['order_no'] ?? '';
    $customerName   = $input['customerName'] ?? $input['customer_name'] ?? '';
    $customerAddress = $input['address'] ?? '';
    $total          = $input['total'] ?? 0;
    $paymentType    = $input['isAba'] ? 'ABA' : 'CASH';
    $plainText      = $input['plainText'] ?? $input['plain_text'] ?? '';
    try {
        $existing = $GLOBALS['pdo']->prepare("SELECT id FROM finished_orders WHERE order_no = ?");
        $existing->execute([$orderNo]);
        if (!$existing->fetch()) {
            $stmt = $GLOBALS['pdo']->prepare("INSERT INTO finished_orders (order_no, customer_name, address, total, payment_type, plain_text) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$orderNo, $customerName, $customerAddress, $total, $paymentType, $plainText]);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ── Report deleted order (telemetry) ───────────────────────────────────────
function reportDeletedOrder() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);

    $orderNo        = $input['orderNo'] ?? $input['order_no'] ?? '';
    $customerName   = $input['customerName'] ?? $input['customer_name'] ?? '';
    $customerAddress = $input['customerAddress'] ?? $input['address'] ?? '';
    $notes          = $input['notes'] ?? '';
    $total          = $input['total'] ?? 0;
    $paymentType    = $input['isAba'] ? 'ABA' : ($input['payment_type'] ?? 'CASH');
    $location       = $input['location'] ?? 'Pending';
    $plainText      = $input['plainText'] ?? $input['plain_text'] ?? '';
    $htmlContent    = $input['htmlContent'] ?? $input['html_content'] ?? '';
    $action         = $input['action'] ?? 'DELETED';

    try {
        $stmt = $pdo->prepare("INSERT INTO deleted_orders (order_no, customer_name, address, notes, total, payment_type, location, plain_text, html_content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderNo, $customerName, $customerAddress, $notes, $total, $paymentType, $location, $plainText, $htmlContent]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
