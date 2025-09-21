<?php
// includes/auth.php
require_once __DIR__ . '/config.php';

/**
 * Checks if a user is logged in.
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']);
}

/**
 * Redirects user based on their role.
 * Assumes the session variable 'user' contains a 'role' key.
 */
function redirectUser() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/login.php");
        exit();
    }

    $role = $_SESSION['user']['role'];
    $redirect_url = '';

    switch ($role) {
        case 'admin':
            $redirect_url = SITE_URL . "/admin/dashboard.php";
            break;
        case 'staff':
            $redirect_url = SITE_URL . "/staff/dashboard.php";
            break;
        case 'customer':
        default:
            $redirect_url = SITE_URL . "/dashboard.php";
            break;
    }
    
    if ($redirect_url) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Redirects user to login if not authenticated.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/login.php");
        exit();
    }
}

/**
 * Redirects user to a specific page if they don't have the required role.
 * @param array|string $required_roles The role or array of roles required to access the page.
 * @param string $redirect_page The page to redirect to if role is not met.
 */
function requireRole($required_roles, $redirect_page = '/dashboard.php') {
    if (!isLoggedIn()) {
        requireLogin();
        return;
    }

    $user_role = $_SESSION['user']['role'];
    if (!in_array($user_role, (array)$required_roles)) {
        header("Location: " . SITE_URL . $redirect_page);
        exit();
    }
}