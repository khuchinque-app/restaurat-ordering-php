# Admin Panel Audit Report
**Date:** 2026-07-05 | **Files audited:** 9 | **Findings:** 17

---

## CRITICAL (6)

### 1. ENTIRE ADMIN UI IS UNSTYLED
**File:** `includes/admin_header.php:12-13`
`admin_header.php` loads `style.css` + `admin.css`, but every admin page uses classes defined ONLY in `superadmin.css`: `.btn`, `.btn-primary`, `.btn-sm`, `.btn-outline`, `.btn-danger`, `.table`, `.status-badge`, `.alert`, `.alert-success`, `.alert-danger`, `.form-control`. These have ZERO rules in loaded stylesheets. Additionally `.card`, `.card-header`, `.pagination`, `.btn-ghost`, `.badge`, `.badge-success`, `.empty-state`, `.text-danger`, `.text-warn`, `.text-muted`, `.ml-2` are undefined in ANY CSS file. **Every admin page renders as raw, unstyled HTML.**
**Fix:** Load `design-system.css` + `superadmin.css` in `admin_header.php`, or extract shared component classes.

### 2. NOTIFICATION BADGES NEVER DISPLAY
**File:** `includes/admin_header.php:52,65`
JS badge poll queries `document.getElementById('chatUnreadBadge')` but sidebar element is `id="badgeChat"`. Same mismatch for `notifUnreadBadge` vs `badgeAlerts`. Unread counts silently fail on null lookups every poll cycle (10-15s).
**Affects:** All 9 admin pages via `admin_header.php`.

### 3. USER MANAGEMENT FILE MISSING
**File:** `admin/users.php` — does not exist.
Admin users have no way to manage staff at their restaurant level. User management only in `superadmin/users.php`.

### 4. PHP 7.4+ ARROW FUNCTION — NO FALLBACK
**File:** `admin/checkout.php:168`
```php
fn($o) => in_array($o['status'], ['PENDING','CONFIRMED','PREPARING'])
```
Fatal error on PHP < 7.4. Also in `admin/orders.php:149`.

### 5. PHP 7.4+ ARROW FUNCTION — DUPLICATE
**File:** `admin/orders.php:149`
Same arrow function pattern. Same fatal risk on older PHP.

### 6. `APP_URL` BREAKING API CALLS IN CHAT
**File:** `admin/chat.php:195,213,313,396`
PHP emits `<?= APP_URL ?>` in JS template strings for API URLs. If `APP_URL` env var missing, produces broken paths. `APP_URL` defaults to `''` in `config.php:6`.

---

## HIGH (3)

### 7. WRONG RESTAURANT FALLBACK
**File:** `admin/accounting.php:10`
When `$current_user['restaurantId']` is empty, calls `get_restaurant()` using `DEFAULT_RESTAURANT_SLUG` ('default'). Admin without assigned restaurant sees wrong data.

### 8. `new_id()` WITHOUT GUARD
**File:** `admin/settings.php:44`
`new_id()` defined in `db.php`, currently included. But if include chain ever changes, fatal error. No guard.

### 9. `initPresence()` RACE CONDITION
**File:** `admin/chat.php:422`
`initPresence()` called inline at line 422 but defined in `admin.js` loaded via `admin_footer.php` at line 508. Inline script runs BEFORE footer loads. Always throws `ReferenceError`.

---

## MEDIUM (5)

### 10. OVER-ESCAPED SQL STRING
**File:** `admin/chat.php:21-22`
`'WHERE c.senderRole NOT IN (\\'ADMIN\\',\\'SUPERADMIN\\')'` — double-escaped quotes in double-quoted PHP string. Works but confusing/fragile.

### 11. STOREFRONT LINK HARDCODED `.html`
**File:** `admin/settings.php:198`
`href="/<?= htmlspecialchars($folder) ?>/index.html"` — hardcoded `.html`. Dead link if storefronts switch to `.php`.

### 12. CONFLICTING PRESENCE MECHANISMS
**File:** `admin/chat.php:88`
Two different presence systems: inline `presenceBar` (line 88, polls via `pollCustomerPresence()`) vs `admin.js` `presenceContainer` (initPresence). Possible visual duplication or conflict.

### 13. EMPTY QUERY PARAM
**File:** `admin/stock.php:49`
`href="stock.php?filter="` — empty filter value. Works but sloppy URL.

### 14. STATUS TRANSITION NOT VALIDATED CLIENT-SIDE
**Files:** `admin/checkout.php:158` `admin/orders.php:133`
Both PUT to `/api/orders/index.php?id=...` with `{status}`. No client-side check that new status is valid for current order transition.

---

## LOW (4)

### 15. BLIND 30s AUTO-REFRESH
**File:** `admin/index.php:105`
`setTimeout(() => location.reload(), 30000)` — if user is mid-form on another tab, loses unsaved work.

### 16. `$_GET['status']` UNSANITIZED THEN FILTERED
**File:** `admin/checkout.php:10`
Reads unsanitized then `in_array` check before DB use. No XSS vector (only compared, not echoed) but inconsistent pattern.

### 17. FLASH MESSAGES WITHOUT ESCAPING
**File:** `admin/hub.php:101,103`
`<?= $message ?>` `<?= $error ?>` — currently set from hardcoded strings, safe. But fragile — add dynamic content and becomes XSS-vulnerable.

### 18. NO CSRF ON POST FORMS
**Files:** `admin/hub.php` `admin/settings.php`
Password change and settings save — neither implements CSRF tokens.

---

## AUTH CHECK SUMMARY

| File | Auth Check | Status |
|------|-----------|--------|
| index.php | Via `admin_header.php` → `require_admin(true)` | OK |
| checkout.php | Via `admin_header.php` → `require_admin(true)` | OK |
| menu.php | Via `admin_header.php` → `require_admin(true)` | OK |
| stock.php | Via `admin_header.php` → `require_admin(true)` | OK |
| orders.php | Via `admin_header.php` → `require_admin(true)` | OK |
| accounting.php | Via `admin_header.php` → `require_admin(true)` | OK |
| chat.php | Via `admin_header.php` → `require_admin(true)` | OK |
| hub.php | Via `admin_header.php` → `require_admin(true)` | OK |
| settings.php | Via `admin_header.php` → `require_admin(true)` | OK |
| users.php | N/A | MISSING FILE |
