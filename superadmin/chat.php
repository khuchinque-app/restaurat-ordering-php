<?php
$page_title = 'Live Chat';
include dirname(__DIR__) . '/includes/superadmin_header.php';
require_once dirname(__DIR__) . '/db.php';

$chat_tab = $_GET['tab'] ?? 'admin';

// Fetch customer chat conversations (latest message per conversation, only customer-initiated)
$customer_conversations = [];
try {
    $customer_conversations = db_query(
        'SELECT c.*, o.orderNumber, r.name AS restaurantName
         FROM CustomerChat c
         LEFT JOIN "Order" o ON o.id = c.orderId
         LEFT JOIN Restaurant r ON r.id = c.restaurantId
         WHERE c.senderRole IN (\'CUSTOMER\',\'GUEST\',\'USER\',\'CUSTOMER\')
           AND c.id IN (
             SELECT MAX(id) FROM CustomerChat GROUP BY COALESCE(orderId, senderName)
           )
         ORDER BY c.createdAt DESC LIMIT 50'
    );
} catch (Throwable $e) { /* table may not exist */ }

// Admin chats
$staff_chats = [];
try {
    $staff_chats = db_query(
        'SELECT id, senderId, senderName, senderRole, message, isRead, createdAt
         FROM StaffChat ORDER BY createdAt DESC LIMIT 100'
    );
} catch (Throwable $e) {}
?>

<style>
.chat-layout { display:grid; grid-template-columns:280px 1fr; gap:0; height:calc(100vh - 180px); }
.chat-sidebar { border-right:1px solid #e2e8f0; display:flex; flex-direction:column; background:#fff; }
.chat-sidebar-header { padding:.75rem 1rem; border-bottom:1px solid #e2e8f0; }
.chat-sidebar-header .tabs { display:flex; gap:.3rem; }
.chat-sidebar-header .tab { padding:.35rem .75rem; border-radius:6px; border:none; background:transparent; cursor:pointer; font-size:.82rem; font-weight:600; color:#64748b; }
.chat-sidebar-header .tab.active { background:#7c3aed; color:#fff; }
.chat-list { flex:1; overflow-y:auto; }
.chat-list-item { padding:.75rem 1rem; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background .1s; }
.chat-list-item:hover { background:#f8fafc; }
.chat-list-item.active { background:#ede9fe; border-left:3px solid #7c3aed; }
.chat-list-item .cli-name { font-weight:600; font-size:.875rem; }
.chat-list-item .cli-preview { font-size:.78rem; color:#64748b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-list-item .cli-time { font-size:.7rem; color:#94a3b8; }
.chat-list-item .cli-badge { background:#ef4444; color:#fff; font-size:.65rem; font-weight:700; padding:.1rem .35rem; border-radius:9999px; }
.chat-main { display:flex; flex-direction:column; }
.chat-main-header { padding:.75rem 1rem; border-bottom:1px solid #e2e8f0; background:#fff; }
.chat-messages { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:#f8fafc; }
.chat-msg { max-width:70%; }
.chat-msg.mine { align-self:flex-end; }
.chat-msg.theirs { align-self:flex-start; }
.chat-bubble { padding:.55rem .9rem; border-radius:12px; font-size:.9rem; line-height:1.4; word-break:break-word; }
.mine .chat-bubble { background:#7c3aed; color:#fff; border-bottom-right-radius:3px; }
.theirs .chat-bubble { background:#fff; color:#1e293b; border:1px solid #e5e7eb; border-bottom-left-radius:3px; }
.chat-meta { font-size:.72rem; color:#94a3b8; margin-top:.2rem; }
.mine .chat-meta { text-align:right; }
.role-badge { display:inline-block; font-size:.65rem; font-weight:700; padding:.05rem .35rem; border-radius:4px; margin-right:.3rem; }
.role-SUPERADMIN { background:#ede9fe; color:#7c3aed; }
.role-ADMIN { background:#dbeafe; color:#1d4ed8; }
.role-CUSTOMER { background:#dcfce7; color:#166534; }
.chat-form { display:flex; gap:.5rem; border:1px solid #e5e7eb; border-top:none; background:#fff; padding:.75rem; border-radius:0 0 8px 8px; }
.chat-form input { flex:1; padding:.55rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; }
.chat-form input:focus { outline:none; border-color:#7c3aed; }
.chat-form button { padding:.55rem 1.25rem; background:#7c3aed; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:.9rem; }
.chat-form button:hover { background:#6d28d9; }
.chat-status { font-size:.75rem; color:#94a3b8; padding:2rem; text-align:center; }
.chat-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94a3b8; }
.chat-empty .icon { font-size:3rem; margin-bottom:1rem; }
.presence-bar { display:flex; flex-wrap:wrap; gap:.35rem; padding:.5rem .75rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; font-size:.78rem; align-items:center; }
.presence-online { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .5rem; background:#dcfce7; border-radius:4px; color:#166534; }
.presence-offline { display:inline-flex; align-items:center; gap:.25rem; padding:.15rem .5rem; background:#f1f5f9; border-radius:4px; color:#64748b; }
.presence-dot { width:6px; height:6px; border-radius:50%; display:inline-block; }
.presence-dot.online { background:#10b981; }
.presence-dot.offline { background:#94a3b8; }
/* ── Notification Toast ──────────────────────── */
.chat-notif-overlay { position:fixed; top:0; left:0; right:0; z-index:9999; display:flex; justify-content:center; padding-top:1rem; pointer-events:none; }
.chat-notif-toast { background:#1e293b; color:#fff; padding:1rem 1.5rem; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.3); max-width:480px; width:100%; transform:translateY(-120%); transition:transform .4s cubic-bezier(.34,1.56,.64,1); pointer-events:auto; border-left:4px solid #7c3aed; }
.chat-notif-toast.show { transform:translateY(0); }
.chat-notif-toast .notif-title { font-weight:700; font-size:1rem; margin-bottom:.25rem; }
.chat-notif-toast .notif-body { font-size:.85rem; color:#cbd5e1; }
.chat-notif-toast .notif-close { float:right; background:none; border:none; color:#64748b; cursor:pointer; font-size:1.2rem; padding:0 .2rem; }
.chat-notif-toast .notif-close:hover { color:#fff; }
.chat-notif-toast.notif-order { border-left-color:#f59e0b; }
.chat-notif-toast.notif-chat { border-left-color:#10b981; }
@keyframes notifPulse { 0%{box-shadow:0 0 0 0 rgba(124,58,237,0.5)} 70%{box-shadow:0 0 0 12px rgba(124,58,237,0)} 100%{box-shadow:0 0 0 0 rgba(124,58,237,0)} }
.chat-notif-toast.pulse { animation:notifPulse 1.5s infinite; }
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
            <div style="font-size:.7rem;color:#7c3aed">Order #<?= htmlspecialchars($conv['orderNumber']) ?></div>
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
        <span style="font-size:.78rem;color:#94a3b8;margin-left:.5rem">All admin members</span>
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
        <span id="customerStatus" style="font-size:.78rem;color:#94a3b8;margin-left:.5rem">Select a conversation</span>
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

<!-- ── Notification Toast ──────────────────────── -->
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
let lastTimestamp = null;
let isAtBottom = true;

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function roleBadge(role) {
    const colors = {SUPERADMIN:'#7c3aed',ADMIN:'#1d4ed8',MANAGER:'#92400e',CUSTOMER:'#166534'};
    return `<span class="role-badge role-${role}" style="background:${colors[role]||'#6b7280'}22;color:${colors[role]||'#6b7280'}">${role}</span>`;
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
        statusEl.textContent = onlineCustomers[name] ? '🟢 Online' : '⚫ Offline';
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
                div.innerHTML = `<div class="chat-bubble">${escHtml(d.data.message)}</div><div class="chat-meta"><span style="color:#10b981;font-size:.65rem" title="Sent">✓</span> <button onclick="editCustMsg('${d.data.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Edit">✏️</button><button onclick="delCustMsg('${d.data.id}')" style="background:none;border:none;cursor:pointer;font-size:.65rem;color:#94a3b8;padding:0;margin-left:.3rem" title="Delete">🗑</button> · ${t}</div>`;
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
                // Update sidebar preview
                const convItem = document.getElementById('conv-' + activeCustomerName.replace(/[^a-zA-Z0-9]/g, '_'));
                if (convItem) {
                    const p = convItem.querySelector('.cli-preview');
                    if (p) p.textContent = d.data.message.substring(0, 40);
                }
            }
        }
    } catch(e) {}
});

document.getElementById('customerChatInput')?.addEventListener('keydown', e => { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('customerChatForm').dispatchEvent(new Event('submit'));} });

setInterval(()=>{ if(activeCustomerChatId) loadCustomerMessages(false); }, 5000);

// Initialize presence via shared superadmin.js
if (document.getElementById('presenceContainer')) { initPresence(); }

// ── Notification System ──────────────────────────────
let notifSoundEnabled = true;
let lastConvCount = document.querySelectorAll('.chat-list-item[data-chat-name]').length;
let notifTimer = null;

function playNotificationSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(800, ctx.currentTime);
        osc.frequency.setValueAtTime(600, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
        // Second beep
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.frequency.setValueAtTime(1000, ctx.currentTime);
        osc2.frequency.setValueAtTime(800, ctx.currentTime + 0.15);
        gain2.gain.setValueAtTime(0.25, ctx.currentTime + 0.2);
        gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45);
        osc2.start(ctx.currentTime + 0.2);
        osc2.stop(ctx.currentTime + 0.45);
    } catch(e) { /* audio not supported */ }
}

function showNotification(title, body, type) {
    const toast = document.getElementById('notifToast');
    const overlay = document.getElementById('notifOverlay');
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

// Poll for NEW conversations (ones that appeared since page load)
let knownConversations = new Set();
document.querySelectorAll('.chat-list-item[data-chat-name]').forEach(el => {
    knownConversations.add(el.dataset.chatName);
});

async function pollNewConversations() {
    try {
        const r = await fetch('/api/customer/chat.php?limit=1', {credentials:'include'});
        const d = await r.json();
        if (!d.success || !d.data.messages) return;
        
        // Get unique senders from the sidebar
        const currentSenders = new Set();
        document.querySelectorAll('.chat-list-item[data-chat-name]').forEach(el => {
            currentSenders.add(el.dataset.chatName);
        });
        
        // Check for new senders
        currentSenders.forEach(name => {
            if (!knownConversations.has(name) && name !== 'Super Admin') {
                knownConversations.add(name);
                showNotification('💬 New Customer Chat', 'Customer "' + name + '" sent a message', 'chat');
            }
        });
    } catch {}
}

// ── Global message poll (notifies on ANY new customer msg) ──
let lastGlobalMsgTs = null;

async function pollAllCustomerMessages() {
    try {
        const r = await fetch('/api/customer/chat.php?limit=10', {credentials:'include'});
        const d = await r.json();
        if (!d.success || !d.data.messages || !d.data.messages.length) return;
        
        const msgs = d.data.messages;
        const latest = msgs[msgs.length - 1];
        
        // Track latest timestamp across polls
        if (!lastGlobalMsgTs) {
            lastGlobalMsgTs = latest.createdAt;
            return;
        }
        
        // Check for NEW messages (not previously seen)
        const newMsgs = msgs.filter(m => m.createdAt > lastGlobalMsgTs && m.senderRole !== 'SUPERADMIN' && m.senderRole !== 'ADMIN');
        
        if (newMsgs.length > 0) {
            lastGlobalMsgTs = newMsgs[newMsgs.length - 1].createdAt;
            
            newMsgs.forEach(msg => {
                const name = msg.senderName || 'Guest';
                const text = (msg.message || '').substring(0, 80);
                
                // Only notify if not currently viewing this conversation
                const isViewing = activeCustomerName === name;
                if (!isViewing) {
                    showNotification('💬 ' + name, text, 'chat');
                }
                
                // Also update sidebar dot + preview for this conversation
                const convItem = document.getElementById('conv-' + name.replace(/[^a-zA-Z0-9]/g, '_'));
                if (convItem) {
                    const preview = convItem.querySelector('.cli-preview');
                    if (preview) preview.textContent = text;
                }
            });
        }
    } catch(e) {}
}

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
            if (dot) {
                dot.style.background = online[name] ? '#10b981' : '#94a3b8';
            }
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
        method: 'DELETE',
        credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({sender_name: senderName, restaurant_id: restaurantId})
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            const el = document.getElementById('conv-' + safeName);
            if (el) el.remove();
            location.reload();
        } else {
            alert('Failed to delete: ' + (d.error||'unknown'));
        }
    }).catch(() => alert('Network error'));
}

if (CHAT_TAB === 'customers') {
    pollCustomerPresence();
    presencePollTimer = setInterval(pollCustomerPresence, 5000);
    pollAllCustomerMessages();
    notifTimer = setInterval(pollAllCustomerMessages, 5000);
    // Sound toggle button
    const topbar = document.querySelector('.sa-topbar-right');
    if (topbar) {
        const btn = document.createElement('button');
        btn.id = 'soundToggle';
        btn.innerHTML = '🔔';
        btn.title = 'Toggle notification sound';
        btn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:1.1rem;margin-left:.5rem;padding:2px 6px;border-radius:6px';
        btn.onclick = function() {
            notifSoundEnabled = !notifSoundEnabled;
            this.innerHTML = notifSoundEnabled ? '🔔' : '🔕';
            this.style.opacity = notifSoundEnabled ? '1' : '.4';
        };
        topbar.appendChild(btn);
    }
}
</script>

<?php include dirname(__DIR__) . '/includes/superadmin_footer.php'; ?>
