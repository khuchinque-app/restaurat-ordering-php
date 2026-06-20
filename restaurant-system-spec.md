# Restaurant Ordering System — Project Spec

**Last updated:** June 17, 2026  
**Status:** Active development (near-term production)  
**Live URL:** `http://187.127.178.20:8080`

---

## 1. Overview

A multi-restaurant ordering system built in pure PHP + vanilla JavaScript. Customers browse menus, place orders, and chat with staff. Restaurant admins manage orders, menus, stock, and staff communication. A superadmin oversees the entire platform across all restaurants.

---

## 2. Architecture & Deployment

### 2.1 Tech Stack
- **Backend:** Pure PHP (no framework, no Composer), SQLite via PDO
- **Frontend:** Server-rendered HTML pages + vanilla JS for interactivity
- **Auth:** Manual HMAC-SHA256 JWT + PHP sessions
- **Database:** SQLite (`../backend/prisma/dev.db`), WAL mode, foreign keys ON
- **Deployment:** VPS / bare metal (Apache or Nginx), PHP built-in server for dev

### 2.2 Multi-Domain Strategy
- Each restaurant has its own domain (e.g. `aseng.com`, `tittil.com`)
- Restaurants are also accessible via subdirectory paths on the main domain (e.g. `yourapp.com/aseng/`)
- **Config:** Each restaurant's DNS points to the server; nginx config maps domains to their folder
- Storefront folders: `aseng/`, `tittil/`

### 2.3 Database Choice
- **Keep SQLite** for current scale
- Future: evaluate MySQL/PostgreSQL if concurrency becomes an issue

---

## 3. User Roles & Permissions

### 3.1 Roles (3 roles, MANAGER removed)

| Role | Description | Landing Page | Capabilities |
|------|-------------|--------------|--------------|
| **SUPERADMIN** | Platform owner / operator | `superadmin/index.php` | Everything: system-wide stats, restaurant CRUD, user CRUD, full stock edits, activity logs, reports, staff chat |
| **ADMIN** | Restaurant admin / operator | `admin/index.php` | Restaurant-scoped: orders, menu CRUD, stock **view-only**, staff chat |
| **CUSTOMER** | End user placing orders | Storefront SPA | Browse menu, place orders, track orders, chat with staff |

### 3.2 Access Rules
- **SUPERADMIN** — can modify anything, create accounts, adjust stock, view all restaurants
- **ADMIN** — scoped to one restaurant: manage orders, menu items, view stock
- **CUSTOMER** — guests only, no registration required, localStorage cart
- Admin/Manager overlap: ADMIN is the only staff role now (MANAGER removed)
- Only SUPERADMIN can adjust stock via API (`POST /api/admin/stock.php` returns 403 for non-SUPERADMIN)

### 3.3 Staff Management
- SUPERADMIN creates all staff accounts via `superadmin/users.php`
- No public registration
- SUPERADMIN can disable/enable users (but cannot disable themselves)
- SUPERADMIN accounts are protected from modification

---

## 4. Customer Experience

### 4.1 Storefront SPA
- Each restaurant has a standalone HTML SPA (`{slug}/index.html`)
- Fetches menu from `/api/menu/items.php?restaurant={slug}`
- Category tabs, search, add-to-cart, cart drawer
- Order placed via `/api/orders/index.php` (POST)

### 4.2 Cart
- **localStorage-based** — no login required
- Cart persisted across page refreshes
- Stock-aware: max quantity limited by `stockQuantity`
- Shows USD + KHR prices (dual currency)

### 4.3 Ordering Flow
1. Customer browses menu → adds items to cart
2. Opens cart drawer → enters name + optional phone + optional notes
3. Places order → POST to `/api/orders/index.php`
4. Stock decremented atomically in transaction
5. Order created with status `PENDING`
6. Customer sees confirmation modal
7. Customer can track order status via `orders.php`

### 4.4 Order Tracking
- Guests: tracked via `$_SESSION['order_ids']` (up to 30 orders)
- Logged-in customers: tracked via `customerId` field
- Auto-refresh every 15 seconds for pending orders

### 4.5 Customer Chat
- **Two chat modes:**
  1. **General support chat** — customer opens a chat with restaurant staff for any question
  2. **Per-order chat** — each order gets its own thread for order-specific questions
- Customer must be logged in (or provide name/phone as guest identifier)
- Chat messages stored in `StaffChat` table (currently) — will need a separate `CustomerChat` table or unified table with `chatType` field
- Staff see unread badge in sidebar (polls every 15 seconds)

---

## 5. Payment System

### 5.1 Supported Payment Methods
| Method | Currency | Notes |
|--------|----------|-------|
| **Cash** | USD or KHR | 90% chance Kiel (KHR), 10% USD. Staff marks payment method manually |
| **ABA** | USD only | Digital payment, always in USD |
| **Bank Transfer** | USD or KHR | Depends on the bank/account |

### 5.2 Dual Currency
- **Conversion rate:** `$1 USD = 4,000 KHR (Cambodian Riel)`
- **Display:** Both currencies shown side-by-side to the customer (e.g. `$10.00 / 40,000 Riel`)
- **ABA always shows USD**
- **Cash:** 90% Riel, 10% USD — but display both
- **Rounding:** Standard rounding to nearest 500 KHR for Riel display

### 5.3 Payment Proof (Optional)
- Customer can optionally upload a screenshot or enter an ABA/bank transaction reference
- Staff manually verify payments — no automated verification
- Proof stored as text reference (transaction ID / screenshot URL)

### 5.4 Order Status for Payments
- When customer marks payment → order moves to `CONFIRMED` (or `PAID` status)
- Staff verifies payment in person → proceeds with preparation
- If payment not verified → staff can reject/cancel

---

## 6. Order Management

### 6.1 Order Status Flow
```
PENDING → CONFIRMED → PREPARING → READY → COMPLETED
                    ↘ CANCELLED
```

| Status | Who Sets | Description |
|--------|----------|-------------|
| `PENDING` | System (auto) | Order just placed, awaiting confirmation |
| `CONFIRMED` | Admin | Staff accepted the order (payment verified) |
| `PREPARING` | Admin | Kitchen started preparing |
| `READY` | Admin | Order ready for pickup/delivery |
| `COMPLETED` | Admin | Order finished (picked up / delivered) |
| `CANCELLED` | Admin | Order cancelled (only from PENDING or CONFIRMED) |

### 6.2 Order Numbers
- **Format:** `{RESTAURANT_SLUG}-{SEQUENTIAL_NUMBER}`
- Examples: `ASNG-1001`, `TITT-1001`
- Counter per restaurant in a `OrderCounter` table or computed from max existing number
- Auto-increment: each new order gets +1

### 6.3 Order Notes
- Customer can add special instructions (e.g. "no onions", "extra spicy")
- Admin sees notes on the order card
- Stored in `Order.notes`

---

## 7. Menu Management

### 7.1 Entities
- **Restaurant** — top-level entity, has slug, name, description, isActive
- **Category** — belongs to restaurant, has sortOrder, isActive
- **MenuItem** — belongs to category + restaurant, has price, stock, image, isAvailable

### 7.2 CRUD Operations
- Admin can create/edit/delete categories and menu items
- Categories cannot be deleted if they have items
- Menu items have: name, description, price, image URL, stock quantity, low stock threshold, preparation time
- Soft-delete via `isActive` / `isAvailable` flags

### 7.3 Stock Tracking
- `stockQuantity` — current stock count (null = unlimited)
- `lowStockThreshold` — triggers alerts when stock falls below
- Stock decremented on order placement (atomic transaction)
- When stock reaches 0, `isAvailable` set to 0 automatically
- Only SUPERADMIN can adjust stock (via `superadmin/stock.php`)

### 7.4 Stock Permissions
- **SUPERADMIN** — full edit (Set to value, +Add, −Remove)
- **ADMIN** — read-only view via `admin/stock.php`
- API returns 403 if non-SUPERADMIN tries to adjust

### 7.5 Seeding
- `php schema.php` — creates tables
- `php setup.php` — seeds the default restaurant + sample menu (run once)
- `php seed_restaurants.php` — syncs menus from `item.txt` files (idempotent)

---

## 8. Staff Chat

### 8.1 Current: Global Staff Chat
- Single channel shared by all staff (SUPERADMIN + ADMIN)
- Stored in `StaffChat` table
- Client polls `GET /api/staff/chat.php?since=<timestamp>` every 5 seconds
- Sidebar badge polls every 15 seconds
- Messages: senderId, senderName, senderRole, message, isRead, createdAt

### 8.2 Planned: Customer-Staff Chat
- **Per-order chat thread** — each order gets a chat room
- **General support chat** — customer can open a chat with restaurant staff
- Needs a new `CustomerChat` table or unified chat table with `chatType` field
- Customer must provide identity (name + phone for guests, or userId for logged-in)
- Staff can see all customer chats and respond
- Unread indicators for both staff and customer

---

## 9. Admin Panel

### 9.1 Dashboard (`admin/index.php`)
- Today's orders count
- Today's revenue
- Pending orders count
- Total revenue
- Recent orders table (last 10)
- Low stock alerts (items at/below threshold)
- Auto-refresh every 30 seconds

### 9.2 Orders (`admin/orders.php`)
- List all orders with status filters
- Pagination (25 per page)
- Status transition buttons (auto-determined from current status)
- Cancel button for PENDING/CONFIRMED orders
- Auto-refresh if active orders exist (every 20 seconds)

### 9.3 Menu Management (`admin/menu.php`)
- Categories: list, add, enable/disable, delete (only if empty)
- Items: list with filter/search, add/edit via modal, enable/disable, delete
- Modal form: name, category, price, stock, threshold, prep time, description, image URL

### 9.4 Stock View (`admin/stock.php`)
- Read-only view of all items with stock info
- Filters: All, Low Stock, Out of Stock
- Shows current stock, threshold, status badges

### 9.5 Staff Chat (`admin/chat.php`)
- Real-time group chat UI
- Shows sender name, role badge, timestamp
- Messages auto-scroll, poll for new messages

---

## 10. Superadmin Panel

### 10.1 Dashboard (`superadmin/index.php`)
- System-wide stats: restaurants, customers, orders, revenue
- Today's orders and revenue
- Restaurants overview table (with per-restaurant stats)
- Recent orders across all restaurants
- Recent activity log (last 10)

### 10.2 Restaurants (`superadmin/restaurants.php`)
- Create new restaurant (name, slug, description)
- Enable/disable restaurants
- Shows customer count, order count, revenue per restaurant

### 10.3 Users (`superadmin/users.php`)
- Create accounts (any role: SUPERADMIN, ADMIN, CUSTOMER)
- Enable/disable users
- Role filter
- Cannot modify other SUPERADMIN accounts

### 10.4 Stock Management (`superadmin/stock.php`)
- Full stock editing with card-based UI
- Restaurant selector dropdown
- Filters: All, Low, Out
- Stepper controls (+/−), direct input, save button
- Toast notifications on save

### 10.5 Reports (`superadmin/reports.php`)
- Revenue by restaurant (with completed/cancelled counts)
- Orders by status breakdown
- Last 7 days trend

### 10.6 Activity Log (`superadmin/activity.php`)
- Full audit trail of admin actions
- Filters: user, action type, restaurant, date range
- Tracks: LOGIN, ORDER_STATUS changes, CREATE/UPDATE/DELETE menu items, STOCK_ADJUST
- Paginated (50 per page)

### 10.7 Staff Chat (`superadmin/chat.php`)
- Same global staff chat as admin panel
- Full-height chat UI

---

## 11. API Endpoints

### 11.1 Menu API
| Endpoint | Methods | Auth | Description |
|----------|---------|------|-------------|
| `/api/menu/items.php` | GET, POST, PUT, DELETE | GET: public, others: ADMIN | CRUD for menu items |
| `/api/menu/categories.php` | GET, POST, PUT, DELETE | GET: public, others: ADMIN | CRUD for categories |
| `/api/menu/restaurant_info.php` | GET | Public | Restaurant info by slug |

### 11.2 Orders API
| Endpoint | Methods | Auth | Description |
|----------|---------|------|-------------|
| `/api/orders/index.php` | GET, POST, PUT | POST: public, others: auth | Order CRUD |
| `/api/orders/track.php` | POST | Session | Track guest orders in session |

### 11.3 Staff Chat API
| Endpoint | Methods | Auth | Description |
|----------|---------|------|-------------|
| `/api/staff/chat.php` | GET, POST | Staff (ADMIN+) | Chat messages |

### 11.4 Admin API
| Endpoint | Methods | Auth | Description |
|----------|---------|------|-------------|
| `/api/admin/stock.php` | GET, POST | ADMIN: GET, SUPERADMIN: POST | Stock management |

### 11.5 Superadmin API
| Endpoint | Methods | Auth | Description |
|----------|---------|------|-------------|
| `/api/superadmin/create_user.php` | POST | SUPERADMIN | Create any-role account |
| `/api/superadmin/users.php` | PUT | SUPERADMIN | Enable/disable users |
| `/api/superadmin/restaurants.php` | GET, POST, PUT | SUPERADMIN | Restaurant CRUD |

---

## 12. Database Schema

### Core Tables
```sql
Restaurant  (id, name, slug, description, isActive, createdAt, updatedAt)
User        (id, email, password, name, phone, role, restaurantId, isActive, createdAt, updatedAt)
Category    (id, name, slug, description, sortOrder, isActive, restaurantId, createdAt, updatedAt)
MenuItem    (id, name, description, price, image, isAvailable, preparationTime, notes, 
             stockQuantity, lowStockThreshold, categoryId, restaurantId, createdAt, updatedAt)
Order       (id, orderNumber, status, totalAmount, itemCount, notes, customerName, 
             customerPhone, customerId, restaurantId, completedAt, createdAt, updatedAt)
OrderItem   (id, orderId, menuItemId, quantity, unitPrice, totalPrice, notes, modifiers, createdAt)
```

### Supporting Tables
```sql
Notification  (id, userId, orderId, type, title, message, isRead, createdAt)
StockLog      (id, menuItemId, quantity, type, reason, createdAt)
StockAlert    (id, menuItemId, threshold, currentStock, isResolved, restaurantId, createdAt)
StaffChat     (id, senderId, senderName, senderRole, message, isRead, createdAt)
ChatRoom      (id, orderId, type, createdAt, updatedAt)
AdminActivity (id, userId, userName, userEmail, userRole, restaurantId, restaurantName, 
               action, entityType, entityId, details, ipAddress, createdAt)
```

### Planned Tables (for customer chat + payments)
```sql
-- Payment tracking
Payment       (id, orderId, method, amount, currency, khrAmount, reference, proofUrl, 
               status, createdAt, updatedAt)

-- Customer chat
CustomerChat  (id, chatType, orderId, customerId, customerName, senderId, senderName, 
               senderRole, message, isRead, createdAt)

-- Order number counter per restaurant
OrderCounter  (restaurantId, currentNumber, updatedAt)
```

---

## 13. Configuration

### 13.1 `config.php`
| Constant | Default | Notes |
|----------|---------|-------|
| `DB_PATH` | `../backend/prisma/dev.db` | SQLite file path |
| `JWT_SECRET` | `dev-secret-key-change-in-prod` | Change before production |
| `APP_URL` | `` (empty) | Set to full URL for production |
| `TAX_RATE` | `0.10` | 10% tax |
| `DEFAULT_RESTAURANT_SLUG` | `aseng` | Default restaurant for API calls |
| `EXCHANGE_RATE_KHR` | `4000` | $1 = 4,000 KHR |

### 13.2 Currency Constants (planned)
| Constant | Value | Notes |
|----------|-------|-------|
| `CURRENCY_USD` | `USD` | US Dollar |
| `CURRENCY_KHR` | `KHR` | Cambodian Riel |
| `KHR_PER_USD` | `4000` | Exchange rate |

---

## 14. Features To Add / Improve

### 14.1 Payment System
- [ ] Add `Payment` table to track payments per order
- [ ] Payment method selection UI (Cash / ABA / Bank Transfer)
- [ ] Dual currency display (USD + KHR) on menu and checkout
- [ ] Optional payment proof upload (screenshot / transaction reference)
- [ ] Admin payment verification workflow
- [ ] ABA always shows USD; Cash shows both (90% KHR)

### 14.2 Order Number Format
- [ ] Change from `ORD-XXXX` (random) to `{SLUG}-{SEQUENTIAL}` (e.g. `ASNG-1001`)
- [ ] Add `OrderCounter` table for per-restaurant sequence tracking
- [ ] Auto-increment on order creation

### 14.3 Customer-Staff Chat
- [ ] Add `CustomerChat` table (or extend `StaffChat` with `chatType` field)
- [ ] Per-order chat thread (linked to `orderId`)
- [ ] General support chat (linked to restaurant)
- [ ] Customer chat UI on storefront
- [ ] Staff chat inbox showing all customer conversations
- [ ] Unread indicators for both sides

### 14.4 Role Simplification
- [ ] Remove MANAGER role from the system
- [ ] Merge ADMIN + MANAGER into just ADMIN
- [ ] Update `require_admin()` to only check for ADMIN and SUPERADMIN
- [ ] Clean up role filter options in superadmin user management

### 14.5 Multi-Domain Support
- [ ] Nginx config for domain-to-folder mapping
- [ ] `APP_URL` per restaurant (not global)
- [ ] CORS handling for API calls across domains
- [ ] SSL certificates per domain

### 14.6 Code Quality
- [ ] Remove MANAGER role references throughout codebase
- [ ] Fix hardcoded `/api/` paths (should use `APP_URL`)
- [ ] Add input validation on all API endpoints
- [ ] Add CSRF protection on form submissions
- [ ] Rate limiting on API endpoints
- [ ] Error logging instead of silent catches

---

## 15. Current File Structure

```
├── index.php                  # Login gate (routes by role)
├── menu.php                   # Customer menu page
├── cart.php                   # Cart & checkout
├── orders.php                 # Order tracking
├── login.php / logout.php     # Auth pages
├── register.php               # Disabled (redirects to index)
├── setup.php                  # DB seeder (run once)
├── schema.php                 # Table creation
├── seed_restaurants.php       # Multi-restaurant seeder
├── config.php                 # Configuration constants
├── db.php                     # PDO singleton + helpers
├── auth.php                   # JWT + session auth
│
├── admin/                     # Admin panel (ADMIN)
│   ├── index.php              # Dashboard
│   ├── orders.php             # Order management
│   ├── menu.php               # Menu CRUD
│   ├── stock.php              # Stock view (read-only)
│   └── chat.php               # Staff chat
│
├── superadmin/                # Superadmin panel (SUPERADMIN)
│   ├── index.php              # System dashboard
│   ├── restaurants.php        # Restaurant management
│   ├── users.php              # User management
│   ├── reports.php            # Reports
│   ├── stock.php              # Stock management (full edit)
│   ├── chat.php               # Staff chat
│   └── activity.php           # Activity log
│
├── api/                       # JSON API
│   ├── menu/items.php         # Menu item CRUD
│   ├── menu/categories.php    # Category CRUD
│   ├── menu/restaurant_info.php
│   ├── orders/index.php       # Order CRUD
│   ├── orders/track.php       # Guest order tracking
│   ├── admin/stock.php        # Stock adjust (SUPERADMIN only)
│   ├── staff/chat.php         # Staff chat
│   └── superadmin/            # Superadmin API
│       ├── create_user.php
│       ├── restaurants.php
│       └── users.php
│
├── includes/                  # Shared includes
│   ├── header.php             # Customer nav
│   ├── footer.php
│   ├── admin_header.php       # Admin sidebar
│   ├── admin_footer.php
│   ├── superadmin_header.php  # Superadmin sidebar
│   ├── superadmin_footer.php
│   ├── storefronts.php        # Storefront nav links
│   └── activity.php           # log_activity() helper
│
├── assets/                    # Shared assets
│   ├── css/style.css          # Customer styles
│   ├── css/admin.css          # Admin styles
│   ├── css/superadmin.css     # Superadmin styles
│   ├── js/app.js              # Customer JS (cart, menu, orders)
│   ├── js/admin.js            # Admin JS
│   └── js/superadmin.js       # Superadmin JS (placeholder)
│
├── aseng/                     # Aseng storefront SPA
├── tittil/                    # Tittil storefront SPA

```

---

## 16. Key Design Decisions

1. **No framework** — pure PHP for simplicity and zero dependencies
2. **SQLite** — chosen for simplicity, evaluate under load later
3. **Guest customers** — no registration required, lowers friction
3. **Dual currency** — USD + KHR displayed side-by-side (4000:1 rate)
4. **Per-restaurant domains** — each restaurant gets its own domain
5. **localStorage cart** — no server-side cart state, simple and fast
6. **3 roles only** — SUPERADMIN, ADMIN, CUSTOMER (MANAGER removed)
7. **Optional payment proof** — trust-based with optional verification
8. **Per-order + general chat** — two chat modes for customer communication

---

## 17. Open Questions

1. **Tax handling** — Is 10% tax applied to all orders? Should it be configurable per restaurant?
2. **Order modifications** — Can customers modify an order after placing it (before CONFIRMED)?
3. **Refunds** — How are refunds handled for cancelled orders?
4. **Menu images** — Currently URL-based. Should there be an image upload feature?
5. **Delivery** — Is delivery handled in-house or via third-party? Need delivery address field?
6. **Promotions/discounts** — Any plans for coupon codes or menu item discounts?
7. **Operating hours** — Should the menu show/hide based on restaurant operating hours?
8. **Notifications** — Push notifications for new orders / status changes, or just in-app?
