# ChinQue POS — Desktop Version (SAUNG ABAH) — Full Documentation

> A desktop-first, multi-panel Point of Sale (POS) web app.  
> Built with vanilla HTML, CSS (external), and JavaScript (external).  
> Optimized for wide screens. All data persists via `localStorage`.  
> Three separate files: `index.html`, `style.css`, `script.js`.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [File Structure](#2-file-structure)
3. [Global Architecture](#3-global-architecture)
   - [Global Variables](#global-variables)
   - [LocalStorage Persistence](#localstorage-persistence)
   - [Order Lifecycle](#order-lifecycle)
   - [Button Management System](#button-management-system)
   - [Drag-and-Drop System](#drag-and-drop-system)
4. [CSS Design System](#4-css-design-system)
   - [Variables & Fonts](#variables--fonts)
   - [Layout Structure](#layout-structure)
   - [Shooting Star Animations](#shooting-star-animations)
   - [History Item Buttons (CSS Positioning)](#history-item-buttons-css-positioning)
   - [Responsive Breakpoints](#responsive-breakpoints)
   - [Print Styles](#print-styles)
5. [PANEL 1 — Left Sidebar (Pending Orders + Checker)](#5-panel-1--left-sidebar)
   - [Pending Orders](#pending-orders-historycontainer)
   - [Order Checker](#order-checker)
6. [PANEL 2 — Main Center (Receipt & Bill Maker)](#6-panel-2--main-center)
   - [Input Fields](#input-fields)
   - [`processOrder()`](#processorder)
   - [`createHistoryItem()`](#createhistoryitem)
   - [`copyReceipt()`](#copyreceipt)
   - [`restoreBill()`](#restorebill)
   - [Print Support](#print-support)
7. [PANEL 3 — Delivery Sidebar (Driver Panels)](#7-panel-3--delivery-sidebar)
   - [`moveToDelivery()`](#movetodelivery)
   - [`editDriverName()`](#editdrivername)
   - [`loadDriverNames()`](#loaddrivernames)
8. [PANEL 4 — Finished Orders Sidebar](#8-panel-4--finished-orders-sidebar)
   - [`moveToFinished()`](#movetofinished)
   - [`exportFinishedOrders()`](#exportfinishedorders)
   - [`updateTotals()`](#updatetotals)
9. [PANEL 5 — Right Sidebar (Tools)](#9-panel-5--right-sidebar-tools)
   - [Live Clock & Date](#live-clock--date)
   - [Order Number Management](#order-number-management)
   - [Calculator](#calculator)
   - [Total Cash-In Summary](#total-cash-in-summary)
   - [Cashier Notepad](#cashier-notepad)
   - [Stock Manager Link](#stock-manager-link)
10. [Order Card Action Buttons](#10-order-card-action-buttons)
    - [`toggleCheck()`](#togglecheck)
    - [`toggleArchive()`](#togglearchive)
    - [`editHistory()`](#edithistory)
    - [`removeHistory()`](#removehistory)
    - [`splitOrder()`](#splitorder)
11. [Image Upload System](#11-image-upload-system)
    - [`showUploadModal()`](#showuploadmodal)
    - [Paste from Clipboard](#paste-from-clipboard)
    - [Choose File](#choose-file)
    - [`saveImageToItem()`](#saveimagetoitem)
    - [`deleteImage()`](#deleteimage)
    - [Hover Preview](#hover-preview)
12. [Order Checker (Detailed Logic)](#12-order-checker-detailed-logic)
13. [AI Context Summary](#13-ai-context-summary)

---

## 1. Project Overview

**ChinQue POS — Desktop Version (SAUNG ABAH)** is a wide-screen, panel-based POS system split across three files. It handles the full order lifecycle across five visible panels simultaneously:

```
[Pending + Checker] | [Receipt Maker] | [Delivery Panels] | [Finished Orders] | [Tools]
```

Key capabilities vs the phone version:
- All panels visible at once — no tab switching required
- Orders are **draggable** between panels and driver slots
- Image attachments per order (clipboard paste or file upload)
- Image hover preview as a floating panel to the right of a card
- Order splitting: divide one order total into a Cash portion and an ABA portion
- Export to two `.txt` files (summary + detailed) with totals
- Five dedicated delivery driver columns
- Print support via `Ctrl+P`
- Keyboard shortcuts for the calculator

**Currency base:** Riel/Rupiah. Totals are displayed as `Riel / $USD`. Export uses `id-ID` locale (dots as thousands separators).

---

## 2. File Structure

```
project/
├── index.html            ← All HTML structure and panel layout
├── style.css             ← All CSS (dark theme, layout, animations)
├── script.js             ← All JavaScript logic
└── asset/
│   └── image1.png        ← Saung Abah logo (displayed in receipt preview header)
└── stock-folder/
    └── stock.html        ← Separate stock manager page (linked via button)
```

### index.html Structure

```
<body>
  <div.container>
    <aside.sidebar.left>        ← Panel 1: Pending Orders + Checker
    <main.main>                 ← Panel 2: Receipt Maker + Bill Preview
    <aside.sidebar.delivery>    ← Panel 3: 5 Driver panels (Delivery)
    <aside.sidebar.finished>    ← Panel 4: Finished / Completed Orders
    <aside.sidebar.right>       ← Panel 5: Clock, Order No, Calc, Totals, Notepad
  </div>
  <input#imageUploadInput>      ← Hidden file input (image upload)
  <div#uploadModal>             ← Image upload modal (paste / file choice)
  <div#floatingImagePreview>    ← Floating image hover preview panel
</body>
```

---

## 3. Global Architecture

### Global Variables

Declared at the top of `script.js`:

```js
let currentOrderNo = 0;          // Running order counter (persisted in localStorage)
let editingId = null;            // ID of the order currently being edited (null = create mode)
let imageStore = {};             // { "1718000000000": "data:image/png;base64,..." }

// Floating image preview DOM refs
const floatingPreview    = document.getElementById('floatingImagePreview');
const floatingImg        = floatingPreview.querySelector('img');
const floatingDeleteBtn  = document.getElementById('floatingDeleteBtn');
let currentHoverItemId = null;   // ID of the card currently being hovered
let hideTimeout        = null;   // setTimeout reference for delayed preview hide
```

---

### LocalStorage Persistence

The app uses several separate localStorage keys:

| Key | Value |
|-----|-------|
| `appData` | JSON blob of all container innerHTML + imageStore |
| `orderNo` | Current order number integer |
| `lastResetDate` | Date string used for daily auto-reset |
| `cashierNotes` | Raw text content of the notepad textarea |
| `driver_name_thorn` | Custom name for driver slot 1 |
| `driver_name_dom` | Custom name for driver slot 2 |
| `driver_name_pozzal` | Custom name for driver slot 3 |
| `driver_name_etc` | Custom name for driver slot 4 |
| `driver_name_extra` | Custom name for driver slot 5 |

#### `saveData()`

Reads the `innerHTML` of all 7 dynamic containers and packages them with `imageStore` into a single JSON object, then calls `localStorage.setItem('appData', JSON.stringify(data))`.

Containers saved:
- `#historyContainer` (Pending Orders)
- `#thornContainer`, `#domContainer`, `#pozzalContainer`, `#etcContainer`, `#extraContainer` (Delivery)
- `#finishedContainer` (Completed Orders)

> ⚠️ **Important:** Because raw innerHTML is saved (not structured data), the DOM is restored visually but JavaScript event handlers are stripped. `reattachEvents()` must always be called after `loadData()` to restore interactivity.

#### `loadData()`

Runs once at startup.

Steps:
1. Parses `localStorage.getItem('appData')`.
2. Injects each saved innerHTML back into its container.
3. Restores `imageStore` from the JSON blob.
4. Calls `reattachEvents()` to re-bind all click/drag events on restored cards.
5. Calls `updateTotals()` to recalculate Cash and ABA totals.
6. Calls `loadDriverNames()` to restore custom driver names.
7. Adds `.has-image` CSS class to any card whose ID exists in the restored `imageStore`.

#### `reattachEvents()`

Called after `loadData()` and after every `processOrder()` call.

For each `.history-item` found anywhere in the DOM:
- Re-enables `draggable = true` and attaches a `dragstart` listener.
- Attaches `onclick` to show the receipt in the preview pane (`restoreBill(id)`), ignoring clicks on buttons.
- Finds each action button by class name and re-binds its `onclick` handler:
  - `.check-btn` → `toggleCheck(id)`
  - `.edit-btn` → `editHistory(id)`
  - `.del-btn` → `removeHistory(id)`
  - `.archive-btn` → `toggleArchive(id)`
  - `.move-btn` → `moveToDelivery(id)`
  - `.finish-btn` → `moveToFinished(id)`
  - `.split-btn` → `splitOrder(id)`
  - `.upload-btn` → `showUploadModal(id)`

Also calls `attachHoverListeners()` to re-bind image hover events.

---

### Order Lifecycle

```
[Center Panel inputs]
        ↓
processOrder()
        ↓
createHistoryItem()  ──→  appended to #historyContainer  (STATUS: PENDING)
        ↓
  [Card action buttons or drag-and-drop]
        ↓
moveToDelivery()     ──→  driver panel (thornContainer etc.)  (STATUS: DELIVERY)
        ↓
moveToFinished()     ──→  #finishedContainer  (STATUS: FINISHED)
        ↓
exportFinishedOrders()  →  download .txt files
```

An order card does **not** have an explicit `status` field. Its status is determined entirely by **which container it currently lives in** (`item.parentElement.id`). Moving a card is the same as changing its status.

---

### Button Management System

#### `updateButtonsForContainer(item)`

Must be called every time an order card is moved to a new container. It removes all existing buttons and adds the correct set for the new location.

**Logic:**
1. Reads `item.parentElement.id`.
2. Removes all `<button>` children from the card.
3. Always adds: **X (delete)**, **E (edit)**, **📷 (upload image)**.
4. Adds container-specific buttons:

| Container | Additional Buttons Added |
|-----------|--------------------------|
| `historyContainer` (Pending) | ✓ Check, A Archive/ABA, D Delivery, F Finish, S Split |
| Any driver panel (Delivery) | ✓ Check, A Archive/ABA, F Finish, S Split |
| `finishedContainer` (Completed) | ✓ Check, A Archive/ABA, S Split |

#### `createButton(className, text, title, onClick)`

Factory function called by `updateButtonsForContainer`. Creates and returns a `<button>` element with:
- The given CSS class (which controls its absolute position via CSS)
- A text label (single letter or emoji)
- A `title` tooltip attribute
- An `onclick` handler

Special inline styles are applied for `.split-btn` (orange, top-left, 20×20px) and `.upload-btn` (cyan, bottom-left, 20×20px). All other button positions are defined by CSS class rules.

---

### Drag-and-Drop System

All `.sidebar` and `.driver-panel` elements are registered as drop targets on page load:

```js
const containers = document.querySelectorAll('.sidebar, .driver-panel');
containers.forEach(container => {
    container.addEventListener('dragover', e => e.preventDefault());
    container.addEventListener('drop', drop);
});
```

#### `drag(e)`

Fires on `dragstart` of a `.history-item`. Stores the element's full `id` string (e.g., `"hist-1718000000000"`) in `dataTransfer`.

#### `drop(e)`

Fires when a dragged card is released on a container.

Steps:
1. Gets the element ID from `dataTransfer`.
2. Uses `e.target.closest('.driver-panel, #historyContainer, #finishedContainer')` to find the valid drop target.
3. If a valid target is found and it is not already the current parent: appends the card there with `appendChild`.
4. Calls `updateButtonsForContainer(item)` to update the button set.
5. Calls `saveData()` and `updateTotals()`.

> ℹ️ Dropping onto `.sidebar.right`, `.sidebar.left` (outside `#historyContainer`), or any other non-listed area does nothing — the `.closest()` check returns null.

---

## 4. CSS Design System

### Variables & Fonts

```css
:root {
  --green:       #4CAF50   /* Primary CTA, active states, header text */
  --green-dim:   #2e7d32   /* Hover states, darker accent, finish button */
  --bg:          #0c0c0c   /* App background */
  --card:        #161616   /* Card/panel backgrounds */
  --card2:       #1e1e1e   /* Input backgrounds, secondary surfaces */
  --border:      #2a2a2a   /* All element borders */
  --text:        #ffffff   /* Primary text */
  --text-dim:    #888888   /* Placeholder and dim text */
  --red:         #ef5350   /* Delete button, danger */
  --orange:      #FF9800   /* Move-to-delivery button, split button */
  --blue:        #f44336   /* ABA/archive border color (NOTE: actually red) */
  --purple:      #9C27B0   /* Archive button background */
  --yellow:      #FFC107   /* Reserved, unused */
  --cyan:        #00bcd4   /* Upload image button */
}
```

> ⚠️ **Note for AI:** `--blue` is defined as `#f44336` (red) in this codebase. It is used as the left-border color on `.history-item.archived` (ABA payment type). Do not assume this is visually blue.

**Fonts:**
- `'Syne'` — All UI text, buttons, labels, body (bold, geometric sans)
- `'JetBrains Mono'` — Calculator display, receipt preview, order checker, notepad

---

### Layout Structure

```
body  (height: 100vh, overflow: hidden, display: flex, flex-direction: column)
  └── div.container  (display: flex, overflow-x: auto, overflow-y: hidden)
        ├── aside.sidebar.left      (width: 280px, display: flex column)
        ├── main.main               (flex: 1, overflow-y: auto, align-items: center)
        ├── aside.sidebar.delivery  (width: 280px, display: flex column)
        ├── aside.sidebar.finished  (width: 280px, display: flex column)
        └── aside.sidebar.right     (width: 280px, align-items: center)
```

The horizontal `overflow-x: auto` on `.container` allows panning sideways on smaller screens to see all panels. The body is fully locked to the viewport — no page-level scroll.

**Individually scrollable areas:**
- `#historyContainer` — flex-grow, overflow-y: auto (Pending list)
- `#finishedContainer` — flex-grow, overflow-y: auto (Finished list)
- Each `.driver-panel` — flex-grow, overflow-y: auto (per-driver order list)
- `main.main` — overflow-y: auto (center column)

All custom scrollbars: 10px wide, dark track, rounded grey thumb (`var(--border)`).

---

### Shooting Star Animations

The app includes a decorative falling-star animation across multiple panels using `::before` / `::after` pseudo-elements:

```css
@keyframes shooting-star {
  0%   { opacity: 0; transform: translateY(-300px); }
  15%  { opacity: 0.8; }
  50%  { opacity: 0.5; }
  100% { opacity: 0; transform: translateY(calc(100% + 300px)); }
}
```

Panels with stars and their timing:

| Selector | Stars | Durations |
|----------|-------|-----------|
| `.main` | 2 | 18s (delay 0s), 20s (delay 5s) |
| `.info-panel` | 2 | 19s (delay 2s), 16s (delay 1.5s) |
| `.summary-panel` | 2 | 17s (delay 10s), 23s (delay 8s) |
| `.calc` | 2 | 21s (delay 7s), 15s (delay 12s) |
| `.editor-toolbar` | 1 | 23s (delay 4s) |
| `button.action-btn:nth-of-type(4)` | 1 | 17s (delay 5s) |

All stars use `pointer-events: none` and `z-index: -1` — purely decorative, zero impact on functionality.

---

### History Item Buttons (CSS Positioning)

Buttons inside `.history-item` are positioned absolutely. They are `opacity: 0` by default and revealed on hover (`opacity: 1`). All buttons are circular (24×24px, `border-radius: 50%`).

```css
.split-btn   { top: 12px;    left: 12px;  background: var(--orange);    }
.check-btn   { top: 12px;    right: 68px; background: var(--green);     }
.edit-btn    { top: 12px;    right: 40px; background: var(--blue);      }
.del-btn     { top: 12px;    right: 12px; background: var(--red);       }
.archive-btn { bottom: 12px; right: 12px; background: var(--purple);    }
.move-btn    { bottom: 12px; right: 40px; background: var(--orange);    }
.finish-btn  { bottom: 12px; right: 68px; background: var(--green-dim); }
.upload-btn  { bottom: 12px; left: 12px;  background: var(--cyan);      }
```

Card visual states:
- `.history-item.checked` → `border-left: 4px solid var(--green)` (green left accent)
- `.history-item.archived` → `border-left: 4px solid var(--blue)` (red left accent — ABA)
- `.history-item.has-image` → faint `📷` emoji bottom-right via `::after` pseudo-element

---

### Responsive Breakpoints

| Max-Width | Sidebar Width | Key Changes |
|-----------|--------------|-------------|
| 1800px | 260px | Slight padding reduction |
| 1600px | 240px | Smaller driver panel padding, smaller edit button |
| 1400px | 220px | Smaller main padding, editor container max-width 520px |
| 1200px | 200px | Smaller history-item min-height, smaller action buttons, inputBox height 320px |
| 1000px | 180px | Further compaction of driver panel and edit button sizes |

---

### Print Styles

The `@media print` block completely transforms the page for printing:

```css
@media print {
  /* Hide all UI except the receipt box */
  .sidebar, .editor-container, .action-btn, .editor-toolbar, .banner { display: none !important; }
  /* Receipt fills full printable area */
  .main { display: block; padding: 0; }
  #outputBox { display: block !important; border: none; background: white; width: 100%; }
  /* All text: black, Helvetica, 14pt, bold */
  * { font-family: Helvetica, Arial !important; font-size: 14pt !important; color: #000 !important; }
  /* Preserve logo image colors */
  .bill-logo img { max-height: 80px; print-color-adjust: exact; }
}
```

---

## 5. PANEL 1 — Left Sidebar

**HTML element:** `<aside class="sidebar left">`

Two sections stacked vertically inside this panel:
1. **`#historyContainer`** — flexible-height scrollable order card list (takes all available space)
2. **`.checker-container`** — fixed-height section at the bottom, never scrolls out of view

---

### Pending Orders (`#historyContainer`)

A `<div>` with `flex: 1 1 auto` and `overflow-y: auto` that holds `.history-item` cards.

- Has a dashed border and semi-transparent dark background when empty (`:empty` CSS selector).
- Border becomes solid and background transparent when cards are present (`:not(:empty)`).
- New orders are appended here by `processOrder()`.
- Cards leave this panel via the **D** (Delivery), **F** (Finish) buttons, or by drag-and-drop.
- Clicking anywhere on a card (not a button) calls `restoreBill(id)` to show the receipt in the center panel.

---

### Order Checker

**HTML elements:**
```html
<div id="checkerInput" contenteditable="true" class="text-area"></div>
<button id="checkOrders">✅ Check Orders</button>
<button id="clearChecker">🗑 Clear</button>
<div id="checkerResult"></div>
```

The checker input is a `contenteditable` div, not a `<textarea>`. This allows the checker to inject colored `<span>` elements directly into the input area to highlight valid and invalid lines in-place.

See [Section 12](#12-order-checker-detailed-logic) for the full validation logic.

---

## 6. PANEL 2 — Main Center

**HTML element:** `<main class="main">`

Two sections:
1. `.editor-container` — the order creation form (centered, max-width 600px)
2. `#outputBox` — the receipt/bill preview (hidden by default, shown after `processOrder()`)

The center panel also contains the animated `shooting-star` stars via `.main::before` and `.main::after`.

---

### Input Fields

| Element ID | Type | Purpose |
|------------|------|---------|
| `#customerName` | `<input>` | Customer name → printed as `CUSTOMER: NAME` in bill |
| `#customerAddress` | `<input>` | Delivery address → printed as `ADDRESS: ADDR` |
| `#notes` | `<textarea>` | Special instructions → printed as `NOTES: TEXT` |
| `#inputBox` | `<textarea>` | Raw order items, one per line (JetBrains Mono, 400px tall) |

All inputs have dark backgrounds (`var(--card2)`), and glow green on focus with a `box-shadow` effect. Notes textarea is 80px tall and `resize: none`. The input box is `resize: vertical`.

---

### `processOrder()`

**Trigger:** "Accept Receipt! ❇️" button (`onclick="processOrder()"`)

This is the core function for order creation and editing.

**Steps:**

1. Reads `#inputBox.value.trim()`. If empty → `alert("Enter order first!")`, returns.
2. Reads `#customerName`, `#customerAddress`, `#notes`.
3. Generates a `dateTime` string in `DD/MM/YYYY, HH:MM:SS` format using `en-GB` locale.
4. Determines order number:
   - **Edit mode** (`editingId !== null`): reads the existing card's `dataset.orderNo` to preserve the original number.
   - **New order mode**: calls `incrementOrderNo()` to advance and return the next padded number.
5. Parses each line of `#inputBox` for quantity and price:
   - **Quantity**: checks for a leading number (`"3 Coffee"`) first, then for a trailing parenthetical (`"Coffee (3)"`). Defaults to `qty = 1`.
   - **Price**: matches `\d+[.,]?\d*k` pattern (multiplies by 1000). Falls back to a bare trailing number; if that number is < 100 it also multiplies by 1000 (assumes "k" shorthand).
   - Lines containing `"total"` or `"subtotal"` (case-insensitive) are displayed but **excluded** from total calculation.
   - Running tallies: `total` (Riel), `items` (count).
6. Builds `billHTML` — structured HTML using CSS classes:
   - `.bill-info` — order number and date/time
   - `.bill-line` — CUSTOMER, ADDRESS, NOTES lines
   - `.bill-item` — `- ITEM NAME` for each order line
   - `.bill-separator` — `-----------------------------------` dividers
   - `.bill-summary` — `* ITEM = N items`
   - `.bill-total` — `* TOTAL : X,XXX / $Y.YY$`
7. Builds `plainText` — exact same content as pure text (no HTML tags), used for clipboard copy and `.txt` export.
8. Injects `billHTML` into `#billContent` and makes `#outputBox` visible (`display: block`).
9. Calls `createHistoryItem(...)` to build the order card DOM element.
10. **Edit mode**: finds the old card by `hist-{editingId}`, replaces it in-place with `replaceChild`. Transfers image attachment (moves `imageStore[editingId]` to the new card ID, adds `.has-image` class). Clears `editingId = null`.
11. **New order mode**: appends the new card to `#historyContainer`.
12. Calls `updateButtonsForContainer(item)` and `reattachEvents()`.
13. Clears all four input fields.
14. Calls `saveData()`.

---

### `createHistoryItem()`

```js
function createHistoryItem(orderNo, htmlContent, plainText, total, items, name, addr, notes, raw)
```

Builds and returns a `<div class="history-item">` element with all data stored as `dataset` attributes.

**`dataset` attributes stored:**

| Attribute | Value |
|-----------|-------|
| `data-order-no` | Zero-padded order number string (e.g., `"005"`) |
| `data-html-content` | Full `billHTML` string (for `restoreBill`) |
| `data-plain-text` | Full `plainText` string (for export) |
| `data-total` | Numeric total (Riel) as a string |
| `data-items` | Item count |
| `data-customer-name` | Customer name |
| `data-customer-address` | Delivery address |
| `data-notes` | Notes |
| `data-raw-input` | Original raw textarea text (restored into `#inputBox` for editing) |

**Visible innerHTML of the card:**
```
Order 005 - John Doe
Address: Street 1
Notes: Extra spicy
Total: 15,000
```

The element `id` is set to `hist-{Date.now()}` — a unique timestamp-based string.

---

### `copyReceipt()`

**Trigger:** "Copy Receipt ❗️" button

- Checks if `#outputBox.style.display === 'none'`. If so → `alert('No receipt to copy!')`.
- Uses `navigator.clipboard.writeText(output.innerText)` to write the visible receipt text to the clipboard.
- On success → `alert('Receipt copied to clipboard!')`.

---

### `restoreBill(id)`

**Trigger:** Clicking on a `.history-item` card body (not on any button).

- Reads `item.dataset.htmlContent`.
- Injects it into `#billContent` (inside `#outputBox`).
- Sets `#outputBox.style.display = 'block'`.

Use case: re-view a previously created receipt without needing to re-enter the order.

---

### Print Support

```js
document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();       // Block browser default print dialog
        if (outputBox has content) window.print();
        else alert("Nothing to print! Generate a receipt first.");
    }
});
```

`Ctrl+P` is intercepted globally. `window.print()` is only called when `#outputBox` is visible and has content. The CSS `@media print` rules then hide everything except the receipt.

---

## 7. PANEL 3 — Delivery Sidebar

**HTML element:** `<aside class="sidebar delivery">`

Contains 5 `.driver-panel` divs (one per driver):

```
#thornContainer   → THORN  (default name)
#domContainer     → DOM
#pozzalContainer  → POZZAL
#etcContainer     → ETC
#extraContainer   → EXTRA
```

Each panel:
- Has an `<h4>` with the driver's name (customizable).
- A `📝` `.edit-driver-btn` button (absolutely positioned top-right of the panel header).
- Accepts `.history-item` cards by drag-and-drop or via the `moveToDelivery()` function.
- Scrolls independently when orders exceed panel height.

---

### `moveToDelivery(id)`

**Trigger:** `D` button on a Pending order card.

Steps:
1. Shows a numbered `prompt()` listing 5 driver slots.
2. Parses the entered integer as an array index (0–4) into `drivers[]` (the container ID array).
3. If invalid input → `alert("Invalid choice!")`, returns.
4. Appends the card to the chosen driver container via `document.getElementById(drivers[index]).appendChild(item)`.
5. Calls `updateButtonsForContainer(item)` (removes the D button since the card is now in delivery).
6. Calls `saveData()`.

> ⚠️ **Known quirk:** The prompt text shows hardcoded driver names (`NITH`, `MEY`, `THORN`, `ETC`, `FOOD READY!`) which are **not linked** to the actual saved/displayed driver names. The mapping is always index-based: choice 1 → `#thornContainer`, choice 2 → `#domContainer`, etc., regardless of what name is displayed in the panel header.

---

### `editDriverName(driver)`

**Trigger:** `📝` button on a driver panel header.  
**Parameter:** The slot key string — one of `'thorn'`, `'dom'`, `'pozzal'`, `'etc'`, `'extra'`.

Steps:
1. Gets the `<h4>` element from the specified container.
2. Shows `prompt("Enter new driver name:", currentName)`.
3. If a non-empty name is entered: uppercases it, updates the `<h4>` text, saves to `localStorage.setItem('driver_name_{key}', cleanName)`.

---

### `loadDriverNames()`

Called once inside `loadData()` on page startup.

Loops through all 5 driver keys. For each: checks `localStorage.getItem('driver_name_{key}')`. If a saved name exists, updates the corresponding `<h4>` text to restore the custom name.

---

## 8. PANEL 4 — Finished Orders Sidebar

**HTML element:** `<aside class="sidebar finished">`

Contains:
- `#finishedContainer` — scrollable list of completed order cards (flex-grow)
- `💾 Download Order` purple button → `exportFinishedOrders()`

---

### `moveToFinished(id)`

**Trigger:** `F` button on a Pending or Delivery card.

Steps:
1. Appends the card to `#finishedContainer` with `appendChild`.
2. Calls `updateButtonsForContainer(item)` — removes the D (delivery) and F (finish) buttons since the order is now complete.
3. Calls `saveData()` and `updateTotals()`.

---

### `exportFinishedOrders()`

**Trigger:** `💾 Download Order` button.

If `#finishedContainer` has no orders → `alert("No completed orders!")`, returns.

Otherwise, builds and downloads two `.txt` files simultaneously:

**File 1 — Summary** (`Completed_Orders_{DD-MM-YYYY}.txt`):
- Header row: `Customer Name | Address | Total | ABA | CHECK`
- One tab-separated data row per finished order.

**File 2 — Detailed** (`Completed_Orders_Detailed_{DD-MM-YYYY}.txt`):
- Same header and data rows as summary.
- Below each order row: the full plain-text bill content (`item.dataset.plainText` or extracted from `dataset.htmlContent`).
- Footer lines at the end:
  - `Grand Total Cash: X,XXX` (unarchived, non-checked)
  - `Grand Total ABA: X,XXX` (archived, non-checked)
  - `Grand Total: X,XXX` (combined)

**Totals in export:**
- Orders with `.checked` class are **excluded** from all grand totals.
- Orders with `.archived` class count as ABA.
- All others count as Cash.
- Total string is extracted from visible card text via regex: `/Total: ([\d.,]+)/`.

Both files are created via `Blob` objects and downloaded with dynamically created `<a>` elements. After both downloads, an `alert()` confirms success and `saveData()` / `updateTotals()` are called.

---

### `updateTotals()`

Called whenever orders are moved to/from `#finishedContainer` or their state toggles.

Steps:
1. Iterates all `.history-item` elements inside `#finishedContainer`.
2. Skips any with `.checked` class.
3. Reads `item.dataset.total` as a float.
4. If `.archived` → adds to `archived` variable. Otherwise → adds to `unarchived`.
5. Updates `#totalUnarchived` (💵 CASH) and `#totalArchived` (💳 ABA) with `id-ID` locale formatting.

---

## 9. PANEL 5 — Right Sidebar (Tools)

**HTML element:** `<aside class="sidebar right">`

Stacked vertically: Clock panel, Order Number panel, Calculator, Summary panel, Notepad panel, Stock button.

---

### Live Clock & Date

**Elements:** `#liveTime`, `#liveDate` inside `.info-panel`

#### `updateTimeAndOrder()`

Runs every 1000ms via `setInterval`.

- Updates `#liveTime` using `toLocaleTimeString('en-GB')` → `"HH:MM:SS"` (24h).
- Updates `#liveDate` using `toLocaleDateString('en-GB')` → `"D/M/YYYY"`.
- **Daily auto-reset**: compares `localStorage.getItem('lastResetDate')` with `now.toDateString()`. If different (new day): resets `currentOrderNo = 0`, updates `lastResetDate`, calls `saveOrderNo()`.
- If `localStorage.getItem('orderNo')` doesn't exist yet: initializes it to `'0'`.

---

### Order Number Management

**Element:** `#displayOrderNo` — a large green clickable number, zero-padded to 3 digits (e.g., `"005"`). Below it is the hint text `"(Click number to reset)"`.

#### `initializeOrderNumber()`

Called once on startup. Reads `localStorage.getItem('orderNo')`, sets `currentOrderNo`, and updates `#displayOrderNo` with 3-digit padding.

#### `incrementOrderNo()`

Called by `processOrder()` for each new order.
- Increments `currentOrderNo`.
- Calls `saveOrderNo()`.
- Updates the displayed number.
- Returns the zero-padded string for use in the bill.

#### `saveOrderNo()`

Saves `currentOrderNo` to `localStorage.setItem('orderNo', currentOrderNo)`.

#### `manualResetOrderNo()`

**Trigger:** Clicking `#displayOrderNo`.
- Shows `prompt("Set new Order Number (0 to reset):", currentOrderNo)`.
- If a valid non-negative integer is entered: updates `currentOrderNo`, saves, refreshes display.

---

### Calculator

**Elements:** `.calc` wrapper, `#calcDisplay` readonly text input, `.calc-grid` button grid, `#calcHistory` div.

The calculator works by building a full expression string in `#calcDisplay` and evaluating it with `eval()`. This means multi-step expressions like `15000+3000-5000/2` are fully supported.

#### `calcAppend(val)`

Appends the given character (`val`) to `calcDisplay.value`. Calls `calcDisplay.scrollLeft = calcDisplay.scrollWidth` to auto-scroll the input when the expression is long.

#### `calcClear()`

Clears `calcDisplay.value` and resets `lastResult = ''`.

#### `calcSolve()`

1. Reads the full expression from `calcDisplay.value`.
2. Calls `eval(expression)`.
3. On success: sets the display to the result, saves to `lastResult`, prepends `"expression = result"` to `#calcHistory` div (history stacks newest-first).
4. On error (malformed expression): reverts the display to `lastResult` (or empty if no previous result).

#### Keyboard Shortcuts

Active globally unless focus is on `#inputBox`, `#customerName`, `#customerAddress`, or `#checkerInput`:

| Key | Action |
|-----|--------|
| `0–9`, `.`, `+`, `-`, `*`, `/` | `calcAppend(key)` |
| `Enter` | `calcSolve()` |
| `Backspace` | Removes last character from display |
| `Escape` | `calcClear()` |

**Button grid layout (4 columns):**
```
7    8    9    /
4    5    6    *
1    2    3    -
0    .    C    +
[   =   (spans 2 columns)    ]
```

`.op` buttons are orange (`var(--orange)`). The `.eq` button (`=`) is green (`var(--green)`) and spans 2 grid columns. All buttons have a hover lift effect (`transform: translateY(-2px)`).

---

### Total Cash-In Summary

**Element:** `.summary-panel`

```html
💵 CASH (RIEL): <span id="totalUnarchived">0</span>
💳 ABA (RIEL):  <span id="totalArchived">0</span>
```

Updated by `updateTotals()`. Only counts orders currently in `#finishedContainer` that do not have `.checked` class. Uses `id-ID` locale (dots as thousands separators).

---

### Cashier Notepad

**Element:** `.notepad-panel` containing `#cashier-notepad` textarea and `#notepad-total`.

Initialized inside `DOMContentLoaded`:
- Loads saved text from `localStorage.getItem('cashierNotes')`.
- On every `input` event: persists current text to `localStorage` and calls `calculateSum()`.

#### `calculateSum()` (inner function)

- Splits the textarea text by newlines.
- For each line: attempts to match `/(\d+)k$/i` — a number followed by `k` at the end of the line.
- Multiplies each matched number by 1000, adds to a running `total`.
- Updates `#notepad-total`: `"Total Outstanding: {total} Riel"`.

**Example:**
```
es batu 1k       →  1,000
kopi 2k          →  2,000
nasi goreng 3k   →  3,000
─────────────────────────
Total Outstanding: 6,000 Riel
```

The notepad content persists across page refreshes via `localStorage.getItem('cashierNotes')`.

---

### Stock Manager Link

**Element:** Orange `📦 Manage Stock` button.

```js
function goToStock() {
    window.location.href = 'stock-folder/stock.html';
}
```

Navigates to a separate stock management HTML page. All POS state remains saved in localStorage and is fully available when returning to the main page.

---

## 10. Order Card Action Buttons

All buttons on `.history-item` cards are hidden by default (`opacity: 0`) and revealed on hover (`opacity: 1`). Which buttons are present is determined by `updateButtonsForContainer()` based on the card's current container.

---

### `toggleCheck(id)`

**Button:** `✓` — green circle, top-right area  
**Available in:** All containers.

- Calls `item.classList.toggle('checked')`.
- `.checked` adds a `border-left: 4px solid var(--green)` accent.
- Checked orders are **excluded from all financial totals** (`updateTotals()` skips them).
- Calls `saveData()` and `updateTotals()`.
- Use case: mark an order as verified, handed over, or otherwise handled.

---

### `toggleArchive(id)`

**Button:** `A` — purple circle, bottom-right  
**Available in:** All containers.

- Calls `item.classList.toggle('archived')`.
- `.archived` adds a `border-left: 4px solid var(--blue)` accent (which appears red, `#f44336`).
- Archived = ABA/card payment type in all financial calculations.
- Calls `saveData()` and `updateTotals()`.

---

### `editHistory(id)`

**Button:** `E` — blue/red circle, top-right  
**Available in:** All containers.

Steps:
1. Sets global `editingId = id`.
2. Populates center panel inputs with the card's stored data:
   - `#inputBox` ← `item.dataset.rawInput`
   - `#customerName` ← `item.dataset.customerName`
   - `#customerAddress` ← `item.dataset.customerAddress`
   - `#notes` ← `item.dataset.notes`
3. Shows `alert("Loaded for editing! Make changes and click 'Accept Receipt!' again.")`.
4. The next `processOrder()` call will **replace** this specific card in-place (at its current position in its current container) rather than creating a new pending order.

---

### `removeHistory(id)`

**Button:** `X` — red circle, top-right corner  
**Available in:** All containers.

Steps:
1. Shows `confirm("Delete this order?")`.
2. If confirmed:
   - Deletes `imageStore[id]` (removes associated image data from memory).
   - Hides floating image preview if this card was being hovered.
   - Removes the DOM element with `item.remove()`.
   - Calls `saveData()` and `updateTotals()`.

---

### `splitOrder(id)`

**Button:** `S` — orange circle, top-left  
**Available in:** All containers.

Splits one order into two separate cards: one for the Cash portion, one for the ABA portion.

**Steps:**
1. Reads `originalItem.dataset.total` and `orderNo`.
2. Shows `prompt("Enter ABA amount for Order X (total: Y Riel):")`.
3. Validates: must be a valid positive number not exceeding the total.
4. Calculates `cashAmount = originalTotal - abaAmount`.
5. Creates a `duplicateItem` — a new `<div class="history-item">` with ID `hist-{Date.now()}`.
6. Copies all `dataset` keys from original to duplicate, except `data-total` (set to `abaAmount`).
7. Adds `.archived` class to the duplicate (marks it as ABA).
8. Updates `originalItem.dataset.total` to `cashAmount`.
9. Updates both cards' visible HTML: replaces `"Total: X"` text with the new amounts.
10. Updates `dataset.htmlContent` via regex (replaces `* TOTAL :` value in stored bill HTML).
11. Updates `dataset.plainText` via regex (replaces `* TOTAL :` in stored plain text).
12. Inserts the duplicate immediately after the original using `insertBefore(duplicateItem, originalItem.nextSibling)`.
13. Calls `updateButtonsForContainer` and `reattachEvents` on both cards.
14. Does **not** copy the image — only the original (Cash) card retains any attached image.
15. Calls `saveData()` and `updateTotals()`.
16. Shows `alert("Order split successfully!")`.

---

## 11. Image Upload System

Each order card has a `📷` (upload) button. Images are stored as base64 data URLs in the global `imageStore` object. Keys are the raw timestamp IDs of orders (the number part of `hist-{id}`, without the prefix).

---

### `showUploadModal(itemId)`

**Trigger:** `📷` button on any card.

- Sets `currentUploadItemId = itemId` (global reference).
- Sets `uploadModal.style.display = 'flex'` to show the modal.
- The modal presents two action buttons and a Cancel button.

Closing behavior:
- **Cancel button** → hides modal, clears `currentUploadItemId`.
- **Clicking the backdrop** (outside `.modal-content`) → same as Cancel.

---

### Paste from Clipboard

**Button:** `📋 Paste from Clipboard`

Uses the modern `navigator.clipboard.read()` API (requires HTTPS or localhost):

1. Hides the modal immediately.
2. Reads clipboard items using `await navigator.clipboard.read()`.
3. Iterates clipboard items looking for `image/png`, `image/jpeg`, or `image/jpg` types.
4. Converts the found blob to a base64 data URL via `blobToDataURL(blob)`.
5. Calls `saveImageToItem(currentUploadItemId, dataUrl)`.

Error handling:
- No image in clipboard → `alert("No image found in clipboard. Please copy an image first (Win+Shift+S).")`.
- Clipboard API fails (browser permissions, non-HTTPS context) → `alert("Unable to read clipboard. Please use 'Choose File' instead.")`.

---

### Choose File

**Button:** `📁 Choose File`

1. Hides the modal.
2. Programmatically clicks the hidden `<input type="file" id="imageUploadInput" accept="image/*">`.
3. On file selection (`change` event):
   - Validates `file.type.startsWith('image/')`.
   - Uses `FileReader.readAsDataURL(file)` to get the base64 string.
   - Calls `saveImageToItem(currentUploadItemId, event.target.result)`.
   - Resets `fileInput.value = ''` so the same file can be re-selected next time.
   - Clears `currentUploadItemId = null`.

#### `blobToDataURL(blob)`

A Promise wrapper around `FileReader`. Resolves with a `data:image/...;base64,...` string from any `Blob` input.

---

### `saveImageToItem(itemId, dataUrl)`

1. Finds `document.getElementById('hist-' + itemId)`.
2. Sets `imageStore[itemId] = dataUrl`.
3. Adds `.has-image` CSS class to the card (triggers the faint `📷` indicator via CSS `::after`).
4. Calls `saveData()` to persist the image in localStorage.

---

### `deleteImage(itemId)`

**Trigger:** `✕` red button on the floating preview panel.

1. Shows `confirm('Remove this image?')`.
2. If confirmed:
   - Removes `.has-image` class from the card element.
   - Deletes `imageStore[itemId]`.
   - Hides floating preview and clears `currentHoverItemId` if this item was being hovered.
   - Calls `saveData()`.

---

### Hover Preview

When the mouse enters a `.history-item` card that has an associated image, a floating preview panel appears to the right of the card.

#### `attachHoverListeners()`

Called by `reattachEvents()`. Removes existing `mouseenter`/`mouseleave` listeners from all `.history-item` elements (to prevent duplicate bindings) then adds them fresh.

#### `onItemMouseEnter(e)`

- Reads the card ID (strips `hist-` prefix).
- If `imageStore[id]` exists:
  - Sets `floatingImg.src` to the stored data URL.
  - Positions the preview: `top = card.getBoundingClientRect().top`, `left = card.right + 10px`.
  - Shows `#floatingImagePreview` (`display: block`).

#### `onItemMouseLeave()`

- Starts a 200ms `setTimeout` before hiding the preview.
- This delay allows the user to move their mouse from the card to the floating preview without it disappearing.

#### Floating Preview Hover Handling

- `mouseenter` on `#floatingImagePreview` → clears the hide `setTimeout` (preview stays open).
- `mouseleave` on `#floatingImagePreview` → hides the preview immediately.
- `#floatingDeleteBtn` (`✕` button) → calls `deleteImage(currentHoverItemId)` and hides the preview.

---

## 12. Order Checker (Detailed Logic)

**Location:** Bottom of Panel 1 (Left Sidebar).  
**Purpose:** Validate a pasted order list to detect pricing errors before creating receipts.

The `#checkerInput` is a `contenteditable` div styled as a code-like text area (JetBrains Mono, 200px, resizable). This allows the checker to inject colored `<span>` elements back into the input to highlight lines in-place.

### `clearBtn.onclick`

- Clears `checkerInput.innerHTML = ''`.
- Sets `resultDiv.innerText = 'Ready to scan...'` in blue.

### `checkBtn.onclick`

Reads `checkerInput.innerText.trim()`, splits by `\n`, processes each line:

**Regex patterns used:**

```js
const rgxPrice  = /(\d+(?:[.,]\d+)?)(\s*)(k|rb)?\b/gi  // Finds price-like tokens
const rgxNote   = /^[(-*]|^(note|catatan):/i            // Note/comment lines
const rgxHeader = /:\s*$/                               // Section header lines (end in colon)
```

**Per-line processing rules:**

| Condition | Treatment |
|-----------|-----------|
| Empty line | Inserts `<br>`, skipped for price check |
| Line ends with `:` (section header) | Rendered as neutral `<span>`, skipped |
| Line starts with `-`, `(`, `*`, `note:`, `catatan:`, or is shorter than 4 chars | Rendered as neutral `<span>`, skipped |
| All other lines | Subjected to price extraction below |

**Price extraction (for all other lines):**

1. Runs `rgxPrice.exec()` in a loop to find all price-like tokens in the line.
2. For each match:
   - Strips formatting dots and commas from the number string.
   - If there is a **space between the number and the suffix** (e.g., `"10 k"`) → marks `malformed = true`.
   - If a `k` or `rb` suffix is present (without space) → multiplies value by 1000.
   - Only prices ≥ 1000 are counted as valid.
3. After scanning the whole line:
   - If `malformed = true` OR the count of valid prices is not exactly 1 → **ERROR** (red highlight, `errorCount++`).
   - If exactly 1 valid price found → **VALID** (green highlight, adds to `total`, `itemCount++`).

**Output:**
- Re-injects `checkerInput.innerHTML` with wrapped `<span class="valid">` (green background) or `<span class="error">` (red background) elements.
- Updates `#checkerResult` with:
  ```
  📦 Items Found: N
  💰 Estimated Total: X,XXX
  ⚠️ Missing Prices: N lines (Highlighted in Red)
  ```
- Result text color: green if no errors, red if any errors.
- If errors found: shows an `alert()` explaining the three error types (no price, multiple prices, malformed price with space before `k`).

#### `escapeHtml(text)`

A safety helper called before injecting any line text into the checker output HTML. Converts `&`, `<`, `>`, `"`, `'` to their HTML entities to prevent broken markup or XSS when lines contain special characters.

---

## 13. AI Context Summary

If you are an AI reading this to understand the codebase for modification or extension, here are the critical facts:

- **Three-file app.** All logic is in `script.js`, styles in `style.css`, structure in `index.html`. No framework, no build tools, no module system.
- **No central state object.** Unlike the phone version, there is no `state = {}`. All data lives in the DOM itself. Orders are `<div>` elements. Their position determines their status.
- **`saveData()` serializes innerHTML.** The app stores the raw HTML string of all containers. This is efficient but means data is tightly coupled to the DOM structure. Do not break card HTML or `data-*` attributes.
- **`reattachEvents()` is mandatory after DOM mutations.** Saving/loading strips JavaScript handlers. Every function that adds `.history-item` elements to the DOM must call `reattachEvents()` afterward or buttons will be dead.
- **Order status = container location.** Check `item.parentElement.id`. Pending = `historyContainer`. Delivery = one of the five driver container IDs. Finished = `finishedContainer`.
- **`updateButtonsForContainer(item)` must be called every time a card moves.** It clears and rebuilds the card's buttons based on its new parent container. Missing this call leaves a card with wrong or stale buttons.
- **`imageStore` is a separate in-memory object.** Images are NOT embedded in card HTML. They are keyed by raw timestamp ID strings (e.g., `"1718000000000"`, not `"hist-1718000000000"`). This object is serialized into `appData` in localStorage alongside the container HTML.
- **`editingId` is the edit/create mode switch.** If `editingId !== null`, `processOrder()` replaces an existing card. After editing completes, `editingId` is reset to `null`. Never leave `editingId` set unintentionally.
- **Calculator uses `eval()`.** The display holds a raw expression string. Any valid JavaScript arithmetic expression works. There is no state machine.
- **Driver names are per-slot in localStorage**, not in a shared array. Keys: `driver_name_thorn`, `driver_name_dom`, etc. The `moveToDelivery()` prompt uses hardcoded names that are **independent** of displayed/saved driver names — this is a known quirk of the current version.
- **`updateTotals()` only reads `#finishedContainer`.** Pending and Delivery orders never contribute to the Cash/ABA summary panel totals.
- **`splitOrder()` creates a new DOM element.** The duplicate gets a brand new `hist-{Date.now()}` ID and is inserted into the DOM immediately after the original. Both are fully independent cards after the split.
- **The Order Checker is purely visual.** It colorizes the `contenteditable` div in-place. It does not create orders, does not interact with the order pipeline, and does not persist anything.
- **Print is Ctrl+P intercepted.** Native browser print is blocked. `window.print()` is only triggered when `#outputBox` contains content, using `@media print` CSS to show only the receipt.
- **Shooting stars are CSS-only decorations.** `::before` / `::after` pseudo-elements with `pointer-events: none` and `z-index: -1`. They have zero functional impact.
- **The `--blue` CSS variable is actually red (`#f44336`).** It is used for `.archived` (ABA) card borders. This may be a legacy naming issue. Do not assume it renders blue.
