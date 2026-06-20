<?php
/**
 * Menu Image Upload API
 * POST: Upload an image for a menu item
 * Accepts multipart/form-data with 'image' file field
 * Returns the relative URL of the uploaded file
 */
require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/includes/activity.php';

$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_error(400, 'No file uploaded or upload error: ' . ($_FILES['image']['error'] ?? 'unknown'));
}

$file = $_FILES['image'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowed_types)) {
    json_error(400, 'Invalid file type: ' . $mime . '. Allowed: ' . implode(', ', $allowed_types));
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    json_error(400, 'File too large. Maximum size: 5MB');
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$ext = strtolower($ext);
if (!in_array($ext, $allowed_exts)) {
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        default => 'jpg',
    };
}

$filename = 'menu_' . bin2hex(random_bytes(8)) . '.' . $ext;
$upload_dir = dirname(__DIR__, 2) . '/menu-uploads';

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filepath = $upload_dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    json_error(500, 'Failed to save uploaded file');
}

// Return the relative URL
$url = 'menu-uploads/' . $filename;

log_activity($user, 'UPLOAD_IMAGE', 'MenuItem', '', "Uploaded image: $filename (" . round($file['size'] / 1024) . "KB)");

json_ok([
    'url'      => $url,
    'filename' => $filename,
    'size'     => $file['size'],
    'mime'     => $mime,
]);
