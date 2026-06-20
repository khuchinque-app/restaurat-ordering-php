<?php
$page_title = 'Live Chat';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';

$chat_tab = $_GET['tab'] ?? 'staff';
$restaurant = get_restaurant();
$rid = $restaurant['id'] ?? null;

// Customer chat conversations for this restaurant
$customer_conversations = [];
if ($rid) {
    try {
        $customer_conversations = db_query(
            'SELECT c.*, o.orderNumber
             FROM CustomerChat c
             LEFT JOIN "Order" o ON o.id = c.orderId
             WHERE c.restaurantId = ?
               AND c.id IN (SELECT MAX(id) FROM CustomerChat WHERE restaurantId = ? GROUP BY COALESCE(orderId, senderName))
             ORDER BY c.createdAt DESC LIMIT 50',
            [$rid, $rid]
        );
    } catch (Throwable $e) { /* table may not exist */ }
}
?>

<style>
.chat-layout { display:grid; grid-template-columns:280px 1fr; gap:0; height:calc(100vh - 180px); }
.chat-sidebar { border-right:1px solid #e2e8f0; display:flex; flex-direction:column; background:#fff; }
.chat-sidebar-header { padding:.75rem 1rem; border-bottom:1px solid #e2e8f0; }
.chat-sidebar-header .tabs { display:flex; gap:.3rem; }
.chat-sidebar-header .tab { padding:.35rem .75rem; border-radius:6px; border:none; background:transparent; cursor:pointer; font-size:.82rem; font-weight:600; color:#64748b; transition:all .15s; }
.chat-sidebar-header .tab.active { background:#059669; color:#fff; }
.chat-sidebar-header .tab:hover:not(.active) { background:#f0fdf4; }
.chat-list { flex:1; overflow-y:auto; }
.chat-list-item { padding:.75rem 1rem; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background .1s; }
.chat-list-item:hover { background:#f8fafc; }
.chat-list-item.active { background:#d1fae5; border-left:3px solid #059669; }
.chat-list-item .cli-name { font-weight:600; font-size:.875rem; }
.chat-list-item .cli-preview { font-size:.78rem; color:#64748b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-list-item .cli-time { font-size:.7rem; color:#94a3b8; }
.chat-main { display:flex; flex-direction:column; }
.chat-main-header { padding:.75rem 1rem; border-bottom:1px solid #e2e8f0; background:#fff; display:flex; align-items:center; gap:.5rem; }
.chat-main-header strong { font-size:.9rem; }
.chat-main-header span { font-size:.78rem; color:#94a3b8; }
.chat-messages { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:#f8fafc; }
.chat-msg { max-width:70%; }
.chat-msg.mine { align-self:flex-end; }
.chat-msg.theirs { align-self:flex-start; }
.chat-bubble { padding:.55rem .9rem; border-radius:12px; font-size:.9rem; line-height:1.4; word-break:break-word; }
.mine .chat-bubble { background:#059669; color:#fff; border-bottom-right-radius:3px; }
.theirs .chat-bubble { background:#fff; color:#1e293b; border:1px solid #e5e7eb; border-bottom-left-radius:3px; }
.chat-meta { font-size:.72rem; color:#94a3b8; margin-top:.2rem; }
.mine .chat-meta { text-align:right; }
.role-badge { display:inline-block; font-size:.65rem; font-weight:700; padding:.05rem .35rem; border-radius:4px; margin-right:.3rem; }
.role-SUPERADMIN { background:#ede9fe; color:#7c3aed; }
.role-ADMIN { background:#d1fae5; color:#059669; }
.role-MANAGER { background:#fef9c3; color:#92400e; }
.role-CUSTOMER { background:#dbeafe; color:#1d4ed8; }
.chat-form { display:flex; gap:.5rem; border:1px solid #e5e7eb; border-top:none; background:#fff; padding:.75rem; border-radius:0 0 8px 8px; }
.chat-form input { flex:1; padding:.55rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; }
.chat-form input:focus { outline:none; border-color:#059669; }
.chat-form button { padding:.55rem 1.25rem; background:#059669; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.9rem; transition:background .15s; }
.chat-form button:hover { background:#047857; }
.chat-status { font-size:.75rem; color:#94a3b8; padding:2rem; text-align:center; }
.chat-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94a3b8; }
.chat-empty .icon { font-size:3rem; margin-bottom:1rem; }
.presence-bar { display:flex; flex-wrap:wrap; gap:.35rem; padding:.5rem .75rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:.78rem; align-items:center; }
.presence-online { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .5rem; background:#dcfce7; border-radius:4px; color:#166534; }
.presence-offline { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .5rem; background:#f1f5f9; border-radius:4px; color:#64748b; }
.presence-dot { width:6px; height:6px; border-radius:50%; display:inline-block; }
.presence-dot.online { background:#10b981; }
.presence-dot.offline { background:#94a3b8; }
</style>

<div class="chat-layout">
<!-- Sidebar -->
<div class="chat-sidebar">
    <div class="chat-sidebar-header">
        <div class="tabs">
            <button class="tab <?= $chat_tab === 'staff' ? 'active' : '' ?>" onclick="location.href='chat.php?tab=staff'">👨‍💼 Staff</button>
            <button class="tab <?= $chat_tab === 'customers' ? 'active' : '' ?>" onclick="location.href='chat.php?tab=customers'">👤 Customers</button>
        </div>
    </div>
    <div class="chat-list">
        <?php if ($chat_tab === 'staff'): ?>
        <div class="chat-list-item active">
            <div class="cli-name">📢 Staff Channel</div>
            <div class="cli-preview">All staff messages</div>
        </div>
        <?php else: ?>
        <?php if (empty($customer_conversations)): ?>
        <div class="chat-status">No customer conversations yet.</div>
        <?php else: foreach ($customer_conversations as $conv): ?>
        <div class="chat-list-item" data-chat-id="<?= htmlspecialchars($conv['id'], ENT_QUOTES) ?>" data-chat-name="<?= htmlspecialchars($conv['senderName'] ?? 'Guest', ENT_QUOTES) ?>" data-chat-order="<?= htmlspecialchars($conv['orderNumber'] ?? '', ENT_QUOTES) ?>" onclick="showCustomerChat(this.dataset.chatId, this.dataset.chatName, this.dataset.chatOrder)">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div class="cli-name">👤 <?= htmlspecialchars($conv['senderName'] ?? 'Guest') ?></div>
                <div class="cli-time"><?= date('H:i', strtotime($conv['createdAt'])) ?></div>
            </div>
            <div class="cli-preview"><?= htmlspecialchars(mb_strimwidth($conv['message'] ?? '', 0, 40, '...')) ?></div>
            <?php if (!empty($conv['orderNumber'])): ?>
            <div style="font-size:.7rem;color:#059669">Order #<?= htmlspecialchars($conv['orderNumber']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Main Chat Area -->
<div class="chat-main">
    <?php if ($chat_tab === 'staff'): ?>
    <div class="chat-main-header">
        <strong>📢 Staff Channel</strong>
        <span>All staff members</span>
    </div>
    <!-- Presence Bar -->
    <div id="presenceContainer"></div>
    <div class="chat-messages" id="chatMessages">
        <div class="chat-status">Loading messages…</div>
    </div>
    <form class="chat-form" id="chatForm">
        <input type="text" id="chatInput" placeholder="Type a message… (Enter to send)" maxlength="1000" autocomplete="off">
        <button type="submit">Send</button>
    </form>
    <?php else: ?>
    <div class="chat-main-header" id="customerChatHeader">
        <strong>👤 Customer Chat</strong>
        <span>Select a conversation</span>
    </div>
    <div class="chat-messages" id="customerChatMessages">
        <div class="chat-empty">
            <div class="icon">💬</div>
            <div>Select a customer conversation from the left panel</div>
        </div>
    </div>
    <form class="chat-form" id="customerChatForm" style="display:none">
        <input type="text" id="customerChatInput" placeholder="Reply to customer…" maxlength="1000" autocomplete="off">
        <button type="submit">Send</button>
    </form>
    <?php endif; ?>
</div>
</div>

<script>
const ME_ID = '<?= htmlspecialchars($current_user['id']) ?>';
const CHAT_TAB = '<?= $chat_tab ?>';
const CHAT_TAB_LABEL = '<?= $chat_tab === "staff" ? "staff" : "customer" ?>';
let lastTimestamp = null;
let isAtBottom = true;

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function roleBadge(role) {
    const colors = {SUPERADMIN:'#7c3aed',ADMIN:'#059669',MANAGER:'#92400e',CUSTOMER:'#1d4ed8'};
    return `<span class="role-badge role-${role}">${role}</span>`;
}

if (CHAT_TAB === 'staff') {
    const box = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    box.addEventListener('scroll', () => { isAtBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40; });

    function addMsg(msg) {
        const mine = msg.senderId === ME_ID;
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (mine ? 'mine' : 'theirs');
        div.dataset.id = msg.id;
        const t = new Date(msg.createdAt+'Z').toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
        div.innerHTML = `<div class="chat-bubble">${escHtml(msg.message)}</div><div class="chat-meta">${mine?'':roleBadge(msg.senderRole)+' '+escHtml(msg.senderName)+' · '}${t}</div>`;
        box.appendChild(div);
    }

    async function poll(initial) {
        const url = '<?= APP_URL ?>/api/staff/chat.php' + (lastTimestamp ? '?since='+encodeURIComponent(lastTimestamp) : '?limit=100');
        try {
            const r = await fetch(url,{credentials:'include'}); const d = await r.json();
            if (!d.success) return;
            if (initial) { box.innerHTML = d.data.messages.length ? '' : '<div class="chat-status">No messages yet.</div>'; }
            d.data.messages.forEach(msg => {
                if (!document.querySelector(`[data-id="${msg.id}"]`)) {
                    const ph = box.querySelector('.chat-status'); if(ph) ph.remove();
                    addMsg(msg); lastTimestamp = msg.createdAt;
                }
            });
            if (isAtBottom||initial) box.scrollTop = box.scrollHeight;
        } catch(e) {}
    }

    form.addEventListener('submit', async e => {
        e.preventDefault(); const msg = input.value.trim(); if(!msg) return; input.value = '';
        try {
            const r = await fetch('<?= APP_URL ?>/api/staff/chat.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg})});
            const d = await r.json();
            if (d.success && !document.querySelector(`[data-id="${d.data.id}"]`)) {
                const ph = box.querySelector('.chat-status'); if(ph) ph.remove();
                addMsg(d.data); lastTimestamp = d.data.createdAt; isAtBottom=true; box.scrollTop=box.scrollHeight;
            }
        } catch(e) {}
    });
    input.addEventListener('keydown', e => { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.dispatchEvent(new Event('submit'));} });
    poll(true); setInterval(()=>poll(false), 5000);
}

// Customer chat
let activeCustomerChatId = null;
let activeCustomerName = '';
let lastCustomerTs = null;

function showCustomerChat(chatId, name, orderNum) {
    activeCustomerChatId = chatId;
    activeCustomerName = name;
    lastCustomerTs = null;
    const msgs = document.getElementById('customerChatMessages');
    msgs.innerHTML = '<div class="chat-status">Loading messages...</div>';
    document.getElementById('customerChatForm').style.display = '';
    const header = document.getElementById('customerChatHeader');
    header.querySelector('strong').textContent = '👤 ' + name;
    header.querySelector('span').textContent = orderNum ? 'Order #'+orderNum : '';
    loadCustomerMessages(true);
}

async function loadCustomerMessages(initial) {
    if (!activeCustomerChatId) return;
    try {
        const params = new URLSearchParams({chat_id: activeCustomerChatId});
        if (lastCustomerTs) params.set('since', lastCustomerTs);
        const r = await fetch('<?= APP_URL ?>/api/customer/chat.php?'+params, {credentials:'include'});
        const d = await r.json();
        if (!d.success) return;
        const box = document.getElementById('customerChatMessages');
        if (initial) box.innerHTML = '';
        (d.data.messages||[]).forEach(msg => {
            if (!document.querySelector(`[data-cid="${msg.id}"]`)) {
                const div = document.createElement('div');
                div.className = 'chat-msg ' + (msg.senderRole==='SUPERADMIN'||msg.senderRole==='ADMIN'?'mine':'theirs');
                div.dataset.cid = msg.id;
                const t = new Date(msg.createdAt+'Z').toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
                div.innerHTML = `<div class="chat-bubble">${escHtml(msg.message)}</div><div class="chat-meta">${roleBadge(msg.senderRole||'CUSTOMER')} ${escHtml(msg.senderName||'')} · ${t}</div>`;
                box.appendChild(div);
                lastCustomerTs = msg.createdAt;
            }
        });
        box.scrollTop = box.scrollHeight;
    } catch(e) {}
}

document.getElementById('customerChatForm')?.addEventListener('submit', async e => {
    e.preventDefault(); const input = document.getElementById('customerChatInput'); const msg = input.value.trim();
    if (!msg || !activeCustomerChatId) return; input.value = '';
    try {
        const r = await fetch('<?= APP_URL ?>/api/customer/chat.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify({chat_id:activeCustomerChatId,message:msg})});
        const d = await r.json();
        if (d.success) loadCustomerMessages(false);
    } catch(e) {}
});

document.getElementById('customerChatInput')?.addEventListener('keydown', e => { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('customerChatForm').dispatchEvent(new Event('submit'));} });

setInterval(()=>{ if(activeCustomerChatId) loadCustomerMessages(false); }, 5000);

// Initialize presence via shared JS
if (document.getElementById('presenceContainer')) { initPresence(); }
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
