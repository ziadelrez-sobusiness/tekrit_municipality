<?php
/**
 * Admin Guard - Central Authentication & Authorization
 * Include this at the top of EVERY admin/module file.
 * This ensures consistent security across all admin pages.
 */

// Prevent multiple inclusions
if (!defined('ADMIN_GUARD_LOADED')) {
    define('ADMIN_GUARD_LOADED', true);
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required auth files (adjust path dynamically if included from deep directories)
$base_dir = __DIR__ . '/../';
require_once $base_dir . 'config/database.php';
require_once $base_dir . 'includes/auth.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    // Handle AJAX requests differently
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'redirect' => 'login.php']);
        exit;
    }
    
    // Determine the correct login.php path based on current script location
    $script_path = $_SERVER['SCRIPT_NAME'];
    $login_url = 'login.php';
    if (strpos($script_path, '/modules/') !== false || strpos($script_path, '/admin/') !== false) {
        $login_url = '../login.php';
    }
    
    header("Location: $login_url");
    exit;
}

// Make auth available globally for legacy compatibility
$GLOBALS['auth'] = $auth;
$GLOBALS['current_user_id'] = $_SESSION['user_id'] ?? null;
$GLOBALS['current_user_type'] = $_SESSION['user_type'] ?? null;
