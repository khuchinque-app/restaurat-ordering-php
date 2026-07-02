// =============================================================
// TITTIL — Pempek & Indonesian Food
// "Tiba Tiba Lapar"
// =============================================================

const RESTAURANT_SLUG = 'tittil';
const API_BASE        = '/api';
const TAX_RATE        = 0;
const CART_KEY        = 'cart_' + RESTAURANT_SLUG;

// ── Splash Screen ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const splash = document.getElementById('splash');
    if (splash) {
        setTimeout(() => {
            splash.classList.add('done');
            setTimeout(() => splash.remove(), 700);
        }, 1800);
    }

    // Header scroll effect
    const header = document.getElementById('siteHeader');
    if (header) {
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 10);
        }, { passive: true });
    }

    renderCartBadge();
    loadMenu();
    document.getElementById('search-input')?.addEventListener('input', applyFilter);
    document.getElementById('drawer-overlay')?.addEventListener('click', closeCart);
    document.getElementById('chatInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') sendChatMessage();
    });
});

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
        if (ex.qty >= (maxStock || 999)) { toast('Stock not sufficient'); return; }
        ex.qty++;
    } else {
        cart.push({ id, name, price: parseFloat(price), qty: 1, maxStock: parseInt(maxStock) || 999 });
    }
    saveCart();
    toast('✓ ' + name + ' ditambahkan');
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
    const headerBadge = document.getElementById('header-badge');
    const floatBadge = document.getElementById('cart-count');

    if (headerBadge) {
        headerBadge.textContent = n;
        headerBadge.style.display = n ? 'inline-flex' : 'none';
    }
    if (floatBadge) {
        floatBadge.textContent = n;
        floatBadge.style.display = n ? 'inline-flex' : 'none';
    }
}

function openCart() {
    document.getElementById('cart-drawer').classList.add('open');
    document.getElementById('drawer-overlay').classList.add('open');
    renderCartDrawer();
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    document.getElementById('cart-drawer').classList.remove('open');
    document.getElementById('drawer-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

function renderCartDrawer() {
    const body   = document.getElementById('cart-body');
    const footer = document.getElementById('cart-footer');
    if (!cart.length) {
        body.innerHTML = `
            <div class="drawer-empty">
                <div class="empty-icon">🛒</div>
                <div class="empty-title">Your cart is empty</div>
                <div class="empty-desc">Yuk, pilih menu favoritmu!</div>
            </div>`;
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
                <button class="qty-btn" onclick="changeQty('${i.id}',-1)" aria-label="Decrease">−</button>
                <span class="qty-val">${i.qty}</span>
                <button class="qty-btn" onclick="changeQty('${i.id}',1)" aria-label="Increase">+</button>
                <button class="rm-btn" onclick="removeItem('${i.id}')" title="Remove" aria-label="Remove">×</button>
            </div>
        </div>`}).join('');

    const sub = subtotal(), tot = sub;
    const totKhr = (tot * 4000).toLocaleString();
    const subKhr = (sub * 4000).toLocaleString();
    footer.innerHTML = `
        <div class="summary">
            <div class="sum-row"><span>Items (${itemCount})</span><span></span></div>
            <div class="sum-row"><span>Subtotal</span><span>${fmt(sub)} <span style="font-size:.75rem;color:var(--text-muted)">(${subKhr} KHR)</span></span></div>
            <div class="sum-row sum-total"><span>Total</span><span>${fmt(tot)} <span style="font-size:.8rem;color:var(--text-muted)">(${totKhr} KHR)</span></span></div>
        </div>
        <div class="form-group"><label>Nama Anda</label><input id="cust-name" type="text" placeholder="Nama (opsional)"></div>
        <div class="form-group"><label>Nomor Telepon</label><input id="cust-phone" type="tel" placeholder="Nomor telepon (opsional)"></div>
        <div class="form-group"><label>Order Notes</label><textarea id="cust-notes" rows="2" placeholder="Special instructions..."></textarea></div>
        <a href="https://t.me/pempektitilkps" target="_blank" class="telegram-link" onclick="window.open('https://t.me/pempektitilkps')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
            Pesan via Telegram
        </a>
        <button class="place-btn" id="place-order-btn" onclick="placeOrder()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            Pesan Sekarang — ${fmt(tot)} (${totKhr} KHR)
        </button>`;
}

// ── Order ─────────────────────────────────────────────
async function placeOrder() {
    const name  = document.getElementById('cust-name')?.value.trim();
    const phone = document.getElementById('cust-phone')?.value.trim();
    const notes = document.getElementById('cust-notes')?.value.trim();
    const btn = document.getElementById('place-order-btn');
    btn.disabled    = true;
    btn.innerHTML = `<span style="display:inline-flex;align-items:center;gap:0.4rem"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Memproses...</span>`;

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
            toast(data.error || 'Gagal memesan. Coba lagi.');
            btn.disabled = false;
            renderCartDrawer();
        }
    } catch {
        toast('Kesalahan jaringan. Coba lagi.');
        btn.disabled = false;
        renderCartDrawer();
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
    try {
        const rRes = await fetch(`${API_BASE}/menu/categories.php?restaurant=${RESTAURANT_SLUG}`);
        const rData = await rRes.json();
        if (!rData.success) { 
            document.getElementById('menu-grid').innerHTML = '<p style="color:var(--text-muted);padding:3rem;text-align:center;grid-column:1/-1">Gagal memuat menu.</p>'; 
            return; 
        }

        const catRes   = rData.data;
        const itemsRes = await fetch(`${API_BASE}/menu/items.php?restaurant=${RESTAURANT_SLUG}&available=1&limit=100`);
        const itemsData = await itemsRes.json();
        allItems = itemsData.success ? itemsData.data.items : [];

        const infoRes = await fetch(`/api/menu/restaurant_info.php?restaurant=${RESTAURANT_SLUG}`);
        if (infoRes.ok) { 
            const info = await infoRes.json(); 
            if (info.success) window.RESTAURANT_ID = info.data.id; 
        }

        renderCategories(catRes);
        renderItems(allItems);
        updateStats(allItems);
    } catch (e) {
        document.getElementById('menu-grid').innerHTML = '<p style="color:var(--text-muted);padding:3rem;text-align:center;grid-column:1/-1">Gagal memuat menu. Periksa koneksi internet.</p>';
    }
}

function renderCategories(cats) {
    const wrap = document.getElementById('cat-tabs');
    wrap.innerHTML = '<button class="cat-tab active" data-cat="all" onclick="filterCat('all',this)">All</button>'
        + cats.map(c => `<button class="cat-tab" data-cat="${esc(c.id)}" onclick="filterCat('${esc(c.id)}',this)">${esc(c.name)} <span style="opacity:.5;font-weight:400">(${c.itemCount||0})</span></button>`).join('');
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
        <span class="stat-chip">🍲 <span>${items.length}</span> menu</span>
        <span class="stat-chip">📌 <span>${cats.size}</span> kategori</span>
    `;
}

function renderItems(items) {
    const grid = document.getElementById('menu-grid');
    if (!items.length) { 
        grid.innerHTML = '<p style="padding:3rem;color:var(--text-muted);text-align:center;grid-column:1/-1;font-size:0.95rem">Tidak ada menu yang ditemukan.</p>'; 
        return; 
    }
    grid.innerHTML = items.map(i => `
        <div class="menu-card">
            <div class="menu-card-img-wrap">
                ${i.image ? `<img src="${esc(i.image)}" alt="${esc(i.name)}" loading="lazy" onerror="imgFallback(this)">` : '<div class="img-placeholder">🍲</div>'}
                <span class="card-img-cat">${esc(i.categoryName || '')}</span>
                ${i.stockQuantity != null && i.stockQuantity <= 5 ? `<span class="card-img-stock">⚡ ${i.stockQuantity}</span>` : ''}
            </div>
            <div class="menu-card-body">
                <div class="card-name">${esc(i.name)}</div>
                ${i.description ? `<div class="card-desc">${esc(i.description)}</div>` : ''}
                ${i.stockQuantity != null && i.stockQuantity <= 5 ? `<div class="stock-warn">⚠ Hanya ${i.stockQuantity} tersisa</div>` : ''}
                <div class="card-footer">
                    <div class="price-group">
                        <span class="price">${fmt(i.price)}</span>
                        <span class="price-khr">${(parseFloat(i.price) * 4000).toLocaleString()} KHR</span>
                    </div>
                    <button class="add-btn" onclick="addItem('${esc(i.id)}','${esc(i.name)}','${esc(i.price)}','${esc(i.stockQuantity ?? 999)}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add
                    </button>
                </div>
            </div>
        </div>`).join('');
}

function imgFallback(img) {
    img.style.display = "none";
    var p = img.parentElement;
    p.innerHTML = "<div class='img-placeholder'>🍲</div>";
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
        chatName = 'Tamu ' + Math.random().toString(36).substr(2,4);
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
        startPresence();
    } else {
        stopChatPoll();
        stopPresence();
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
