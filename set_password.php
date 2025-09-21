<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Initialize variables
$errors = [];
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = '';

// Validate token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $errors[] = 'Invalid or expired setup token.';
    } else {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        if (strtotime($row['expires_at']) < time()) {
            $errors[] = 'This setup link has expired. Please contact the administrator.';
        }
    }
    $stmt->close();
} else {
    $errors[] = 'No setup token provided.';
}

// Handle password setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validate passwords
    if (empty($new_password) || strlen($new_password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        // Update password in users table
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        if ($stmt->execute()) {
            // Delete the used token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
            $success = 'Password set successfully. You can now <a href="' . SITE_URL . '/login.php" class="text-primary-red hover:underline">log in</a>.';
        } else {
            $errors[] = 'Failed to set password. Please try again.';
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --light-gray: #e5e7eb;
            --accent-blue: #3b82f6;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--white), var(--gray));
        }
        header {
            background: #000;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 40;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }
        .header-logo img {
            height: 40px;
        }
        .header-nav {
            display: flex;
            gap: 20px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .header-nav a:hover {
            color: #dc2626;
        }
        #mobile-menu {
            display: none;
            background: #000;
            padding: 20px;
        }
        #mobile-menu.active {
            display: block;
        }
        .mobile-nav a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .mobile-nav a:hover {
            color: #dc2626;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: none;
        }
        .toggle-btn:hover {
            color: #dc2626;
        }
        main {
            flex: 1;
            padding-top: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 768px) {
            .header-nav {
                display: flex;
            }
            .toggle-btn {
                display: none;
            }
            header.md-hidden {
                display: none;
            }
        }
        @media (max-width: 767px) {
            .toggle-btn {
                display: block;
            }
            .header-nav {
                display: none;
            }
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 400px;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }
        .text-red-600 { color: #e30613; }
        .bg-red-600 { background-color: #e30613; }
        .hover\:bg-red-600:hover { background-color: #e30613; }
        .hover\:text-red-600:hover { color: #e30613; }
        .bg-red-700 { background-color: #c70410; }
        .hover\:bg-red-700:hover { background-color: #c70410; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="fixed top-0 left-0 w-full bg-black text-white p-4 shadow-md flex items-center justify-between z-40 md-hidden">
        <button id="sidebar-toggle-mobile" class="toggle-btn">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <div class="flex-1 text-center">
            <a href="<?= SITE_URL ?>">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo" class="h-8 mx-auto">
            </a>
        </div>
        <div class="w-10"></div>
    </header>
    <div id="mobile-menu" class="mobile-menu">
        <nav class="mobile-nav">
            <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
            <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
            <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
            <a href="<?= SITE_URL ?>/login.php">Login</a>
        </nav>
    </div>

    <main class="p-4 sm:p-6 lg:p-10">
        <div class="container mx-auto">
            <div class="card p-6 sm:p-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                    <i class="fas fa-lock text-primary-red mr-3"></i>Set Your Password
                </h1>
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                    <p class="text-center text-gray-600">Contact the administrator to request a new setup link.</p>
                <?php elseif ($success): ?>
                    <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= $success ?></p>
                <?php else: ?>
                    <form method="POST" class="space-y-6">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Set Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenuCancel = document.getElementById('mobile-menu-cancel');
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuIcon = document.getElementById('mobile-menu-icon');
            if (mobileMenuToggle && mobileMenuCancel && mobileMenu && mobileMenuIcon) {
                mobileMenuToggle.addEventListener('click', function() {
                    mobileMenu.classList.toggle('active');
                    mobileMenuToggle.style.display = mobileMenu.classList.contains('active') ? 'none' : 'block';
                    mobileMenuCancel.style.display = mobileMenu.classList.contains('active') ? 'block' : 'none';
                    mobileMenuIcon.classList.toggle('fa-bars');
                    mobileMenuIcon.classList.toggle('fa-times');
                });
                mobileMenuCancel.addEventListener('click', function() {
                    mobileMenu.classList.remove('active');
                    mobileMenuToggle.style.display = 'block';
                    mobileMenuCancel.style.display = 'none';
                    mobileMenuIcon.classList.remove('fa-times');
                    mobileMenuIcon.classList.add('fa-bars');
                });
            }
        });
    </script>
</body>
</html>