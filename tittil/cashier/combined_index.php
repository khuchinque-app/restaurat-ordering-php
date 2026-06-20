<?php
session_start();

// ── DB setup (self-contained, no external db.php needed) ───────────────────
$dbFile = __DIR__ . '/database.db';
try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Create common tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS finished_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_no TEXT UNIQUE, customer_name TEXT, address TEXT, total REAL, payment_type TEXT, plain_text TEXT, html_content TEXT, is_checked INTEGER DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_no TEXT, customer_name TEXT, address TEXT, notes TEXT, total REAL, payment_type TEXT, location TEXT, plain_text TEXT, html_content TEXT, is_checked INTEGER DEFAULT 0, deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, order_no TEXT, username TEXT, action TEXT, details TEXT, ip_address TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, category TEXT, quantity INTEGER DEFAULT 0, unit TEXT DEFAULT 'pcs', min_level INTEGER DEFAULT 10, price INTEGER DEFAULT 0, supplier TEXT, last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, notes TEXT)");
} catch (Exception $e) {
    // continue, some pages may still work
}

// ── Auth gate ─────────────────────────────
if (empty($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

if (function_exists('updateOnlineStatus')) {
    updateOnlineStatus($pdo, $_SESSION['username'], true);
}

// ── Determine current page ─────────────────
$page = $_GET['page'] ?? 'cashier';
$action = $_GET['action'] ?? '';

// ── Base path helper ──────────────────────
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = $basePath ?: '';
$basePrefix = $basePath ? $basePath : '';
$selfUrl = $basePrefix . '/' . basename($_SERVER['SCRIPT_NAME']);

// Helper links
$cashierLink = $selfUrl . '?page=cashier';
$historyLink = $selfUrl . '?page=history';
$stockLink = $selfUrl . '?page=stock';
$logoutLink = $basePrefix . '/../logout.php';

// ── Common auth bar HTML ──────────────────
$safeUser = htmlspecialchars($_SESSION['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
$superLinkHtml = ($role === 'superadmin')
    ? '<a href="' . ($basePrefix ? $basePrefix . '/../superadmin/index.php' : '../superadmin/index.php') . '" style="color:#00bcff;text-decoration:none;margin-left:15px;font-weight:bold;">⚙️ Superadmin</a>'
    : '';

$authBarHtml = '<div id="php-auth-bar" style="background:#0c0c0c;border-bottom:2px solid #00ff00;padding:10px 20px;font-family:monospace;display:flex;justify-content:space-between;align-items:center;color:#ccc;font-size:13px;position:sticky;top:0;z-index:99999;">'
    . '<div><span style="color:#00ff00;font-weight:bold;">🔒 AUTHENTICATED</span>'
    . '<span style="margin-left:12px;">👤 ' . $safeUser . ' <span style="color:#888;">(' . $safeRole . ')</span></span>'
    . '<a href="' . $cashierLink . '" style="color:#4CAF50;text-decoration:none;margin-left:15px;font-weight:bold;">🏡 Cashier</a>'
    . '<a href="' . $historyLink . '" style="color:#FFC107;text-decoration:none;margin-left:15px;font-weight:bold;">📜 History</a>'
    . '<a href="' . $stockLink . '" style="color:#FF9800;text-decoration:none;margin-left:15px;font-weight:bold;">📦 Stock</a>'
    . $superLinkHtml . '</div>'
    . '<a href="' . $logoutLink . '" style="background:#ff3333;color:#fff;padding:4px 14px;text-decoration:none;border-radius:2px;font-size:12px;font-weight:bold;">Logout</a>'
    . '</div>';

// ── PAGE: HISTORY ──────────────────────────────────────────────────────────
if ($page === 'history') {
    // API handlers for history
    if ($action === 'history_action_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $details = $input['details'] ?? '';
            preg_match('/Order #(\S+)/', $details, $m);
            $orderNo = $m[1] ?? null;
            $username = $_SESSION['username'] ?? 'admin';
            $stmt = $pdo->prepare("INSERT INTO audit_logs (order_no, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $orderNo,
                $username,
                $input['action'] ?? 'UNKNOWN',
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'trace_order' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        $orderNo = $_GET['order_no'] ?? '';
        $logs = [];
        try {
            $stmt = $pdo->prepare("SELECT created_at as timestamp, username, action, details FROM audit_logs WHERE order_no = ? ORDER BY created_at DESC");
            $stmt->execute([$orderNo]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($logs)) {
                $stmt = $pdo->prepare("SELECT deleted_at as timestamp, 'SYSTEM' as username, 'DELETED' as action, ('Order deleted from ' || COALESCE(location, 'unknown')) as details FROM deleted_orders WHERE order_no = ?");
                $stmt->execute([$orderNo]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $logs = [];
        }
        echo json_encode($logs);
        exit;
    }

    // Load dbItems for seeding
    $dbItems = [];
    try {
        $stmt = $pdo->query("SELECT order_no, customer_name, address, total, payment_type, plain_text, 'Finished' as location, 0 as isDeleted, 0 as isChecked, null as deletedAt, null as notes, null as html_content FROM finished_orders");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dbItems[] = [
                'orderNo'     => $row['order_no'],
                'name'        => $row['customer_name'],
                'address'     => $row['address'],
                'notes'       => $row['notes'] ?? '',
                'total'       => (float)$row['total'],
                'isAba'       => ($row['payment_type'] === 'ABA'),
                'isChecked'   => (bool)$row['isChecked'],
                'htmlContent' => $row['html_content'] ?? '',
                'plainText'   => $row['plain_text'] ?? '',
                'location'    => $row['location'],
                'deletedAt'   => $row['deletedAt'],
                'isDeleted'   => (bool)$row['isDeleted']
            ];
        }
        $stmt = $pdo->query("SELECT order_no, customer_name, address, notes, total, payment_type, location, plain_text, html_content, is_checked, deleted_at FROM deleted_orders");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dbItems[] = [
                'orderNo'     => $row['order_no'],
                'name'        => $row['customer_name'],
                'address'     => $row['address'],
                'notes'       => $row['notes'] ?? '',
                'total'       => (float)$row['total'],
                'isAba'       => ($row['payment_type'] === 'ABA'),
                'isChecked'   => (bool)$row['is_checked'],
                'htmlContent' => $row['html_content'] ?? '',
                'plainText'   => $row['plain_text'] ?? '',
                'location'    => '🗑 Deleted (' . ($row['location'] ?? '?') . ')',
                'deletedAt'   => $row['deleted_at'],
                'isDeleted'   => true
            ];
        }
    } catch (Exception $e) {
        $dbItems = [];
    }

    // Output the full history HTML (from fixed version, with links adjusted to query param)
    $historyHtml = file_get_contents(__DIR__ . '/history.php.txt'); // fallback if needed, but we embed below
    // Since we are combining, we will output the HTML directly with adjustments
    // To keep it simple, output the HTML part with replaced links for self calls
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All History - ASENG</title>
    <style>
        @import url(\'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;700;800&display=swap\');
        :root { --green: #4CAF50; --green-dim: #2e7d32; --bg: #111111; --card: #161616; --card2: #1e1e1e; --border: #2a2a2a; --text: #ffffff; --text-dim: #888888; --red: #ef5350; --orange: #FF9800; --blue: #f44336; --yellow: #FFC107; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: \'Syne\', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; padding: 20px; gap: 16px; }
        .page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: var(--card); padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); }
        .page-header h1 { font-size: 1.4em; color: var(--green); flex: 1; }
        .back-btn { background: var(--card2); color: var(--text); border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-family: \'Syne\', sans-serif; font-size: 14px; transition: all 0.2s; }
        .back-btn:hover { background: var(--green); color: #000; border-color: var(--green); }
        .header-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .header-controls input, .header-controls select { background: var(--card2); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-family: \'Syne\', sans-serif; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .header-controls input { width: 280px; }
        .header-controls input:focus, .header-controls select:focus { border-color: var(--green); }
        .btn-export { background: #8e44ad; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-family: \'Syne\', sans-serif; font-size: 14px; font-weight: 700; transition: all 0.2s; }
        .btn-export:hover { background: #7d3c98; transform: translateY(-1px); }
        .stats-bar { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 140px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; text-align: center; }
        .stat-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-value { font-family: \'JetBrains Mono\', monospace; font-size: 1.3em; font-weight: 700; color: var(--text); }
        .stat-value.green { color: var(--green); } .stat-value.red { color: var(--red); } .stat-value.yellow { color: var(--yellow); }
        .table-wrapper { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: auto; flex: 1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead tr { background: var(--card2); border-bottom: 2px solid var(--border); }
        th { padding: 12px 14px; text-align: left; color: var(--green); font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        tbody tr:hover { background: var(--card2); }
        td { padding: 11px 14px; vertical-align: middle; color: var(--text); font-size: 13px; max-width: 200px; word-break: break-word; }
        td:first-child { color: var(--text-dim); font-size: 11px; font-family: \'JetBrains Mono\', monospace; white-space: nowrap; }
        .td-total { font-family: \'JetBrains Mono\', monospace; font-weight: 700; color: var(--yellow); white-space: nowrap; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .badge-cash  { background: rgba(76,175,80,0.18); color: var(--green); border: 1px solid var(--green-dim); }
        .badge-aba   { background: rgba(239,83,80,0.18);  color: var(--red);   border: 1px solid var(--red); }
        .badge-check { background: rgba(76,175,80,0.12); color: var(--green); }
        .badge-pending { background: rgba(255,152,0,0.15); color: var(--orange); }
        .btn-view { background: var(--card2); color: var(--text-dim); border: 1px solid var(--border); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-family: \'Syne\', sans-serif; transition: all 0.2s; white-space: nowrap; width: 70px; text-align: center; }
        .btn-view:hover { background: var(--green); color: #000; border-color: var(--green); }
        .empty-msg { padding: 60px; text-align: center; color: var(--text-dim); font-size: 16px; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 30px; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; font-family: \'JetBrains Mono\', monospace; font-size: 13px; color: #000; line-height: 1.6; }
        .modal-content h3 { color: #000; margin-bottom: 16px; font-family: \'Syne\', sans-serif; font-size: 1.1em; }
        .modal-close { position: absolute; top: 12px; right: 12px; background: var(--red); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 14px; line-height: 28px; text-align: center; }
        .modal-close:hover { background: #c62828; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        tr.row-deleted { opacity: 0.6; }
        tr.row-deleted td { color: #ff3333 !important; text-decoration: none; }
        tr.row-deleted:hover { opacity: 0.85; background: rgba(239, 83, 80, 0.05); }
        .location-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; letter-spacing: 0.3px; }
        .loc-live { background: rgba(76, 175, 80, 0.1); color: var(--green); border: 1px solid rgba(76, 175, 80, 0.25); }
        .loc-deleted { background: rgba(239, 83, 80, 0.1); color: var(--red); border: 1px solid rgba(239, 83, 80, 0.25); }
        .deleted-time { font-size: 10px; color: #888; margin-top: 3px; font-family: \'JetBrains Mono\', monospace; }
        .auth-bar { background: #0c0c0c; border-bottom: 2px solid #00ff00; padding: 8px 20px; font-family: monospace; display: flex; justify-content: space-between; align-items: center; color: #ccc; font-size: 13px; position: sticky; top: 0; z-index: 99999; }
    </style>
</head>
<body>
' . $authBarHtml . '
    <div class="page-header">
        <a href="' . $cashierLink . '" class="back-btn">← Back to Cashier</a>
        <h1>📜 All Order History</h1>
        <div class="header-controls">
            <input type="text" id="searchInput" placeholder="🔍 Search by name, address, order no..." />
            <select id="filterType">
                <option value="all">All Orders</option>
                <option value="active">Live Only</option>
                <option value="deleted">🗑 Deleted Only</option>
                <option value="cash">Cash Only</option>
                <option value="aba">ABA Only</option>
                <option value="checked">Checked</option>
                <option value="unchecked">Unchecked</option>
            </select>
            <button class="btn-export" onclick="exportHistory()">💾 Export CSV</button>
        </div>
    </div>

    <div class="stats-bar" id="statsBar">
        <div class="stat-box"><span class="stat-label">Total Orders</span><span class="stat-value" id="statTotal">0</span></div>
        <div class="stat-box"><span class="stat-label">💵 Cash (Riel)</span><span class="stat-value green" id="statCash">0</span></div>
        <div class="stat-box"><span class="stat-label">💳 ABA (Riel)</span><span class="stat-value red" id="statAba">0</span></div>
        <div class="stat-box"><span class="stat-label">Grand Total</span><span class="stat-value yellow" id="statGrand">0</span></div>
        <div class="stat-box"><span class="stat-label">🗑 Deleted</span><span class="stat-value" style="color:#888" id="statDeleted">0</span></div>
    </div>

    <div class="table-wrapper">
        <table id="historyTable">
            <thead>
                <tr><th>#</th><th>Order No</th><th>Customer</th><th>Address</th><th>Notes</th><th>Total (Riel)</th><th>Payment</th><th>Status</th><th>Location</th><th>Bill</th></tr>
            </thead>
            <tbody id="historyBody"></tbody>
        </table>
        <div id="emptyMsg" class="empty-msg" style="display:none;">No order history found.</div>
    </div>

    <div id="billModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeBillModal()">✕</button>
            <h3>📄 Order Receipt</h3>
            <div id="modalBillContent"></div>
        </div>
    </div>

    <script>
        const __dbItems = ' . json_encode($dbItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';

        let allItems = [];
        let filtered = [];

        async function postAuditLog(actionType, detailDescription) {
            try {
                await fetch("' . $historyLink . '&action=history_action_log", {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify({ action: actionType, details: detailDescription })
                });
            } catch (e) {
                console.warn("Failed to stream telemetry audit to server:", e);
            }
        }

        document.addEventListener(\'DOMContentLoaded\', () => {
            loadHistory();
            document.getElementById(\'searchInput\').addEventListener(\'input\', applyFilters);
            document.getElementById(\'filterType\').addEventListener(\'change\', applyFilters);
        });

        function loadHistory() {
            allItems = [];
            const raw = localStorage.getItem(\'appData\');
            if (raw) {
                let parsed;
                try { parsed = JSON.parse(raw); } catch { parsed = null; }
                if (parsed) {
                    const sources = [
                        { html: parsed.history,  label: \'Pending\'  },
                        { html: parsed.finished, label: \'Finished\' },
                        { html: parsed.thorn,    label: null },
                        { html: parsed.dom,      label: null },
                        { html: parsed.pozzal,   label: null },
                        { html: parsed.etc,      label: null },
                        { html: parsed.extra,    label: null },
                    ];
                    sources.forEach(({ html, label }) => {
                        if (!html) return;
                        const wrapper = document.createElement(\'div\');
                        wrapper.innerHTML = html;
                        wrapper.querySelectorAll(\'.history-item\').forEach(item => {
                            allItems.push(parseItemFromEl(item, label));
                        });
                    });
                }
            }
            const deletedRaw = localStorage.getItem(\'deletedOrders\');
            if (deletedRaw) {
                let deletedList;
                try { deletedList = JSON.parse(deletedRaw); } catch { deletedList = []; }
                deletedList.forEach(d => {
                    allItems.push({
                        orderNo: d.orderNo || \'—\', name: d.customerName || \'N/A\', address: d.customerAddress || \'N/A\',
                        notes: d.notes || \'\', total: parseFloat(d.total) || 0, isAba: !!d.isAba,
                        isChecked: !!d.isChecked, htmlContent: d.htmlContent || \'\', plainText: d.plainText || \'\',
                        location: \'🗑 Deleted (\' + (d.location || \'?\') + \')\', deletedAt: d.deletedAt || null, isDeleted: true
                    });
                });
            }
            if (allItems.length === 0 && __dbItems.length > 0) {
                allItems = __dbItems.map(d => ({...d, total: parseFloat(d.total) || 0}));
            }
            if (allItems.length === 0) { showEmpty(); return; }
            filtered = [...allItems];
            renderTable(filtered);
            renderStats(filtered);
        }

        function parseItemFromEl(el, forcedLabel) {
            let location = forcedLabel;
            if (!location) {
                const h4 = el.parentElement ? el.parentElement.querySelector(\'h4\') : null;
                location = h4 ? h4.innerText : \'Delivery\';
            }
            return {
                orderNo: el.dataset.orderNo || \'—\', name: el.dataset.customerName || \'N/A\',
                address: el.dataset.customerAddress || \'N/A\', notes: el.dataset.notes || \'\',
                total: parseFloat(el.dataset.total) || 0, isAba: el.classList.contains(\'archived\'),
                isChecked: el.classList.contains(\'checked\'), htmlContent: el.dataset.htmlContent || \'\',
                plainText: el.dataset.plainText || \'\', location, deletedAt: null, isDeleted: false
            };
        }

        function renderTable(items) {
            const tbody = document.getElementById(\'historyBody\');
            const emptyMsg = document.getElementById(\'emptyMsg\');
            if (items.length === 0) { tbody.innerHTML = \'\'; emptyMsg.style.display = \'block\'; return; }
            emptyMsg.style.display = \'none\';
            tbody.innerHTML = items.map((it, i) => `
                <tr class="${it.isDeleted ? \'row-deleted\' : \'\'}">
                    <td>${i + 1}</td>
                    <td><strong>#${escHtml(it.orderNo)}</strong></td>
                    <td>${escHtml(it.name)}</td>
                    <td>${escHtml(it.address)}</td>
                    <td>${escHtml(it.notes) || \'<span style="color:#555">—</span>\'}</td>
                    <td class="td-total">${it.total.toLocaleString(\'id-ID\')}</td>
                    <td><span class="badge ${it.isAba ? \'badge-aba\' : \'badge-cash\'}">${it.isAba ? \'💳 ABA\' : \'💵 CASH\'}</span></td>
                    <td><span class="badge ${it.isChecked ? \'badge-check\' : \'badge-pending\'}">${it.isChecked ? \'✅ Done\' : \'⏳ Open\'}</span></td>
                    <td>
                        <span class="location-tag ${it.isDeleted ? \'loc-deleted\' : \'loc-live\'}">${escHtml(it.location)}</span>
                        ${it.deletedAt ? `<div class="deleted-time">${formatDate(it.deletedAt)}</div>` : \'\'}
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                            <button class="btn-view" onclick="viewBill(${i})">👁 View</button>
                            <button class="btn-view" style="background:#00bcff;border-color:#0099d6;font-size:11px;padding:3px 8px;" onclick="traceAdminAction(\'${it.orderNo}\')">🔍 Trace</button>
                        </div>
                    </td>
                </tr>
            `).join(\'\');
        }

        async function traceAdminAction(orderNo) {
            if (!orderNo || orderNo === \'—\') { alert("Cannot trace an item without a valid order number."); return; }
            try {
                const res = await fetch(`' . $historyLink . '&action=trace_order&order_no=${encodeURIComponent(orderNo)}`);
                if (!res.ok) throw new Error("Server error");
                const logs = await res.json();
                if (logs.length === 0) {
                    alert(`No server-side deletion logs found for Order #${orderNo}.\\n(It might have been cleared locally within the browser memory)`);
                    return;
                }
                let logReport = `📋 AUDIT TRACE FOR ORDER #${orderNo}\\n-------------------------------------------\\n`;
                logs.forEach(log => {
                    logReport += `⏰ Time: ${log.timestamp}\\n👤 User: ${log.username}\\n⚡ Action: ${log.action}\\n📝 Context: ${log.details}\\n\\n`;
                });
                alert(logReport);
            } catch (err) {
                alert("Failed to pull audit trace history from the database.");
            }
        }

        function renderStats(items) {
            let cash = 0, aba = 0, deletedCount = 0;
            items.forEach(it => {
                if (it.isDeleted) { deletedCount++; return; }
                if (it.isChecked) return;
                if (it.isAba) aba += it.total; else cash += it.total;
            });
            document.getElementById(\'statTotal\').textContent = items.length;
            document.getElementById(\'statCash\').textContent = cash.toLocaleString(\'id-ID\');
            document.getElementById(\'statAba\').textContent = aba.toLocaleString(\'id-ID\');
            document.getElementById(\'statGrand\').textContent = (cash + aba).toLocaleString(\'id-ID\');
            const deletedEl = document.getElementById(\'statDeleted\');
            if (deletedEl) deletedEl.textContent = deletedCount;
        }

        function applyFilters() {
            const query = document.getElementById(\'searchInput\').value.trim().toLowerCase();
            const fType = document.getElementById(\'filterType\').value;
            filtered = allItems.filter(it => {
                if (fType === \'cash\' && it.isAba) return false;
                if (fType === \'aba\' && !it.isAba) return false;
                if (fType === \'checked\' && !it.isChecked) return false;
                if (fType === \'unchecked\' && it.isChecked) return false;
                if (fType === \'deleted\' && !it.isDeleted) return false;
                if (fType === \'active\' && it.isDeleted) return false;
                if (query) {
                    const haystack = `${it.orderNo} ${it.name} ${it.address} ${it.notes} ${it.location}`.toLowerCase();
                    if (!haystack.includes(query)) return false;
                }
                return true;
            });
            renderTable(filtered);
            renderStats(filtered);
        }

        function viewBill(index) {
            const it = filtered[index];
            if (!it) return;
            postAuditLog("VIEW_BILL", `Looked at Order #${it.orderNo} (${it.name}) total: ${it.total.toLocaleString(\'id-ID\')} Riel`);
            const modal = document.getElementById(\'billModal\');
            const content = document.getElementById(\'modalBillContent\');
            if (it.htmlContent) content.innerHTML = it.htmlContent;
            else if (it.plainText) content.innerHTML = `<pre>${escHtml(it.plainText)}</pre>`;
            else content.innerHTML = \'<p style="color:#888">No receipt data available.</p>\';
            modal.style.display = \'flex\';
        }

        function closeBillModal() {
            document.getElementById(\'billModal\').style.display = \'none\';
        }

        document.addEventListener(\'click\', e => {
            const modal = document.getElementById(\'billModal\');
            if (e.target === modal) closeBillModal();
        });
        document.addEventListener(\'keydown\', e => {
            if (e.key === \'Escape\') closeBillModal();
        });

        function csvEscape(val) {
            const str = String(val ?? \'\').replace(/"/g, \'""\');
            return `"${str}"`;
        }

        function exportHistory() {
            if (filtered.length === 0) { alert(\'Nothing to export!\'); return; }
            postAuditLog("CSV_EXPORT", `Exported history datasheet containing ${filtered.length} total raw row entries.`);
            const now = new Date();
            const pad = n => String(n).padStart(2, \'0\');
            const datetime = `${pad(now.getDate())}-${pad(now.getMonth()+1)}-${now.getFullYear()}_${pad(now.getHours())}-${pad(now.getMinutes())}`;
            const headers = [\'No\',\'Order No\',\'Customer\',\'Address\',\'Notes\',\'Total (Riel)\',\'Payment\',\'Status\',\'Location\',\'Deleted At\'];
            let rows = [headers.map(csvEscape).join(\',\')];
            let cash = 0, aba = 0;
            filtered.forEach((it, i) => {
                const payment = it.isAba ? \'ABA\' : \'CASH\';
                const status = it.isChecked ? \'DONE\' : \'OPEN\';
                const delAt = it.deletedAt ? formatDate(it.deletedAt) : \'\';
                rows.push([csvEscape(i+1), csvEscape(\'#\'+it.orderNo), csvEscape(it.name), csvEscape(it.address), csvEscape(it.notes), csvEscape(it.total.toLocaleString(\'id-ID\')), csvEscape(payment), csvEscape(status), csvEscape(it.location), csvEscape(delAt)].join(\',\'));
                if (!it.isDeleted && !it.isChecked) { if (it.isAba) aba += it.total; else cash += it.total; }
            });
            rows.push(\'\'); rows.push([csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'Grand Total CASH\'),csvEscape(cash.toLocaleString(\'id-ID\')),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\')].join(\',\'));
            rows.push([csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'Grand Total ABA\'),csvEscape(aba.toLocaleString(\'id-ID\')),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\')].join(\',\'));
            rows.push([csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'GRAND TOTAL\'),csvEscape((cash+aba).toLocaleString(\'id-ID\')),csvEscape(\'\'),csvEscape(\'\'),csvEscape(\'\')].join(\',\'));
            const BOM = \'\\uFEFF\';
            const blob = new Blob([BOM + rows.join(\'\\n\')], { type: \'text/csv;charset=utf-8;\' });
            const a = document.createElement(\'a\');
            a.href = URL.createObjectURL(blob);
            a.download = `AllHistory_${datetime}.csv`;
            a.click();
        }

        function escHtml(str) {
            return String(str).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\');
        }
        function formatDate(iso) {
            if (!iso) return \'\';
            const d = new Date(iso);
            const pad = n => String(n).padStart(2,\'0\');
            return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }
        function showEmpty() {
            document.getElementById(\'historyBody\').innerHTML = \'\';
            document.getElementById(\'emptyMsg\').style.display = \'block\';
            [\'statTotal\',\'statCash\',\'statAba\',\'statGrand\'].forEach(id => { document.getElementById(id).textContent = \'0\'; });
        }
    </script>
</body>
</html>';
    exit;
}

// ── PAGE: STOCK ────────────────────────────────────────────────────────────
if ($page === 'stock') {
    // API for stock
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            if (!empty($input['id'])) {
                $stmt = $pdo->prepare("UPDATE stock_items SET name=?, category=?, quantity=?, unit=?, min_level=?, price=?, supplier=?, notes=? WHERE id=?");
                $stmt->execute([$input['name'], $input['category'], $input['quantity'], $input['unit'], $input['min_level'], $input['price'], $input['supplier'], $input['notes'], $input['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO stock_items (name, category, quantity, unit, min_level, price, supplier, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$input['name'], $input['category'], $input['quantity'], $input['unit'], $input['min_level'], $input['price'], $input['supplier'], $input['notes']]);
            }
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
            $stmt->execute([$input['id']]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'list') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->query("SELECT * FROM stock_items ORDER BY category, name");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }

    // Load stock data
    $stockItems = [];
    $categories = [];
    try {
        $stmt = $pdo->query("SELECT * FROM stock_items ORDER BY category, name");
        $stockItems = $stmt->fetchAll();
        $catStmt = $pdo->query("SELECT DISTINCT category FROM stock_items WHERE category IS NOT NULL AND category != ''");
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $stockItems = [];
        $categories = [];
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - ASENG</title>
    <style>
        @import url(\'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;700;800&display=swap\');
        :root { --green: #4CAF50; --green-dim: #2e7d32; --bg: #111111; --card: #161616; --card2: #1e1e1e; --border: #2a2a2a; --text: #ffffff; --text-dim: #888888; --red: #ef5350; --orange: #FF9800; --yellow: #FFC107; --blue: #00bcff; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: \'Syne\', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 20px; gap: 16px; display: flex; flex-direction: column; }
        .auth-bar { background: #0c0c0c; border-bottom: 2px solid #00ff00; padding: 10px 20px; font-family: monospace; display: flex; justify-content: space-between; align-items: center; color: #ccc; font-size: 13px; position: sticky; top: 0; z-index: 99999; }
        .auth-bar a { color: #00ff00; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .auth-bar a:hover { text-decoration: underline; }
        .auth-bar .logout { background: #ff3333; color: #fff; padding: 4px 14px; text-decoration: none; border-radius: 2px; font-size: 12px; font-weight: bold; }
        .page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: var(--card); padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); margin-top: 16px; }
        .page-header h1 { font-size: 1.4em; color: var(--green); flex: 1; }
        .back-btn { background: var(--card2); color: var(--text); border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-family: \'Syne\', sans-serif; font-size: 14px; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .back-btn:hover { background: var(--green); color: #000; border-color: var(--green); }
        .btn { background: var(--green); color: #000; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-family: \'Syne\', sans-serif; font-size: 14px; font-weight: 700; transition: all 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-red { background: var(--red); color: #fff; }
        .btn-blue { background: var(--blue); color: #fff; }
        .stats-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
        .stat-box { flex: 1; min-width: 140px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; text-align: center; }
        .stat-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-value { font-family: \'JetBrains Mono\', monospace; font-size: 1.3em; font-weight: 700; color: var(--text); }
        .stat-value.green { color: var(--green); } .stat-value.red { color: var(--red); } .stat-value.yellow { color: var(--yellow); } .stat-value.blue { color: var(--blue); }
        .table-wrapper { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: auto; flex: 1; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead tr { background: var(--card2); border-bottom: 2px solid var(--border); }
        th { padding: 12px 14px; text-align: left; color: var(--green); font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        tbody tr:hover { background: var(--card2); }
        tbody tr.low-stock { background: rgba(239, 83, 80, 0.08); }
        td { padding: 11px 14px; vertical-align: middle; color: var(--text); font-size: 13px; }
        td:first-child { color: var(--text-dim); font-size: 11px; font-family: \'JetBrains Mono\', monospace; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-ok { background: rgba(76,175,80,0.15); color: var(--green); }
        .badge-low { background: rgba(239,83,80,0.15); color: var(--red); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-top: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-group label { font-size: 12px; color: var(--text-dim); text-transform: uppercase; }
        .form-group input, .form-group select { background: var(--card2); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-family: \'Syne\', sans-serif; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: var(--green); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 30px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { color: var(--green); font-size: 1.2em; }
        .modal-close { background: var(--red); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 14px; }
        .actions { display: flex; gap: 8px; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .empty-msg { padding: 60px; text-align: center; color: var(--text-dim); font-size: 16px; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }
    </style>
</head>
<body>
' . $authBarHtml . '
    <div class="page-header">
        <a href="' . $cashierLink . '" class="back-btn">← Back</a>
        <h1>📦 Stock Management</h1>
        <button class="btn" onclick="openModal()">+ Add Item</button>
    </div>

    <div class="stats-bar">
        <div class="stat-box">
            <span class="stat-label">Total Items</span>
            <span class="stat-value" id="statTotal">0</span>
        </div>
        <div class="stat-box">
            <span class="stat-label">Categories</span>
            <span class="stat-value blue" id="statCategories">0</span>
        </div>
        <div class="stat-box">
            <span class="stat-label">Low Stock</span>
            <span class="stat-value red" id="statLow">0</span>
        </div>
        <div class="stat-box">
            <span class="stat-label">Total Value</span>
            <span class="stat-value yellow" id="statValue">0</span>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="stockTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Min Level</th>
                    <th>Status</th>
                    <th>Price</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="stockBody">
                <!-- JS renders rows -->
            </tbody>
        </table>
        <div id="emptyMsg" class="empty-msg" style="display:none;">No stock items found.</div>
    </div>

    <!-- Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Stock Item</h2>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <form id="itemForm" onsubmit="return saveItem(event)">
                <input type="hidden" id="itemId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" id="itemName" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" id="itemCategory" list="categoryList">
                        <datalist id="categoryList">
                            ' . implode('', array_map(function($cat) { return '<option value="' . htmlspecialchars($cat) . '">'; }, $categories)) . '
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" id="itemQuantity" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" id="itemUnit" value="pcs">
                    </div>
                    <div class="form-group">
                        <label>Min Level</label>
                        <input type="number" id="itemMinLevel" min="0" value="10">
                    </div>
                    <div class="form-group">
                        <label>Price (Riel)</label>
                        <input type="number" id="itemPrice" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="text" id="itemSupplier">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Notes</label>
                        <input type="text" id="itemNotes">
                    </div>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-red" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn">Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let items = ' . json_encode($stockItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';
        let editingId = null;

        function renderTable() {
            const tbody = document.getElementById(\'stockBody\');
            const emptyMsg = document.getElementById(\'emptyMsg\');
            
            if (items.length === 0) {
                tbody.innerHTML = \'\';
                emptyMsg.style.display = \'block\';
                updateStats();
                return;
            }
            emptyMsg.style.display = \'none\';
            
            tbody.innerHTML = items.map((it, i) => {
                const isLow = parseInt(it.quantity) <= parseInt(it.min_level);
                return `<tr class="${isLow ? \'low-stock\' : \'\'}">
                    <td>${i + 1}</td>
                    <td><strong>${escHtml(it.name)}</strong></td>
                    <td>${escHtml(it.category || \'—\')}</td>
                    <td>${it.quantity}</td>
                    <td>${escHtml(it.unit || \'pcs\')}</td>
                    <td>${it.min_level}</td>
                    <td><span class="badge ${isLow ? \'badge-low\' : \'badge-ok\'}">${isLow ? \'⚠️ LOW\' : \'✅ OK\'}</span></td>
                    <td>${parseInt(it.price).toLocaleString(\'id-ID\')}</td>
                    <td>${escHtml(it.supplier || \'—\')}</td>
                    <td class="actions">
                        <button class="btn btn-blue btn-sm" onclick="editItem(${it.id})">✎</button>
                        <button class="btn btn-red btn-sm" onclick="deleteItem(${it.id})">🗑</button>
                    </td>
                </tr>`;
            }).join(\'\');
            
            updateStats();
        }

        function updateStats() {
            const total = items.length;
            const categories = new Set(items.map(i => i.category).filter(Boolean)).size;
            const low = items.filter(i => parseInt(i.quantity) <= parseInt(i.min_level)).length;
            const value = items.reduce((sum, i) => sum + (parseInt(i.quantity) * parseInt(i.price)), 0);
            
            document.getElementById(\'statTotal\').textContent = total;
            document.getElementById(\'statCategories\').textContent = categories;
            document.getElementById(\'statLow\').textContent = low;
            document.getElementById(\'statValue\').textContent = value.toLocaleString(\'id-ID\');
        }

        function openModal(id = null) {
            editingId = id;
            document.getElementById(\'modalTitle\').textContent = id ? \'Edit Stock Item\' : \'Add Stock Item\';
            document.getElementById(\'itemId\').value = id || \'\';
            
            if (id) {
                const item = items.find(i => i.id == id);
                if (item) {
                    document.getElementById(\'itemName\').value = item.name;
                    document.getElementById(\'itemCategory\').value = item.category || \'\';
                    document.getElementById(\'itemQuantity\').value = item.quantity;
                    document.getElementById(\'itemUnit\').value = item.unit || \'pcs\';
                    document.getElementById(\'itemMinLevel\').value = item.min_level;
                    document.getElementById(\'itemPrice\').value = item.price;
                    document.getElementById(\'itemSupplier\').value = item.supplier || \'\';
                    document.getElementById(\'itemNotes\').value = item.notes || \'\';
                }
            } else {
                document.getElementById(\'itemForm\').reset();
            }
            
            document.getElementById(\'itemModal\').style.display = \'flex\';
        }

        function closeModal() {
            document.getElementById(\'itemModal\').style.display = \'none\';
            editingId = null;
        }

        async function saveItem(e) {
            e.preventDefault();
            const data = {
                id: document.getElementById(\'itemId\').value || null,
                name: document.getElementById(\'itemName\').value,
                category: document.getElementById(\'itemCategory\').value,
                quantity: parseInt(document.getElementById(\'itemQuantity\').value) || 0,
                unit: document.getElementById(\'itemUnit\').value || \'pcs\',
                min_level: parseInt(document.getElementById(\'itemMinLevel\').value) || 0,
                price: parseInt(document.getElementById(\'itemPrice\').value) || 0,
                supplier: document.getElementById(\'itemSupplier\').value,
                notes: document.getElementById(\'itemNotes\').value
            };
            
            try {
                const res = await fetch(\'' . $stockLink . '&action=save\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.status === \'ok\') {
                    location.reload();
                } else {
                    alert(\'Error: \' + (result.error || \'Unknown\'));
                }
            } catch (err) {
                alert(\'Failed to save item\');
            }
        }

        async function deleteItem(id) {
            if (!confirm(\'Delete this item?\')) return;
            try {
                const res = await fetch(\'' . $stockLink . '&action=delete\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify({ id })
                });
                const result = await res.json();
                if (result.status === \'ok\') {
                    location.reload();
                }
            } catch (err) {
                alert(\'Failed to delete item\');
            }
        }

        function editItem(id) {
            openModal(id);
        }

        function escHtml(str) {
            return String(str || \'\').replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\');
        }

        document.addEventListener(\'DOMContentLoaded\', renderTable);
        document.addEventListener(\'click\', e => {
            if (e.target === document.getElementById(\'itemModal\')) closeModal();
        });
    </script>
</body>
</html>';
    exit;
}

// ── DEFAULT: CASHIER (load original index.html + fixes + auth bar) ─────────
$htmlFile = __DIR__ . '/index.html';
if (!file_exists($htmlFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html>
<html><body style="background:#0c0c0c;color:#ff3333;font-family:monospace;padding:40px;text-align:center;">
Error: <b>index.html</b> not found in this folder.<br>Upload your original cashier app\'s index.html here (along with any static/ folder if present).
</body></html>');
}

$content = file_get_contents($htmlFile);

// ── Fix paths and cache bust ───────
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$content = str_replace('localhost:8081', $currentHost, $content);

if ($basePath !== '') {
    $escBase = preg_quote($basePath, '/');
    $content = preg_replace('/(href\s*=\s*["\'])\/(?!\/)(?!' . $escBase . '\/)/i', '$1' . $basePath . '/', $content);
    $content = preg_replace('/(src\s*=\s*["\'])\/(?!\/)(?!' . $escBase . '\/)/i',  '$1' . $basePath . '/', $content);
    $content = preg_replace('/(url\s*\(\s*["\']?)\\/(?!\/)(?!' . $escBase . '\/)/i','$1' . $basePath . '/', $content);
}

// Fix history and stock buttons/links in the original app to point to this combined file with query
$content = str_replace('history-folder/', '?page=history', $content);
$content = str_replace('./history-folder', $historyLink, $content);
$content = str_replace('href="./history-folder/"', 'href="' . $historyLink . '"', $content);
$content = str_replace("href='./history-folder/'", "href='" . $historyLink . "'", $content);

$content = str_replace('stock-folder/', '?page=stock', $content);
$content = str_replace('./stock-folder', $stockLink, $content);
$content = str_replace('href="./stock-folder/"', 'href="' . $stockLink . '"', $content);
$content = str_replace("href='./stock-folder/'", "href='" . $stockLink . "'", $content);

// Convert absolute static paths
$content = str_replace('src="/static/', 'src="./static/', $content);
$content = str_replace('href="/static/', 'href="./static/', $content);

// Strong cache bust for script
$content = preg_replace('/(src=["\'].*?\/script\.js)(["\'])/', '$1?v=20250605$2', $content);

// Inject auth bar (the common one)
$bodyPos = stripos($content, '<body');
if ($bodyPos !== false) {
    $gtPos = strpos($content, '>', $bodyPos);
    if ($gtPos !== false) {
        $content = substr_replace($content, $authBarHtml, $gtPos + 1, 0);
    } else {
        $content = $authBarHtml . PHP_EOL . $content;
    }
} else {
    $content = $authBarHtml . PHP_EOL . $content;
}

// Final init script for the cashier app
$finalInit = '<script>
window.addEventListener("load", function() {
    console.log("✅ Page fully loaded. Checking main script...");

    if (typeof addMenuRow === "function") {
        console.log("✅ Main script loaded successfully!");
        
        if (typeof initMenuInput === "function") initMenuInput();
        if (typeof updateTimeAndOrder === "function") updateTimeAndOrder();

        const addBtn = document.getElementById("addMenuBtn");
        if (addBtn) {
            addBtn.onclick = function() { addMenuRow(); };
        }
    } else {
        console.error("[FATAL] addMenuRow is missing even after full load.");
        alert("Application script failed to load properly.\\n\\nPlease hard-refresh (Ctrl + Shift + R)");
    }
});
</script>';

$bodyEnd = stripos($content, "</body>");
if ($bodyEnd !== false) {
    $content = substr_replace($content, $finalInit, $bodyEnd, 0);
} else {
    $content .= $finalInit;
}

// Serve
header('Content-Type: text/html; charset=utf-8');
echo $content;
?>