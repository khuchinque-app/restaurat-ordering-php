<?php
/**
 * Schema bootstrap — recreates the tables the original Prisma backend owned.
 * Run once before setup.php:  php schema.php
 */
require_once __DIR__ . '/db.php';

$db = get_db();

$db->exec('
CREATE TABLE IF NOT EXISTS Restaurant (
    id          TEXT PRIMARY KEY,
    name        TEXT NOT NULL,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT,
    isActive    INTEGER NOT NULL DEFAULT 1,
    createdAt   TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt   TEXT NOT NULL DEFAULT (datetime(\'now\'))
);

CREATE TABLE IF NOT EXISTS User (
    id           TEXT PRIMARY KEY,
    email        TEXT NOT NULL UNIQUE,
    password     TEXT NOT NULL,
    name         TEXT,
    phone        TEXT,
    role         TEXT NOT NULL DEFAULT \'CUSTOMER\',
    restaurantId TEXT,
    isActive     INTEGER NOT NULL DEFAULT 1,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS Category (
    id           TEXT PRIMARY KEY,
    name         TEXT NOT NULL,
    slug         TEXT NOT NULL,
    description  TEXT,
    sortOrder    INTEGER NOT NULL DEFAULT 0,
    isActive     INTEGER NOT NULL DEFAULT 1,
    restaurantId TEXT NOT NULL,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS MenuItem (
    id                TEXT PRIMARY KEY,
    name              TEXT NOT NULL,
    description       TEXT,
    price             TEXT NOT NULL,
    image             TEXT,
    isAvailable       INTEGER NOT NULL DEFAULT 1,
    preparationTime   INTEGER NOT NULL DEFAULT 15,
    notes             TEXT,
    stockQuantity     INTEGER,
    lowStockThreshold INTEGER NOT NULL DEFAULT 5,
    categoryId        TEXT NOT NULL,
    restaurantId      TEXT NOT NULL,
    createdAt         TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt         TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (categoryId)   REFERENCES Category(id)   ON DELETE CASCADE,
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "Order" (
    id           TEXT PRIMARY KEY,
    orderNumber  TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT \'PENDING\',
    totalAmount  TEXT NOT NULL,
    itemCount    INTEGER NOT NULL DEFAULT 0,
    notes        TEXT,
    customerName TEXT,
    customerPhone TEXT,
    customerId   TEXT,
    restaurantId TEXT NOT NULL,
    completedAt  TEXT,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE,
    FOREIGN KEY (customerId)   REFERENCES User(id)       ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS OrderItem (
    id         TEXT PRIMARY KEY,
    orderId    TEXT NOT NULL,
    menuItemId TEXT NOT NULL,
    quantity   INTEGER NOT NULL DEFAULT 1,
    unitPrice  TEXT NOT NULL,
    totalPrice TEXT NOT NULL,
    notes      TEXT,
    modifiers  TEXT,
    createdAt  TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (orderId)    REFERENCES "Order"(id)  ON DELETE CASCADE,
    FOREIGN KEY (menuItemId) REFERENCES MenuItem(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Notification (
    id        TEXT PRIMARY KEY,
    userId    TEXT,
    orderId   TEXT,
    type      TEXT NOT NULL,
    title     TEXT NOT NULL,
    message   TEXT NOT NULL,
    isRead    INTEGER NOT NULL DEFAULT 0,
    createdAt TEXT NOT NULL DEFAULT (datetime(\'now\'))
);

CREATE TABLE IF NOT EXISTS StockLog (
    id         TEXT PRIMARY KEY,
    menuItemId TEXT NOT NULL,
    quantity   INTEGER NOT NULL,
    type       TEXT NOT NULL,
    reason     TEXT,
    createdAt  TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (menuItemId) REFERENCES MenuItem(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS StockAlert (
    id           TEXT PRIMARY KEY,
    menuItemId   TEXT NOT NULL,
    threshold    INTEGER NOT NULL,
    currentStock INTEGER NOT NULL,
    isResolved   INTEGER NOT NULL DEFAULT 0,
    restaurantId TEXT,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (menuItemId) REFERENCES MenuItem(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS StaffChat (
    id         TEXT PRIMARY KEY,
    senderId   TEXT NOT NULL,
    senderName TEXT,
    senderRole TEXT,
    message    TEXT NOT NULL,
    isRead     INTEGER NOT NULL DEFAULT 0,
    createdAt  TEXT NOT NULL DEFAULT (datetime(\'now\'))
);

CREATE TABLE IF NOT EXISTS ChatRoom (
    id        TEXT PRIMARY KEY,
    orderId   TEXT,
    type      TEXT NOT NULL DEFAULT \'ORDER\',
    createdAt TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt TEXT NOT NULL DEFAULT (datetime(\'now\'))
);

CREATE TABLE IF NOT EXISTS RestaurantSetting (
    id           TEXT PRIMARY KEY,
    restaurantId TEXT NOT NULL,
    settingKey   TEXT NOT NULL,
    settingValue TEXT,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Payment (
    id           TEXT PRIMARY KEY,
    orderId      TEXT NOT NULL,
    method       TEXT NOT NULL DEFAULT \'CASH\',
    amount       TEXT NOT NULL,
    currency     TEXT NOT NULL DEFAULT \'USD\',
    khrAmount    TEXT,
    reference    TEXT,
    proofUrl     TEXT,
    status       TEXT NOT NULL DEFAULT \'PENDING\',
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (orderId) REFERENCES "Order"(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS CustomerChat (
    id           TEXT PRIMARY KEY,
    chatType     TEXT NOT NULL DEFAULT \'SUPPORT\',
    orderId      TEXT,
    restaurantId TEXT NOT NULL,
    senderId     TEXT,
    senderName   TEXT,
    senderRole   TEXT,
    message      TEXT NOT NULL,
    isRead       INTEGER NOT NULL DEFAULT 0,
    createdAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (orderId) REFERENCES "Order"(id) ON DELETE SET NULL,
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS OrderCounter (
    restaurantId TEXT PRIMARY KEY,
    currentNum   INTEGER NOT NULL DEFAULT 0,
    updatedAt    TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (restaurantId) REFERENCES Restaurant(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS UserPresence (
    userId     TEXT PRIMARY KEY,
    lastSeenAt TEXT NOT NULL DEFAULT (datetime(\'now\')),
    FOREIGN KEY (userId) REFERENCES User(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS AdminActivity (
    id             TEXT PRIMARY KEY,
    userId         TEXT,
    userName       TEXT,
    userEmail      TEXT,
    userRole       TEXT,
    restaurantId   TEXT,
    restaurantName TEXT,
    action         TEXT NOT NULL,
    entityType     TEXT,
    entityId       TEXT,
    details        TEXT,
    ipAddress      TEXT,
    createdAt      TEXT NOT NULL DEFAULT (datetime(\'now\'))
);
');

echo "Schema created at " . DB_PATH . "\n";
