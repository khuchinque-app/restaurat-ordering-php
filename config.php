<?php
define('DB_PATH', __DIR__ . '/data/restaurant.db');
$jwt_from_env = getenv('JWT_SECRET');
$jwt_from_file = is_readable(__DIR__ . '/.jwt_secret') ? trim(file_get_contents(__DIR__ . '/.jwt_secret')) : null;
define('JWT_SECRET', $jwt_from_env ?: ($jwt_from_file ?: 'change-me-in-production-' . bin2hex(random_bytes(16))));
define('APP_URL', getenv('APP_URL') ?: '');
define('TAX_RATE', 0.10);
define('DEFAULT_RESTAURANT_SLUG', 'default');
