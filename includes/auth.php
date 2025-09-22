<?php
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
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    if (!in_array($user_role, $required_roles)) {
        header("Location: " . SITE_URL . $redirect_page);
        exit();
    }
}

/**
 * Generates a unique affiliate ID for a user in the format FIRST_NAME+ID (all caps).
 * @param mysqli $conn Database connection.
 * @param string $first_name User's first name.
 * @param int $user_id User's ID.
 * @return string Unique affiliate ID.
 */
function generateAffiliateId($conn, $first_name, $user_id) {
    // Create base affiliate ID: FIRST_NAME+ID (all caps)
    $base_id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $first_name)  . $user_id);
    
    // Check if the affiliate ID already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE affiliate_id = ?");
    $stmt->bind_param("s", $base_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // If ID exists, append a number until unique
    if ($count > 0) {
        $suffix = 1;
        do {
            $new_id = $base_id . $suffix;
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE affiliate_id = ?");
            $stmt->bind_param("s", $new_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $suffix++;
        } while ($count > 0);
        return $new_id;
    }
    
    return $base_id;
}
?>