<?php
// ============================================================
// HISTORY PAGE - AUTO-RUN MODE (DB inside folder)
// ============================================================

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

// ── Auto-create audit_logs table if missing ─────────────────
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            order_no    TEXT,
            username    TEXT,
            action      TEXT,
            details     TEXT,
            ip_address  TEXT,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_order_no ON audit_logs (order_no)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_created  ON audit_logs (created_at DESC)");
    }
} catch (Exception $e) {
    // silently fail if DB not reachable
}

// ── API: POST ?action=history_action_log ───────────────────
if ($action === 'history_action_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $details = $input['details'] ?? '';
        preg_match('/Order #(\S+)/', $details, $m);
        $orderNo = $m[1] ?? null;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (order_no, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderNo,
            'auto-user',
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

// ── API: GET ?action=trace_order ─────────────────────────
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

// ── Load server-side data ─────────────────────────────────
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All History - TITTIL</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;700;800&display=swap');
        :root { --green: #4CAF50; --green-dim: #2e7d32; --bg: #111111; --card: #161616; --card2: #1e1e1e; --border: #2a2a2a; --text: #ffffff; --text-dim: #888888; --red: #ef5350; --orange: #FF9800; --blue: #f44336; --yellow: #FFC107; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; padding: 20px; gap: 16px; }
        .app-bar { background: #0c0c0c; border-bottom: 2px solid #00ff00; padding: 10px 20px; font-family: monospace; display: flex; justify-content: space-between; align-items: center; color: #ccc; font-size: 13px; position: sticky; top: 0; z-index: 99999; }
        .app-bar a { color: #00ff00; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: var(--card); padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); }
        .page-header h1 { font-size: 1.4em; color: var(--green); flex: 1; }
        .back-btn { background: var(--card2); color: var(--text); border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-family: 'Syne', sans-serif; font-size: 14px; transition: all 0.2s; }
        .back-btn:hover { background: var(--green); color: #000; border-color: var(--green); }
        .header-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .header-controls input, .header-controls select { background: var(--card2); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-family: 'Syne', sans-serif; font-size: 14px; outline: none; }
        .header-controls input { width: 280px; }
        .header-controls input:focus, .header-controls select:focus { border-color: var(--green); }
        .btn-export { background: #8e44ad; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; transition: all 0.2s; }
        .btn-export:hover { background: #7d3c98; transform: translateY(-1px); }
        .stats-bar { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 140px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; text-align: center; }
        .stat-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-value { font-family: 'JetBrains Mono', monospace; font-size: 1.3em; font-weight: 700; color: var(--text); }
        .stat-value.green { color: var(--green); } .stat-value.red { color: var(--red); } .stat-value.yellow { color: var(--yellow); }
        .table-wrapper { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: auto; flex: 1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead tr { background: var(--card2); border-bottom: 2px solid var(--border); }
        th { padding: 12px 14px; text-align: left; color: var(--green); font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        tbody tr:hover { background: var(--card2); }
        td { padding: 11px 14px; vertical-align: middle; color: var(--text); font-size: 13px; max-width: 200px; word-break: break-word; }
        td:first-child { color: var(--text-dim); font-size: 11px; font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
        .td-total { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--yellow); white-space: nowrap; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .badge-cash  { background: rgba(76,175,80,0.18); color: var(--green); border: 1px solid var(--green-dim); }
        .badge-aba   { background: rgba(239,83,80,0.18);  color: var(--red);   border: 1px solid var(--red); }
        .badge-check { background: rgba(76,175,80,0.12); color: var(--green); }
        .badge-pending { background: rgba(255,152,0,0.15); color: var(--orange); }
        .btn-view { background: var(--card2); color: var(--text-dim); border: 1px solid var(--border); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-family: 'Syne', sans-serif; transition: all 0.2s; white-space: nowrap; width: 70px; text-align: center; }
        .btn-view:hover { background: var(--green); color: #000; border-color: var(--green); }
        .empty-msg { padding: 60px; text-align: center; color: var(--text-dim); font-size: 16px; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 30px; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #000; line-height: 1.6; }
        .modal-content h3 { color: #000; margin-bottom: 16px; font-family: 'Syne', sans-serif; font-size: 1.1em; }
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
        .deleted-time { font-size: 10px; color: #888; margin-top: 3px; font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body>

    <div class="app-bar">
        <div>
            <span style="color:#00ff00;font-weight:bold;">⚡ TITTIL CASHIER</span>
            <span style="margin-left:12px;color:#888;">Auto-run mode | DB connected</span>
            <a href="./index.php">🏡 Cashier</a>
            <a href="./stock.php">📦 Stock</a>
        </div>
    </div>

    <div class="page-header">
        <button class="back-btn" onclick="window.location.href='./index.php'">← Back</button>
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
        const __dbItems = <?= json_encode($dbItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        let allItems = [];
        let filtered = [];

        async function postAuditLog(actionType, detailDescription) {
            try {
                await fetch('./history.php?action=history_action_log', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: actionType, details: detailDescription })
                });
            } catch (e) {
                console.warn("Audit log failed:", e);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadHistory();
            document.getElementById('searchInput').addEventListener('input', applyFilters);
            document.getElementById('filterType').addEventListener('change', applyFilters);
        });

        function loadHistory() {
            allItems = [];
            const raw = localStorage.getItem('appData');
            if (raw) {
                let parsed;
                try { parsed = JSON.parse(raw); } catch { parsed = null; }
                if (parsed) {
                    const sources = [
                        { html: parsed.history,  label: 'Pending'  },
                        { html: parsed.finished, label: 'Finished' },
                        { html: parsed.thorn,    label: null },
                        { html: parsed.dom,      label: null },
                        { html: parsed.pozzal,   label: null },
                        { html: parsed.etc,      label: null },
                        { html: parsed.extra,    label: null },
                    ];
                    sources.forEach(({ html, label }) => {
                        if (!html) return;
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = html;
                        wrapper.querySelectorAll('.history-item').forEach(item => {
                            allItems.push(parseItemFromEl(item, label));
                        });
                    });
                }
            }
            const deletedRaw = localStorage.getItem('deletedOrders');
            if (deletedRaw) {
                let deletedList;
                try { deletedList = JSON.parse(deletedRaw); } catch { deletedList = []; }
                deletedList.forEach(d => {
                    allItems.push({
                        orderNo: d.orderNo || '—', name: d.customerName || 'N/A', address: d.customerAddress || 'N/A',
                        notes: d.notes || '', total: parseFloat(d.total) || 0, isAba: !!d.isAba,
                        isChecked: !!d.isChecked, htmlContent: d.htmlContent || '', plainText: d.plainText || '',
                        location: '🗑 Deleted (' + (d.location || '?') + ')', deletedAt: d.deletedAt || null, isDeleted: true
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
                const h4 = el.parentElement ? el.parentElement.querySelector('h4') : null;
                location = h4 ? h4.innerText : 'Delivery';
            }
            return {
                orderNo: el.dataset.orderNo || '—', name: el.dataset.customerName || 'N/A',
                address: el.dataset.customerAddress || 'N/A', notes: el.dataset.notes || '',
                total: parseFloat(el.dataset.total) || 0, isAba: el.classList.contains('archived'),
                isChecked: el.classList.contains('checked'), htmlContent: el.dataset.htmlContent || '',
                plainText: el.dataset.plainText || '', location, deletedAt: null, isDeleted: false
            };
        }

        function renderTable(items) {
            const tbody = document.getElementById('historyBody');
            const emptyMsg = document.getElementById('emptyMsg');
            if (items.length === 0) { tbody.innerHTML = ''; emptyMsg.style.display = 'block'; return; }
            emptyMsg.style.display = 'none';
            tbody.innerHTML = items.map((it, i) => `
                <tr class="${it.isDeleted ? 'row-deleted' : ''}">
                    <td>${i + 1}</td>
                    <td><strong>#${escHtml(it.orderNo)}</strong></td>
                    <td>${escHtml(it.name)}</td>
                    <td>${escHtml(it.address)}</td>
                    <td>${escHtml(it.notes) || '<span style="color:#555">—</span>'}</td>
                    <td class="td-total">${it.total.toLocaleString('id-ID')}</td>
                    <td><span class="badge ${it.isAba ? 'badge-aba' : 'badge-cash'}">${it.isAba ? '💳 ABA' : '💵 CASH'}</span></td>
                    <td><span class="badge ${it.isChecked ? 'badge-check' : 'badge-pending'}">${it.isChecked ? '✅ Done' : '⏳ Open'}</span></td>
                    <td>
                        <span class="location-tag ${it.isDeleted ? 'loc-deleted' : 'loc-live'}">${escHtml(it.location)}</span>
                        ${it.deletedAt ? `<div class="deleted-time">${formatDate(it.deletedAt)}</div>` : ''}
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                            <button class="btn-view" onclick="viewBill(${i})">👁 View</button>
                            <button class="btn-view" style="background:#00bcff;border-color:#0099d6;font-size:11px;padding:3px 8px;" onclick="traceAdminAction('${it.orderNo}')">🔍 Trace</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        async function traceAdminAction(orderNo) {
            if (!orderNo || orderNo === '—') { alert("Cannot trace an item without a valid order number."); return; }
            try {
                const res = await fetch(`./history.php?action=trace_order&order_no=${encodeURIComponent(orderNo)}`);
                if (!res.ok) throw new Error("Server error");
                const logs = await res.json();
                if (logs.length === 0) {
                    alert(`No server-side deletion logs found for Order #${orderNo}.\n(It might have been cleared locally within the browser memory)`);
                    return;
                }
                let logReport = `📋 AUDIT TRACE FOR ORDER #${orderNo}\n-------------------------------------------\n`;
                logs.forEach(log => {
                    logReport += `⏰ Time: ${log.timestamp}\n👤 User: ${log.username}\n⚡ Action: ${log.action}\n📝 Context: ${log.details}\n\n`;
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
            document.getElementById('statTotal').textContent = items.length;
            document.getElementById('statCash').textContent = cash.toLocaleString('id-ID');
            document.getElementById('statAba').textContent = aba.toLocaleString('id-ID');
            document.getElementById('statGrand').textContent = (cash + aba).toLocaleString('id-ID');
            const deletedEl = document.getElementById('statDeleted');
            if (deletedEl) deletedEl.textContent = deletedCount;
        }

        function applyFilters() {
            const query = document.getElementById('searchInput').value.trim().toLowerCase();
            const fType = document.getElementById('filterType').value;
            filtered = allItems.filter(it => {
                if (fType === 'cash' && it.isAba) return false;
                if (fType === 'aba' && !it.isAba) return false;
                if (fType === 'checked' && !it.isChecked) return false;
                if (fType === 'unchecked' && it.isChecked) return false;
                if (fType === 'deleted' && !it.isDeleted) return false;
                if (fType === 'active' && it.isDeleted) return false;
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
            postAuditLog("VIEW_BILL", `Looked at Order #${it.orderNo} (${it.name}) total: ${it.total.toLocaleString('id-ID')} Riel`);
            const modal = document.getElementById('billModal');
            const content = document.getElementById('modalBillContent');
            if (it.htmlContent) content.innerHTML = it.htmlContent;
            else if (it.plainText) content.innerHTML = `<pre>${escHtml(it.plainText)}</pre>`;
            else content.innerHTML = '<p style="color:#888">No receipt data available.</p>';
            modal.style.display = 'flex';
        }

        function closeBillModal() {
            document.getElementById('billModal').style.display = 'none';
        }

        document.addEventListener('click', e => {
            const modal = document.getElementById('billModal');
            if (e.target === modal) closeBillModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeBillModal();
        });

        function csvEscape(val) {
            const str = String(val ?? '').replace(/"/g, '""');
            return `"${str}"`;
        }

        function exportHistory() {
            if (filtered.length === 0) { alert('Nothing to export!'); return; }
            postAuditLog("CSV_EXPORT", `Exported history datasheet containing ${filtered.length} total raw row entries.`);
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const datetime = `${pad(now.getDate())}-${pad(now.getMonth()+1)}-${now.getFullYear()}_${pad(now.getHours())}-${pad(now.getMinutes())}`;
            const headers = ['No','Order No','Customer','Address','Notes','Total (Riel)','Payment','Status','Location','Deleted At'];
            let rows = [headers.map(csvEscape).join(',')];
            let cash = 0, aba = 0;
            filtered.forEach((it, i) => {
                const payment = it.isAba ? 'ABA' : 'CASH';
                const status = it.isChecked ? 'DONE' : 'OPEN';
                const delAt = it.deletedAt ? formatDate(it.deletedAt) : '';
                rows.push([csvEscape(i+1), csvEscape('#'+it.orderNo), csvEscape(it.name), csvEscape(it.address), csvEscape(it.notes), csvEscape(it.total.toLocaleString('id-ID')), csvEscape(payment), csvEscape(status), csvEscape(it.location), csvEscape(delAt)].join(','));
                if (!it.isDeleted && !it.isChecked) { if (it.isAba) aba += it.total; else cash += it.total; }
            });
            rows.push(''); rows.push([csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape('Grand Total CASH'),csvEscape(cash.toLocaleString('id-ID')),csvEscape(''),csvEscape(''),csvEscape('')].join(','));
            rows.push([csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape('Grand Total ABA'),csvEscape(aba.toLocaleString('id-ID')),csvEscape(''),csvEscape(''),csvEscape('')].join(','));
            rows.push([csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape(''),csvEscape('GRAND TOTAL'),csvEscape((cash+aba).toLocaleString('id-ID')),csvEscape(''),csvEscape(''),csvEscape('')].join(','));
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `AllHistory_${datetime}.csv`;
            a.click();
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function formatDate(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            const pad = n => String(n).padStart(2,'0');
            return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }
        function showEmpty() {
            document.getElementById('historyBody').innerHTML = '';
            document.getElementById('emptyMsg').style.display = 'block';
            ['statTotal','statCash','statAba','statGrand'].forEach(id => { document.getElementById(id).textContent = '0'; });
        }
    </script>
</body>
</html>
