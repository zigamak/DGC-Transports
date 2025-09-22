<?php
// templates/header.php
// Use relative paths based on __DIR__ for portability across environments
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
?>

<!-- Common Head Elements (to be included in <head> of pages) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#dc2626',
                    secondary: '#991b1b',
                }
            }
        }
    }
</script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Header Navigation (to be included in <body>) -->
<header class="bg-white text-gray-900 fixed top-0 left-0 w-full z-50 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo (Centered on mobile) -->
            <div class="flex-shrink-0 order-2">
                <a href="<?= SITE_URL ?>">
                    <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo" class="h-10 w-auto">
                </a>
            </div>

            <!-- Navigation Menu (Desktop) -->
            <nav class="hidden md:flex space-x-8 order-3">
                <?php if (isLoggedIn()): ?>
                    <?php if ($_SESSION['user']['role'] === 'customer'): ?>
                        <a href="<?= SITE_URL ?>" class="hover:text-primary transition-colors duration-200 font-semibold">Book Trip</a>
                        <a href="<?= SITE_URL ?>/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                        <a href="<?= SITE_URL ?>/profile.php" class="hover:text-primary transition-colors duration-200 font-semibold">Profile</a>
                        <a href="<?= SITE_URL ?>/my-bookings.php" class="hover:text-primary transition-colors duration-200 font-semibold">My Bookings</a>
                        <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                    <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
                        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                        <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                    <?php elseif ($_SESSION['user']['role'] === 'staff'): ?>
                        <a href="<?= SITE_URL ?>/staff/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                        <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>" class="hover:text-primary transition-colors duration-200 font-semibold">Book a Trip</a>
                    <a href="<?= SITE_URL ?>/bookings/manage_booking.php" class="hover:text-primary transition-colors duration-200 font-semibold">Manage Booking</a>
                    <a href="<?= SITE_URL ?>/contact.php" class="hover:text-primary transition-colors duration-200 font-semibold">Contact Us</a>
                    <a href="<?= SITE_URL ?>/login.php" class="hover:text-primary transition-colors duration-200 font-semibold">Login</a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Menu Button -->
            <div class="md:hidden order-3">
                <button id="mobile-menu-button" class="focus:outline-none">
                    <i class="fas fa-bars text-2xl hover:text-primary transition-colors duration-200"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu (Hidden by default) -->
    <div id="mobile-menu" class="md:hidden hidden bg-white">
        <nav class="flex flex-col space-y-4 px-4 py-4 text-gray-900">
            <?php if (isLoggedIn()): ?>
                <?php if ($_SESSION['user']['role'] === 'customer'): ?>
                    <a href="<?= SITE_URL ?>" class="hover:text-primary transition-colors duration-200 font-semibold">Book Trip</a>
                    <a href="<?= SITE_URL ?>/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                    <a href="<?= SITE_URL ?>/profile.php" class="hover:text-primary transition-colors duration-200 font-semibold">Profile</a>
                    <a href="<?= SITE_URL ?>/my-bookings.php" class="hover:text-primary transition-colors duration-200 font-semibold">My Bookings</a>
                    <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                    <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                <?php elseif ($_SESSION['user']['role'] === 'staff'): ?>
                    <a href="<?= SITE_URL ?>/staff/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                    <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= SITE_URL ?>" class="hover:text-primary transition-colors duration-200 font-semibold">Book a Trip</a>
                <a href="<?= SITE_URL ?>/bookings/manage_booking.php" class="hover:text-primary transition-colors duration-200 font-semibold">Manage Booking</a>
                <a href="<?= SITE_URL ?>/contact.php" class="hover:text-primary transition-colors duration-200 font-semibold">Contact Us</a>
                <a href="<?= SITE_URL ?>/login.php" class="hover:text-primary transition-colors duration-200 font-semibold">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- Spacer to prevent content from being hidden under fixed header -->
<div class="h-16"></div>

<script>
    // Toggle mobile menu
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenu.classList.toggle('hidden');
    });
</script>
