# 🍽️ Khuchinque Web Makan — Multi-Restaurant Ordering Platform

A complete multi-restaurant ordering system built with **PHP 8**, **vanilla JavaScript**, and **SQLite**. Features customer storefronts, admin panels, superadmin oversight, real-time chat, stock management, accounting, and integrated **cashier-outstore** tools — all gated through a single login portal on **port 7500**.

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
- [End-to-End Test Verification](#end-to-end-test-verification)
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
cd /home/hermes/khuchinque-web-makan

# 2. Initialize the database (creates all tables)
php schema.php

# 3. Seed default data (restaurant, admin user, sample menu items)
php setup.php

# 4. Start the development server
php -S 0.0.0.0:7500
```

Then open `http://localhost:7500` in your browser and log in.

---

## Configuration

All settings live in **`config.php`**:

| Constant | Value | Description |
|----------|-------|-------------|
| `DB_PATH` | `__DIR__ . '/data/restaurant.db'` | Path to SQLite database file |
| `JWT_SECRET` | `dev-secret-key-change-in-prod` | Secret key for JWT token signing |
| `APP_URL` | `''` (empty) | Base URL of the application |
| `TAX_RATE` | `0.10` (10%) | Tax rate applied to orders |
| `DEFAULT_RESTAURANT_SLUG` | `'default'` | Default restaurant slug (matches seeded data) |

> ⚠️ **Change `JWT_SECRET` before deploying to production!**

### Config Fixes Applied

- **DB_PATH** — Originally pointed to `/../backend/prisma/dev.db` (non-existent). Fixed to `data/restaurant.db` (local to project).
- **DEFAULT_RESTAURANT_SLUG** — Originally `'aseng'` but `setup.php` creates the restaurant with slug `'default'`. Fixed to `'default'` so menu/category APIs resolve correctly.

---

## Access Control — Login Gate

**All administrative and cashier pages are gated through the login portal on port 7500.** No direct access is permitted without authentication.

| Area | Access | Auth Required |
|------|--------|---------------|
| `http://localhost:7500/` | Login page | Public (entry point) |
| `http://localhost:7500/aseng/` | Customer storefront | Public |
| `http://localhost:7500/tittil/` | Customer storefront | Public |
| `http://localhost:7500/admin/*` | Admin panel | **Login required** (ADMIN/SUPERADMIN) |
| `http://localhost:7500/superadmin/*` | Superadmin panel | **Login required** (SUPERADMIN) |
| `http://localhost:7500/aseng/cashier/` | Aseng Cashier tool | Standalone (own auth, separate from 7500 gate) |
| `http://localhost:7500/tittil/cashier/` | Tittil Cashier tool | Standalone (own auth, separate from 7500 gate) |
| `http://localhost:7500/api/*` | REST API | Mixed (per-endpoint) |

**Flow:**
1. User visits `http://localhost:7500/` → login form
2. After successful login → redirected to admin or superadmin dashboard based on role
3. Sidebar navigation provides links to all authorized areas including **Cashier-Outstore** (opens in new tab)
4. Unauthorized access attempts redirect back to the login page

---

## Directory Structure

```
khuchinque-web-makan/
├── index.php                 # Landing page / login portal (GATE)
├── auth.php                  # Authentication & authorization (JWT + session)
├── config.php                # Application configuration
├── db.php                    # Database connection & helpers
├── schema.php                # Database table creation
├── setup.php                 # Initial data seeding
├── logout.php                # Logout handler
├── register.php              # User registration
├── README.md                 # This file
├── restaurant-system-spec.md # Specification docs
├── data/                     # SQLite database storage
│   └── restaurant.db         # Main database file (auto-created by schema.php)
│
├── includes/                 # Shared PHP components
│   ├── header.php            # Public site header
│   ├── footer.php            # Public site footer
│   ├── admin_header.php      # Admin panel header (auth + sidebar + JS polls)
│   ├── admin_footer.php
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
