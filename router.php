<?php
/**
 * PHP built-in server router.
 * Handles static files correctly and routes PHP requests.
 * Start: php -S 0.0.0.0:7500 router.php
 */
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$docRoot = __DIR__;
$file = $docRoot . $path;

// PHP files must go through the PHP interpreter, NOT readfile()
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($ext === 'php') {
    return false; // Let PHP built-in server handle it
}

// Serve static files directly (CSS, JS, images, etc.)
if (is_file($file)) {
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'txt'  => 'text/plain',
        'xml'  => 'application/xml',
        'pdf'  => 'application/pdf',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp'])) {
        header('Cache-Control: public, max-age=3600');
    }
    readfile($file);
    return true;
}

// For directory requests (e.g., /aseng/), load index.php
if ($path === '/' || substr($path, -1) === '/') {
    $indexFile = $docRoot . $path . 'index.php';
    if (is_file($indexFile)) {
        require $indexFile;
        return true;
    }
    $indexHtml = $docRoot . $path . 'index.html';
    if (is_file($indexHtml)) {
        readfile($indexHtml);
        return true;
    }
}

// 404 for everything else
http_response_code(404);
echo '404 Not Found';
return true;
