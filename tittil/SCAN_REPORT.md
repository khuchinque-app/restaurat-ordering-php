# Storefront Scan Report — Issues Found

## Files Scanned (storefront only, excluding cashier/)

| File | Type | Framework |
|------|------|-----------|
| tittil/index.php | Tailwind CDN | v1 (inline PHP loader) |
| tittil/index.html | CSS classes | v2 (style.css + theme.css) |
| aseng/index.php | Tailwind CDN | v1 (inline PHP loader) |
| aseng/index.html | CSS classes | v2 (style.css + theme.css) |

Supporting files: tittil/assets/app.js, aseng/assets/app.js, tittil/assets/style.css, aseng/assets/style.css (identical), tittil/assets/theme.css (stub), aseng/assets/theme.css (dark mode overrides)

---

## 1) Undefined onclick Functions

### ISSUE-1: `sendChatMessage()` called but `sendChatMsg()` defined
The CSS-class-based HTML files call `sendChatMessage()` (with "Message") but app.js defines `sendChatMsg()` (without "Message").

**[CRITICAL] tittil/index.html:253**
```html
<button onclick="sendChatMessage()" aria-label="Send">
```
→ Only `sendChatMsg()` exists in tittil/assets/app.js:254. Chat send button is dead.

**[CRITICAL] aseng/index.html:252**
```html
<button onclick="sendChatMessage()" aria-label="Send">
```
→ Only `sendChatMsg()` exists in aseng/assets/app.js:228. Chat send button is dead.

### ISSUE-2: `selectedPayment` undefined in aseng/app.js
**[CRITICAL] aseng/assets/app.js:98** — `placeOrder()` sends `paymentType: selectedPayment` but `selectedPayment` is never declared in aseng/app.js. Throws ReferenceError when placing order. (Only tittil/app.js defines it at line 25.)

### ISSUE-3: `confirm-num` vs `confirm-order-num` ID mismatch
**[CRITICAL] aseng/index.html:204** — Confirm modal uses `id="confirm-order-num"` but aseng/app.js:105 references `document.getElementById('confirm-num')`. After a successful order, `confirm-num` is null → TypeError thrown → caught as "Network error" toast. Order succeeds but user sees error.

### ISSUE-4: `confirm-payment` element missing in aseng/index.html
**[CRITICAL] aseng/index.html:204-205** — Confirm modal has no `#confirm-payment` element, but aseng/app.js:106 does `document.getElementById('confirm-payment').textContent = ...` → TypeError, caught as "Network error" toast.

---

## 2) Broken Event Handlers (Class Convention Mismatch)

Both CSS-based index.html files use `style.css` + `theme.css` with custom `.open` class conventions, but **app.js uses Tailwind's `.hidden` / `.invisible` class conventions** (designed for the Tailwind-based index.php). The two conventions never meet, rendering three components non-functional:

### ISSUE-5: Cart Drawer never opens on CSS pages
**[CRITICAL] All CSS-based pages (tittil/index.html, aseng/index.html)**
- app.js `openCart()`: `classList.remove('invisible')` — CSS has no `.invisible` rule
- app.js `closeCart()`: `classList.add('invisible')` — CSS has no `.invisible` rule
- CSS uses `.cart-drawer.open { right: 0 }` — JS never toggles `open`
- Result: Cart stays at `right: -420px`. Cart is completely non-functional.

### ISSUE-6: Confirm Modal never shows on CSS pages
**[CRITICAL] All CSS-based pages**
- app.js `placeOrder()`: `classList.remove('hidden')` to open — CSS expects `.confirm-modal.open`
- HTML close handlers: `classList.remove('open')` — but `open` was never added
- Result: Confirmation modal never appears after placing order on CSS-based pages.

### ISSUE-7: Chat Widget never opens on CSS pages
**[CRITICAL] All CSS-based pages**
- app.js `toggleChat()`: `classList.toggle('hidden', !chatOpen)` — CSS expects `.chat-widget.open`
- Result: Chat widget never becomes visible.

### ISSUE-8: Drawer Overlay never shown
**[MINOR] All CSS-based pages**
- CSS has `.drawer-overlay.open { opacity:1; visibility:visible }`
- No JS code ever adds the `open` class to the overlay
- Cart drawer has no backdrop on CSS pages (on Tailwind pages, the overlay div has inline onclick="closeCart()")

---

## 3) CSS Layout / Alignment Problems

### ISSUE-9: Greeting bar completely unstyled on tittil/index.html
**[MODERATE] tittil/index.html:91-99** — Classes `.greeting-bar`, `.greeting-text`, `.greeting-sub`, `.greeting-avatar` are used in the HTML but **none of these are defined in the base style.css**. Tittil's theme.css is a stub (only 1 line, no real styles). Aseng's theme.css defines them (lines 103-107) so aseng/index.html is fine.

### ISSUE-10: section-more button has dark-theme backgrounds on light theme
**[MINOR] tittil/assets/style.css:551-566**
```css
.section-more {
  background: rgba(255,255,255,0.06);  /* near-invisible on cream bg */
  border: 1px solid rgba(255,255,255,0.08);
}
```
This button (at `tittil/index.html:124`, `aseng/index.html:124`) has `background: rgba(255,255,255,0.06)` — designed for a dark background but the base style.css applies it to all themes. Aseng fixes it via theme.css `!important` overrides; tittil does not.

### ISSUE-11: Broken onerror handler on greeting avatar
**[MINOR] tittil/index.html:97, aseng/index.html:97**
```html
onerror="this.style.display='none';this.parentElement.innerHTML='<div class=\'avatar-placeholder\'>img src='assets/logo-icon.png?v=2' alt='' style='width:40px;height:40px;border-radius:50%;object-fit:cover'></div>'"
```
The inner `src='assets/...'` single quotes will break the JavaScript string. If the avatar image fails to load, the onerror handler will crash with a JS syntax error.

### ISSUE-12: Food drawer exists in HTML but no add-to-cart button inside it
**[MINOR] All pages** — The food item detail drawer (`.php` files: lines 94-108) shows name/category/price/image but has no "Add to Cart" button, so users must close it and use the + button on the menu card.

---

## Summary Table

| # | Impact | File | Line | Issue |
|---|--------|------|------|-------|
| 1 | CRITICAL | tittil/index.html | 253 | `sendChatMessage()` undefined → `sendChatMsg()` |
| 1 | CRITICAL | aseng/index.html | 252 | `sendChatMessage()` undefined → `sendChatMsg()` |
| 2 | CRITICAL | aseng/app.js | 98 | `selectedPayment` not declared → ReferenceError on order |
| 3 | CRITICAL | aseng/index.html | 204 | `confirm-order-num` id mismatch with app.js `confirm-num` |
| 4 | CRITICAL | aseng/index.html | - | `#confirm-payment` element missing, app.js line 106 crashes |
| 5 | CRITICAL | tittil/index.html + aseng/index.html | cart drawer | JS uses `invisible` class, CSS uses `.open` → cart never opens |
| 6 | CRITICAL | tittil/index.html + aseng/index.html | confirm modal | JS uses `hidden` class, CSS uses `.open` → modal never shows |
| 7 | CRITICAL | tittil/index.html + aseng/index.html | chat widget | JS uses `hidden` class, CSS uses `.open` → widget never shows |
| 8 | MINOR | tittil/index.html + aseng/index.html | drawer overlay | `.open` class never toggled by JS |
| 9 | MODERATE | tittil/index.html | 91-99 | Greeting bar classes missing from style.css, theme.css is stub |
| 10 | MINOR | style.css | 551-566 | `section-more` button has dark-bg tints on light theme |
| 11 | MINOR | tittil/index.html + aseng/index.html | 97 | Broken onerror handler (unescaped quotes) |
| 12 | MINOR | All .php pages | food drawer | No "Add to Cart" button in food detail drawer |
