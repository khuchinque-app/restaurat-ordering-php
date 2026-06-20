<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/activity.php';
session_init();

// If already logged in, redirect to the right place
$user = get_auth_user();
if ($user) {
    if ($user['role'] === 'SUPERADMIN') { header('Location: superadmin/index.php'); exit; }
    if (in_array($user['role'], ['ADMIN', 'MANAGER'])) { header('Location: admin/index.php'); exit; }
    header('Location: superadmin/index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $result = auth_login($email, $password);
        if ($result === false) {
            $error = 'Invalid email or password.';
        } else {
            $role = $result['user']['role'];
            log_activity($result['user'], 'LOGIN', 'User', $result['user']['id'], "Signed in from " . ($_SERVER['REMOTE_ADDR'] ?? ''));
            if ($role === 'SUPERADMIN')                        { header('Location: superadmin/index.php'); exit; }
            if (in_array($role, ['ADMIN', 'MANAGER']))         { header('Location: admin/index.php'); exit; }
            header('Location: superadmin/index.php'); exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Ordering System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 1.5rem;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(124,58,237,0.1) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(13,148,136,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .page-wrap {
            width: 100%;
            max-width: 480px;
            position: relative;
        }

        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .brand h1 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .brand p {
            color: rgba(255,255,255,0.5);
            font-size: 0.875rem;
            margin-top: 0.3rem;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 2.25rem 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .card-sub {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }

.form-group { margin-bottom: 1rem; }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.3rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-group input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
            background: #fff;
        }

        .btn-primary {
            width: 100%;
            padding: 0.7rem;
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(124,58,237,0.35);
        }

        .btn-primary:active { transform: translateY(0); }

        /* ===== Storefront Buttons ===== */
        .storefronts-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0 1rem;
        }

        .storefronts-label::before,
        .storefronts-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .storefronts-label span {
            font-size: 0.78rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .storefront-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .sf-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 1.25rem 1rem;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .sf-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .sf-btn:active { transform: translateY(0); }

        .sf-btn .sf-icon {
            font-size: 1.8rem;
            line-height: 1;
        }

        .sf-btn .sf-name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .sf-btn .sf-desc {
            font-size: 0.72rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.3;
        }

        .sf-btn.aseng {
            border-color: #0d9488;
            background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
        }

        .sf-btn.aseng:hover {
            border-color: #0d9488;
            box-shadow: 0 4px 16px rgba(13,148,136,0.2);
        }

        .sf-btn.tittil {
            border-color: #7c3aed;
            background: linear-gradient(135deg, #faf5ff, #ede9fe);
        }

        .sf-btn.tittil:hover {
            border-color: #7c3aed;
            box-shadow: 0 4px 16px rgba(124,58,237,0.2);
        }

        .alert {
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        @media (max-width: 480px) {
            .card { padding: 1.75rem 1.25rem; }
            .storefront-btns { grid-template-columns: 1fr; }
            body { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="brand">
        <div class="brand-icon">&#127860;</div>
        <h1>Restaurant Ordering System</h1>
        <p>Manage orders, menus, and your online storefronts</p>
    </div>

    <div class="card">
        <div class="card-title">Sign In</div>
        <div class="card-sub">Access the admin control panel</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

<form method="POST" action="index.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus
                       placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                       placeholder="Enter your password">
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="storefronts-label">
            <span>Browse Storefronts</span>
        </div>

        <div class="storefront-btns">
            <a href="/tittil/" class="sf-btn tittil">
                <span class="sf-icon">&#127870;</span>
                <span class="sf-name">Tittil</span>
                <span class="sf-desc">Treats &amp; bites you'll love</span>
            </a>
            <a href="/aseng/" class="sf-btn aseng">
                <span class="sf-icon">&#127858;</span>
                <span class="sf-name">Aseng</span>
                <span class="sf-desc">Authentic Asian flavors</span>
            </a>
        </div>
    </div>
</div>
</body>
</html>
