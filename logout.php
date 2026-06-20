<?php
/**
 * Logout — destroys the session and redirects to the login page.
 */
require_once __DIR__ . '/auth.php';

auth_logout();

// Redirect to login page
header('Location: ' . APP_URL . '/index.php');
exit;
