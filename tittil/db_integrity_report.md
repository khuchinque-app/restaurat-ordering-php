# Database Integrity Report
**Date:** 2026-07-04  
**Databases checked:**
- `/data/restaurant.db` (main system DB)
- `/tittil/cashier/database.db`
- `/aseng/cashier/database.db`

---

## 1. All tables from schema.php exist in restaurant.db ✅

**schema.php defines 18 tables — all present:**

| Table | Records | Notes |
|-------|---------|-------|
| Restaurant | 3 | Default, Aseng, Tittil |
| User | 8 | — |
| Category | 16 | — |
| MenuItem | 74 | — |
| Order | 1 | Only 1 order exists |
| OrderItem | 2 | Linked to the single order |
| Payment | **0** | ⚠️ Empty |
| Notification | 2571 | High volume |
| StockLog | 0 | — |
| StockAlert | 0 | — |
| StaffChat | 4 | — |
| ChatRoom | 1 | — |
| RestaurantSetting | 2 | — |
| CustomerChat | 53 | — |
| OrderCounter | **0** | ⚠️ Empty |
| CustomerPresence | 17 | — |
| UserPresence | 6 | — |
| AdminActivity | 648 | — |

**Schema drift detected:** `CustomerChat` in the live DB has an extra `mediaUrl TEXT` column not present in schema.php.

---

## 2. Foreign key violations — NONE ✅

- `PRAGMA foreign_key_check` → **empty** (no violations)
- Manual orphan checks on all FK relationships → **0 orphan records**

| Check | Orphans |
|-------|---------|
| OrderItems with orderId not in Order | 0 |
| MenuItems with categoryId not in Category | 0 |
| Categories with restaurantId not in Restaurant | 0 |
| Users with restaurantId not in Restaurant | 0 |

**Note:** `PRAGMA foreign_keys = 0` (FK enforcement OFF at connection level), but the stored data is structurally clean.

---

## 3. Payment records linked to orders — ISSUE ❌

- **`Payment` table is completely empty** (0 records)
- Only 1 order exists: `ORD-E13B6E` (CANCELLED, $40.67, linked to "Default Restaurant")
- The CANCELLED order never had a payment recorded in restaurant.db
- **Actual payment tracking happens only in the cashier DBs** (inline `payment_type` field on order records)

---

## 4. Data consistency: restaurant.db vs cashier DBs — ISSUES ❌

### 4a. No overlap between systems
| Database | Order ID format | # Orders |
|----------|----------------|----------|
| restaurant.db | `ORD-E13B6E` (UUID-based) | 1 |
| tittil/cashier | `001`–`024` (sequential) | 24 finished |
| aseng/cashier | `001`–`015` (sequential) | 15 finished |

The cashier DBs use simple sequential order numbers completely unrelated to restaurant.db's format. The single order in restaurant.db belongs to "Default Restaurant" (inactive), while Tittil and Aseng are active restaurants but have zero orders in restaurant.db.

### 4b. Cashier DB - All finished orders also appear in deleted_orders ⚠️
- **Tittil:** All 24 finished order numbers appear in deleted_orders (24 out of 106 total deleted entries)
- **Aseng:** All 15 finished order numbers appear in deleted_orders (15 out of 67 total deleted entries)
- This suggests every completed order was also moved/copied to deleted_orders

### 4c. Duplicate deleted entries ⚠️
- **Tittil:** Order 001–003 each appear **9 times** in deleted_orders; order 004 appears 8 times
- **Aseng:** Order 001 appears 7 times; order 011 appears 6 times
- These are true duplicate rows, not separate edits — likely from repeated delete operations

### 4d. Cashier DB location breakdown
| DB | Location | Count |
|----|----------|-------|
| **Tittil** | Pending | 76 deleted |
| | Finished | 30 deleted |
| **Aseng** | Pending | 35 deleted |
| | Finished | 32 deleted |

### 4e. Restaurants
| Name | Slug | Active |
|------|------|--------|
| Default Restaurant | default | **No** (inactive) |
| Aseng | aseng | Yes |
| Tittil | tittil | Yes |

---

## Summary of Issues

| # | Issue | Severity | Details |
|---|-------|----------|---------|
| 1 | **Payment table empty** | Medium | restaurant.db never records payments; payment info lives only in cashier DBs |
| 2 | **OrderCounter empty** | Low | Counters for auto-incrementing order numbers are not initialized |
| 3 | **restaurant.db vs cashier DBs disconnected** | High | The two systems operate independently — orders created in cashier DBs never sync to restaurant.db |
| 4 | **Duplicate deleted_orders** | Medium | Tittil: up to 9 copies of same order. Aseng: up to 7 copies |
| 5 | **All finished orders also deleted** | Low | Every finished order has a corresponding deleted entry; design may intend this as archival |
| 6 | **Schema drift (mediaUrl column)** | Low | CustomerChat has an extra column not in schema.php |

**No data loss or corruption found** — the systems are simply designed to operate independently rather than syncing.
