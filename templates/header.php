<?php
// templates/header.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Define the navigation links based on user role
$nav_links = [];
$is_logged_in = isLoggedIn();
$user_role = $is_logged_in ? $_SESSION['user']['role'] : null;

if ($is_logged_in) {
    if ($user_role === 'investor') {
        $nav_links = [
            ['text' => 'Dashboard', 'url' => SITE_URL . '/investor/dashboard.php'],
            ['text' => 'Expenses', 'url' => SITE_URL . '/investor/expenses.php'],
            ['text' => 'My Vehicles', 'url' => SITE_URL . '/investor/vehicles.php'],
            ['text' => 'Profile', 'url' => SITE_URL . '/profile.php'],
            ['text' => 'Logout', 'url' => SITE_URL . '/logout.php', 'is_logout' => true],
        ];
    } elseif ($user_role === 'customer') {
        $nav_links = [
            ['text' => 'Book Trip', 'url' => SITE_URL],
            ['text' => 'Dashboard', 'url' => SITE_URL . '/dashboard.php'],
            ['text' => 'My Bookings', 'url' => SITE_URL . '/my-bookings.php'],
            ['text' => 'Logout', 'url' => SITE_URL . '/logout.php', 'is_logout' => true],
        ];
    } elseif ($user_role === 'admin') {
        $nav_links = [
            ['text' => 'Admin Dashboard', 'url' => SITE_URL . '/admin/dashboard.php'],
            ['text' => 'Investors', 'url' => SITE_URL . '/admin/investors.php'],
            ['text' => 'Logout', 'url' => SITE_URL . '/logout.php', 'is_logout' => true],
        ];
    }
} else {
    $nav_links = [
        ['text' => 'Book a Trip', 'url' => SITE_URL],
        ['text' => 'Login', 'url' => SITE_URL . '/login.php'],
    ];
}
?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    // Ensure primary-red and dark-red are accessible via standard Tailwind classes too
                    primary: '#dc2626',
                    secondary: '#991b1b',
                    'primary-red': '#e30613',
                    'dark-red': '#c70410',
                }
            }
        }
    }
</script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/icon-2.png" sizes="32x32">

<style>
    /* Custom style for transition effect on mobile menu */
    .mobile-menu-transition {
        transition: max-height 0.3s ease-in-out;
        overflow: hidden;
    }
</style>

<header class="bg-white text-gray-900 fixed top-0 left-0 w-full z-50 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex-shrink-0">
                <a href="<?= SITE_URL ?>" aria-label="Home">
                    <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="Logo" class="h-10 w-auto">
                </a>
            </div>

            <nav class="hidden md:flex space-x-8" aria-label="Primary Navigation">
                <?php foreach ($nav_links as $link): ?>
                    <?php 
                        $text_color_class = isset($link['is_logout']) ? 'text-red-600' : 'text-gray-900';
                        $hover_color_class = 'hover:text-primary-red';
                    ?>
                    <a href="<?= $link['url'] ?>" class="font-semibold transition <?= $text_color_class ?> <?= $hover_color_class ?>">
                        <?= htmlspecialchars($link['text']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="md:hidden">
                <button id="mobile-menu-button" 
                        class="text-gray-900 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-red" 
                        aria-controls="mobile-menu" 
                        aria-expanded="false">
                    <i class="fas fa-bars text-2xl" id="menu-icon"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-menu" 
         class="mobile-menu-transition max-h-0 md:hidden bg-white border-t border-gray-200" 
         role="menu" 
         aria-labelledby="mobile-menu-button">
        <nav class="px-4 py-4 space-y-3">
            <?php foreach ($nav_links as $link): ?>
                <?php 
                    $text_color_class = isset($link['is_logout']) ? 'text-red-600' : 'text-gray-900';
                    $hover_color_class = 'hover:text-primary-red';
                ?>
                <a href="<?= $link['url'] ?>" 
                   class="block py-2 text-lg font-medium border-b border-gray-100 last:border-b-0 <?= $text_color_class ?> <?= $hover_color_class ?>" 
                   role="menuitem">
                    <?= htmlspecialchars($link['text']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<div class="h-16"></div> <script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.getElementById('menu-icon');

        menuButton.addEventListener('click', () => {
            const isExpanded = menuButton.getAttribute('aria-expanded') === 'true' || false;

            // Toggle accessibility attributes
            menuButton.setAttribute('aria-expanded', !isExpanded);

            if (isExpanded) {
                // Close menu
                mobileMenu.style.maxHeight = '0';
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            } else {
                // Open menu - Set max-height to scrollHeight to enable transition
                mobileMenu.style.maxHeight = mobileMenu.scrollHeight + 'px';
                menuIcon.classList.remove('fa-bars');
                menuIcon.classList.add('fa-times');
            }
        });

        // Optional: Reset max-height on window resize (for proper responsiveness)
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                mobileMenu.style.maxHeight = '0';
                menuButton.setAttribute('aria-expanded', 'false');
                menuIcon.classList.remove('fa-times');
                menuIcon.classList.add('fa-bars');
            }
        });
    });
</script>