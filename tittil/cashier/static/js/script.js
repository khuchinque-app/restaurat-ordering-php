// =============================================================================
// KhuChinQue POS SYSTEM - MAIN SCRIPT
// =============================================================================
// Version: 2.3
// Features: Order management, calculator, image upload, drag & drop,
//           driver panels, simple confirm deletion, admin action telemetry,
//           STRUCTURED MENU INPUT with searchable dropdown,
//           per-line price + count tabs, menu.txt add via UI
// =============================================================================


// =============================================================================
// SECTION 1: GLOBAL VARIABLES & CONFIGURATION
// =============================================================================

let currentOrderNo = 0;
let editingId = null;
let imageStore = {};
let MENU_DB = {};
let rowCounter = 0;

// Server-side API endpoint for cross-browser persistence
const API_ENDPOINT = './static/js/api.php';

const floatingPreview  = document.getElementById('floatingImagePreview');
const floatingImg      = floatingPreview ? floatingPreview.querySelector('img') : null;
const floatingDeleteBtn = document.getElementById('floatingDeleteBtn');

let currentHoverItemId = null;
let hideTimeout = null;


// =============================================================================
// SECTION 1.5: MENU DATABASE & FILE LOADER
// =============================================================================

async function loadMenuFromFile() {
    try {
        const response = await fetch('menu.txt');
        if (!response.ok) throw new Error('menu.txt not found');
        const text = await response.text();
        parseMenuText(text);
        console.log('[Menu] Loaded', Object.keys(MENU_DB).length, 'items');
    } catch (err) {
        console.warn('[Menu] Could not load menu.txt:', err);
        MENU_DB = {};
    }
}

function parseMenuText(text) {
    MENU_DB = {};
    text.split('\n').forEach(line => {
        line = line.trim();
        if (!line) return;
        // Allow optional trailing text after price (e.g. "Cuko 2k / Cup")
        const match = line.match(/^(.+?)\s+(\d+(?:[.,]\d+)?)\s*[kK]?(?:\s+\S.*)?$/);
        if (match) {
            const name = match[1].trim();
            let price = parseFloat(match[2].replace(',', '.'));
            // Check if k/K appears right after the captured price digits
            // (looks at the suffix in the match rather than the full line, so "Kapal" or "Kecil"
            //  in the item name don't cause false positives)
            const afterPrice = match[0].slice(match[0].lastIndexOf(match[2]) + match[2].length);
            if (/^\s*[kK]/.test(afterPrice)) {
                price *= 1000;
            } else if (price < 1000) {
                price *= 1000;
            }
            MENU_DB[name] = price;
        }
    });
    populateAllDropdowns();
}



// =============================================================================
// SECTION 1.6: SERVER-SIDE API HELPERS (Cross-browser persistence)
// =============================================================================

async function apiSaveState(data) {
    try {
        const res = await fetch(API_ENDPOINT + '?action=save_state', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data })
        });
        const json = await res.json();
        if (json.ok) return true;
        console.warn('[API] save_state failed:', json.error);
        return false;
    } catch (err) {
        console.warn('[API] save_state unreachable:', err.message);
        return false;
    }
}

async function apiLoadState() {
    try {
        const res = await fetch(API_ENDPOINT + '?action=load_state');
        const json = await res.json();
        if (json.ok && json.data) return json.data;
        return null;
    } catch (err) {
        console.warn('[API] load_state unreachable:', err.message);
        return null;
    }
}

async function apiSaveOrderNo(orderNo, lastResetDate) {
    try {
        const res = await fetch(API_ENDPOINT + '?action=save_order_no', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_no: orderNo, last_reset_date: lastResetDate })
        });
        return (await res.json()).ok === true;
    } catch (err) {
        console.warn('[API] save_order_no unreachable:', err.message);
        return false;
    }
}

async function apiLoadOrderNo() {
    try {
        const res = await fetch(API_ENDPOINT + '?action=load_order_no');
        const json = await res.json();
        if (json.ok && json.data) return json.data;
        return null;
    } catch (err) {
        console.warn('[API] load_order_no unreachable:', err.message);
        return null;
    }
}

async function apiSaveDriverName(driver, name) {
    try {
        const res = await fetch(API_ENDPOINT + '?action=save_driver_name', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ driver, name })
        });
        return (await res.json()).ok === true;
    } catch (err) {
        console.warn('[API] save_driver_name unreachable:', err.message);
        return false;
    }
}

async function apiLoadDriverNames() {
    try {
        const res = await fetch(API_ENDPOINT + '?action=load_driver_names');
        const json = await res.json();
        if (json.ok && json.data) return json.data;
        return null;
    } catch (err) {
        console.warn('[API] load_driver_names unreachable:', err.message);
        return null;
    }
}


// =============================================================================
// SECTION 2: ADMIN ACTION TELEMETRY
// =============================================================================

async function reportAdminAction(actionType, orderNo, totalAmount, customerName = '', customerAddress = '') {
    try {
        await fetch(API_ENDPOINT + '?action=report_deleted_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                orderNo:         orderNo,
                total:           totalAmount,
                action:          actionType,
                customerName:    customerName,
                customerAddress: customerAddress,
                plainText:       `Admin triggered ${actionType} on Order #${orderNo}`
            })
        });
        console.log(`[Telemetry] Successfully recorded ${actionType} for #${orderNo}`);
    } catch (err) {
        console.error('Database sync failed:', err);
    }
}


// =============================================================================
// SECTION 3: CLOCK & ORDER NUMBER MANAGEMENT
// =============================================================================

function updateTimeAndOrder() {
    const now = new Date();
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-GB', {
        day: 'numeric', month: 'numeric', year: 'numeric'
    });
    document.getElementById('liveTime').innerText = now.toLocaleTimeString('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    const today = now.toDateString();
    if (localStorage.getItem('lastResetDate') !== today) {
        currentOrderNo = 0;
        localStorage.setItem('lastResetDate', today);
        saveOrderNo();
    }
    if (!localStorage.getItem('orderNo')) {
        localStorage.setItem('orderNo', '0');
    }
}

async function initializeOrderNumber() {
    // Try server first for cross-browser consistency
    const serverData = await apiLoadOrderNo();
    if (serverData && serverData.order_no !== undefined) {
        currentOrderNo = parseInt(serverData.order_no);
        localStorage.setItem('orderNo', currentOrderNo);
        if (serverData.last_reset_date) {
            localStorage.setItem('lastResetDate', serverData.last_reset_date);
        }
    } else if (localStorage.getItem('orderNo')) {
        currentOrderNo = parseInt(localStorage.getItem('orderNo'));
        // Sync local to server
        apiSaveOrderNo(currentOrderNo, localStorage.getItem('lastResetDate') || '');
    }
    document.getElementById('displayOrderNo').innerText = String(currentOrderNo).padStart(3, '0');
}

function incrementOrderNo() {
    currentOrderNo++;
    saveOrderNo();
    document.getElementById('displayOrderNo').innerText = String(currentOrderNo).padStart(3, '0');
    return String(currentOrderNo).padStart(3, '0');
}

function saveOrderNo() {
    localStorage.setItem('orderNo', currentOrderNo);
    apiSaveOrderNo(currentOrderNo, localStorage.getItem('lastResetDate') || '');
}

function manualResetOrderNo() {
    const input = prompt("Set new Order Number (0 to reset):", currentOrderNo);
    if (input !== null && input.trim() !== "") {
        const num = parseInt(input);
        if (!isNaN(num) && num >= 0) {
            currentOrderNo = num;
            saveOrderNo();
            document.getElementById('displayOrderNo').innerText = String(currentOrderNo).padStart(3, '0');
        }
    }
}

setInterval(updateTimeAndOrder, 1000);
updateTimeAndOrder();
initializeOrderNumber();


// =============================================================================
// SECTION 4: CALCULATOR FUNCTIONALITY
// =============================================================================

const calcDisplay = document.getElementById('calcDisplay');
let lastResult = '';

function calcAppend(val) {
    calcDisplay.value += val;
    calcDisplay.scrollLeft = calcDisplay.scrollWidth;
}

function calcClear() {
    calcDisplay.value = '';
    lastResult = '';
}

function calcSolve() {
    try {
        const expression = calcDisplay.value;
        const result = eval(expression);
        calcDisplay.value = result;
        lastResult = result.toString();
        const historyDiv = document.getElementById('calcHistory');
        historyDiv.innerHTML = `<div>${expression} = ${result}</div>` + historyDiv.innerHTML;
    } catch {
        calcDisplay.value = lastResult || '';
    }
}

document.addEventListener('keydown', e => {
    const activeElementId = document.activeElement.id;
    const activeClass = document.activeElement.classList;
    if (['inputBox', 'customerName', 'customerAddress'].includes(activeElementId) ||
        activeClass.contains('menu-search') ||
        activeClass.contains('menu-add-input') ||
        activeClass.contains('menu-add-price')) return;

    if (/[0-9.+\-*/]/.test(e.key)) calcAppend(e.key);
    if (e.key === 'Enter')     calcSolve();
    if (e.key === 'Backspace') calcDisplay.value = calcDisplay.value.slice(0, -1);
    if (e.key === 'Escape')    calcClear();
});


// =============================================================================
// SECTION 5: DATA PERSISTENCE (LocalStorage)
// =============================================================================

function saveData() {
    const data = {
        history:  document.getElementById('historyContainer').innerHTML,
        thorn:    document.getElementById('thornContainer').innerHTML,
        dom:      document.getElementById('domContainer').innerHTML,
        pozzal:   document.getElementById('pozzalContainer').innerHTML,
        etc:      document.getElementById('etcContainer').innerHTML,
        extra:    document.getElementById('extraContainer').innerHTML,
        finished: document.getElementById('finishedContainer').innerHTML,
        images:   imageStore
    };
    const json = JSON.stringify(data);
    localStorage.setItem('appData', json);
    apiSaveState(json); // fire-and-forget server sync
}

async function loadData() {
    // Try loading from server first (cross-browser persistence)
    const serverData = await apiLoadState();
    const sourceData = serverData || localStorage.getItem('appData');

    if (sourceData) {
        try {
            const parsed = JSON.parse(sourceData);
            document.getElementById('historyContainer').innerHTML  = parsed.history  || '';
            document.getElementById('thornContainer').innerHTML    = parsed.thorn    || '';
            document.getElementById('domContainer').innerHTML      = parsed.dom      || '';
            document.getElementById('pozzalContainer').innerHTML   = parsed.pozzal   || '';
            document.getElementById('etcContainer').innerHTML      = parsed.etc      || '';
            document.getElementById('extraContainer').innerHTML    = parsed.extra    || '';
            document.getElementById('finishedContainer').innerHTML = parsed.finished || '';
            imageStore = parsed.images || {};

            document.querySelectorAll('.history-item').forEach(item => {
                updateButtonsForContainer(item);
            });

            reattachEvents();
            updateTotals();

            document.querySelectorAll('.history-item').forEach(item => {
                const id = item.id.replace('hist-', '');
                if (imageStore[id]) item.classList.add('has-image');
            });

            // If data came from localStorage but not server, push it up
            if (!serverData) {
                apiSaveState(sourceData);
            }
        } catch (e) {
            console.error('[LoadData] Failed to parse saved data:', e);
        }
    }

    // Always load driver names from server (regardless of saved state data)
    loadDriverNames();
}

function reattachEvents() {
    document.querySelectorAll('.history-item').forEach(item => {
        const id = item.id.replace('hist-', '');
        item.draggable = true;
        item.addEventListener('dragstart', drag);
        item.onclick = (e) => {
            if (!e.target.tagName.match(/BUTTON/i)) restoreBill(id);
        };

        const checkBtn   = item.querySelector('.check-btn');
        if (checkBtn)   checkBtn.onclick   = () => toggleCheck(id);

        const editBtn    = item.querySelector('.edit-btn');
        if (editBtn)    editBtn.onclick    = () => editHistory(id);

        const delBtn     = item.querySelector('.del-btn');
        if (delBtn)     delBtn.onclick     = () => removeHistory(id);

        const archiveBtn = item.querySelector('.archive-btn');
        if (archiveBtn) archiveBtn.onclick = () => toggleArchive(id);

        const moveBtn    = item.querySelector('.move-btn');
        if (moveBtn)    moveBtn.onclick    = () => moveToDelivery(id);

        const finishBtn  = item.querySelector('.finish-btn');
        if (finishBtn)  finishBtn.onclick  = () => moveToFinished(id);

        const splitBtn   = item.querySelector('.split-btn');
        if (splitBtn)   splitBtn.onclick   = () => splitOrder(id);

        const uploadBtn  = item.querySelector('.upload-btn');
        if (uploadBtn)  uploadBtn.onclick  = (e) => {
            e.stopPropagation();
            showUploadModal(id);
        };
    });
    attachHoverListeners();
}

loadData();


// =============================================================================
// SECTION 6: FLOATING IMAGE PREVIEW
// =============================================================================

function attachHoverListeners() {
    document.querySelectorAll('.history-item').forEach(item => {
        item.removeEventListener('mouseenter', onItemMouseEnter);
        item.removeEventListener('mouseleave', onItemMouseLeave);
        item.addEventListener('mouseenter', onItemMouseEnter);
        item.addEventListener('mouseleave', onItemMouseLeave);
    });
}

function onItemMouseEnter(e) {
    const item = e.currentTarget;
    const id   = item.id.replace('hist-', '');
    if (imageStore[id]) {
        currentHoverItemId = id;
        floatingImg.src    = imageStore[id];
        const rect         = item.getBoundingClientRect();
        floatingPreview.style.top     = rect.top + 'px';
        floatingPreview.style.left    = (rect.right + 10) + 'px';
        floatingPreview.style.display = 'block';
    }
}

function onItemMouseLeave() {
    hideTimeout = setTimeout(() => {
        if (!floatingPreview.matches(':hover')) {
            floatingPreview.style.display = 'none';
            currentHoverItemId = null;
        }
    }, 200);
}

if (floatingPreview) {
    floatingPreview.addEventListener('mouseenter', () => {
        if (hideTimeout) { clearTimeout(hideTimeout); hideTimeout = null; }
    });
    floatingPreview.addEventListener('mouseleave', () => {
        floatingPreview.style.display = 'none';
        currentHoverItemId = null;
    });
}

if (floatingDeleteBtn) {
    floatingDeleteBtn.onclick = () => {
        if (currentHoverItemId) {
            deleteImage(currentHoverItemId);
            floatingPreview.style.display = 'none';
        }
    };
}


// =============================================================================
// SECTION 7: RECEIPT GENERATION & PROCESSING
// =============================================================================

// ── Style injection ───────────────────────────────────────────────────────────
// Called once; safe to call multiple times (id-guarded).
function initBillStyles() {
    if (document.getElementById('kq-bill-styles')) return;
    const s = document.createElement('style');
    s.id = 'kq-bill-styles';
    s.textContent = `
/* ── Per-line item row ─────────────────────────────────────── */
.bill-item-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    line-height: 1.6;
}
.bill-item-desc {
    flex: 1;
    min-width: 0;
    overflow-wrap: break-word;
    word-break: normal;
    padding-left: 1.2em;
    text-indent: -1.2em;
}
.bill-item-price {
    flex-shrink: 0;
    min-width: 52px;
    text-align: right;
    font-weight: 700;
    white-space: nowrap;
}
/* ── Per-portion unit price sub-line ─────────────────────── */
.bill-unit-price {
    padding-left: 1.2em;
    font-size: 0.85em;
    color: #666;
    font-style: italic;
    line-height: 1.2;
    margin-top: -2px;
}
/* ── Full-width horizontal separator (replaces --- text) ───── */
.bill-separator {
    border: none;
    border-top: 1px solid rgba(0,0,0,0.2);
    margin: 5px 0;
    height: 0;
    overflow: hidden;
    color: transparent;
    font-size: 0;
}

/* ── Menu "+" add row ──────────────────────────────────────── */
.menu-add-row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 5px 4px;
    border-top: 1px dashed rgba(255,255,255,0.18);
    margin-top: 3px;
}
.menu-add-input {
    flex: 1;
    min-width: 0;
    background: #ffffff;
    border: 1px solid #bdc3c7;
    color: #2c3e50;
    font-size: 11px;
    padding: 3px 6px;
    border-radius: 3px;
    outline: none;
    font-family: inherit;
}
.menu-add-input:focus {
    border-color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.12);
}
.menu-add-price {
    width: 42px;
    background: #ffffff;
    border: 1px solid #bdc3c7;
    color: #2c3e50;
    font-size: 11px;
    padding: 3px 4px;
    border-radius: 3px;
    outline: none;
    text-align: center;
    font-family: inherit;
}
.menu-add-price:focus {
    border-color: rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.12);
}
.menu-add-btn {
    background: #27ae60;
    color: #fff;
    border: none;
    border-radius: 3px;
    width: 22px;
    height: 22px;
    font-size: 17px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    flex-shrink: 0;
    font-weight: 700;
    transition: background 0.15s;
}
.menu-add-btn:hover  { background: #219653; }
.menu-add-btn:active { background: #1a7a42; }
.menu-add-feedback {
    font-size: 10px;
    color: #27ae60;
    padding: 2px 5px 3px;
    text-align: center;
    animation: kqFadeOut 1.8s forwards;
}
@keyframes kqFadeOut {
    0%   { opacity: 1; }
    60%  { opacity: 1; }
    100% { opacity: 0; }
}
    `;
    document.head.appendChild(s);
}

// ── Shared price/qty extraction (FIXED: end-anchor $ prevents mid-description
//    matches e.g. "2KERITING" being read as 2k instead of the real price) ─────

function _extractLinePrice(l) {
    const kMatch = l.match(/(\d+[.,]?\d*)[kK]\s*$/i);
    if (kMatch) return parseFloat(kMatch[1].replace(',', '.')) * 1000;
    const numMatch = l.match(/(\d+[.,]?\d*)\s*$/);
    if (numMatch) {
        const n = parseFloat(numMatch[1].replace(',', '.'));
        return n * (n < 100 ? 1000 : 1);
    }
    return 0;
}

function _extractLineQty(l) {
    const startMatch = l.match(/^(\d+)/);
    if (startMatch) return parseInt(startMatch[1]);
    const endMatch = l.match(/\(\s*(\d+)\s*\)$/);
    return endMatch ? parseInt(endMatch[1]) : 1;
}

// Split a raw order line into [descriptionText, priceToken].
// Uses greedy .* so it always finds the LAST price-like token at end of line.
// e.g. "1 PEMPEK MIX (2L, 2KERITING, 32K" → ["1 PEMPEK MIX (2L, 2KERITING,", "32K"]
// e.g. "6 KAPAL SELAM 84K"                 → ["6 KAPAL SELAM", "84K"]
// e.g. "1 ITEM NO PRICE"                   → ["1 ITEM NO PRICE", null]
function _splitDescPrice(l) {
    // Price with K/k suffix at end of line
    const km = l.match(/^(.*)\s+(\d+[.,]?\d*[kK])\s*$/i);
    if (km) return [km[1].trim(), km[2].toUpperCase()];
    // Plain large number at end (≥ 4 digits, e.g. 14000)
    const nm = l.match(/^(.*)\s+(\d{4,})\s*$/);
    if (nm) return [nm[1].trim(), nm[2]];
    return [l.trim(), null];
}

// ── Copy receipt ──────────────────────────────────────────────────────────────

function copyReceipt() {
    const output = document.getElementById('outputBox');
    if (output.style.display === 'none') { alert('No receipt to copy!'); return; }
    const billContent  = document.getElementById('billContent');
    const orderNoMatch = billContent.innerHTML.match(/\*order\s*(\d+)/);
    const totalMatch   = billContent.innerHTML.match(/TOTAL\s*:\s*([\d.,]+)/);
    if (orderNoMatch && totalMatch) {
        const orderNo = orderNoMatch[1].padStart(3, '0');
        const total   = parseFloat(totalMatch[1].replace(/\./g, '').replace(/,/g, '.')) || 0;
        reportAdminAction("COPIED_BILL", orderNo, total);
    }
    navigator.clipboard.writeText(output.innerText).then(() => {
        alert('Receipt copied to clipboard!');
    });
}

// ── Process order ─────────────────────────────────────────────────────────────

function processOrder() {
    let raw = '';
    const menuContainer = document.getElementById('menuInputContainer');
    if (menuContainer && menuContainer.querySelectorAll('.menu-row').length > 0) {
        raw = buildRawTextFromRows();
        const inputBox = document.getElementById('inputBox');
        if (inputBox) inputBox.value = raw;
    } else {
        raw = document.getElementById('inputBox').value.trim();
    }

    if (!raw) { alert("Enter order first!"); return; }

    const name  = document.getElementById('customerName').value.trim();
    const addr  = document.getElementById('customerAddress').value.trim();
    const notes = document.getElementById('notes').value.trim();

    const lines    = raw.split('\n');
    const now      = new Date();
    const dateTime = now.toLocaleDateString('en-GB', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    }) + ', ' + now.toLocaleTimeString('en-GB', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    let total = 0, items = 0, orderNo;

    if (editingId) {
        const item = document.getElementById(`hist-${editingId}`);
        if (!item) return;
        orderNo = item.dataset.orderNo;
    } else {
        orderNo = incrementOrderNo();
    }

    const calculationResult = calculateOrderTotals(lines);
    total = calculationResult.total;
    items = calculationResult.items;

    let billHTML  = buildReceiptHTML(orderNo, dateTime, name, addr, notes, lines);
        billHTML += buildReceiptFooter(items, total);
    let plainText = buildReceiptText(orderNo, dateTime, name, addr, notes, lines, total, items);

    document.getElementById('billContent').innerHTML = billHTML;
    document.getElementById('outputBox').style.display = 'block';

    const historyItem = createHistoryItem(orderNo, billHTML, plainText, total, items, name, addr, notes, raw);

    if (editingId) {
        const oldItem = document.getElementById(`hist-${editingId}`);
        if (oldItem) {
            if (imageStore[editingId]) {
                const newId = historyItem.id.replace('hist-', '');
                imageStore[newId] = imageStore[editingId];
                delete imageStore[editingId];
                historyItem.classList.add('has-image');
            }
            const parent = oldItem.parentElement;
            parent.replaceChild(historyItem, oldItem);
            editingId = null;
        }
    } else {
        document.getElementById('historyContainer').appendChild(historyItem);
    }

    updateButtonsForContainer(historyItem);
    reattachEvents();

    document.getElementById('inputBox').value         = '';
    document.getElementById('customerName').value     = '';
    document.getElementById('customerAddress').value  = '';
    document.getElementById('notes').value            = '';

    if (menuContainer) {
        menuContainer.innerHTML = '';
        rowCounter = 0;
        addMenuRow();
    }

    saveData();
}

// ── Build receipt HTML (price extracted and pushed to right column) ───────────

function buildReceiptHTML(orderNo, dateTime, name, addr, notes, lines) {
    initBillStyles();
    let html = '';
    html += `<div class="bill-info">*order ${orderNo}</div>`;
    html += `<div class="bill-info">*${dateTime}</div>`;
    html += `<br>`;

    if (name)  html += `<div class="bill-line">CUSTOMER: ${name.toUpperCase()}</div>`;
    if (addr)  html += `<div class="bill-line">ADDRESS: ${addr.toUpperCase()}</div>`;
    if (notes) html += `<div class="bill-line">NOTES: ${notes.toUpperCase()}</div>`;
    if (name || addr || notes) html += `<br>`;

    html += `<div class="bill-separator">-----------------------------------</div>`;

    lines.forEach(line => {
        const l = line.trim();
        if (!l) return;
        if (l.toLowerCase().includes('total') || l.toLowerCase().includes('subtotal')) return;

        const [desc, priceToken] = _splitDescPrice(l);

        html += `<div class="bill-item-row">`;
        html += `<div class="bill-item-desc">- ${desc.toUpperCase()}</div>`;
        if (priceToken) html += `<div class="bill-item-price">${priceToken}</div>`;
        html += `</div>`;

        // Always show per-portion rate below the line (with item name)
        if (priceToken) {
            const totalPrice = _extractLinePrice(l);
            const qty = _extractLineQty(l);
            const unitPrice = qty > 0 ? Math.round(totalPrice / qty) : totalPrice;
            const unitPriceFmt = (unitPrice >= 1000 && unitPrice % 1000 === 0)
                ? `${unitPrice / 1000}k`
                : String(unitPrice);
            const itemName = desc.replace(/^\d+\s*/, '').trim();
            html += `<div class="bill-unit-price">@${itemName} ${unitPriceFmt}</div>`;
        }

        html += `<br>`;
    });

    if (html.endsWith('<br>')) html = html.slice(0, -4);
    return html;
}

// ── Receipt footer ────────────────────────────────────────────────────────────

function buildReceiptFooter(items, total) {
    let html = '';
    html += `<div class="bill-separator">-----------------------------------</div>`;
    html += `<br>`;
    html += `<div class="bill-summary">* ITEM = ${items} items</div>`;
    html += `<div class="bill-total">* TOTAL : ${total.toLocaleString('id-ID')} / $${(total / 4000).toFixed(2)}$</div>`;
    return html;
}

// ── Calculate totals (uses same fixed extraction as buildReceiptHTML) ──────────

function calculateOrderTotals(lines) {
    let total = 0, items = 0;
    lines.forEach(line => {
        const l = line.trim();
        if (!l || l.toLowerCase().includes('total')) return;
        const qty   = _extractLineQty(l);
        const price = _extractLinePrice(l);
        if (price > 0) { items += qty; total += price; }
    });
    return { total, items };
}

// ── Plain-text receipt (for clipboard) ───────────────────────────────────────

function buildReceiptText(orderNo, dateTime, name, addr, notes, lines, total, items) {
    let text = '';
    text += `*order ${orderNo}\n`;
    text += `*${dateTime}\n\n`;
    if (name)  text += `CUSTOMER: ${name.toUpperCase()}\n`;
    if (addr)  text += `ADDRESS: ${addr.toUpperCase()}\n`;
    if (notes) text += `NOTES: ${notes.toUpperCase()}\n`;
    if (name || addr || notes) text += `\n`;
    text += `-----------------------------------\n`;

    lines.forEach(line => {
        const l = line.trim();
        if (!l || l.toLowerCase().includes('total') || l.toLowerCase().includes('subtotal')) return;

        const [desc, priceToken] = _splitDescPrice(l);
        const fullDesc = `- ${desc.toUpperCase()}`;

        if (priceToken) {
            const lineWidth = 36;
            const pad = Math.max(1, lineWidth - fullDesc.length - priceToken.length);
            text += `${fullDesc}${' '.repeat(pad)}${priceToken}\n`;

            // Always show per-portion rate below the line (with item name)
            const totalPrice = _extractLinePrice(l);
            const qty = _extractLineQty(l);
            const unitPrice = qty > 0 ? Math.round(totalPrice / qty) : totalPrice;
            const unitPriceFmt = (unitPrice >= 1000 && unitPrice % 1000 === 0)
                ? `${unitPrice / 1000}k`
                : String(unitPrice);
            const itemName = desc.replace(/^\d+\s*/, '').trim();
            const unitLine = `@${itemName} ${unitPriceFmt}`;
            const pricePad = Math.max(1, lineWidth - unitLine.length);
            text += `${' '.repeat(pricePad)}${unitLine}\n`;
        } else {
            text += `${fullDesc}\n`;
        }
    });

    text += `-----------------------------------\n\n`;
    text += `* ITEM = ${items} items\n`;
    text += `* TOTAL : ${total.toLocaleString('id-ID')} / $${(total / 4000).toFixed(2)}$\n`;
    return text;
}

// ── History item creation ─────────────────────────────────────────────────────

function createHistoryItem(orderNo, htmlContent, plainText, total, items, name, addr, notes, raw) {
    const id   = Date.now();
    const item = document.createElement('div');
    item.className = 'history-item';
    item.id        = `hist-${id}`;
    item.draggable = true;

    item.dataset.orderNo         = orderNo;
    item.dataset.htmlContent     = htmlContent;
    item.dataset.plainText       = plainText;
    item.dataset.total           = total;
    item.dataset.items           = items;
    item.dataset.customerName    = name  || '';
    item.dataset.customerAddress = addr  || '';
    item.dataset.notes           = notes || '';
    item.dataset.rawInput        = raw;
    item.dataset.menuRows        = JSON.stringify(getRowsData());

    item.innerHTML = `
        Order ${orderNo} - ${name || 'N/A'}<br>
        Address: ${addr || 'N/A'}<br>
        Notes: ${notes || 'N/A'}<br>
        Total: ${total.toLocaleString('id-ID')}
    `;
    return item;
}

// ── Restore / edit history ────────────────────────────────────────────────────

function restoreBill(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;
    initBillStyles();
    document.getElementById('billContent').innerHTML = item.dataset.htmlContent;
    document.getElementById('outputBox').style.display = 'block';
}

function editHistory(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;

    editingId = id;
    const menuContainer = document.getElementById('menuInputContainer');

    if (menuContainer) {
        if (item.dataset.menuRows) {
            try   { rebuildMenuRows(JSON.parse(item.dataset.menuRows)); }
            catch { parseRawToRows(item.dataset.rawInput || ''); }
        } else {
            parseRawToRows(item.dataset.rawInput || '');
        }
    } else {
        document.getElementById('inputBox').value = item.dataset.rawInput || '';
    }

    document.getElementById('customerName').value    = item.dataset.customerName    || '';
    document.getElementById('customerAddress').value = item.dataset.customerAddress || '';
    document.getElementById('notes').value           = item.dataset.notes           || '';

    alert("Loaded for editing! Make changes and click 'Accept Receipt!' again.");
}


// =============================================================================
// SECTION 7.5: MENU ROW MANAGEMENT (Searchable Dropdown Input)
// =============================================================================

function initMenuInput() {
    const container = document.getElementById('menuInputContainer');
    if (!container) return;
    if (container.children.length === 0) addMenuRow();
}

function addMenuRow(data = null) {
    const container = document.getElementById('menuInputContainer');
    if (!container) return;

    const rowId = rowCounter++;
    const row   = document.createElement('div');
    row.className    = 'menu-row';
    row.dataset.rowId = rowId;

    const wrapper  = document.createElement('div');
    wrapper.className = 'menu-select-wrapper';

    const input      = document.createElement('input');
    input.type       = 'text';
    input.className  = 'menu-search';
    input.placeholder = 'Select or type menu...';
    input.autocomplete = 'off';

    const dropdown   = document.createElement('div');
    dropdown.className = 'menu-dropdown';

    wrapper.appendChild(input);
    wrapper.appendChild(dropdown);

    const qty      = document.createElement('input');
    qty.type       = 'number';
    qty.className  = 'menu-qty';
    qty.value      = data ? data.qty : '1';
    qty.min        = '1';

    const total    = document.createElement('div');
    total.className  = 'menu-total';
    total.textContent = '0';

    const removeBtn      = document.createElement('button');
    removeBtn.className  = 'menu-remove';
    removeBtn.textContent = '×';
    removeBtn.title      = 'Remove row';
    removeBtn.onclick    = () => removeMenuRow(row);

    row.appendChild(wrapper);
    row.appendChild(qty);
    row.appendChild(total);
    row.appendChild(removeBtn);
    container.appendChild(row);

    setupMenuRowEvents(row);

    if (data) {
        input.value = data.name;
        if (data.unitPrice) row.dataset.unitPrice = data.unitPrice;
        updateRowTotal(row);
    }

    populateDropdown(dropdown);
    return row;
}

function removeMenuRow(row) {
    const container = document.getElementById('menuInputContainer');
    if (!container) return;
    if (container.children.length <= 1) {
        const input = row.querySelector('.menu-search');
        const qty   = row.querySelector('.menu-qty');
        if (input) input.value = '';
        if (qty)   qty.value   = '1';
        delete row.dataset.unitPrice;
        updateRowTotal(row);
        return;
    }
    row.remove();
}

function setupMenuRowEvents(row) {
    const input    = row.querySelector('.menu-search');
    const qty      = row.querySelector('.menu-qty');
    const dropdown = row.querySelector('.menu-dropdown');

    input.addEventListener('input', () => {
        filterDropdown(dropdown, input.value);
        const exact = Object.keys(MENU_DB).find(k => k.toLowerCase() === input.value.trim().toLowerCase());
        if (exact) {
            row.dataset.unitPrice = MENU_DB[exact];
        } else {
            const price = extractPriceFromText(input.value);
            if (price > 0) row.dataset.unitPrice = price;
            else           delete row.dataset.unitPrice;
        }
        updateRowTotal(row);
    });

    input.addEventListener('focus', () => {
        filterDropdown(dropdown, input.value);
        dropdown.classList.add('active');
    });
    input.addEventListener('click', () => { dropdown.classList.add('active'); });

    qty.addEventListener('input', () => {
        if (qty.value < 1) qty.value = 1;
        updateRowTotal(row);
    });

    input.addEventListener('keydown', (e) => {
        if (!dropdown.classList.contains('active')) return;
        const visibleItems = dropdown.querySelectorAll('.menu-dropdown-item:not(.hidden)');
        const highlighted  = dropdown.querySelector('.highlighted');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (highlighted) highlighted.classList.remove('highlighted');
            let next = highlighted ? highlighted.nextElementSibling : null;
            while (next && next.classList.contains('hidden')) next = next.nextElementSibling;
            if (next && next.classList.contains('menu-dropdown-item')) next.classList.add('highlighted');
            else if (visibleItems[0]) visibleItems[0].classList.add('highlighted');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (highlighted) highlighted.classList.remove('highlighted');
            let prev = highlighted ? highlighted.previousElementSibling : null;
            while (prev && prev.classList.contains('hidden')) prev = prev.previousElementSibling;
            if (prev && prev.classList.contains('menu-dropdown-item')) prev.classList.add('highlighted');
            else if (visibleItems[visibleItems.length - 1]) visibleItems[visibleItems.length - 1].classList.add('highlighted');
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlighted) highlighted.click();
            else dropdown.classList.remove('active');
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('active');
        }
    });
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.menu-row')) {
        document.querySelectorAll('.menu-dropdown.active').forEach(d => d.classList.remove('active'));
    }
});

// ── Populate dropdown with menu items ──────────────────────────────────────

function populateDropdown(dropdown) {
    dropdown.innerHTML = '';

    // Menu items
    Object.keys(MENU_DB).sort().forEach(name => {
        const item       = document.createElement('div');
        item.className   = 'menu-dropdown-item';
        item.textContent = `${name} (${(MENU_DB[name] / 1000).toFixed(0)}k)`;
        item.dataset.name  = name;
        item.dataset.price = MENU_DB[name];
        item.onclick = () => {
            const input = dropdown.parentElement.querySelector('.menu-search');
            const row   = dropdown.closest('.menu-row');
            input.value           = name;
            row.dataset.unitPrice = MENU_DB[name];
            dropdown.classList.remove('active');
            updateRowTotal(row);
        };
        dropdown.appendChild(item);
    });

    // "+" add new menu item row
    const addRow     = document.createElement('div');
    addRow.className = 'menu-add-row';

    const nameIn       = document.createElement('input');
    nameIn.className   = 'menu-add-input';
    nameIn.placeholder = 'New item name...';
    nameIn.title       = 'Menu item name';
    nameIn.type        = 'text';

    const priceIn      = document.createElement('input');
    priceIn.className  = 'menu-add-price';
    priceIn.placeholder = '25k';
    priceIn.title      = 'Price  e.g. 25k  or  25000';
    priceIn.type       = 'text';

    const addBtn       = document.createElement('button');
    addBtn.className   = 'menu-add-btn';
    addBtn.textContent = '+';
    addBtn.title       = 'Add to menu.txt';

    // Prevent dropdown close when interacting with the add row
    [addRow, nameIn, priceIn].forEach(el => {
        el.addEventListener('click',     e => e.stopPropagation());
        el.addEventListener('mousedown', e => e.stopPropagation());
    });
    nameIn.addEventListener('keydown',  e => e.stopPropagation());
    priceIn.addEventListener('keydown', e => e.stopPropagation());

    // Enter in name → focus price; Enter in price → submit
    nameIn.addEventListener('keydown',  e => { if (e.key === 'Enter') priceIn.focus(); });
    priceIn.addEventListener('keydown', e => { if (e.key === 'Enter') addBtn.click(); });

    addBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const itemName  = nameIn.value.trim();
        const priceText = priceIn.value.trim();
        if (!itemName)  { nameIn.focus();  return; }
        if (!priceText) { priceIn.focus(); return; }

        const price = extractPriceFromText(priceText);
        if (!price || price <= 0) {
            priceIn.style.borderColor = '#e74c3c';
            setTimeout(() => priceIn.style.borderColor = '', 1200);
            priceIn.focus();
            return;
        }

        addMenuItemToFile(itemName, price, addRow);
        nameIn.value  = '';
        priceIn.value = '';
        nameIn.focus();
    });

    addRow.appendChild(nameIn);
    addRow.appendChild(priceIn);
    addRow.appendChild(addBtn);
    dropdown.appendChild(addRow);
}

function populateAllDropdowns() {
    document.querySelectorAll('.menu-dropdown').forEach(d => populateDropdown(d));
}

function filterDropdown(dropdown, query) {
    const items = dropdown.querySelectorAll('.menu-dropdown-item');
    const q     = query.toLowerCase();
    items.forEach(item => {
        if (item.dataset.name && item.dataset.name.toLowerCase().includes(q))
            item.classList.remove('hidden');
        else
            item.classList.add('hidden');
    });
}

function updateRowTotal(row) {
    const qty = parseInt(row.querySelector('.menu-qty').value) || 1;
    let unitPrice = 0;
    if (row.dataset.unitPrice) {
        unitPrice = parseFloat(row.dataset.unitPrice);
    } else {
        const text = row.querySelector('.menu-search').value;
        unitPrice  = extractPriceFromText(text);
        if (unitPrice > 0) row.dataset.unitPrice = unitPrice;
    }
    const total = qty * unitPrice;
    row.querySelector('.menu-total').textContent = total > 0 ? total.toLocaleString('id-ID') : '0';
}

// FIXED: end-anchor $ — only match price token at the END of the text string
function extractPriceFromText(text) {
    const kMatch = text.match(/(\d+[.,]?\d*)[kK]\s*$/i);
    if (kMatch) return parseFloat(kMatch[1].replace(',', '.')) * 1000;
    const numMatch = text.match(/(\d+[.,]?\d*)\s*$/);
    if (numMatch) {
        const num = parseFloat(numMatch[1].replace(',', '.'));
        return num * (num < 100 ? 1000 : 1);
    }
    return 0;
}

function stripPriceFromText(text) {
    return text
        .replace(/\s+\d+[.,]?\d*\s*[kK]\b/i, '')
        .replace(/\s+\d+[.,]?\d*$/, '')
        .trim();
}

function buildRawTextFromRows() {
    const lines = [];
    document.querySelectorAll('.menu-row').forEach(row => {
        let name = row.querySelector('.menu-search').value.trim();
        const qty = parseInt(row.querySelector('.menu-qty').value) || 1;
        if (!name) return;

        let unitPrice = row.dataset.unitPrice ? parseFloat(row.dataset.unitPrice) : 0;
        if (unitPrice === 0) unitPrice = extractPriceFromText(name);

        if (extractPriceFromText(name) > 0) name = stripPriceFromText(name);
        if (!name) return;

        const totalPrice = qty * unitPrice;
        let line = `${qty} ${name}`;
        if (totalPrice > 0) {
            const priceStr = (totalPrice >= 1000 && totalPrice % 1000 === 0)
                ? (totalPrice / 1000) + 'k'
                : String(totalPrice);
            line += ` ${priceStr}`;
        }
        lines.push(line);
    });
    return lines.join('\n');
}

function getRowsData() {
    const rows = [];
    document.querySelectorAll('.menu-row').forEach(row => {
        rows.push({
            name:      row.querySelector('.menu-search').value,
            qty:       row.querySelector('.menu-qty').value,
            unitPrice: row.dataset.unitPrice || ''
        });
    });
    return rows;
}

function rebuildMenuRows(rowsData) {
    const container = document.getElementById('menuInputContainer');
    if (!container) return;
    container.innerHTML = '';
    rowCounter = 0;
    if (!rowsData || rowsData.length === 0) { addMenuRow(); return; }
    rowsData.forEach(data => addMenuRow(data));
}

function parseRawToRows(rawText) {
    const container = document.getElementById('menuInputContainer');
    if (!container) return;
    container.innerHTML = '';
    rowCounter = 0;
    if (!rawText.trim()) { addMenuRow(); return; }

    const lines = rawText.split('\n');
    lines.forEach(line => {
        line = line.trim();
        if (!line) return;

        let qty = 1;
        const startQty = line.match(/^(\d+)/);
        if (startQty) { qty = parseInt(startQty[1]); line = line.replace(/^\d+\s*/, ''); }

        let totalPrice = 0;
        const kMatch   = line.match(/(\d+[.,]?\d*)[kK]\s*$/i);
        const numMatch = line.match(/(\d+[.,]?\d*)\s*$/);
        if (kMatch) {
            totalPrice = parseFloat(kMatch[1].replace(',', '.')) * 1000;
            line = line.replace(/\s+\d+[.,]?\d*[kK]\s*$/i, '');
        } else if (numMatch) {
            const num  = parseFloat(numMatch[1].replace(',', '.'));
            totalPrice = num * (num < 100 ? 1000 : 1);
            line = line.replace(/\s+\d+[.,]?\d*\s*$/, '');
        }

        const trimmedName = line.trim();
        let unitPrice = 0;
        if (totalPrice > 0 && qty > 0) unitPrice = Math.round(totalPrice / qty);
        if (MENU_DB[trimmedName] && totalPrice === 0) unitPrice = MENU_DB[trimmedName];

        addMenuRow({ name: trimmedName, qty: String(qty), unitPrice: unitPrice > 0 ? String(unitPrice) : '' });
    });

    if (container.children.length === 0) addMenuRow();
}


// ── Add menu item to file via API ─────────────────────────────────────────────
// Backend route needed (Express example):
//   app.post('/api/menu/add', (req, res) => {
//       const { name, price } = req.body;
//       const line = `${name} ${(price / 1000).toFixed(0)}k\n`;
//       fs.appendFileSync(path.join(__dirname, 'menu.txt'), line);
//       res.json({ ok: true });
//   });

async function addMenuItemToFile(name, price, addRow) {
    // 1. Update MENU_DB in memory
    MENU_DB[name] = price;

    // 2. Insert the new item directly into every open dropdown — no innerHTML wipe,
    //    so addRow / nameIn / priceIn all stay alive in the DOM.
    document.querySelectorAll('.menu-dropdown').forEach(dropdown => {
        const newItem        = document.createElement('div');
        newItem.className    = 'menu-dropdown-item';
        newItem.textContent  = `${name} (${(price / 1000).toFixed(0)}k)`;
        newItem.dataset.name  = name;
        newItem.dataset.price = price;
        newItem.onclick = () => {
            const input = dropdown.parentElement.querySelector('.menu-search');
            const row   = dropdown.closest('.menu-row');
            input.value           = name;
            row.dataset.unitPrice = price;
            dropdown.classList.remove('active');
            updateRowTotal(row);
        };
        // Place before the add-row so it appears in the item list
        const existingAddRow = dropdown.querySelector('.menu-add-row');
        if (existingAddRow) dropdown.insertBefore(newItem, existingAddRow);
        else                 dropdown.appendChild(newItem);

        // Re-apply any active search filter so the new item obeys it immediately
        const searchInput = dropdown.parentElement.querySelector('.menu-search');
        if (searchInput && searchInput.value) filterDropdown(dropdown, searchInput.value);
    });

    // 3. Show inline feedback — addRow is still attached, so .parentElement is valid
    if (addRow && addRow.parentElement) {
        const fb       = document.createElement('div');
        fb.className   = 'menu-add-feedback';
        fb.textContent = `✓ "${name}" added`;
        addRow.parentElement.insertBefore(fb, addRow);
        setTimeout(() => { try { fb.remove(); } catch (_) {} }, 2000);
    }

    // 4. Persist to server
    //    Backend route (Express):
    //      app.post('/api/menu/add', (req, res) => {
    //          const { name, price } = req.body;
    //          fs.appendFileSync('menu.txt', `${name} ${(price/1000).toFixed(0)}k\n`);
    //          res.json({ ok: true });
    //      });
    try {
        const res = await fetch(API_ENDPOINT + '?action=add_menu_item', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, price })
        });
        const json = await res.json();
        if (json.ok) {
            console.log(`[Menu] Saved to menu.txt: ${name} ${price / 1000}k`);
        } else {
            console.warn('[Menu] Server error — item is in memory only until reload');
        }
    } catch (err) {
        console.warn('[Menu] API add_menu_item unreachable — item is in memory only:', err.message);
    }
}


// =============================================================================
// SECTION 8: DELETE FUNCTIONALITY (with Telemetry)
// =============================================================================

function removeHistory(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;
    if (!confirm('Delete this order?')) return;

    const orderNo       = item.dataset.orderNo       || '000';
    const total         = parseFloat(item.dataset.total) || 0;
    const customerName  = item.dataset.customerName  || '';
    const customerAddress = item.dataset.customerAddress || '';
    reportAdminAction("DELETED_BILL", orderNo, total, customerName, customerAddress);

    performDelete(id);
}

function archiveDeletedItem(item) {
    const existing = JSON.parse(localStorage.getItem('deletedOrders') || '[]');
    const parentId = item.parentElement ? item.parentElement.id : '';
    let location   = 'Pending';
    if (parentId === 'finishedContainer') {
        location = 'Finished';
    } else if (['thornContainer','domContainer','pozzalContainer','etcContainer','extraContainer'].includes(parentId)) {
        const h4 = item.parentElement.querySelector('h4');
        location = h4 ? h4.innerText : 'Delivery';
    }

    const record = {
        orderNo:         item.dataset.orderNo       || '—',
        customerName:    item.dataset.customerName  || 'N/A',
        customerAddress: item.dataset.customerAddress || 'N/A',
        notes:           item.dataset.notes         || '',
        total:           parseFloat(item.dataset.total) || 0,
        isAba:           item.classList.contains('archived'),
        isChecked:       item.classList.contains('checked'),
        htmlContent:     item.dataset.htmlContent   || '',
        plainText:       item.dataset.plainText     || '',
        location,
        deletedAt:       new Date().toISOString()
    };

    existing.push(record);
    localStorage.setItem('deletedOrders', JSON.stringify(existing));

    fetch(API_ENDPOINT + '?action=report_deleted_order', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(record)
    }).catch(() => {});
}

function performDelete(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;

    const location   = item.dataset.location || '';
    const isFinished = location.toLowerCase() === 'finished';

    if (isFinished) {
        item.classList.add('row-deleted');
        item.dataset.isDeleted  = 'true';
        item.dataset.deletedAt  = new Date().toISOString();
        const locationCell = item.querySelector('.location-tag');
        if (locationCell) {
            locationCell.classList.add('loc-deleted');
            locationCell.textContent = '🗑 Deleted (Finished)';
        }
        saveData();
        updateTotals();
        return;
    }

    archiveDeletedItem(item);
    if (imageStore[id]) delete imageStore[id];
    if (currentHoverItemId === id) {
        floatingPreview.style.display = 'none';
        currentHoverItemId = null;
    }
    item.remove();
    saveData();
    updateTotals();
}


// =============================================================================
// SECTION 9: ITEM STATE TOGGLES
// =============================================================================

function toggleCheck(id) {
    const item = document.getElementById(`hist-${id}`);
    if (item) { item.classList.toggle('checked'); saveData(); updateTotals(); }
}

function toggleArchive(id) {
    const item = document.getElementById(`hist-${id}`);
    if (item) { item.classList.toggle('archived'); saveData(); updateTotals(); }
}


// =============================================================================
// SECTION 10: DRAG & DROP
// =============================================================================

const containers = document.querySelectorAll('.sidebar, .driver-panel');
containers.forEach(container => {
    container.addEventListener('dragover', e => e.preventDefault());
    container.addEventListener('drop', drop);
});

function drag(e) {
    e.dataTransfer.setData('text/plain', e.target.id);
}

function drop(e) {
    e.preventDefault();
    const id   = e.dataTransfer.getData('text/plain');
    const item = document.getElementById(id);
    if (item) {
        const targetContainer = e.target.closest('.driver-panel, #historyContainer, #finishedContainer');
        if (targetContainer && targetContainer !== item.parentElement) {
            targetContainer.appendChild(item);
            updateButtonsForContainer(item);
            saveData();
            updateTotals();
        }
    }
}


// =============================================================================
// SECTION 11: ORDER MOVEMENT
// =============================================================================

function moveToDelivery(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;

    const drivers = ['thornContainer', 'domContainer', 'pozzalContainer', 'etcContainer', 'extraContainer'];
    const choice  = prompt(`Enter driver number:\n1: NITH\n2: MEY\n3: THORN\n4: ETC\n5: FOOD READY!`);
    const index   = parseInt(choice) - 1;

    if (isNaN(index) || index < 0 || index > 4) { alert("Invalid choice!"); return; }

    document.getElementById(drivers[index]).appendChild(item);
    updateButtonsForContainer(item);
    saveData();
}

function moveToFinished(id) {
    const item = document.getElementById(`hist-${id}`);
    if (!item) return;
    document.getElementById('finishedContainer').appendChild(item);
    updateButtonsForContainer(item);
    saveData();
    updateTotals();
    // Report for accounting — send full receipt data to DB
    const orderNo = item.dataset.orderNo || '';
    const name = item.dataset.customerName || '';
    const address = item.dataset.customerAddress || '';
    const total = parseFloat(item.dataset.total) || 0;
    const isAba = item.classList.contains('archived');
    const plainText = item.dataset.plainText || '';
    const htmlContent = item.dataset.htmlContent || '';
    fetch('./static/js/api.php?action=save_finished_order', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({orderNo, customerName: name, address, total, isAba, plainText, htmlContent})
    }).catch(() => {});
}


// =============================================================================
// SECTION 12: BUTTON MANAGEMENT
// =============================================================================

function updateButtonsForContainer(item) {
    const parentId = item.parentElement.id;
    const id       = item.id.replace('hist-', '');

    item.querySelectorAll('button').forEach(btn => btn.remove());

    const delBtn    = createButton('del-btn',    'X',  'Delete',        () => removeHistory(id));
    const editBtn   = createButton('edit-btn',   'E',  'Edit',          () => editHistory(id));
    const uploadBtn = createButton('upload-btn', '📷', 'Upload Image',  () => showUploadModal(id));

    item.appendChild(delBtn);
    item.appendChild(editBtn);
    item.appendChild(uploadBtn);

    const isDelivery = ['thornContainer', 'domContainer', 'pozzalContainer', 'etcContainer', 'extraContainer'].includes(parentId);

    if (parentId === 'historyContainer') {
        item.appendChild(createButton('check-btn',   '✓', 'Check',              () => toggleCheck(id)));
        item.appendChild(createButton('archive-btn', 'A', 'Archive (ABA)',       () => toggleArchive(id)));
        item.appendChild(createButton('move-btn',    'D', 'Move to Delivery',    () => moveToDelivery(id)));
        item.appendChild(createButton('finish-btn',  'F', 'Finish',              () => moveToFinished(id)));
        item.appendChild(createButton('split-btn',   'S', 'Split Payment',       () => splitOrder(id)));
    } else if (isDelivery) {
        item.appendChild(createButton('check-btn',   '✓', 'Check',              () => toggleCheck(id)));
        item.appendChild(createButton('archive-btn', 'A', 'Archive (ABA)',       () => toggleArchive(id)));
        item.appendChild(createButton('finish-btn',  'F', 'Finish',              () => moveToFinished(id)));
        item.appendChild(createButton('split-btn',   'S', 'Split Payment',       () => splitOrder(id)));
    } else if (parentId === 'finishedContainer') {
        item.appendChild(createButton('check-btn',   '✓', 'Check',              () => toggleCheck(id)));
        item.appendChild(createButton('archive-btn', 'A', 'Archive (ABA)',       () => toggleArchive(id)));
        item.appendChild(createButton('split-btn',   'S', 'Split Payment',       () => splitOrder(id)));
    }
}

function createButton(className, text, title, onClick) {
    const btn       = document.createElement('button');
    btn.className   = className;
    btn.textContent = text;
    btn.title       = title;
    btn.onclick     = onClick;

    if (className === 'split-btn') {
        btn.style.position    = 'absolute';
        btn.style.top         = '5px';
        btn.style.left        = '5px';
        btn.style.background  = '#f39c12';
        btn.style.border      = 'none';
        btn.style.width       = '20px';
        btn.style.height      = '20px';
        btn.style.borderRadius = '50%';
        btn.style.cursor      = 'pointer';
        btn.style.lineHeight  = '18px';
        btn.style.textAlign   = 'center';
        btn.style.fontSize    = '12px';
        btn.style.opacity     = '0';
        btn.style.transition  = 'opacity 0.2s';
    }
    return btn;
}


// =============================================================================
// SECTION 13: IMAGE UPLOAD FUNCTIONALITY
// =============================================================================

const uploadModal    = document.getElementById('uploadModal');
const modalPasteBtn  = document.getElementById('modalPasteBtn');
const modalFileBtn   = document.getElementById('modalFileBtn');
const modalCloseBtn  = document.getElementById('modalCloseBtn');
const fileInput      = document.getElementById('imageUploadInput');

let currentUploadItemId = null;

function showUploadModal(itemId) {
    currentUploadItemId = itemId;
    uploadModal.style.display = 'flex';
}

modalCloseBtn.onclick = () => {
    uploadModal.style.display = 'none';
    currentUploadItemId = null;
};

window.addEventListener('click', (e) => {
    if (e.target === uploadModal) {
        uploadModal.style.display = 'none';
        currentUploadItemId = null;
    }
});

modalPasteBtn.onclick = async () => {
    uploadModal.style.display = 'none';
    if (!currentUploadItemId) return;
    try {
        const clipboardItems = await navigator.clipboard.read();
        for (let item of clipboardItems) {
            if (item.types.includes('image/png') || item.types.includes('image/jpeg') || item.types.includes('image/jpg')) {
                const blob   = await item.getType(item.types.find(type => type.startsWith('image/')));
                const dataUrl = await blobToDataURL(blob);
                saveImageToItem(currentUploadItemId, dataUrl);
                return;
            }
        }
        alert('No image found in clipboard. Please copy an image first (Win+Shift+S).');
    } catch (err) {
        console.error('Clipboard read failed:', err);
        alert('Unable to read clipboard. Please use "Choose File" instead.');
    } finally {
        currentUploadItemId = null;
    }
};

modalFileBtn.onclick = () => {
    uploadModal.style.display = 'none';
    if (!currentUploadItemId) return;
    fileInput.click();
};

fileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { alert('Please select an image file (JPG/PNG).'); return; }
    const reader = new FileReader();
    reader.onload = (event) => {
        saveImageToItem(currentUploadItemId, event.target.result);
        fileInput.value     = '';
        currentUploadItemId = null;
    };
    reader.readAsDataURL(file);
});

function blobToDataURL(blob) {
    return new Promise((resolve, reject) => {
        const reader  = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

function saveImageToItem(itemId, dataUrl) {
    const item = document.getElementById(`hist-${itemId}`);
    if (!item) return;
    imageStore[itemId] = dataUrl;
    item.classList.add('has-image');
    saveData();
}

function deleteImage(itemId) {
    if (!confirm('Remove this image?')) return;
    const item = document.getElementById(`hist-${itemId}`);
    if (item) item.classList.remove('has-image');
    if (imageStore[itemId]) delete imageStore[itemId];
    if (currentHoverItemId === itemId) {
        floatingPreview.style.display = 'none';
        currentHoverItemId = null;
    }
    saveData();
}


// =============================================================================
// SECTION 14: SPLIT ORDER FUNCTIONALITY
// =============================================================================

function splitOrder(id) {
    const originalItem = document.getElementById(`hist-${id}`);
    if (!originalItem) return;

    const originalTotal = parseFloat(originalItem.dataset.total) || 0;
    const orderNo       = originalItem.dataset.orderNo;

    const abaInput  = prompt(`Enter ABA amount for Order ${orderNo} (total: ${originalTotal.toLocaleString('id-ID')} Riel):`);
    const abaAmount = parseFloat(abaInput);

    if (isNaN(abaAmount) || abaAmount <= 0 || abaAmount > originalTotal) {
        alert("Invalid ABA amount! Must be a number between 1 and the total.");
        return;
    }

    const cashAmount   = originalTotal - abaAmount;
    const duplicateId  = Date.now();
    const duplicateItem = document.createElement('div');
    duplicateItem.className = 'history-item';
    duplicateItem.id        = `hist-${duplicateId}`;
    duplicateItem.draggable = true;

    Object.keys(originalItem.dataset).forEach(key => {
        if (key !== 'total') duplicateItem.dataset[key] = originalItem.dataset[key];
    });
    duplicateItem.dataset.total = abaAmount;
    duplicateItem.classList.add('archived');

    originalItem.dataset.total = cashAmount;

    updateItemDisplay(originalItem,   cashAmount, '-CASH', orderNo);
    updateItemDisplay(duplicateItem,  abaAmount,  '-ABA',  orderNo);
    updateHtmlContent(originalItem,   cashAmount);
    updateHtmlContent(duplicateItem,  abaAmount);

    if (originalItem.dataset.plainText) {
        updatePlainText(originalItem,  cashAmount);
        updatePlainText(duplicateItem, abaAmount);
    }

    originalItem.parentElement.insertBefore(duplicateItem, originalItem.nextSibling);
    updateButtonsForContainer(originalItem);
    updateButtonsForContainer(duplicateItem);
    reattachEvents();
    saveData();
    updateTotals();

    alert("Order split successfully! Edit items in raw input if needed for accurate bill details.");
}

function updateItemDisplay(item, amount, suffix, orderNo) {
    let display = item.innerHTML.replace(
        /Total: [\d.,]+/,
        `Total: ${amount.toLocaleString('id-ID')}`
    );
    display = display.replace(
        new RegExp(`Order ${orderNo}`),
        `Order ${orderNo}${suffix}`
    );
    item.innerHTML = display;
}

function updateHtmlContent(item, amount) {
    let newHtml = item.dataset.htmlContent.replace(
        /(TOTAL: ៛?)[\d.,]+( \/ \$\d+\.\d+)/,
        `$1${amount.toLocaleString('id-ID')}$2`
    );
    newHtml = newHtml.replace(
        /(\* TOTAL : )[\d.,]+( \/ \$\d+\.\d+\$?)/,
        `$1${amount.toLocaleString('id-ID')}$2`
    );
    item.dataset.htmlContent = newHtml;
}

function updatePlainText(item, amount) {
    const text = item.dataset.plainText.replace(
        /(\* TOTAL : )[\d.,]+( \/ \$\d+\.\d+\$?)/,
        `$1${amount.toLocaleString('id-ID')}$2`
    );
    item.dataset.plainText = text;
}


// =============================================================================
// SECTION 15: EXPORT & TOTALS CALCULATION
// =============================================================================

function csvEscape(val) {
    const str = String(val ?? '').replace(/"/g, '""');
    return `"${str}"`;
}

function exportFinishedOrders() {
    const items = document.querySelectorAll('#finishedContainer .history-item');
    if (items.length === 0) { alert("No completed orders!"); return; }

    const now      = new Date();
    const pad      = n => String(n).padStart(2, '0');
    const datetime = `${pad(now.getDate())}-${pad(now.getMonth()+1)}-${now.getFullYear()}_${pad(now.getHours())}-${pad(now.getMinutes())}`;

    const summaryHeader = ['No','Customer Name','Address','Total','Payment','Status'];
    let summaryRows = [summaryHeader.map(csvEscape).join(',')];

    const detailHeader = ['No','Customer Name','Address','Total','Payment','Status','Bill Detail'];
    let detailRows = [detailHeader.map(csvEscape).join(',')];

    let unarchived = 0, archived = 0, orderIndex = 1;

    items.forEach(item => {
        const name       = item.dataset.customerName    || 'N/A';
        const addr       = item.dataset.customerAddress || 'N/A';
        const totalMatch = item.innerText.match(/Total: ([\d.,]+)/)?.[1] || '0';
        const payment    = item.classList.contains('archived') ? 'ABA' : 'CASH';
        const status     = item.classList.contains('checked')  ? 'CHECK' : '';

        let billText = '';
        if (item.dataset.plainText) {
            billText = item.dataset.plainText;
        } else {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = item.dataset.htmlContent || '';
            billText = tempDiv.innerText;
        }

        summaryRows.push([csvEscape(orderIndex), csvEscape(name), csvEscape(addr), csvEscape(totalMatch), csvEscape(payment), csvEscape(status)].join(','));
        detailRows.push([csvEscape(orderIndex),  csvEscape(name), csvEscape(addr), csvEscape(totalMatch), csvEscape(payment), csvEscape(status), csvEscape(billText)].join(','));

        if (!item.classList.contains('checked')) {
            const totalNum = parseFloat(totalMatch.replace(/\./g, '').replace(/,/g, '.')) || 0;
            if (item.classList.contains('archived')) archived   += totalNum;
            else                                      unarchived += totalNum;
        }
        orderIndex++;
    });

    summaryRows.push('');
    summaryRows.push([csvEscape(''), csvEscape(''), csvEscape('Grand Total CASH'), csvEscape(unarchived.toLocaleString('id-ID')),             csvEscape(''), csvEscape('')].join(','));
    summaryRows.push([csvEscape(''), csvEscape(''), csvEscape('Grand Total ABA'),  csvEscape(archived.toLocaleString('id-ID')),               csvEscape(''), csvEscape('')].join(','));
    summaryRows.push([csvEscape(''), csvEscape(''), csvEscape('GRAND TOTAL'),       csvEscape((unarchived+archived).toLocaleString('id-ID')), csvEscape(''), csvEscape('')].join(','));

    const BOM = '\uFEFF';
    const summaryBlob = new Blob([BOM + summaryRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const summaryLink = document.createElement('a');
    summaryLink.href  = URL.createObjectURL(summaryBlob);
    summaryLink.download = `laporan_hariini(${datetime}).csv`;
    summaryLink.click();

    setTimeout(() => {
        const detailBlob = new Blob([BOM + detailRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const detailLink = document.createElement('a');
        detailLink.href  = URL.createObjectURL(detailBlob);
        detailLink.download = `Bill_Lengkap(${datetime}).csv`;
        detailLink.click();
    }, 300);

    saveData();
    alert(`✅ Downloaded 2 CSV files:\n1. laporan_hariini(${datetime}).csv\n2. Bill_Lengkap(${datetime}).csv`);
    updateTotals();
}

function updateTotals() {
    let unarchived = 0, archived = 0;
    document.querySelectorAll('#finishedContainer .history-item').forEach(item => {
        if (item.classList.contains('checked')) return;
        const total = parseFloat(item.dataset.total) || 0;
        if (item.classList.contains('archived')) archived   += total;
        else                                      unarchived += total;
    });
    document.getElementById('totalUnarchived').innerText = unarchived.toLocaleString('id-ID');
    document.getElementById('totalArchived').innerText   = archived.toLocaleString('id-ID');
}


// =============================================================================
// SECTION 16: DELETE ALL FINISHED ORDERS
// =============================================================================

function deleteAllFinishedOrders() {
    const items = document.querySelectorAll('#finishedContainer .history-item');
    if (items.length === 0) { alert("No completed orders to delete!"); return; }
    if (!confirm(`Delete ALL ${items.length} completed orders? This cannot be undone.`)) return;

    items.forEach(item => {
        const id            = item.id.replace('hist-', '');
        const orderNo       = item.dataset.orderNo       || '000';
        const total         = parseFloat(item.dataset.total) || 0;
        const customerName  = item.dataset.customerName  || '';
        const customerAddress = item.dataset.customerAddress || '';
        reportAdminAction("DELETED_BILL", orderNo, total, customerName, customerAddress);
        archiveDeletedItem(item);  // save full receipt to DB before removing
        performDelete(id);
    });
}


// =============================================================================
// SECTION 17: PRINT FUNCTIONALITY
// =============================================================================

document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        const outputBox = document.getElementById('outputBox');
        if (outputBox.style.display !== 'none' && outputBox.innerHTML.trim() !== '') {
            const billContent  = document.getElementById('billContent');
            const orderNoMatch = billContent.innerHTML.match(/\*order\s*(\d+)/);
            const totalMatch   = billContent.innerHTML.match(/TOTAL\s*:\s*([\d.,]+)/);
            if (orderNoMatch && totalMatch) {
                const orderNo = orderNoMatch[1].padStart(3, '0');
                const total   = parseFloat(totalMatch[1].replace(/\./g, '').replace(/,/g, '.')) || 0;
                reportAdminAction("PRINTED_BILL", orderNo, total);
            }
            window.print();
        } else {
            alert("Nothing to print! Generate a receipt first.");
        }
    }
});


// =============================================================================
// SECTION 18: DRIVER NAME MANAGEMENT
// =============================================================================

async function loadDriverNames() {
    // Try server first for cross-browser persistence
    const serverNames = await apiLoadDriverNames();
    const drivers = ['thorn', 'dom', 'pozzal', 'etc', 'extra'];

    if (serverNames && Object.keys(serverNames).length > 0) {
        drivers.forEach(driver => {
            const container = document.getElementById(`${driver}Container`);
            if (container) {
                const header = container.querySelector('h4');
                if (header && serverNames[driver]) {
                    header.innerText = serverNames[driver];
                    localStorage.setItem(`driver_name_${driver}`, serverNames[driver]);
                }
            }
        });
    } else {
        // Fallback to localStorage
        drivers.forEach(driver => {
            const container = document.getElementById(`${driver}Container`);
            if (container) {
                const header = container.querySelector('h4');
                if (header) {
                    const savedName = localStorage.getItem(`driver_name_${driver}`);
                    if (savedName) header.innerText = savedName;
                }
            }
        });
    }
}

function editDriverName(driver) {
    const container = document.getElementById(`${driver}Container`);
    if (!container) return;
    const header = container.querySelector('h4');
    if (!header)  return;
    const currentName = header.innerText;
    const newName     = prompt("Enter new driver name:", currentName);
    if (newName && newName.trim() !== "") {
        const cleanName = newName.trim().toUpperCase();
        header.innerText = cleanName;
        localStorage.setItem(`driver_name_${driver}`, cleanName);
        apiSaveDriverName(driver, cleanName);
    }
}


// =============================================================================
// SECTION 19: UTILITY FUNCTIONS
// =============================================================================

function goToStock() {
    window.location.href = './stock.php';
}

function goToAllHistory() {
    window.location.href = './history.php';
}


// =============================================================================
// INITIALIZATION
// =============================================================================

initBillStyles();
loadMenuFromFile();
initMenuInput();

// =============================================================================
// END OF SCRIPT
// =============================================================================
