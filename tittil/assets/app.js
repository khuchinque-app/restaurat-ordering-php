// ===== Tittil — Treats & Bites =====
const RESTAURANT_SLUG = 'tittil';
const API_BASE        = '/api';
const TAX_RATE        = 0;
const CART_KEY        = 'cart_' + RESTAURANT_SLUG;

// ── Cart ──────────────────────────────────────────────
let cart = [];
try { cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch { cart = []; }

function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    renderCartBadge();
    renderCartDrawer();
}

function addItem(id, name, price, maxStock) {
    const ex = cart.find(i => i.id === id);
    if (ex) {
        if (ex.qty >= (maxStock || 999)) { toast('Not enough stock'); return; }
        ex.qty++;
    } else {
        cart.push({ id, name, price: parseFloat(price), qty: 1, maxStock: parseInt(maxStock) || 999 });
    }
    saveCart();
    toast('✓ ' + name + ' added');
}

function removeItem(id)        { cart = cart.filter(i => i.id !== id); saveCart(); }
function changeQty(id, delta)  { const i = cart.find(x => x.id === id); if (i) { i.qty = Math.max(1, i.qty + delta); if (i.qty > i.maxStock) i.qty = i.maxStock; } saveCart(); }
function clearCart()           { cart = []; saveCart(); }
function totalItems()          { return cart.reduce((s, i) => s + i.qty, 0); }
function subtotal()            { return cart.reduce((s, i) => s + i.price * i.qty, 0); }

// ── UI helpers ────────────────────────────────────────
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(n)  { return '$' + parseFloat(n).toFixed(2); }

function toast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2500);
}

function renderCartBadge() {
    const n = totalItems();
    const badge = document.getElementById('cart-count');
    if (badge) {
        badge.textContent = n;
        badge.style.display = n ? 'inline-flex' : 'none';
    }
}

function openCart() {
    document.getElementById('cart-drawer').classList.add('open');
    document.getElementById('drawer-overlay').classList.add('open');
    renderCartDrawer();
}

function closeCart() {
    document.getElementById('cart-drawer').classList.remove('open');
    document.getElementById('drawer-overlay').classList.remove('open');
}

function renderCartDrawer() {
    const body   = document.getElementById('cart-body');
    const footer = document.getElementById('cart-footer');
    if (!cart.length) {
        body.innerHTML   = '<div class="drawer-empty"><div class="empty-icon">&#128722;</div>Pesanan masih kosong</div>';
        footer.innerHTML = '';
        return;
    }
    const itemCount = totalItems();
    body.innerHTML = cart.map(i => {
        const lineTotal = i.price * i.qty;
        const lineKhr = (lineTotal * 4000).toLocaleString();
        return `
        <div class="cart-item">
            <div class="ci-info">
                <div class="ci-name">${esc(i.name)}</div>
                <div class="ci-price">${fmt(i.price)} &times; ${i.qty} = <strong>${fmt(lineTotal)}</strong></div>
                <div class="ci-price-khr">${lineKhr} KHR</div>
            </div>
            <div class="ci-controls">
                <button class="qty-btn" onclick="changeQty('${i.id}',-1)">&#8722;</button>
                <span class="qty-val">${i.qty}</span>
                <button class="qty-btn" onclick="changeQty('${i.id}',1)">&#43;</button>
                <button class="rm-btn" onclick="removeItem('${i.id}')" title="Remove">&times;</button>
            </div>
        </div>`}).join('');

    const sub = subtotal(), tot = sub;
    const totKhr = (tot * 4000).toLocaleString();
    const subKhr = (sub * 4000).toLocaleString();
    footer.innerHTML = `
        <div class="summary">
            <div class="sum-row"><span>Items (${itemCount})</span><span></span></div>
            <div class="sum-row"><span>Subtotal</span><span>${fmt(sub)} <span style="font-size:.75rem;color:#94a3b8">(${subKhr} KHR)</span></span></div>
            <div class="sum-row sum-total"><span>Total</span><span>${fmt(tot)} <span style="font-size:.8rem">(${totKhr} KHR)</span></span></div>
        </div>
        <div class="form-group"><label>Your Name</label><input id="cust-name" type="text" placeholder="Enter your name (optional)"></div>
        <div class="form-group"><label>Phone</label><input id="cust-phone" type="tel" placeholder="Phone number (optional)"></div>
        <div class="form-group"><label>Order Notes</label><textarea id="cust-notes" rows="2" placeholder="Any special instructions..."></textarea></div>
        <a href="https://t.me/pempektitilkps" target="_blank" style="display:block;text-align:center;padding:.5rem;background:#0088cc;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;margin-bottom:.4rem" onclick="window.open('https://t.me/pempektitilkps')">📱 Accept &amp; Order via Telegram</a><button class="place-btn" id="place-order-btn" onclick="placeOrder()">Place Order &mdash; ${fmt(tot)} (${totKhr} KHR)</button>`;
}

// ── Order ─────────────────────────────────────────────
async function placeOrder() {
    const name  = document.getElementById('cust-name')?.value.trim();
    const phone = document.getElementById('cust-phone')?.value.trim();
    const notes = document.getElementById('cust-notes')?.value.trim();
    const btn = document.getElementById('place-order-btn');
    btn.disabled    = true;
    btn.textContent = 'Placing order…';

    try {
        const res  = await fetch(`${API_BASE}/orders/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                restaurantId: window.RESTAURANT_ID,
                customerName: name,
                customerPhone: phone || undefined,
                notes: notes || undefined,
                items: cart.map(i => ({ menuItemId: i.id, quantity: i.qty })),
            })
        });
        const data = await res.json();
        if (data.success) {
            clearCart();
            closeCart();
            fetch(`${API_BASE}/orders/track.php`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderId: data.data.id })
            }).catch(() => {});
            showOrderConfirm(data.data.orderNumber);
        } else {
            alert(data.error || 'Failed to place order. Please try again.');
            btn.disabled    = false;
            btn.textContent = 'Place Order';
        }
    } catch {
        alert('Network error. Please try again.');
        btn.disabled    = false;
        btn.textContent = 'Place Order';
    }
}

function showOrderConfirm(orderNumber) {
    document.getElementById('confirm-order-num').textContent = '#' + orderNumber;
    document.getElementById('order-confirm').classList.add('open');
    window.open('https://t.me/pempektitilkps', '_blank');
}

// ── Menu Rendering ────────────────────────────────────
let allItems = [], activeCategory = 'all';

async function loadMenu() {
    const rRes = await fetch(`${API_BASE}/menu/categories.php?restaurant=${RESTAURANT_SLUG}`);
    const rData = await rRes.json();
    if (!rData.success) { document.getElementById('menu-grid').innerHTML = '<p style="color:red;padding:2rem;text-align:center">Failed to load menu.</p>'; return; }

    const catRes   = rData.data;
    const itemsRes = await fetch(`${API_BASE}/menu/items.php?restaurant=${RESTAURANT_SLUG}&available=1&limit=100`);
    const itemsData = await itemsRes.json();
    allItems = itemsData.success ? itemsData.data.items : [];

    // Stash restaurant ID for ordering
    const infoRes = await fetch(`/api/menu/restaurant_info.php?restaurant=${RESTAURANT_SLUG}`);
    if (infoRes.ok) { const info = await infoRes.json(); if (info.success) window.RESTAURANT_ID = info.data.id; }

    renderCategories(catRes);
    renderItems(allItems);
    updateStats(allItems);
}

function renderCategories(cats) {
    const wrap = document.getElementById('cat-tabs');
    wrap.innerHTML = '<button class="cat-tab active" data-cat="all" onclick="filterCat(\'all\',this)">All</button>'
        + cats.map(c => `<button class="cat-tab" data-cat="${esc(c.id)}" onclick="filterCat('${esc(c.id)}',this)">${esc(c.name)} <span style="opacity:.6;font-weight:400">(${c.itemCount||0})</span></button>`).join('');
}

function filterCat(id, btn) {
    activeCategory = id;
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilter();
}

function applyFilter() {
    const q = (document.getElementById('search-input')?.value || '').toLowerCase().trim();
    const filtered = allItems.filter(i => {
        const matchCat  = activeCategory === 'all' || i.categoryId === activeCategory;
        const matchName = !q || i.name.toLowerCase().includes(q) || (i.description || '').toLowerCase().includes(q);
        return matchCat && matchName;
    });
    renderItems(filtered);
}

function updateStats(items) {
    const bar = document.getElementById('stats-bar');
    if (!bar) return;
    const cats = new Set(items.map(i => i.categoryName));
    bar.innerHTML = `
        <span class="stat-chip">&#127860; <span>${items.length}</span> items</span>
        <span class="stat-chip">&#128204; <span>${cats.size}</span> categories</span>
    `;
}

function renderItems(items) {
    const grid = document.getElementById('menu-grid');
    if (!items.length) { grid.innerHTML = '<p style="padding:2rem;color:#94a3b8;text-align:center;grid-column:1/-1">No items found.</p>'; return; }
    grid.innerHTML = items.map(i => `
        <div class="menu-card">
            <div class="menu-card-img-wrap">
                ${i.image ? `<img src="${esc(i.image)}" alt="${esc(i.name)}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\\\\\\'img-placeholder\\\\\\'>🍽</div>'">` : '<div class="img-placeholder">&#127870;</div>'}
            </div>
            <div class="menu-card-body">
                <div class="card-cat">${esc(i.categoryName || '')}</div>
                <div class="card-name">${esc(i.name)}</div>
                ${i.description ? `<div class="card-desc">${esc(i.description)}</div>` : ''}
                ${i.stockQuantity != null && i.stockQuantity <= 5 ? `<div class="stock-warn">&#9888; Only ${i.stockQuantity} left</div>` : ''}
                <div class="card-footer">
                    <div>
                        <span class="price">${fmt(i.price)}</span>
                        <span class="price-khr">&#xf0e0; ${(parseFloat(i.price) * 4000).toLocaleString()} KHR</span>
                    </div>
                    <button class="add-btn" onclick="addItem('${esc(i.id)}','${esc(i.name)}','${esc(i.price)}','${esc(i.stockQuantity ?? 999)}')">+ Add</button>
                </div>
            </div>
        </div>`).join('');
}

function imgFallback(img) {
    img.style.display = "none";
    var p = img.parentElement;
    p.innerHTML = "<div style='display:flex;align-items:center;justify-content:center;height:100%;font-size:3rem'>🧁</div>";
}

// ── Chat ──────────────────────────────────────────────
let chatName = '';
let chatWidgetOpen = false;

function getChatCustomerName() {
    const nameInput = document.getElementById('cust-name');
    if (nameInput && nameInput.value.trim()) {
        chatName = nameInput.value.trim();
        localStorage.setItem('tittil_chat_name', chatName);
        return chatName;
    }
    chatName = localStorage.getItem('tittil_chat_name');
    if (!chatName) {
        chatName = 'Guest ' + Math.random().toString(36).substr(2,4);
        localStorage.setItem('tittil_chat_name', chatName);
    }
    return chatName;
}

function toggleChat() {
    const widget = document.getElementById('chatWidget');
    chatWidgetOpen = !chatWidgetOpen;
    widget.classList.toggle('open', chatWidgetOpen);
    if (chatWidgetOpen) {
        document.getElementById('chatInput').focus();
        loadChatHistory();
        startChatPoll();
    } else {
        stopChatPoll();
    }
}

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    addChatMessage(msg, 'mine');
    try {
        const res = await fetch('/api/customer/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: msg,
                restaurant_id: window.RESTAURANT_ID,
                customer_name: getChatCustomerName()
            })
        });
        const data = await res.json();
        if (data.success) {
            // Tag the last local message with server ID to prevent duplication on poll
            const allMsgs = document.querySelectorAll('#chatMessages .chat-widget-msg');
            const last = allMsgs[allMsgs.length - 1];
            if (last) last.setAttribute('data-msg-id', data.data.id);
            sessionStorage.setItem('tittil_chat_id', data.data.id);
        }
    } catch {}
}

function addChatMessage(msg, type, msgId) {
    const container = document.getElementById('chatMessages');
    if (msgId && document.querySelector('[data-msg-id="' + msgId + '"]')) return;
    const div = document.createElement('div');
    div.className = 'chat-widget-msg ' + type;
    if (msgId) div.setAttribute('data-msg-id', msgId);
    const t = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    div.innerHTML = '<div class="chat-widget-bubble">' + esc(msg) + '</div><div class="chat-widget-time">' + t + '</div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

let chatPollTimer = null;
let chatSenderName = '';

function startChatPoll() {
    stopChatPoll();
    chatPollTimer = setInterval(loadChatHistory, 5000);
}

function stopChatPoll() {
    if (chatPollTimer) {
        clearInterval(chatPollTimer);
        chatPollTimer = null;
    }
}

async function loadChatHistory() {
    try {
        var sender = encodeURIComponent(getChatCustomerName());
        var res = await fetch('/api/customer/chat.php?limit=50&sender=' + sender + '&restaurant=' + RESTAURANT_SLUG, {
            credentials: 'include'
        });
        var data = await res.json();
        if (!data.success || !data.data.messages) return;
        data.data.messages.forEach(function(msg) {
            // Skip if already displayed (dedup by server ID)
            if (document.querySelector('[data-msg-id="' + msg.id + '"]')) return;
            const type = (msg.senderRole === 'ADMIN' || msg.senderRole === 'SUPERADMIN') ? 'theirs' : 'mine';
            addChatMessage(msg.message, type, msg.id);
        });
    } catch {}
}

// ── Presence (Heartbeat) ──────────────────────────
let presenceTimer = null;

function startPresence() {
    stopPresence();
    sendHeartbeat();
    presenceTimer = setInterval(sendHeartbeat, 10000);
}

function stopPresence() {
    if (presenceTimer) {
        clearInterval(presenceTimer);
        presenceTimer = null;
    }
    sendPresenceOffline();
}

async function sendHeartbeat() {
    try {
        await fetch('/api/customer/presence.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sender_name: getChatCustomerName(),
                restaurant: RESTAURANT_SLUG
            })
        });
    } catch {}
}

async function sendPresenceOffline() {
    try {
        await fetch('/api/customer/presence.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sender_name: getChatCustomerName(),
                restaurant: RESTAURANT_SLUG
            })
        });
    } catch {}
}

const _origToggleChat = toggleChat;
toggleChat = function() {
    _origToggleChat();
    if (chatWidgetOpen) {
        startPresence();
    } else {
        stopPresence();
    }
};

// ── Init ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderCartBadge();
    loadMenu();
    document.getElementById('search-input')?.addEventListener('input', applyFilter);
    document.getElementById('drawer-overlay')?.addEventListener('click', closeCart);
});
