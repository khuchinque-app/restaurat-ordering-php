<?php
session_start();

// =============================================================================
// LOGIN PAGE - TOOLS RESTO & DELIVERY
// =============================================================================
// Simple login form that submits to index.php for processing
// =============================================================================

$error = isset($_GET['error']) && $_GET['error'] == '1';
if (isset($_SESSION['login_error'])) {
    $error = true;
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TOOLS RESTO & DELIVERY</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: #1e1e1e;
            padding: 40px 50px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            border: 1px solid #333;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-height: 100px;
            width: auto;
        }
        .login-logo h1 {
            color: #fff;
            font-size: 24px;
            margin-top: 15px;
            font-weight: 600;
        }
        .login-logo p {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: #00ff00;
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: #00ff00;
            color: #000;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 10px;
        }
        .submit-btn:hover {
            opacity: 0.9;
        }
        .error-msg {
            background: #ff3333;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .forgot-note {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-logo">
            <img src="/asset/image1.png" alt="Logo">
            <h1>TOOLS RESTO & DELIVERY</h1>
            <p>ASENG - Login</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-msg">❌ Invalid username or password</div>
        <?php endif; ?>
        
        <form method="POST" action="/index.php">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus placeholder="Enter username">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">
            </div>
            
            <input type="hidden" name="login_submit" value="1">
            <button type="submit" class="submit-btn">🚀 Login</button>
        </form>
        
        <div class="forgot-note">
            Default: superadmin / superpassword
        </div>
    </div>
</body>
</html>