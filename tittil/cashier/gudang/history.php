<?php
session_start();
require_once '../db.php';   // ✅ correct path

if (empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'superadmin') {
    header('Location: index.php');
    exit;
}

// Create audit_logs table if missing (SQLite)
$dbFile = __DIR__ . '/database.db';
$dsn = "sqlite:$dbFile";
try {
    $auditDb = new PDO($dsn);
    $auditDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $auditDb->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no    TEXT,
        username    TEXT,
        action      TEXT,
        details     TEXT,
        ip_address  TEXT,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // ignore – will still work without audit
}

$action = $_GET['action'] ?? '';

// API endpoints (same as before)
if ($action === 'history_action_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $auditDb = new PDO($dsn);
        $input = json_decode(file_get_contents('php://input'), true);
        preg_match('/Order #(\S+)/', $input['details'] ?? '', $m);
        $orderNo = $m[1] ?? null;
        $stmt = $auditDb->prepare("INSERT INTO audit_logs (order_no, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$orderNo, 'admin', $input['action'] ?? 'UNKNOWN', $input['details'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
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
        $auditDb = new PDO($dsn);
        $stmt = $auditDb->prepare("SELECT created_at as timestamp, username, action, details FROM audit_logs WHERE order_no = ? ORDER BY created_at DESC");
        $stmt->execute([$orderNo]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $logs = [];
    }
    echo json_encode($logs);
    exit;
}

// Load from localStorage (JavaScript does the rendering) – no server‑side DB load needed
$dbItems = []; // keep empty – UI will load from localStorage
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All History - ASENG</title>
    <style>
        /* Your existing CSS – unchanged (keep as is) */
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;700;800&display=swap');
        :root { --green: #4CAF50; --green-dim: #2e7d32; --bg: #111111; --card: #161616; --card2: #1e1e1e; --border: #2a2a2a; --text: #ffffff; --text-dim: #888888; --red: #ef5350; --orange: #FF9800; --blue: #f44336; --yellow: #FFC107; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; padding: 20px; gap: 16px; }
        .page-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: var(--card); padding: 16px 20px; border-radius: 10px; border: 1px solid var(--border); }
        .page-header h1 { font-size: 1.4em; color: var(--green); flex: 1; }
        .back-btn { background: var(--card2); color: var(--text); border: 1px solid var(--border); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-family: 'Syne', sans-serif; font-size: 14px; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .back-btn:hover { background: var(--green); color: #000; border-color: var(--green); }
        .header-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .header-controls input, .header-controls select { background: var(--card2); color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-family: 'Syne', sans-serif; font-size: 14px; outline: none; }
        .btn-export { background: #8e44ad; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: 700; }
        .stats-bar { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 140px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; text-align: center; }
        .stat-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; }
        .stat-value { font-family: 'JetBrains Mono', monospace; font-size: 1.3em; font-weight: 700; }
        .stat-value.green { color: var(--green); } .stat-value.red { color: var(--red); } .stat-value.yellow { color: var(--yellow); }
        .table-wrapper { background: var(--card); border: 1px solid var(--border); border-radius: 10px; overflow: auto; flex: 1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--green); text-transform: uppercase; font-size: 12px; }
        tbody tr:hover { background: var(--card2); }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-cash { background: rgba(76,175,80,0.18); color: var(--green); }
        .badge-aba { background: rgba(239,83,80,0.18); color: var(--red); }
        .badge-check { background: rgba(76,175,80,0.12); color: var(--green); }
        .badge-pending { background: rgba(255,152,0,0.15); color: var(--orange); }
        .btn-view { background: var(--card2); color: var(--text-dim); border: 1px solid var(--border); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-view:hover { background: var(--green); color: #000; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: #fff; border-radius: 10px; padding: 30px; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; color: #000; }
        .modal-close { background: var(--red); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; float: right; }
        .empty-msg { padding: 60px; text-align: center; color: var(--text-dim); }
        tr.row-deleted { opacity: 0.6; }
        .location-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        .loc-live { background: rgba(76,175,80,0.1); color: var(--green); }
        .loc-deleted { background: rgba(239,83,80,0.1); color: var(--red); }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="./index.php" class="back-btn">← Back</a>
        <h1>📜 All Order History</h1>
        <div class="header-controls">
            <input type="text" id="searchInput" placeholder="🔍 Search...">
            <select id="filterType">
                <option value="all">All Orders</option>
                <option value="active">Live Only</option>
                <option value="deleted">Deleted Only</option>
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
        <div class="stat-box"><span class="stat-label">💵 Cash</span><span class="stat-value green" id="statCash">0</span></div>
        <div class="stat-box"><span class="stat-label">💳 ABA</span><span class="stat-value red" id="statAba">0</span></div>
        <div class="stat-box"><span class="stat-label">Grand Total</span><span class="stat-value yellow" id="statGrand">0</span></div>
        <div class="stat-box"><span class="stat-label">🗑 Deleted</span><span class="stat-value" id="statDeleted">0</span></div>
    </div>
    <div class="table-wrapper">
        <table id="historyTable"><thead><tr><th>#</th><th>Order No</th><th>Customer</th><th>Address</th><th>Notes</th><th>Total</th><th>Payment</th><th>Status</th><th>Location</th><th>Bill</th></tr></thead><tbody id="historyBody"></tbody></table>
        <div id="emptyMsg" class="empty-msg" style="display:none;">No order history found.</div>
    </div>
    <div id="billModal" class="modal"><div class="modal-content"><button class="modal-close" onclick="closeBillModal()">✕</button><h3>📄 Order Receipt</h3><div id="modalBillContent"></div></div></div>

    <script>
        // Load data from localStorage (same as before)
        let allItems = [], filtered = [];
        function loadFromLocalStorage() {
            allItems = [];
            const raw = localStorage.getItem('appData');
            if (raw) {
                let parsed; try { parsed = JSON.parse(raw); } catch(e) { parsed = null; }
                if (parsed) {
                    const containers = ['historyContainer', 'finishedContainer', 'thornContainer', 'domContainer', 'pozzalContainer', 'etcContainer', 'extraContainer'];
                    containers.forEach(containerId => {
                        const html = parsed[containerId];
                        if (html) {
                            const wrapper = document.createElement('div');
                            wrapper.innerHTML = html;
                            wrapper.querySelectorAll('.history-item').forEach(el => {
                                let location = containerId === 'historyContainer' ? 'Pending' : (containerId === 'finishedContainer' ? 'Finished' : (el.parentElement?.querySelector('h4')?.innerText || 'Delivery'));
                                allItems.push({
                                    orderNo: el.dataset.orderNo || '—',
                                    name: el.dataset.customerName || 'N/A',
                                    address: el.dataset.customerAddress || 'N/A',
                                    notes: el.dataset.notes || '',
                                    total: parseFloat(el.dataset.total) || 0,
                                    isAba: el.classList.contains('archived'),
                                    isChecked: el.classList.contains('checked'),
                                    htmlContent: el.dataset.htmlContent || '',
                                    plainText: el.dataset.plainText || '',
                                    location: location,
                                    isDeleted: false
                                });
                            });
                        }
                    });
                }
            }
            const deletedRaw = localStorage.getItem('deletedOrders');
            if (deletedRaw) {
                let deletedList; try { deletedList = JSON.parse(deletedRaw); } catch(e) { deletedList = []; }
                deletedList.forEach(d => {
                    allItems.push({
                        orderNo: d.orderNo || '—',
                        name: d.customerName || 'N/A',
                        address: d.customerAddress || 'N/A',
                        notes: d.notes || '',
                        total: parseFloat(d.total) || 0,
                        isAba: !!d.isAba,
                        isChecked: !!d.isChecked,
                        htmlContent: d.htmlContent || '',
                        plainText: d.plainText || '',
                        location: '🗑 Deleted (' + (d.location || '?') + ')',
                        deletedAt: d.deletedAt,
                        isDeleted: true
                    });
                });
            }
            if (allItems.length === 0) showEmpty();
            else applyFilters();
        }
        function showEmpty() { document.getElementById('historyBody').innerHTML = ''; document.getElementById('emptyMsg').style.display = 'block'; updateStats([]); }
        function updateStats(items) { let cash=0,aba=0,del=0; items.forEach(it=>{ if(it.isDeleted) del++; else if(!it.isChecked) it.isAba? aba+=it.total : cash+=it.total; }); document.getElementById('statTotal').textContent=items.length; document.getElementById('statCash').textContent=cash.toLocaleString('id-ID'); document.getElementById('statAba').textContent=aba.toLocaleString('id-ID'); document.getElementById('statGrand').textContent=(cash+aba).toLocaleString('id-ID'); document.getElementById('statDeleted').textContent=del; }
        function applyFilters() { const query=document.getElementById('searchInput').value.toLowerCase(); const fType=document.getElementById('filterType').value; filtered=allItems.filter(it=>{ if(fType==='cash' && it.isAba) return false; if(fType==='aba' && !it.isAba) return false; if(fType==='checked' && !it.isChecked) return false; if(fType==='unchecked' && it.isChecked) return false; if(fType==='deleted' && !it.isDeleted) return false; if(fType==='active' && it.isDeleted) return false; if(query && !`${it.orderNo} ${it.name} ${it.address} ${it.notes}`.toLowerCase().includes(query)) return false; return true; }); renderTable(filtered); updateStats(filtered); }
        function renderTable(items) { const tbody=document.getElementById('historyBody'); const emptyMsg=document.getElementById('emptyMsg'); if(items.length===0){ tbody.innerHTML=''; emptyMsg.style.display='block'; return; } emptyMsg.style.display='none'; tbody.innerHTML=items.map((it,i)=>`<tr class="${it.isDeleted?'row-deleted':''}"><td>${i+1}</td><td><strong>#${escapeHtml(it.orderNo)}</strong></td><td>${escapeHtml(it.name)}</td><td>${escapeHtml(it.address)}</td><td>${escapeHtml(it.notes)||'—'}</td><td class="td-total">${it.total.toLocaleString('id-ID')}</td><td><span class="badge ${it.isAba?'badge-aba':'badge-cash'}">${it.isAba?'💳 ABA':'💵 CASH'}</span></td><td><span class="badge ${it.isChecked?'badge-check':'badge-pending'}">${it.isChecked?'✅ Done':'⏳ Open'}</span></td><td><span class="location-tag ${it.isDeleted?'loc-deleted':'loc-live'}">${escapeHtml(it.location)}</span>${it.deletedAt?`<div class="deleted-time" style="font-size:10px;margin-top:3px;">${new Date(it.deletedAt).toLocaleString()}</div>`:''}</td><td><button class="btn-view" onclick="viewBill(${i})">👁 View</button></td></tr>`).join(''); }
        function viewBill(index){ const it=filtered[index]; if(!it) return; document.getElementById('modalBillContent').innerHTML=it.htmlContent||`<pre>${escapeHtml(it.plainText)}</pre>`; document.getElementById('billModal').style.display='flex'; }
        function closeBillModal(){ document.getElementById('billModal').style.display='none'; }
        function exportHistory(){ if(filtered.length===0) return alert('Nothing to export'); let rows=[['#','Order No','Customer','Address','Notes','Total','Payment','Status','Location','Deleted At'].map(c=>`"${c}"`).join(',')]; filtered.forEach((it,i)=>{ rows.push([i+1,it.orderNo,it.name,it.address,it.notes,it.total,it.isAba?'ABA':'CASH',it.isChecked?'DONE':'OPEN',it.location,it.deletedAt||''].map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')); }); const blob=new Blob(["\uFEFF"+rows.join('\n')],{type:'text/csv'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`history_${new Date().toISOString().slice(0,19)}.csv`; a.click(); }
        function escapeHtml(str){ return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        document.getElementById('searchInput').addEventListener('input',applyFilters);
        document.getElementById('filterType').addEventListener('change',applyFilters);
        window.addEventListener('click',e=>{ if(e.target===document.getElementById('billModal')) closeBillModal(); });
        loadFromLocalStorage();
    </script>
</body>
</html>