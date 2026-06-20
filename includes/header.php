<?php
require_once dirname(__DIR__) . '/auth.php';
session_init();
$current_user = get_auth_user();
$is_admin = $current_user && in_array($current_user['role'], ['ADMIN', 'SUPERADMIN', 'MANAGER']);
$current_path = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Restaurant') ?></title>
    <link rel="stylesheet" href=" <?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?= APP_URL ?>/index.php" class="logo">&#127860; Restaurant</a>
        <nav class="main-nav">
            <?php if ($current_user): ?>
                <?php if ($is_admin): ?>
                    <a href="<?= APP_URL ?>/admin/index.php">Admin</a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/logout.php">Logout (<?= htmlspecialchars($current_user['name']) ?>)</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/index.php">Login</a>
            <?php endif; ?>
        </nav>
        <button class="nav-toggle" onclick="document.querySelector('.main-nav').classList.toggle('open')">&#9776;</button>
    </div>
</header>
<main class="container main-content">
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
