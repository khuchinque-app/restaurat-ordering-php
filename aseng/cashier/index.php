<?php
// ============================================================
// ASENG CASHIER - AUTO-RUN MODE (DB inside folder)
// ============================================================

require_once __DIR__ . '/db.php';

// ── Load the HTML template ─────────────────────────────────
$htmlFile = __DIR__ . '/index.html';
if (!file_exists($htmlFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html>
<html><body style="background:#0c0c0c;color:#ff3333;font-family:monospace;padding:40px;text-align:center;">
Error: <b>index.html</b> not found in same folder.<br>
Make sure index.html exists alongside this index.php.
</body></html>');
}

$content = file_get_contents($htmlFile);

// ── Fix paths for Docker/VPS subfolder hosting ─────────────
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$basePath = $basePath ?: '';

if ($basePath !== '') {
    $escBase = preg_quote($basePath, '/');
    $content = preg_replace('/(href\s*=\s*["\'])\/(?!\/)(?!' . $escBase . '\/)/i', '$1' . $basePath . '/', $content);
    $content = preg_replace('/(src\s*=\s*["\'])\/(?!\/)(?!' . $escBase . '\/)/i',  '$1' . $basePath . '/', $content);
    $content = preg_replace('/(url\s*\(\s*["\']?)\\/(?!\/)(?!' . $escBase . '\/)/i','$1' . $basePath . '/', $content);
}

// Convert absolute static/asset paths to relative
$content = str_replace('src="/static/', 'src="./static/', $content);
$content = str_replace('href="/static/', 'href="./static/', $content);
$content = str_replace('src="/asset/', 'src="./asset/', $content);

// Fix history/stock links
$content = str_replace('history-folder/', 'history.php', $content);
$content = str_replace('./history-folder', './history.php', $content);
$content = str_replace('stock-folder/', 'stock.php', $content);
$content = str_replace('./stock-folder', './stock.php', $content);

// Cache bust
$content = preg_replace('/(src=["\'].*?\/script\.js)(["\'])/', '$1?v=standalone$2', $content);

// ── Minimal status bar ─────────────────────────────────────
$statusBar = '<div id="app-bar" style="background:#0c0c0c;border-bottom:2px solid #00ff00;padding:10px 20px;font-family:monospace;display:flex;justify-content:space-between;align-items:center;color:#ccc;font-size:13px;position:sticky;top:0;z-index:99999;">'
    . '<div><span style="color:#00ff00;font-weight:bold;">⚡ ASENG CASHIER</span>'
    . '<span style="margin-left:12px;color:#888;">Auto-run mode | DB connected</span></div>'
    . '<a href="./history.php" style="color:#FFC107;text-decoration:none;margin-left:15px;font-weight:bold;">📜 History</a>'
    . '<a href="./stock.php" style="color:#FF9800;text-decoration:none;margin-left:15px;font-weight:bold;">📦 Stock</a>'
    . '</div>';

$bodyPos = stripos($content, '<body');
if ($bodyPos !== false) {
    $gtPos = strpos($content, '>', $bodyPos);
    if ($gtPos !== false) {
        $content = substr_replace($content, $statusBar, $gtPos + 1, 0);
    }
}

// ── Ensure script loads ───────────────────────────────────
$finalInit = '<script>
window.addEventListener("load", function() {
    console.log("⚡ ASENG Auto-run mode active | DB: connected");
    if (typeof addMenuRow === "function") {
        console.log("✅ Main script loaded");
        if (typeof initMenuInput === "function") initMenuInput();
        if (typeof updateTimeAndOrder === "function") updateTimeAndOrder();
    } else {
        console.error("[FATAL] script.js not loaded. Check static/js/ folder.");
    }
});
</script>';

$bodyEnd = stripos($content, "</body>");
if ($bodyEnd !== false) {
    $content = substr_replace($content, $finalInit, $bodyEnd, 0);
}

// ── Serve ─────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
echo $content;
