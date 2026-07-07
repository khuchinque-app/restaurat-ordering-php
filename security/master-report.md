# Master Audit Report — Restaurant Ordering System
**Date:** 2026-07-05 | **Total files audited:** 28 | **Total findings:** 33

---

## Executive Summary

Two subagents audited the superadmin panel (11 files + 8 includes) and admin panel (9 files) for dead buttons, broken links, missing functions, style misalignments, and auth gaps.

**Most impactful:** The entire admin UI is broken — `admin_header.php` loads the wrong CSS files, so every admin page renders as raw, unstyled HTML. Notification badges across both panels silently fail due to DOM ID mismatches.

---

## By Severity

| Severity | Superadmin | Admin | Total |
|----------|-----------|-------|-------|
| Critical | 1 | 6 | **7** |
| High | 6 | 3 | **9** |
| Medium | 5 | 5 | **10** |
| Low | 4 | 3 | **7** |
| **Total** | **16** | **17** | **33** |

---

## Critical — Fix First

| # | Panel | Issue |
|---|-------|-------|
| C1 | Admin | Whole admin UI unstyled — header loads wrong CSS files |
| C2 | Admin | Notification badges never display — JS ID mismatch |
| C3 | Admin | `admin/users.php` missing — no staff management |
| C4 | Admin | PHP 7.4 arrow functions fatal on older PHP |
| C5 | Admin | `APP_URL` empty breaks API calls in chat JS |
| C6 | Admin | `initPresence()` race condition — called before `admin.js` loads |
| C7 | Superadmin | Leading space in CSS `href` breaks stylesheet in `includes/header.php` |

---

## High

| # | Panel | Issue |
|---|-------|-------|
| H1 | Superadmin | `.btn-ghost` class undefined (used 5× in menu + users) |
| H2 | Superadmin | `.badge-success` / `.badge-warn` undefined — payment badges invisible |
| H3 | Superadmin | `.text-success/danger/muted/warn` undefined (used 7×) |
| H4 | Superadmin | `.card` / `.card-header` undefined in superadmin modal |
| H5 | Superadmin | "Delete All Storefronts" reactivates instead of deleting |
| H6 | Superadmin | `include` not `require_once` for header — potential fatal |
| H7 | Admin | Wrong restaurant fallback when admin has no `restaurantId` |
| H8 | Admin | `new_id()` called without guard |
| H9 | Admin | `initPresence()` race condition in chat |

---

## Medium

| # | Panel | Issue |
|---|-------|-------|
| M1 | Superadmin | Dead file `restaurants.php` — redirect only |
| M2 | Both | Hardcoded aseng/tittil cashier paths — won't scale |
| M3 | Superadmin | Hardcoded cashier DB paths in accounting |
| M4 | Superadmin | Garbled formatting in settings defaults |
| M5 | Superadmin | `$current_user['id']` in JS without null guard |
| M6 | Admin | Double-escaped SQL string in chat |
| M7 | Admin | Storefront link hardcoded `.html` extension |
| M8 | Admin | Two conflicting presence mechanisms |
| M9 | Admin | Empty query param `?filter=` |
| M10 | Admin | Status transition not validated client-side |

---

## Low

| # | Panel | Issue |
|---|-------|-------|
| L1 | Superadmin | ADMIN can access all superadmin pages |
| L2 | Superadmin | `APP_URL` defaults to empty string |
| L3 | Superadmin | Low-stock threshold hardcoded to 5 in JS |
| L4 | Admin | Blind 30s auto-refresh on dashboard |
| L5 | Admin | `$_GET['status']` unsanitized in initial read |
| L6 | Admin | Flash messages without `htmlspecialchars` |
| L7 | Admin | No CSRF tokens on any POST form |

---

## Detailed Reports

- [Superadmin + Includes Audit](superadmin-audit.md)
- [Admin Panel Audit](admin-audit.md)
