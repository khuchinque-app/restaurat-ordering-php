<?php
/**
 * Seed / sync extra restaurants and their menus from per-restaurant item files.
 *
 *   Run:  php seed_restaurants.php
 *
 * Each restaurant has a storefront folder (slug) containing an `item.txt` file.
 * Lines are: category , name , price , stock , lowStock , image   (# = comment)
 *
 * Idempotent — safe to re-run:
 *   - Restaurant matched by unique `slug`.
 *   - Category matched by (slug, restaurantId).
 *   - MenuItem matched by (name, restaurantId): updated in place, never duplicated.
 *   - `image` is stored as `assets/images/<file>`, resolved relative to the
 *     storefront page (e.g. /aseng/assets/images/<file>).
 */
require_once __DIR__ . '/db.php';

$RESTAURANTS = [
    ['slug' => 'aseng',  'name' => 'Aseng',  'description' => 'Authentic flavors, freshly prepared.'],
    ['slug' => 'tittil', 'name' => 'Tittil', 'description' => "Treats and bites you'll love."],
];

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function upsert_restaurant(array $r): string {
    $existing = db_fetch('SELECT id FROM Restaurant WHERE slug = ?', [$r['slug']]);
    if ($existing) {
        db_execute(
            'UPDATE Restaurant SET name = ?, description = ?, isActive = 1, updatedAt = datetime("now") WHERE id = ?',
            [$r['name'], $r['description'], $existing['id']]
        );
        return $existing['id'];
    }
    $id = new_id();
    db_execute(
        'INSERT INTO Restaurant (id, name, slug, description, isActive, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, 1, datetime("now"), datetime("now"))',
        [$id, $r['name'], $r['slug'], $r['description']]
    );
    return $id;
}

function upsert_category(string $restaurantId, string $name, int $sortOrder): string {
    $slug = slugify($name);
    $existing = db_fetch('SELECT id FROM Category WHERE slug = ? AND restaurantId = ?', [$slug, $restaurantId]);
    if ($existing) {
        db_execute('UPDATE Category SET name = ?, sortOrder = ?, isActive = 1, updatedAt = datetime("now") WHERE id = ?',
            [$name, $sortOrder, $existing['id']]);
        return $existing['id'];
    }
    $id = new_id();
    db_execute(
        'INSERT INTO Category (id, name, slug, description, sortOrder, isActive, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, 1, ?, datetime("now"), datetime("now"))',
        [$id, $name, $slug, '', $sortOrder, $restaurantId]
    );
    return $id;
}

function upsert_item(string $restaurantId, string $categoryId, array $it): void {
    $existing = db_fetch('SELECT id FROM MenuItem WHERE name = ? AND restaurantId = ?', [$it['name'], $restaurantId]);
    $available = ($it['stock'] === null || $it['stock'] > 0) ? 1 : 0;
    if ($existing) {
        db_execute(
            'UPDATE MenuItem SET description = ?, price = ?, image = ?, isAvailable = ?, stockQuantity = ?,
                 lowStockThreshold = ?, categoryId = ?, updatedAt = datetime("now") WHERE id = ?',
            [$it['description'], $it['price'], $it['image'], $available, $it['stock'], $it['lowStock'], $categoryId, $existing['id']]
        );
        return;
    }
    $id = new_id();
    db_execute(
        'INSERT INTO MenuItem (id, name, description, price, image, isAvailable, preparationTime, stockQuantity,
             lowStockThreshold, categoryId, restaurantId, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, 15, ?, ?, ?, ?, datetime("now"), datetime("now"))',
        [$id, $it['name'], $it['description'], $it['price'], $it['image'], $available, $it['stock'], $it['lowStock'], $categoryId, $restaurantId]
    );
}

function parse_items(string $file): array {
    if (!is_readable($file)) return [];
    $items = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $cols = array_map('trim', str_getcsv($line));
        if (count($cols) < 3 || $cols[1] === '' || $cols[0] === '') continue;
        [$category, $name, $price] = [$cols[0], $cols[1], $cols[2]];
        $stock    = (isset($cols[3]) && $cols[3] !== '') ? (int)$cols[3] : null;
        $lowStock = (isset($cols[4]) && $cols[4] !== '') ? (int)$cols[4] : 5;
        $image    = (isset($cols[5]) && $cols[5] !== '') ? 'assets/images/' . $cols[5] : null;
        $items[] = [
            'category' => $category, 'name' => $name, 'price' => (string)(float)$price,
            'description' => '', 'stock' => $stock, 'lowStock' => $lowStock, 'image' => $image,
        ];
    }
    return $items;
}

$grand = 0;
foreach ($RESTAURANTS as $r) {
    $file  = __DIR__ . '/' . $r['slug'] . '/item.txt';
    $items = parse_items($file);
    $rid   = upsert_restaurant($r);

    // Establish a stable sort order for categories by first appearance.
    $catOrder = [];
    foreach ($items as $it) {
        if (!isset($catOrder[$it['category']])) $catOrder[$it['category']] = count($catOrder);
    }
    $catIds = [];
    foreach ($catOrder as $catName => $sort) {
        $catIds[$catName] = upsert_category($rid, $catName, $sort);
    }
    foreach ($items as $it) {
        upsert_item($rid, $catIds[$it['category']], $it);
    }
    $count = count($items);
    $grand += $count;
    echo str_pad($r['name'], 10) . " (slug: {$r['slug']}) — " . count($catOrder) . " categories, $count items"
        . ($count === 0 ? "  [no readable {$r['slug']}/item.txt — skipped items]" : '') . "\n";
}

echo "=== Done. $grand items seeded across " . count($RESTAURANTS) . " restaurants. ===\n";
