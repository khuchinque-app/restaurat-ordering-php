# 🍽️ Khuchinque Web Makan — Multi-Restaurant Ordering Platform

A complete multi-restaurant ordering system built with **PHP 8**, **vanilla JavaScript**, and **SQLite**. Features customer storefronts (`aseng`, `tittil`), an admin panel, superadmin oversight, real-time chat, stock management, accounting, and integrated **cashier-outstore** tools — all gated through a single login portal on **port 7500**.

---

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Access Control — Login Gate](#access-control--login-gate)
- [Directory Structure](#directory-structure)
- [Database Schema](#database-schema)
- [Features](#features)
- [API Endpoints](#api-endpoints)
- [Key Workflows](#key-workflows)
- [Default Accounts](#default-accounts)
- [How to Run](#how-to-run)
- [Security Notes](#security-notes)

---

## Prerequisites

- **PHP 7.4+** with `pdo_sqlite` extension enabled
- A web server (Apache / Nginx) **or** PHP built-in server
- SQLite (no separate database server required)

---

## Quick Start

```bash
# 1. Navigate to the project root
cd /home/khuchinque/barang-pindahan/restaurat-ordering-php

# 2. Initialize the database (creates all tables)
php schema.php

# 3. Seed default data (restaurants, admin user, sample menu items)
php setup.php

# 4. Start the development server on port 7500
php -S 0.0.0.0:7500 router.php
```

Then open `http://localhost:7500` in your browser and log in.

> The `router.php` is required so the built-in server serves static assets correctly and routes directory requests to `index.php` / `index.html`.

---

## Configuration

All settings live in **`config.php`**:

| Constant | Value | Description |
|----------|-------|-------------|
| `DB_PATH` | `__DIR__ . '/data/restaurant.db'` | Path to SQLite database file |
| `JWT_SECRET` | env `JWT_SECRET` → `.jwt_secret` → random | Secret key for JWT token signing |
| `APP_URL` | env `APP_URL` → `''` | Base URL of the application |
| `TAX_RATE` | `0.10` (10%) | Tax rate applied to orders |
| `DEFAULT_RESTAURANT_SLUG` | `'default'` | Default restaurant slug (matches seeded data) |

> ⚠️ **Change `JWT_SECRET` before deploying to production!**
> Set it via the `JWT_SECRET` environment variable or by writing a random string to `.jwt_secret` in the project root. If neither is provided, a random secret is generated on each request (all sessions invalidated on restart).

### Config Fixes Applied

- **DB_PATH** — Originally pointed to `/../backend/prisma/dev.db` (non-existent). Fixed to `data/restaurant.db` (local to project).
- **DEFAULT_RESTAURANT_SLUG** — Originally `'aseng'` but `setup.php` seeds the restaurant with slug `'default'`. Fixed to `'default'` so menu/category APIs resolve correctly.
- **JWT_SECRET** — Now sourced from env var or `.jwt_secret` file, with a one-shot random fallback (previously hard-coded `dev-secret-key-change-in-prod`).

---

## Access Control — Login Gate

**All administrative pages are gated through the login portal on port 7500.** The customer storefronts (`/aseng/`, `/tittil/`) are public, and the cashier tools run on their own ports with their own auth.

| Area | Access | Auth Required |
|------|--------|---------------|
| `http://localhost:7500/` | Login page | Public (entry point) |
| `http://localhost:7500/aseng/` | Customer storefront | Public |
| `http://localhost:7500/tittil/` | Customer storefront | Public |
| `http://localhost:7500/admin/*` | Admin panel | **Login required** (ADMIN/SUPERADMIN) |
| `http://localhost:7500/superadmin/*` | Superadmin panel | **Login required** (SUPERADMIN) |
| `http://localhost:9000/aseng/cashier/` | Aseng Cashier tool | Standalone (own auth, separate from 7500 gate) |
| `http://localhost:8080/tittil/cashier/` | Tittil Cashier tool | Standalone (own auth, separate from 7500 gate) |
| `http://localhost:7500/api/*` | REST API | Mixed (per-endpoint) |

**Flow:**
1. User visits `http://localhost:7500/` → login form
2. After successful login → redirected to admin or superadmin dashboard based on role
3. Sidebar navigation provides links to all authorized areas including **Cashier-Outstore** (opens in new tab)
4. Unauthorized access attempts redirect back to the login page

---

## Directory Structure

```
restaurat-ordering-php/
├── index.php                 # Landing page / login portal (GATE)
├── auth.php                  # Authentication & authorization (JWT + session)
├── config.php                # Application configuration
├── db.php                    # Database connection & helpers
├── router.php                # Built-in PHP server router (static + dir index)
├── schema.php                # Database table creation
├── setup.php                 # Initial data seeding (seed_restaurants.php)
├── seed_restaurants.php      # Restaurant seed helper
├── logout.php                # Logout handler
├── register.php              # User registration
├── README.md                 # This file
├── restaurant-system-spec.md # Specification docs
├── .gitignore
│
├── data/                     # SQLite database storage
│   └── restaurant.db         # Main database file (auto-created by schema.php)
│
├── includes/                 # Shared PHP components
│   ├── header.php            # Public site header
│   ├── footer.php            # Public site footer
│   ├── admin_header.php      # Admin panel header (auth + sidebar + JS polls)
│   ├── admin_footer.php
│   ├── superadmin_header.php # Superadmin panel header
│   ├── superadmin_footer.php
│   ├── activity.php          # Activity feed helper
│   └── storefronts.php       # Storefront registry
│
├── admin/                    # Admin panel (restaurant-scoped, ADMIN/SUPERADMIN)
│   ├── index.php             # Dashboard
│   ├── checkout.php          # Live orders
│   ├── menu.php              # Menu editor
│   ├── stock.php             # Stock (read-only)
│   ├── accounting.php        # Revenue reports
│   ├── orders.php            # Order history + alerts
│   ├── settings.php          # Restaurant settings
│   ├── chat.php              # Staff & customer chat
│   └── hub.php               # Customer hub
│
├── superadmin/               # Superadmin panel (cross-restaurant, SUPERADMIN)
│   ├── index.php             # Dashboard
│   ├── users.php             # User management
│   ├── restaurants.php       # Restaurant management
│   ├── menu.php              # Cross-restaurant menu editor
│   ├── stock.php             # Cross-restaurant stock
│   ├── checkout.php          # Live orders (all restaurants)
│   ├── orders.php            # Order history (all restaurants)
│   ├── accounting.php        # Revenue reports
│   ├── activity.php          # Activity feed
│   ├── chat.php              # Cross-restaurant chat
│   ├── reports.php           # Reporting
│   └── settings.php          # Global settings
│
├── aseng/                    # Aseng customer storefront (public)
│   ├── index.php             # PHP entry
│   ├── index.html            # Static fallback
│   ├── assets/               # Storefront assets
│   ├── menu-logo/            # Logo assets
│   └── cashier/              # Aseng cashier-outstore tool (port 9000)
│       ├── index.php
│       ├── index.html
│       ├── combined_index.php
│       ├── db.php
│       ├── stock.php
│       ├── history.php
│       ├── test.php
│       ├── gudang/           # Warehouse tool
│       ├── static/           # Static assets
│       ├── asset/            # Local assets
│       ├── database.db       # Standalone SQLite (cashier)
│       └── docker-compose.yml
│
├── tittil/                   # Tittil customer storefront (public)
│   ├── index.php
│   ├── index.html
│   ├── assets/
│   ├── tittil/               # Tittil sub-assets
│   ├── SCAN_REPORT.md        # Local audit report
│   ├── db_integrity_report.md
│   └── cashier/              # Tittil cashier-outstore tool (port 8080)
│       ├── index.php
│       ├── index.html
│       ├── combined_index.php
│       ├── db.php
│       ├── backdb.php
│       ├── stock.php
│       ├── history.php
│       ├── test.php
│       ├── gudang/
│       ├── static/
│       ├── asset/
│       ├── database.db
│       └── docker-compose.yml
│
├── api/                      # REST API endpoints (called by all panels)
│   ├── unread-counts.php     # Notification unread badge
│   ├── auth/                 # /api/auth/{login,me,register}.php
│   ├── customer/             # Customer-facing endpoints
│   ├── staff/                # Staff/admin endpoints
│   ├── admin/                # Admin dashboard endpoints
│   ├── superadmin/           # Superadmin endpoints
│   ├── menu/                 # Menu + category CRUD + uploads
│   ├── orders/               # Order lifecycle
│   └── notifications/        # Notification feed
│
├── asset-img/                # Shared image assets
├── assets/                   # Shared CSS/JS assets
├── menu-uploads/             # Uploaded menu item images
└── security/                 # Internal audit reports (do not commit secrets)
```

---

## Database Schema

Tables created by `schema.php` (see file for full column definitions):

- `users` — login accounts with `role` ∈ {CUSTOMER, STAFF, ADMIN, SUPERADMIN}
- `restaurants` — restaurant records (slug, name, settings JSON, owner_id)
- `categories` — menu categories scoped to a restaurant
- `menu_items` — menu items scoped to a category / restaurant
- `orders` — customer orders (status, totals, payment method, customer info)
- `order_items` — line items on an order
- `stock` — current stock per menu item
- `stock_movements` — stock audit trail
- `chat_rooms` / `chat_messages` — staff ↔ customer chat
- `notifications` — in-app notification feed
- `activity_log` — admin / superadmin action audit trail
- `shipping_addresses` — customer delivery addresses
- `sessions` — server-side session/JWT bookkeeping

Seeded restaurants live in `seed_restaurants.php` and include the default slug plus the `aseng` and `tittil` storefronts.

---

## Features

- **Multi-tenant storefronts** — `aseng` and `tittil` are independent customer-facing sites sharing one DB.
- **Role-based access** — CUSTOMER / STAFF / ADMIN / SUPERADMIN with route guards.
- **Menu management** — categories + items, image upload, price/stock editing, soft-archive.
- **Cart + checkout** — single-screen cart with cash + ABA payment options, tax calculation.
- **Live order pipeline** — `pending → confirmed → preparing → ready → delivered` with status notifications.
- **Stock control** — per-item stock with movement audit trail; cashier can deduct.
- **Real-time chat** — staff ↔ customer rooms with unread badge.
- **Accounting** — revenue reports by restaurant/period.
- **Superadmin oversight** — cross-restaurant view, user CRUD, restaurant CRUD, delete-all, activity log.
- **Cashier-outstore** — standalone tools per restaurant (own DB, own port) for warehouse + POS flows.

---

## API Endpoints

All endpoints live under `http://localhost:7500/api/`. Each PHP file is a self-contained handler.

| Group | Endpoints | Notes |
|-------|-----------|-------|
| `auth/` | `login.php`, `me.php`, `register.php` | Issues JWT, returns current user |
| `customer/` | `chat.php`, `presence.php` | Customer chat + presence |
| `staff/` | `chat.php`, `presence.php` | Staff chat + presence |
| `admin/` | `dashboard.php`, `stock.php` | Admin scoped to caller's restaurant |
| `superadmin/` | `users.php`, `create_user.php`, `delete_all.php`, `restaurants.php` | Cross-restaurant |
| `menu/` | `categories.php`, `items.php`, `restaurant_info.php`, `upload.php` | Menu CRUD + media |
| `orders/` | `index.php`, `shipping.php`, `track.php` | Order lifecycle + tracking |
| `notifications/` | `index.php` | Notification feed |
| (root) | `unread-counts.php` | Unread badge aggregation |

Auth: most write endpoints require a valid JWT in `Authorization: Bearer <token>` or a session cookie. Public reads (menu, restaurant info) do not.

---

## Key Workflows

**Customer ordering:**
1. Visit `/aseng/` or `/tittil/`
2. Browse menu, add to cart
3. Checkout → fill name + phone + delivery address → choose payment (Cash / ABA)
4. Order saved as `pending`, staff get a notification

**Admin handling an order:**
1. Login → Admin panel → **Live Orders** (`/admin/checkout.php`)
2. Update status: `pending → confirmed → preparing → ready → delivered`
3. Customer can track order at `/api/orders/track.php?id=…`

**Superadmin onboarding a new restaurant:**
1. Login → Superadmin → **Restaurants** → create restaurant + slug
2. Create an ADMIN user for that restaurant via **Users**
3. Admin logs in and edits menu + settings for their restaurant only

**Cashier / outstore flow:**
- `aseng` cashier runs on port 9000 (`docker-compose.yml` or `php -S 0.0.0.0:9000`)
- `tittil` cashier runs on port 8080
- Each has its own SQLite `database.db` and standalone auth, independent from the 7500 gate

---

## Default Accounts

Created by `setup.php` + `seed_restaurants.php`. **Change all passwords before deploying.**

| Role | Username | Password | Scope |
|------|----------|----------|-------|
| SUPERADMIN | (see `seed_restaurants.php`) | (see file) | All restaurants |
| ADMIN | (see `seed_restaurants.php`) | (see file) | One restaurant |
| CUSTOMER | (register via `/register.php`) | — | Self only |

---

## How to Run

```bash
# Main platform (login gate, admin, superadmin, storefronts, API)
php -S 0.0.0.0:7500 router.php

# Aseng cashier-outstore (standalone, port 9000)
cd aseng/cashier && php -S 0.0.0.0:9000

# Tittil cashier-outstore (standalone, port 8080)
cd tittil/cashier && php -S 0.0.0.0:8080
```

Then:
- `http://localhost:7500/` — login
- `http://localhost:7500/aseng/` — Aseng storefront
- `http://localhost:7500/tittil/` — Tittil storefront
- `http://localhost:9000/` — Aseng cashier
- `http://localhost:8080/` — Tittil cashier

> Cashier sub-apps can also be brought up with their own `docker-compose.yml` if you prefer containers.

---

## Security Notes

- `JWT_SECRET` must be set in production (env var or `.jwt_secret`).
- `data/*.db` and `menu-uploads/` are user-writable — protect with filesystem ACLs.
- The `database.db` files at the project root and in cashier sub-apps are zero-byte or stale placeholders; the real DB lives at `data/restaurant.db`.
- The `security/` directory contains internal audit reports — review before publishing.
- No CSRF tokens are issued for API endpoints; rely on the `Authorization` header for non-cookie auth.
- `api/superadmin/delete_all.php` is destructive — guard it with role checks (already enforced in the handler) and confirm it is not exposed to ADMIN role.
- The root `register.php` is a 43-byte stub — use `/api/auth/register.php` for actual registration.
