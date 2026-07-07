# Superadmin + Includes Audit Report
**Date:** 2026-07-05 | **Files audited:** 19 (11 superadmin + 8 includes) | **Findings:** 16

---

## CRITICAL (1)

### 1. BROKEN CSS LINK — Leading Space in `href`
**File:** `includes/header.php:14`
```php
<link rel="stylesheet" href=" <?= APP_URL ?>/assets/css/style.css">
```
The leading space before `<?= APP_URL ?>` creates a malformed URL. Browsers will interpret this as a relative path starting with a space, breaking the stylesheet load.

---

## HIGH (6)

### 2. UNDEFINED CSS CLASS: `.btn-ghost`
**Files:** `superadmin/menu.php:301,350,467,508` `superadmin/users.php:200`
`.btn-ghost` is NOT defined in any CSS file (`superadmin.css`, `design-system.css`, `admin.css`, `style.css`). These buttons render with no styling.

### 3. UNDEFINED CSS CLASSES: `.badge-success`, `.badge-warn`
**File:** `superadmin/checkout.php:292`
Payment status badges have no visual distinction — neither class exists in any stylesheet.

### 4. UNDEFINED CSS UTILITY CLASSES: `.text-success`, `.text-danger`, `.text-muted`, `.text-warn`
**Files:** `superadmin/reports.php:48,49,83` `superadmin/accounting.php:254,337` `superadmin/users.php:252` `superadmin/stock.php:435`
Green/red/muted/warning text colors will not render.

### 5. UNDEFINED CSS CLASSES: `.card`, `.card-header`
**File:** `superadmin/menu.php:464-465`
Used in modal dialog context. The CSS files don't define these for superadmin. Modal lacks proper styling.

### 6. MISLEADING "Delete All" BUTTON for Storefronts
**File:** `superadmin/index.php:304`
Button calls `/api/superadmin/delete_all.php` with `type: 'storefronts'`, but the API only does `UPDATE Restaurant SET isActive = 1 WHERE isActive = 0` — it REACTIVATES, not deletes. Label is dangerously misleading.

### 7. MISSING AUTH INCLUDE GUARD
**Files:** `superadmin/checkout.php:3` `superadmin/index.php:4`
Both use `include` (not `require_once`) for `superadmin_header.php`. If ever included twice, `session_init()` fatals.

---

## MEDIUM (5)

### 8. DEAD FILE: `superadmin/restaurants.php`
Only redirects to index.php. Serves no purpose, wastes a route.

### 9. HARDCODED CASHIER PATHS
**File:** `includes/superadmin_header.php:45-46` `includes/admin_header.php:32-33`
Hardcoded `aseng`/`tittil` cashier links won't scale to new restaurants.

### 10. HARDCODED CASHIER DATABASE PATHS
**File:** `superadmin/accounting.php:64-67`
Hardcoded `aseng/cashier/database.db` and `tittil/cashier/database.db` — new restaurants' cashier data missed in reports.

### 11. GARBLED FORMATTING
**File:** `superadmin/settings.php:28`
Two array elements on one line: `'accent_color' => '#6366f1', 'logo_url' => '',`

### 12. `$current_user` WITHOUT NULL GUARD IN JS
**File:** `superadmin/chat.php:180`
`const ME_ID = '<?= htmlspecialchars($current_user['id']) ?>'` — safe because header auth guard runs first, but fragile dependency.

---

## LOW (4)

### 13. ADMIN CAN ACCESS SUPERADMIN PAGES
**File:** `includes/superadmin_header.php:5`
`in_array($current_user['role'], ['SUPERADMIN', 'ADMIN'])` — ADMIN role can access all superadmin pages.

### 14. `APP_URL` DEFAULTS TO EMPTY STRING
**File:** `config.php:6`
`define('APP_URL', getenv('APP_URL') ?: '')` — all absolute URLs become relative.

### 15. HARDCODED LOW-STOCK THRESHOLD IN JS
**File:** `superadmin/stock.php:500`
`qty <= 5 ? 'low' : 'ok'` — hardcoded in JS, but database has per-item `lowStockThreshold`. Card badges inaccurate after inline edits.

### 16. DEAD FILE: `superadmin/restaurants.php`
**File:** `superadmin/restaurants.php:1-3`
Does nothing but redirect. Waste of a route.

---

## CLEAN FILES (no issues)
`includes/superadmin_footer.php`, `includes/admin_footer.php`, `includes/footer.php`, `includes/storefronts.php`, `includes/activity.php`, `superadmin/activity.php`
