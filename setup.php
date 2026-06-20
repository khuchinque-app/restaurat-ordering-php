<?php
/**
 * One-time setup script.
 * Run once in a browser or CLI: php setup.php
 * Creates default restaurant, admin user, and sample menu.
 * DELETE THIS FILE after running in production.
 */
require_once __DIR__ . '/db.php';

$existing = db_fetch('SELECT id FROM Restaurant LIMIT 1');
if ($existing) {
    echo "Setup already complete. Restaurant exists.\n";
    echo "Superadmin : superadmin@restaurant.com / admin123\n";
    echo "Admin      : admin@restaurant.com / admin123\n";
    echo "Customer   : customer@example.com / customer123\n";
    exit;
}

$restaurant_id = new_id();
db_execute(
    'INSERT INTO Restaurant (id, name, slug, description, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$restaurant_id, 'Default Restaurant', 'default', 'Main restaurant for the system']
);

// Superadmin user
$superadmin_id = new_id();
db_execute(
    'INSERT INTO User (id, email, password, name, role, restaurantId, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$superadmin_id, 'superadmin@restaurant.com', password_hash('admin123', PASSWORD_DEFAULT), 'Super Admin', 'SUPERADMIN', $restaurant_id]
);

// Admin user
$admin_id = new_id();
db_execute(
    'INSERT INTO User (id, email, password, name, role, restaurantId, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$admin_id, 'admin@restaurant.com', password_hash('admin123', PASSWORD_DEFAULT), 'Admin User', 'ADMIN', $restaurant_id]
);

// Customer user
$cust_id = new_id();
db_execute(
    'INSERT INTO User (id, email, password, name, role, restaurantId, isActive, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
    [$cust_id, 'customer@example.com', password_hash('customer123', PASSWORD_DEFAULT), 'Default Customer', 'CUSTOMER', $restaurant_id]
);

// Sample categories
$cat_mains  = new_id();
$cat_sides  = new_id();
$cat_drinks = new_id();
$categories = [
    [$cat_mains,  'Main Dishes',  'main-dishes',  'Hearty main courses',     0],
    [$cat_sides,  'Side Dishes',  'side-dishes',  'Perfect complements',     1],
    [$cat_drinks, 'Drinks',       'drinks',        'Refreshing beverages',   2],
];
foreach ($categories as [$id, $name, $slug, $desc, $sort]) {
    db_execute(
        'INSERT INTO Category (id, name, slug, description, sortOrder, isActive, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, 1, ?, datetime("now"), datetime("now"))',
        [$id, $name, $slug, $desc, $sort, $restaurant_id]
    );
}

// Sample menu items
$menu_items = [
    ['Grilled Chicken Burger', 'Juicy grilled chicken with lettuce and tomato',  '12.99', $cat_mains,  50, 5],
    ['Classic Cheeseburger',   'Beef patty with melted cheese and pickles',       '10.99', $cat_mains,  40, 5],
    ['Veggie Wrap',            'Fresh vegetables in a warm tortilla wrap',        '9.50',  $cat_mains,  30, 5],
    ['Caesar Salad',           'Crisp romaine, parmesan, and croutons',           '8.99',  $cat_mains,  25, 5],
    ['French Fries',           'Crispy golden fries with seasoning',              '3.99',  $cat_sides,  80, 10],
    ['Onion Rings',            'Battered and fried onion rings',                  '4.50',  $cat_sides,  60, 10],
    ['Coleslaw',               'Creamy homemade coleslaw',                        '2.99',  $cat_sides,  40, 5],
    ['Soft Drink',             'Choice of Coke, Sprite, or Fanta',               '2.50',  $cat_drinks, 100, 10],
    ['Fresh Lemonade',         'Freshly squeezed lemon juice with mint',          '3.99',  $cat_drinks, 50, 5],
    ['Water Bottle',           'Still or sparkling mineral water',                '1.50',  $cat_drinks, 200, 20],
];
foreach ($menu_items as [$name, $desc, $price, $cat, $stock, $threshold]) {
    $item_id = new_id();
    db_execute(
        'INSERT INTO MenuItem (id, name, description, price, isAvailable, preparationTime, stockQuantity, lowStockThreshold, categoryId, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, 1, 15, ?, ?, ?, ?, datetime("now"), datetime("now"))',
        [$item_id, $name, $desc, $price, $stock, $threshold, $cat, $restaurant_id]
    );
}

echo "=== Setup Complete! ===\n";
echo "Restaurant : Default Restaurant (slug: default)\n";
echo "Superadmin : superadmin@restaurant.com / admin123\n";
echo "Admin      : admin@restaurant.com / admin123\n";
echo "Customer   : customer@example.com / customer123\n";
echo "Menu       : 10 items across 3 categories\n";
echo "\nDELETE this file before going to production!\n";
