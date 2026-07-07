<?php
$page_title = 'Live Chat';
include dirname(__DIR__) . '/includes/admin_header.php';
require_once dirname(__DIR__) . '/db.php';

$chat_tab = $_GET['tab'] ?? 'admin';
$restaurant = !empty($current_user['restaurantId'])
    ? db_fetch('SELECT * FROM Restaurant WHERE id = ? AND isActive = 1', [$current_user['restaurantId']])
    : null;
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
               AND c.senderRole NOT IN (\'ADMIN\',\'SUPERADMIN\')
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
            <button class="tab <?= $chat_tab === 'admin' ? 'active' : '' ?>" onclick="location.href='chat.php?tab=admin'">👨‍💼 Admin</button>
            <button class="tab <?= $chat_tab === 'customers' ? 'active' : '' ?>" onclick="location.href='chat.php?tab=customers'">👤 Customers <span id="onlineCount" style="font-size:.7rem;color:#10b981;font-weight:400"></span></button>
        </div>
    </div>
    <div id="presenceBar" style="display:none;padding:.35rem .75rem;border-bottom:1px solid #e2e8f0;background:#f0fdf4;font-size:.75rem;color:#166534">🟢 <span id="onlineNames"></span> online</div>
    <div class="chat-list">
        <?php if ($chat_tab === 'admin'): ?>
        <div class="chat-list-item active">
            <div class="cli-name">📢 Admin Channel</div>
            <div class="cli-preview">All admin messages</div>
        </div>
        <?php else: ?>
        <?php if (empty($customer_conversations)): ?>
        <div class="chat-status">No customer conversations yet.</div>
        <?php else: foreach ($customer_conversations as $conv): 
            $sender_name = $conv['senderName'] ?? 'Guest';
            $rid = $conv['restaurantId'] ?? '';
        ?>
        <div class="chat-list-item" id="conv-<?= htmlspecialchars($sender_name, ENT_QUOTES) ?>" data-chat-id="<?= htmlspecialchars($conv['id'], ENT_QUOTES) ?>" data-chat-name="<?= htmlspecialchars($sender_name, ENT_QUOTES) ?>" data-chat-order="<?= htmlspecialchars($conv['orderNumber'] ?? '', ENT_QUOTES) ?>" data-restaurant-id="<?= htmlspecialchars($rid, ENT_QUOTES) ?>" onclick="showCustomerChat(this.dataset.chatId, this.dataset.chatName, this.dataset.chatOrder)">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div class="cli-name"><span class="status-dot" id="dot-<?= htmlspecialchars($sender_name, ENT_QUOTES) ?>" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#94a3b8;margin-right:6px;vertical-align:middle"></span>👤 <?= htmlspecialchars($sender_name) ?></div>
                <div style="display:flex;align-items:center;gap:6px">
                    <span class="cli-time"><?= date('H:i', strtotime($conv['createdAt'])) ?></span>
                    <button onclick="event.stopPropagation();deleteConversation('<?= htmlspecialchars($sender_name, ENT_QUOTES) ?>','<?= htmlspecialchars($rid, ENT_QUOTES) ?>')" style="background:none;border:none;cursor:pointer;font-size:.8rem;color:#94a3b8;padding:2px 4px;border-radius:4px" title="Delete conversation">🗑</button>
                </div>
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
    <?php if ($chat_tab === 'admin'): ?>
    <div class="chat-main-header">
        <strong>📢 Admin Channel</strong>
        <span>All admin members</span>
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
        <span id="customerStatus">Select a conversation</span>
    </div>
    <div class="chat-messages" id="customerChatMessages">
        <div class="chat-empty">
            <div class="icon">💬</div>
            <div>Select a customer conversation from the left panel</div>
        </div>
    </div>
    <form class="chat-form" id="customerChatForm" style="display:none">
        <input type="text" id="customerChatInput" placeholder="Reply to customer…" maxlength="1000" autocomplete="off" style="flex:1">
        <input type="file" id="chatMediaInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
        <button type="button" onclick="document.getElementById('chatMediaInput').click()" style="background:none;border:none;cursor:pointer;font-size:1.1rem;padding:2px 6px" title="Attach image">📎</button>
        <button type="submit" style="padding:.55rem 1.25rem;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9rem">Send</button>
    </form>
    <?php endif; ?>
</div>
</div>

<div class="chat-notif-overlay" id="notifOverlay">
    <div class="chat-notif-toast" id="notifToast">
        <button class="notif-close" onclick="closeNotif()">&times;</button>
        <div class="notif-title" id="notifTitle">💬 New Message</div>
        <div class="notif-body" id="notifBody">Customer says: ...</div>
    </div>
</div>

<script>
const ME_ID = '<?= htmlspecialchars($current_user['id']) ?>';
const CHAT_TAB = '<?= $chat_tab ?>';
const CHAT_TAB_LABEL = 'admin';
let lastTimestamp = null;
let isAtBottom = true;

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function roleBadge(role) {
    const colors = {SUPERADMIN:'#7c3aed',ADMIN:'#059669',MANAGER:'#92400e',CUSTOMER:'#1d4ed8'};
    return `<span class="role-badge role-${role}">${role}</span>`;
}

if (CHAT_TAB === 'admin') {
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
        div.innerHTML = `<div class="chat-bubble">${escHtml(msg.message)}</div><div class="chat-meta">${mine?'':roleBadge(msg.senderRole)+' '+escHtml(msg.senderName)+' · '}${t}${mine?' <button class="del-staff-msg" data-mid="'+msg.id+'" style="background:none;border:none;cursor:pointer;font-size:.7rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Delete">🗑</button>':''}</div>`;
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

let notifSoundEnabled = true;
let lastGlobalMsgTs = null;
let notifTimer = null;

function playNotificationSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator(); const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(800, ctx.currentTime);
        osc.frequency.setValueAtTime(600, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.3);
        const osc2 = ctx.createOscillator(); const gain2 = ctx.createGain();
        osc2.connect(gain2); gain2.connect(ctx.destination);
        osc2.frequency.setValueAtTime(1000, ctx.currentTime);
        osc2.frequency.setValueAtTime(800, ctx.currentTime + 0.15);
        gain2.gain.setValueAtTime(0.25, ctx.currentTime + 0.2);
        gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45);
        osc2.start(ctx.currentTime + 0.2); osc2.stop(ctx.currentTime + 0.45);
    } catch(e) {}
}

function showNotification(title, body, type) {
    const toast = document.getElementById('notifToast');
    if (!toast) return;
    document.getElementById('notifTitle').textContent = title;
    document.getElementById('notifBody').textContent = body;
    toast.className = 'chat-notif-toast ' + (type||'chat') + ' pulse';
    toast.classList.add('show');
    if (notifSoundEnabled) playNotificationSound();
    setTimeout(closeNotif, 8000);
}

function closeNotif() {
    const toast = document.getElementById('notifToast');
    if (toast) toast.classList.remove('show');
}

async function pollAllCustomerMessages() {
    try {
        const r = await fetch('/api/customer/chat.php?limit=10', {credentials:'include'});
        const d = await r.json();
        if (!d.success || !d.data.messages || !d.data.messages.length) return;
        const msgs = d.data.messages;
        const latest = msgs[msgs.length - 1];
        if (!lastGlobalMsgTs) { lastGlobalMsgTs = latest.createdAt; return; }
        const newMsgs = msgs.filter(m => m.createdAt > lastGlobalMsgTs && m.senderRole !== 'SUPERADMIN' && m.senderRole !== 'ADMIN');
        if (newMsgs.length > 0) {
            lastGlobalMsgTs = newMsgs[newMsgs.length - 1].createdAt;
            newMsgs.forEach(msg => {
                const name = msg.senderName || 'Guest';
                const text = (msg.message || '').substring(0, 80);
                if (activeCustomerName !== name) showNotification('💬 ' + name, text, 'chat');
                const convItem = document.getElementById('conv-' + name.replace(/[^a-zA-Z0-9]/g, '_'));
                if (convItem) { const p = convItem.querySelector('.cli-preview'); if(p) p.textContent = text; }
            });
        }
    } catch(e) {}
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
    const hdr = document.getElementById('customerChatHeader');
    hdr.querySelector('strong').textContent = '👤 ' + name;
    const statusEl = document.getElementById('customerStatus');
    if (statusEl) {
        statusEl.textContent = orderNum ? 'Order #'+orderNum : (onlineCustomers[name] ? '🟢 Online' : '');
    }
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
                const isMine = msg.senderRole==='SUPERADMIN'||msg.senderRole==='ADMIN';
                const seenIcon = msg.isRead ? '<span style="color:#10b981;font-size:.65rem;margin-left:.2rem" title="Read">✓✓</span>' : '<span style="color:#94a3b8;font-size:.65rem;margin-left:.2rem" title="Sent">✓</span>';
                const editBtn = isMine ? `<button onclick="editCustMsg('${msg.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Edit">✏️</button>` : '';
                const delBtn = isMine ? `<button onclick="delCustMsg('${msg.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Delete">🗑</button>` : '';
                const mediaHtml = msg.mediaUrl ? `<br><a href="${escHtml(msg.mediaUrl)}" target="_blank" style="color:#7c3aed;font-size:.82rem">📎 Attachment</a>` : '';
                div.innerHTML = `<div class="chat-bubble">${escHtml(msg.message)}${mediaHtml}</div><div class="chat-meta">${roleBadge(msg.senderRole||'CUSTOMER')} ${escHtml(msg.senderName||'')} · ${t} ${isMine ? seenIcon : ''} ${editBtn} ${delBtn}</div>`;
                box.appendChild(div);
                lastCustomerTs = msg.createdAt;
            }
        });
        box.scrollTop = box.scrollHeight;
    } catch(e) {}
}

// ── Edit customer message ──────────────────────────
function editCustMsg(msgId) {
    const bubble = document.querySelector(`[data-cid="${msgId}"] .chat-bubble`);
    if (!bubble) return;
    const currentText = bubble.textContent;
    const newText = prompt('Edit message:', currentText);
    if (!newText || newText.trim() === '' || newText === currentText) return;
    fetch('/api/customer/chat.php', {
        method: 'PUT',
        credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: msgId, message: newText.trim()})
    }).then(r=>r.json()).then(d => {
        if (d.success) { bubble.textContent = d.data.message; }
        else { alert(d.error || 'Failed to edit'); }
    }).catch(() => alert('Network error'));
}

// ── Delete customer message ────────────────────────
function delCustMsg(msgId) {
    if (!confirm('Delete this message?')) return;
    fetch('/api/customer/chat.php', {
        method: 'DELETE',
        credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: msgId})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            const el = document.querySelector(`[data-cid="${msgId}"]`);
            if (el) el.remove();
        } else { alert(d.error || 'Failed'); }
    }).catch(() => alert('Network error'));
}

// ── Media upload ───────────────────────────────────
document.getElementById('chatMediaInput')?.addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file || !activeCustomerChatId) return;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('chat_id', activeCustomerChatId);
    try {
        const r = await fetch('/api/customer/chat.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const d = await r.json();
        if (d.success) { loadCustomerMessages(false); }
        else { alert(d.error || 'Upload failed'); }
    } catch(e) { alert('Network error'); }
    e.target.value = '';
});

document.getElementById('customerChatForm')?.addEventListener('submit', async e => {
    e.preventDefault(); const input = document.getElementById('customerChatInput'); const msg = input.value.trim();
    if (!msg || !activeCustomerChatId) return; input.value = '';
    try {
        const r = await fetch('<?= APP_URL ?>/api/customer/chat.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify({chat_id:activeCustomerChatId,message:msg})});
        const d = await r.json();
        if (d.success) {
            // Directly append the new message to the DOM
            const box = document.getElementById('customerChatMessages');
            if (box && !document.querySelector(`[data-cid="${d.data.id}"]`)) {
                const ph = box.querySelector('.chat-status'); if(ph) ph.remove();
                const div = document.createElement('div');
                div.className = 'chat-msg mine';
                div.dataset.cid = d.data.id;
                const t = new Date(d.data.createdAt+'Z').toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
                div.innerHTML = `<div class="chat-bubble">${escHtml(d.data.message)}</div><div class="chat-meta"><span style="color:#059669;font-size:.65rem" title="Sent">✓</span> <button onclick="editCustMsg('${d.data.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Edit">✏️</button><button onclick="delCustMsg('${d.data.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Delete">🗑</button> · ${t}</div>`;
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
                const convItem = document.getElementById('conv-' + activeCustomerName.replace(/[^a-zA-Z0-9]/g, '_'));
                if (convItem) { const p = convItem.querySelector('.cli-preview'); if (p) p.textContent = d.data.message.substring(0, 40); }
            }
        }
    } catch(e) {}
});

document.getElementById('customerChatInput')?.addEventListener('keydown', e => { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('customerChatForm').dispatchEvent(new Event('submit'));} });

setInterval(()=>{ if(activeCustomerChatId) loadCustomerMessages(false); }, 5000);

// Initialize presence via shared JS
if (document.getElementById('presenceContainer')) { initPresence(); }

// ── Delete Staff/Admin Chat Message ───────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.del-staff-msg');
    if (!btn) return;
    var msgId = btn.dataset.mid;
    if (!confirm('Delete this message?')) return;
    fetch('/api/staff/chat.php', {
        method: 'DELETE',
        credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: msgId})
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var el = document.querySelector('[data-id="'+msgId+'"]');
            if (el) el.remove();
        } else {
            alert(d.error || 'Failed to delete');
        }
    }).catch(function() { alert('Network error'); });
});

// ── Customer Presence Polling ──────────────────────────
let presencePollTimer = null;
let onlineCustomers = {};

async function pollCustomerPresence() {
    try {
        const r = await fetch('/api/customer/presence.php?all=1', {credentials:'include'});
        const d = await r.json();
        if (!d.success || !d.data.online) return;
        const online = {};
        d.data.online.forEach(p => { online[p.senderName] = p.restaurantName || '?'; });
        onlineCustomers = online;
        document.querySelectorAll('.chat-list-item[data-chat-name]').forEach(el => {
            const name = el.dataset.chatName;
            const safeName = name.replace(/[^a-zA-Z0-9]/g, '_');
            const dot = document.getElementById('dot-' + safeName);
            if (dot) dot.style.background = online[name] ? '#10b981' : '#94a3b8';
            const onlineNames = new Set(Object.keys(online));
            if (el.classList.contains('active')) {
                const st = document.getElementById('customerStatus');
                if (st) st.textContent = online.hasOwnProperty(name) ? '🟢 Online' : '⚫ Offline';
            }
        });
        const names = Object.keys(online);
        const countEl = document.getElementById('onlineCount');
        if (countEl) countEl.textContent = names.length ? '🟢'+names.length : '';
        const bar = document.getElementById('presenceBar');
        const namesEl = document.getElementById('onlineNames');
        if (bar && namesEl) {
            bar.style.display = names.length ? '' : 'none';
            namesEl.textContent = names.map(n => n + '@' + (online[n]||'?')).join(', ');
        }
    } catch {}
}

function deleteConversation(senderName, restaurantId) {
    if (!confirm('Delete this conversation? All messages will be permanently removed.')) return;
    const safeName = senderName.replace(/[^a-zA-Z0-9]/g, '_');
    fetch('/api/customer/chat.php', {
        method: 'DELETE', credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({sender_name: senderName, restaurant_id: restaurantId})
    }).then(r=>r.json()).then(d => {
        if (d.success) { const el = document.getElementById('conv-'+safeName); if(el) el.remove(); location.reload(); }
        else alert('Failed: '+(d.error||'unknown'));
    }).catch(()=>alert('Network error'));
}
if (CHAT_TAB === 'customers') {
    pollAllCustomerMessages();
    notifTimer = setInterval(pollAllCustomerMessages, 5000);
    pollCustomerPresence();
    presencePollTimer = setInterval(pollCustomerPresence, 5000);
    const tb = document.querySelector('.sa-topbar, .admin-header, .sidebar-header, .page-title');
    if (tb) {
        const btn = document.createElement('button');
        btn.id = 'soundToggle'; btn.innerHTML = '🔔'; btn.title = 'Toggle sound';
        btn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:1.1rem;margin-left:.5rem;padding:2px 6px;border-radius:6px';
        btn.onclick = function() { notifSoundEnabled = !notifSoundEnabled; this.innerHTML = notifSoundEnabled ? '🔔' : '🔕'; this.style.opacity = notifSoundEnabled ? '1' : '.4'; };
        tb.appendChild(btn);
    }
}
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
