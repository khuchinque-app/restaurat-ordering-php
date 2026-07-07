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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse at 20% 30%, #4ade80 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, #06b6d4 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, #0d9488 0%, transparent 60%),
                linear-gradient(160deg, #064e3b 0%, #115e59 30%, #0f766e 60%, #0e7490 100%);
            padding: 1.5rem;
            position: relative;
        }

        /* Aurora blur overlay */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(74,222,128,0.25) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 80%, rgba(6,182,212,0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(13,148,136,0.15) 0%, transparent 60%);
            pointer-events: none;
            backdrop-filter: blur(40px);
        }

        .page-wrap {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }

        /* Glass card */
        .card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 20px;
            padding: 2.5rem 2rem 2rem;
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow:
                0 8px 32px rgba(0,0,0,0.12),
                inset 0 1px 0 rgba(255,255,255,0.1);
        }

        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
        }

        .brand h1 {
            color: #fff;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }

        .brand p {
            color: rgba(255,255,255,0.6);
            font-size: 0.82rem;
            margin-top: 0.3rem;
        }

        /* Form */
        .form-group { margin-bottom: 1rem; }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.25s;
            background: rgba(255,255,255,0.12);
            color: #fff;
            text-align: center;
        }

        .form-group input::placeholder { color: rgba(255,255,255,0.45); }

        .form-group input:focus {
            outline: none;
            border-color: rgba(74,222,128,0.6);
            box-shadow: 0 0 0 3px rgba(74,222,128,0.15);
            background: rgba(255,255,255,0.18);
        }

        /* Sign In button - dark pill */
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 9999px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s;
            font-family: inherit;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-top: 0.25rem;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e293b, #334155);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
        }

        .btn-primary:active { transform: translateY(0); }

        /* Forgot link */
        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 0.75rem;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            font-style: italic;
            transition: color 0.2s;
        }
        .forgot-link:hover { color: rgba(255,255,255,0.75); }

        /* Alert */
        .alert {
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .alert-danger {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }

        /* Storefront section */
        .storefronts-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.75rem 0 1rem;
        }

        .storefronts-label::before,
        .storefronts-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.12);
        }

        .storefronts-label span {
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.08em;
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
            gap: 0.35rem;
            padding: 1.1rem 0.8rem;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s;
            backdrop-filter: blur(8px);
        }

        .sf-btn:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .sf-btn:active { transform: translateY(0); }

        .sf-btn .sf-icon { font-size: 1.6rem; line-height: 1; }
        .sf-btn .sf-name { font-size: 0.85rem; font-weight: 700; color: #fff; }
        .sf-btn .sf-desc {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.5);
            text-align: center;
            line-height: 1.3;
        }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem; }
            .storefront-btns { grid-template-columns: 1fr; }
            body { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="brand">
        <div class="brand-icon">
            <img src="menu-uploads/logo_admin.png?v=2" alt="" style="width:64px;height:64px;border-radius:12px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.3)">
        </div>
        <h1>Login Panel</h1>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <div class="form-group">
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus
                       placeholder="Email">
            </div>
            <div class="form-group">
                <input type="password" name="password" required
                       placeholder="Password">
            </div>
            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="storefronts-label">
            <span>Browse Storefronts</span>
        </div>

        <div class="storefront-btns">
            <a href="https://tittil.online" class="sf-btn">
                <img src="tittil/assets/logo-icon.png?v=2" alt="Tittil" class="w-10 h-10 rounded-full object-cover">
            </a>
            <a href="https://pempekaseng.com" class="sf-btn">
                <img src="aseng/assets/logo-icon.png?v=2" alt="Aseng" class="w-10 h-10 rounded-full object-cover">
            </a>
        </div>
    </div>
</div>
</body>
</html>
