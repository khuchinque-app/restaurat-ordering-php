<?php
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';

$slug = $_GET['restaurant'] ?? DEFAULT_RESTAURANT_SLUG;
$r = get_restaurant($slug);
if (!$r) json_error(404, 'Restaurant not found');

json_ok([
    'id'          => $r['id'],
    'name'        => $r['name'],
    'slug'        => $r['slug'],
    'description' => $r['description'] ?? null,
]);
