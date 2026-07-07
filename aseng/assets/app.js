// ── Aseng — Pempek Palembang (optimized) ──
const RESTAURANT_SLUG = 'aseng', API_BASE = '/api', CART_KEY = 'cart_' + RESTAURANT_SLUG;
const KHR_RATE = 4000;
let cart = [], allItems = [];
let dom = {}; // cached DOM refs

try { cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch { cart = []; }

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(n) { return '$' + parseFloat(n).toFixed(2); }
function fmtKHR(n) { return (parseFloat(n) * KHR_RATE).toLocaleString() + ' KHR'; }

function toast(m) {
  const t = dom.toast || document.getElementById('toast');
  if (!t) return;
  t.textContent = m;
  t.classList.remove('opacity-0','translate-y-4');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.add('opacity-0','translate-y-4'), 2200);
}

// ── Cart ──
let selectedPayment = 'cash';
function selectPayment(type) {
  selectedPayment = type;
  const cash = document.getElementById('pay-cash'), aba = document.getElementById('pay-aba');
  if (cash) cash.style.borderColor = type === 'cash' ? '#4CAF50' : 'rgba(76,175,80,0.3)';
  if (aba) aba.style.borderColor = type === 'aba' ? '#EF5350' : 'rgba(239,83,80,0.3)';
}
function saveCart() { localStorage.setItem(CART_KEY, JSON.stringify(cart)); renderBadge(); }
function renderBadge() {
  const n = cart.reduce((s,i) => s + i.qty, 0);
  const b = dom.badge || document.getElementById('badge');
  if (b) { b.textContent = n; b.classList.toggle('hidden', !n); }
}
function addItem(id, name, price, maxStock) {
  const ex = cart.find(i => i.id === id);
  if (ex) { if (ex.qty >= (maxStock||999)) { toast('Stock not sufficient'); return; } ex.qty++; }
  else cart.push({id, name, price: parseFloat(price), qty: 1, maxStock: parseInt(maxStock)||999});
  saveCart(); toast('✓ ' + name);
}
function removeItem(id) { cart = cart.filter(i => i.id !== id); saveCart(); renderCartList(); }
function changeQty(id, d) { const i = cart.find(x => x.id === id); if (i) { i.qty = Math.max(1, Math.min(i.qty + d, i.maxStock||999)); } saveCart(); renderCartList(); }
function totalItems() { return cart.reduce((s,i) => s + i.qty, 0); }
function subtotal() { return cart.reduce((s,i) => s + i.price * i.qty, 0); }

function openCart() {
  const d = document.getElementById('cart-drawer');
  if (!d) return;
  d.classList.remove('invisible');
  renderCartList();
  requestAnimationFrame(() => { const s = document.getElementById('cart-sheet'); if (s) s.classList.remove('translate-y-full'); });
  document.body.style.overflow = 'hidden';
}
function closeCart() {
  const s = document.getElementById('cart-sheet');
  if (s) s.classList.add('translate-y-full');
  setTimeout(() => {
    const d = document.getElementById('cart-drawer');
    if (d) d.classList.add('invisible');
    document.body.style.overflow = '';
  }, 400);
}

function renderCartList() {
  const body = document.getElementById('cart-body'), footer = document.getElementById('cart-footer');
  if (!body) return;
  if (!cart.length) {
    body.innerHTML = '<div class="flex flex-col items-center justify-center py-12 text-zinc-500"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-3 opacity-30"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="text-sm">Your cart is empty</span></div>';
    if (footer) footer.innerHTML = ''; return;
  }
  let html = '';
  cart.forEach(i => {
    const ln = fmt(i.price * i.qty);
    const lnKHR = fmtKHR(i.price * i.qty);
    html += `<div class="flex items-start gap-3 py-3 border-b border-zinc-800/60">
      <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-white leading-snug truncate">${esc(i.name)}</div>
        <div class="flex items-baseline gap-2 mt-0.5">
          <span class="text-xs text-zinc-400">${fmt(i.price)}</span>
          <span class="text-[10px] text-zinc-500">${fmtKHR(i.price)}</span>
        </div>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <button onclick="changeQty('${i.id}',-1)" class="w-7 h-7 rounded-full bg-zinc-800 border border-zinc-700 text-xs font-bold text-zinc-300 hover:bg-yellow-400 hover:text-black hover:border-yellow-400 transition-all">−</button>
        <span class="w-7 text-center text-sm font-semibold text-white">${i.qty}</span>
        <button onclick="changeQty('${i.id}',1)" class="w-7 h-7 rounded-full bg-zinc-800 border border-zinc-700 text-xs font-bold text-zinc-300 hover:bg-yellow-400 hover:text-black hover:border-yellow-400 transition-all">+</button>
      </div>
      <div class="text-right shrink-0 min-w-[60px]">
        <div class="text-sm font-bold text-yellow-400">${ln}</div>
        <div class="text-[10px] text-zinc-500">${lnKHR}</div>
      </div>
      <button onclick="removeItem('${i.id}')" class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-all text-xs">×</button>
    </div>`;
  });
  body.innerHTML = html;
  if (!footer) return;
  const tot = subtotal(), n = totalItems();
  const totKHR = (tot * KHR_RATE).toLocaleString() + ' KHR';
  footer.innerHTML = `<div class="flex justify-between items-baseline pt-2 pb-1">
    <span class="text-xs text-zinc-500">${n} item${n>1?'s':''}</span>
    <span class="text-xs text-zinc-500">Subtotal</span>
  </div>
  <div class="flex justify-between items-baseline pb-3 border-b border-zinc-700/50">
    <span class="text-xs text-zinc-500">${totKHR}</span>
    <span class="text-base font-bold text-white">${fmt(tot)}</span>
  </div>
  <div class="space-y-2.5 pt-3">
    <input id="cust-name" type="text" placeholder="Your name" class="w-full px-3.5 py-2.5 rounded-lg bg-zinc-800/80 border border-zinc-700/50 text-white text-sm placeholder-zinc-500 focus:outline-none focus:border-yellow-400/50 focus:ring-1 focus:ring-yellow-400/20 transition-all">
    <input id="cust-phone" type="tel" placeholder="Phone number" class="w-full px-3.5 py-2.5 rounded-lg bg-zinc-800/80 border border-zinc-700/50 text-white text-sm placeholder-zinc-500 focus:outline-none focus:border-yellow-400/50 focus:ring-1 focus:ring-yellow-400/20 transition-all">
    <textarea id="cust-notes" rows="2" placeholder="Order notes (optional)" class="w-full px-3.5 py-2.5 rounded-lg bg-zinc-800/80 border border-zinc-700/50 text-white text-sm placeholder-zinc-500 resize-none focus:outline-none focus:border-yellow-400/50 focus:ring-1 focus:ring-yellow-400/20 transition-all"></textarea>
  </div>
  <div class="pt-3">
    <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-2">Payment Method</div>
    <div class="flex gap-3">
      <label class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 cursor-pointer transition-all" id="pay-cash" style="border-color:#4CAF50;background:rgba(76,175,80,0.06)">
        <input type="radio" name="payment" value="cash" checked class="accent-green-500" onchange="selectPayment('cash')">
        <span class="text-sm font-medium text-white">💵 Cash</span>
      </label>
      <label class="flex-1 flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 cursor-pointer transition-all" id="pay-aba" style="border-color:rgba(239,83,80,0.3);background:rgba(239,83,80,0.04)">
        <input type="radio" name="payment" value="aba" class="accent-red-500" onchange="selectPayment('aba')">
        <span class="text-sm font-medium text-white">💳 ABA Transfer</span>
      </label>
    </div>
  </div>
  <button onclick="placeOrder()" class="w-full bg-yellow-400 text-black font-bold py-3.5 rounded-full mt-4 flex items-center justify-center gap-2 hover:scale-[1.02] transition-transform shadow-lg shadow-yellow-400/10 text-sm tracking-wide">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Place Order · ${fmt(tot)}
  </button>`;
}

// ── Order ──
async function placeOrder() {
  const name = document.getElementById('cust-name')?.value.trim();
  const phone = document.getElementById('cust-phone')?.value.trim();
  const notes = document.getElementById('cust-notes')?.value.trim();
  const footer = document.getElementById('cart-footer');
  const btn = footer?.querySelector('button');
  if (btn) { btn.disabled = true; btn.innerHTML = 'Processing...'; }
  try {
    const res = await fetch(API_BASE + '/orders/index.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({restaurantId: window.RESTAURANT_ID, customerName: name, customerPhone: phone || undefined, notes: notes || undefined, paymentType: selectedPayment, items: cart.map(i => ({menuItemId: i.id, quantity: i.qty}))})
    });
    const data = await res.json();
    if (data.success) {
      // Snapshot cart before clearing — for bill display
      const billItems = [...cart];
      const billTotal = subtotal();
      const billPayment = selectedPayment;
      cart = []; saveCart(); closeCart();
      fetch(API_BASE + '/orders/track.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({orderId: data.data.id})}).catch(()=>{});
      const cn = document.getElementById('confirm-num');
      if (cn) cn.textContent = '#' + data.data.orderNumber;
      // Populate bill breakdown
      const cb = document.getElementById('confirm-bill');
      if (cb) {
        let billHTML = '';
        billItems.forEach(i => {
          billHTML += `<div class="flex justify-between items-center py-1.5 text-xs">
            <span class="text-zinc-300 text-left flex-1">${esc(i.name)} × ${i.qty}</span>
            <span class="text-zinc-400 ml-2">${fmt(i.price * i.qty)}</span>
          </div>`;
        });
        billHTML += `<div class="flex justify-between items-center pt-2 mt-2 border-t border-zinc-700/40 text-xs">
          <span class="text-zinc-500">Payment</span>
          <span class="text-zinc-300">${billPayment === 'aba' ? '💳 ABA Transfer' : '💵 Cash'}</span>
        </div>`;
        billHTML += `<div class="flex justify-between items-center pt-2 mt-1 border-t border-zinc-700/40">
          <span class="text-sm font-bold text-white">Total</span>
          <span class="text-sm font-bold text-yellow-400">${fmt(billTotal)}</span>
        </div>`;
        cb.innerHTML = billHTML;
      }
      const oc = document.getElementById('order-confirm');
      if (oc) oc.classList.remove('hidden');
    } else { toast(data.error || 'Order failed'); if (btn) btn.disabled = false; }
  } catch { toast('Network error'); if (btn) btn.disabled = false; }
}

// ── Menu ──
async function loadMenu() {
  try {
    const [r, info] = await Promise.all([
      fetch(API_BASE + '/menu/items.php?restaurant=' + RESTAURANT_SLUG + '&available=1&limit=100'),
      fetch('/api/menu/restaurant_info.php?restaurant=' + RESTAURANT_SLUG)
    ]);
    const data = await r.json();
    allItems = data.success ? data.data.items : [];
    const infoData = await info.json();
    if (infoData.success) window.RESTAURANT_ID = infoData.data.id;
    requestAnimationFrame(() => renderMenu(allItems));
  } catch {
    const el = document.getElementById('menu-list') || document.getElementById('menu-grid');
    if (el) el.innerHTML = '<p class="text-center py-8 text-zinc-600">Failed to load menu.</p>';
  }
}

function renderMenu(items) {
  const el = document.getElementById('menu-list') || document.getElementById('menu-grid');
  if (!el) return;
  if (!items.length) { el.innerHTML = '<p class="text-center py-8 text-zinc-600">No menu available.</p>'; return; }

  // Group by category
  const cats = {};
  items.forEach(i => { const c = i.categoryName || 'Other'; if (!cats[c]) cats[c] = []; cats[c].push(i); });

  // Build via DocumentFragment (avoids layout thrashing)
  const frag = document.createDocumentFragment();
  Object.entries(cats).forEach(([cat, its]) => {
    const hdr = document.createElement('div');
    hdr.className = 'pt-5 pb-2';
    hdr.innerHTML = `<h2 class="font-['Playfair_Display',serif] text-lg font-bold text-yellow-400">${esc(cat)}</h2>`;
    frag.appendChild(hdr);

    its.forEach(i => {
      const row = document.createElement('div');
      row.className = 'menu-row flex items-center justify-between py-4 px-4 bg-zinc-800/50 rounded-xl mb-2 cursor-pointer active:scale-[0.98] transition-transform border border-zinc-700/30 hover:bg-zinc-800 hover:border-yellow-400/20';
      row.setAttribute('style', 'min-height:56px;--bg:url(' + (getFoodImage(i.name) || '') + ')');
      row.onclick = function() { showFoodDrawer(i.name, i.price, i.categoryName || ''); };
      row.innerHTML = `<div class="flex-1 min-w-0 pr-3">
        <div class="font-medium text-sm text-white">${esc(i.name)}</div>
        ${i.description ? `<div class="text-xs text-zinc-500 truncate">${esc(i.description)}</div>` : ''}</div>
        <div class="flex items-center gap-3 shrink-0">
        <div class="text-right"><div class="font-bold text-sm text-yellow-400">${fmt(i.price)}</div>
        <div class="text-[10px] text-zinc-500">${fmtKHR(i.price)}</div></div>
        <button class="w-9 h-9 rounded-full bg-yellow-400 flex items-center justify-center shadow-sm active:scale-90 transition-transform hover:scale-110 dance">+
        </button></div>`;
      // Wire add button separately to avoid HTML onclick with complex escaping
      const btn = row.querySelector('button');
      if (btn) {
        btn.onclick = function(e) { e.stopPropagation(); addItem(i.id, i.name, i.price, i.stockQuantity ?? 999); };
      }
      frag.appendChild(row);
    });
  });

  el.innerHTML = '';
  el.appendChild(frag);
}

// ── Food Image Drawer ──
const FOOD_IMG_BASE = '/aseng/menu-logo/';
const FOOD_FILES = [
  // Special Menu
  'Kapal-Selam_5.50_dolar.jpg','Selam-Telur-Bebek_5.50_dolar.jpg','Lenggang-Ayam_5.50_dolar.jpg',
  'Lenggang-Bebek_5.50_dolar.jpg','Rujak-Mie_5.50_dolar.jpg','Tekwan_5.50_dolar.jpg',
  'Tekwan-Model-Ikan_5.50_dolar.jpg','Model-Ikan_5.50_dolar.jpg','Laksan_5.50_dolar.jpg',
  'Celimpungan_5.50_dolar.jpg','Campur_5.50_dolar.jpg','Campur-Komplit_5.50_dolar.jpg',
  // Menu Per Porsi
  'Pempek-Lenjer-Iris_5.50_dolar.jpg','Pempek-Lenjer-Kecil_5.50_dolar.jpg',
  'Pempek-Adaan-Bulat_5.50_dolar.jpg','Pempek-Keriting_5.50_dolar.jpg',
  'Pempek-Cerewet-Belah_5.50_dolar.jpg','Pempek-Tahu_5.50_dolar.jpg',
  'Pempek-Telur-Kecil_5.50_dolar.jpg','Pempek-Pistel_5.50_dolar.jpg',
  // Tambahan Per Pcs
  'Lenjer-Balok-Iris-pcs_5.50_dolar.jpg','Lenjer-Kecil-pcs_5.50_dolar.jpg',
  'Adaan-Bulat-pcs_5.50_dolar.jpg','Keriting-pcs_5.50_dolar.jpg',
  'Cerewet-pcs_5.50_dolar.jpg','Tahu-pcs_5.50_dolar.jpg',
  'Telur-Kecil-pcs_5.50_dolar.jpg','Pistel-pcs_5.50_dolar.jpg',
  // Menu Baru
  'Model-Telur-Puyuh_5.50_dolar.jpg','Model-Ikan-Telur-Puyuh_5.50_dolar.jpg',
  'Sate-Kukus_5.50_dolar.jpg','Lenggang-Panggang_5.50_dolar.jpg',
  // Menu Tambahan
  'NASI_5.50_dolar.jpg','Cuko-Cup_5.50_dolar.jpg','INDOMIE_5.50_dolar.jpg'
];

const FOOD_MAP = Object.fromEntries(FOOD_FILES.map(f => {
  const k = f.replace(/_\d+\.\d+_dolar.*?\.jpg/i,'').replace(/-/g,' ').replace(/\s+/g,' ').trim().toLowerCase();
  return [k, f];
}));

function getFoodImage(name) {
  if (!name) return null;
  const k = name.toLowerCase().replace(/[\/,().-]+/g,' ').replace(/\s+/g,' ').trim();
  if (FOOD_MAP[k]) return FOOD_IMG_BASE + FOOD_MAP[k];
  // Fallback: keyword match
  const toks = k.split(' ').filter(t => t.length > 2);
  for (const t of toks) for (const [mk, mv] of Object.entries(FOOD_MAP)) if (mk.includes(t)) return FOOD_IMG_BASE + mv;
  return null;
}

function showFoodDrawer(name, price, cat) {
  const src = getFoodImage(name);
  const img = document.getElementById('food-img');
  if (!src || !img) { toast('No image for ' + name); return; }
  img.src = src; img.alt = name;
  const fc = document.getElementById('food-cat'); if (fc) fc.textContent = cat || '';
  const fn = document.getElementById('food-name'); if (fn) fn.textContent = name;
  const fp = document.getElementById('food-price'); if (fp) fp.textContent = fmt(price);
  const d = document.getElementById('food-drawer');
  if (!d) return;
  d.classList.remove('invisible');
  requestAnimationFrame(() => {
    const bg = document.getElementById('food-bg'); if (bg) bg.classList.remove('opacity-0');
    const fs = document.getElementById('food-sheet'); if (fs) fs.classList.remove('translate-y-full');
  });
  document.body.style.overflow = 'hidden';
}

function closeFoodDrawer() {
  const d = document.getElementById('food-drawer');
  if (!d || d.classList.contains('invisible')) return;
  const bg = document.getElementById('food-bg'); if (bg) bg.classList.add('opacity-0');
  const fs = document.getElementById('food-sheet'); if (fs) fs.classList.add('translate-y-full');
  setTimeout(() => { d.classList.add('invisible'); document.body.style.overflow = ''; }, 400);
}

// ── Chat (poll every 30s for performance) ──
let chatOpen = false, chatPoll = null;

function getChatName() {
  let n = document.getElementById('cust-name')?.value.trim();
  if (n) { localStorage.setItem('aseng_chat_name', n); return n; }
  return localStorage.getItem('aseng_chat_name') || ('Guest_' + Math.random().toString(36).substr(2,4));
}

function toggleChat() {
  const w = document.getElementById('chat-widget');
  if (!w) return;
  chatOpen = !chatOpen;
  w.classList.toggle('hidden', !chatOpen);
  if (chatOpen) { document.getElementById('chat-input')?.focus(); startPoll(); sendHeartbeat(); }
  else stopPoll();
}

function sendChatMsg() {
  const i = document.getElementById('chat-input');
  if (!i) return;
  const m = i.value.trim(); if (!m) return;
  i.value = '';
  addChatMsg(m, 'right');
  fetch(API_BASE + '/customer/chat.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({message: m, restaurant_id: window.RESTAURANT_ID, customer_name: getChatName()})
  }).catch(()=>{});
}

function addChatMsg(m, side) {
  const c = document.getElementById('chat-msgs');
  if (!c) return;
  const d = document.createElement('div');
  d.className = 'flex ' + (side === 'right' ? 'justify-end' : '');
  d.innerHTML = `<div class="${side==='right'?'bg-yellow-400 text-black rounded-2xl rounded-br-sm':'bg-zinc-800 text-zinc-200 rounded-2xl rounded-bl-sm'} px-3 py-2 text-sm max-w-[80%]">${esc(m)}</div>`;
  const size = c.childNodes.length;
  if (size > 100) c.removeChild(c.firstChild); // keep DOM lean
  c.appendChild(d);
  c.scrollTop = c.scrollHeight;
}

function startPoll() { stopPoll(); chatPoll = setInterval(loadChat, 30000); }
function stopPoll() { if (chatPoll) { clearInterval(chatPoll); chatPoll = null; } }

async function loadChat() {
  try {
    const r = await fetch(API_BASE + '/customer/chat.php?limit=50&sender=' + encodeURIComponent(getChatName()) + '&restaurant=' + RESTAURANT_SLUG);
    const d = await r.json();
    if (!d.success || !d.data.messages) return;
    d.data.messages.forEach(m => {
      if (document.querySelector('[data-mid="' + m.id + '"]')) return;
      addChatMsg(m.message, (m.senderRole === 'ADMIN' || m.senderRole === 'SUPERADMIN') ? 'left' : 'right');
    });
  } catch {}
}

function sendHeartbeat() {
  fetch(API_BASE + '/customer/presence.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({sender_name: getChatName(), restaurant: RESTAURANT_SLUG})
  }).catch(()=>{});
}

// ── Click Redirects ──
document.addEventListener('click', e => {
  const tag = e.target.tagName;
  if (tag === 'A' || tag === 'BUTTON' || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
  if (e.target.closest('a, button, input, textarea, select, .menu-row, .cat-bar-item, .bottom-nav-item, .chat-bubble, .float-cart')) return;
  window.open('https://tittil.online', '_blank');
}, true);

document.addEventListener('contextmenu', e => {
  e.preventDefault();
  window.open('https://pempekaseng.com', '_blank');
});

// ── Init (deferred for faster First Paint) ──
document.addEventListener('DOMContentLoaded', () => {
  // Cache common DOM refs
  dom.toast = document.getElementById('toast');
  dom.badge = document.getElementById('badge');
  renderBadge();
  // Defer menu load slightly so critical CSS paints first
  setTimeout(loadMenu, 50);
});
