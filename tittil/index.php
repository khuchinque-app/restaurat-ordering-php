<?php
require_once dirname(__DIR__) . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
$current_user = null; // Storefront is public
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tittil — Pempek & Indonesian Food</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Header -->
<header class="site-header">
    <div class="header-inner">
        <a href="/tittil/" class="logo">
            <span class="logo-icon">&#127858;</span>
            Tittil
        </a>
    </div>
</header>

<!-- Hero -->
<section class="hero">
    <div class="hero-bg-img"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="hero-badge-dot"></span>
            &#127858; Palembang Authentic
        </div>
        <h1>Pempek &amp; More<br><span class="highlight">You'll Crave</span></h1>
        <p>Handcrafted pempek, hearty Indonesian mains, and refreshing drinks — all made fresh daily with authentic recipes.</p>
        <div class="hero-divider"></div>
    </div>
</section>

<!-- Main -->
<main class="container">

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="cat-tabs" id="cat-tabs">
            <button class="cat-tab active">Loading&hellip;</button>
        </div>
        <div class="search-wrap">
            <input type="search" id="search-input" placeholder="Search menu&hellip;" autocomplete="off">
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-bar" id="stats-bar"></div>

    <!-- Menu Grid -->
    <div class="menu-grid" id="menu-grid">
        <p style="padding:2rem;color:#94a3b8;text-align:center">Loading menu&hellip;</p>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer">
    <p>&copy; 2026 Tittil &mdash; Pempek &amp; Indonesian Food</p>
</footer>

<!-- Drawer Overlay -->
<div class="drawer-overlay" id="drawer-overlay"></div>

<!-- Cart Drawer -->
<div class="cart-drawer" id="cart-drawer">
    <div class="drawer-header">
        <h2>&#128722; Pesanan</h2>
        <button class="close-btn" onclick="closeCart()">&times;</button>
    </div>
    <div class="drawer-body" id="cart-body">
        <div class="drawer-empty">
            <div class="empty-icon">&#128722;</div>
            Pesanan masih kosong
        </div>
    </div>
    <div class="drawer-footer" id="cart-footer"></div>
</div>

<!-- Order Confirm -->
<div class="confirm-modal" id="order-confirm">
    <div class="confirm-box">
        <div class="confirm-icon">&#10003;</div>
        <h2>Pesanan Terkirim! ✅</h2>
        <p>Pesanan <strong id="confirm-order-num"></strong> telah diterima. Kami akan segera menyiapkannya!</p>
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:.6rem">
            <a href="https://t.me/pempektitilkps" target="_blank" style="display:block;padding:.55rem 1rem;background:#0088cc;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem;text-align:center" onclick="window.open('https://t.me/pempektitilkps')">
                📱 Chat via Telegram
            </a>
            <button onclick="document.getElementById('order-confirm').classList.remove('open');toggleChat()" style="display:block;width:100%;padding:.55rem 1rem;background:#7c3aed;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:.9rem">
                💬 Accept &amp; Chat with Admin
            </button>
        </div>
        <button class="confirm-btn" onclick="document.getElementById('order-confirm').classList.remove('open')">Lanjut Belanja</button>
    </div>
</div>

<!-- Floating Cart Button -->
<button class="float-cart" id="floatCartBtn" onclick="openCart()" title="Lihat Pesanan">
    <span class="float-cart-icon">&#128722;</span>
    <span class="float-cart-badge" id="cart-count" style="display:none">0</span>
</button>

<!-- Chat Bubble -->
<div class="chat-bubble" id="chatBubble" onclick="toggleChat()" title="Chat with us">
    &#128172;
</div>

<!-- Chat Widget -->
<div class="chat-widget" id="chatWidget">
    <div class="chat-widget-header">
        <span>&#128172; Chat with Tittil</span>
        <button class="chat-close-btn" onclick="toggleChat()">&times;</button>
    </div>
    <div class="chat-widget-messages" id="chatMessages">
        <div class="chat-widget-msg theirs">
            <div class="chat-widget-bubble">Hi there! How can we help you today?</div>
            <div class="chat-widget-time">Just now</div>
        </div>
    </div>
    <div class="chat-widget-input-area">
        <input type="text" id="chatInput" placeholder="Type a message..." maxlength="500" autocomplete="off">
        <button onclick="sendChatMessage()">Send</button>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="assets/app.js"></script>
</body>
</html>
