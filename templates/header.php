<?php
// templates/header.php
// Use absolute paths based on document root
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
</head>
<body>
    <!-- Header -->
    <header class="bg-black text-white fixed top-0 left-0 w-full z-50 shadow-lg">
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
                        <a href="<?= SITE_URL ?>/admin/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                        <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/index.php" class="hover:text-primary transition-colors duration-200 font-semibold">Book a Trip</a>
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
        <div id="mobile-menu" class="md:hidden hidden bg-black">
            <nav class="flex flex-col space-y-4 px-4 py-4">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="hover:text-primary transition-colors duration-200 font-semibold">Dashboard</a>
                    <a href="<?= SITE_URL ?>/logout.php" class="hover:text-primary transition-colors duration-200 font-semibold">Logout</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/index.php" class="hover:text-primary transition-colors duration-200 font-semibold">Book a Trip</a>
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
</body>
</html>