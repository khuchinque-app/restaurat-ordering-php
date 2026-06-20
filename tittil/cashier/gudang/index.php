<?php
session_start();
require_once '../db.php';

// Auth gate
if (empty($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'superadmin') {
    header('Location: ../index.php');
    exit;
}

if (function_exists('updateOnlineStatus')) {
    updateOnlineStatus($pdo, $_SESSION['username'], true);
}

// Load original HTML
$htmlFile = __DIR__ . '/index.html';
if (!file_exists($htmlFile)) {
    http_response_code(500);
    exit('Error: index.html not found');
}
$content = file_get_contents($htmlFile);

// Inject <base> tag to make relative URLs work
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
$baseTag = '<base href="' . htmlspecialchars($baseUrl, ENT_QUOTES) . '">';
$content = str_replace('<head>', '<head>' . $baseTag, $content);

// Convert absolute asset paths to relative (so they use the base)
$content = str_replace('href="/static/', 'href="static/', $content);
$content = str_replace('src="/static/', 'src="static/', $content);
$content = str_replace('src="/asset/', 'src="asset/', $content);

// Fix history link
$content = str_replace('href="./history-folder/"', 'href="./history.php"', $content);
$content = str_replace("href='./history-folder/'", "href='./history.php'", $content);

// Cache bust script.js
$content = preg_replace('/(src=["\'].*?\/script\.js)(["\'])/', '$1?v=' . date('Ymd') . '$2', $content);

// Auth bar (unchanged)
$safeUser = htmlspecialchars($_SESSION['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
$superLink = ($role === 'superadmin') ? '<a href="../superadmin/index.php" style="color:#00bcff;text-decoration:none;margin-left:15px;font-weight:bold;">⚙️ Superadmin</a>' : '';
$authBar = '<div id="php-auth-bar" style="background:#0c0c0c;border-bottom:2px solid #00ff00;padding:10px 20px;font-family:monospace;display:flex;justify-content:space-between;align-items:center;color:#ccc;font-size:13px;position:sticky;top:0;z-index:99999;">'
    . '<div><span style="color:#00ff00;font-weight:bold;">🔒 AUTHENTICATED</span>'
    . '<span style="margin-left:12px;">👤 ' . $safeUser . ' <span style="color:#888;">(' . $safeRole . ')</span></span>'
    . '<a href="./history.php" style="color:#FFC107;text-decoration:none;margin-left:15px;font-weight:bold;">📜 History</a>'
    . '<a href="./stock.php" style="color:#FF9800;text-decoration:none;margin-left:15px;font-weight:bold;">📦 Stock</a>'
    . $superLink . '</div>'
    . '<a href="../logout.php" style="background:#ff3333;color:#fff;padding:4px 14px;text-decoration:none;border-radius:2px;font-size:12px;font-weight:bold;">Logout</a>'
    . '</div>';
// Inject after <body>
$bodyPos = stripos($content, '<body');
if ($bodyPos !== false) {
    $gtPos = strpos($content, '>', $bodyPos);
    if ($gtPos !== false) {
        $content = substr_replace($content, $authBar, $gtPos + 1, 0);
    } else {
        $content = $authBar . PHP_EOL . $content;
    }
} else {
    $content = $authBar . PHP_EOL . $content;
}

// Final initialization script (unchanged)
$finalInit = '<script>
window.addEventListener("load", function() {
    console.log("✅ Page fully loaded. Checking main script...");
    if (typeof addMenuRow === "function") {
        console.log("✅ Main script loaded successfully!");
        if (typeof initMenuInput === "function") initMenuInput();
        if (typeof updateTimeAndOrder === "function") updateTimeAndOrder();
        const addBtn = document.getElementById("addMenuBtn");
        if (addBtn) addBtn.onclick = function() { addMenuRow(); };
    } else {
        console.error("[FATAL] addMenuRow is missing even after full load.");
        alert("Application script failed to load properly.\\n\\nPlease hard-refresh (Ctrl + Shift + R)");
    }
});
</script>';
$bodyEnd = stripos($content, "</body>");
if ($bodyEnd !== false) {
    $content = substr_replace($content, $finalInit, $bodyEnd, 0);
} else {
    $content .= $finalInit;
}

header('Content-Type: text/html; charset=utf-8');
echo $content;
?>