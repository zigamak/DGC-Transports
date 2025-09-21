<?php
// templates/sidebar.php
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        #sidebar {
            width: 250px;
            background: linear-gradient(to bottom, #1a202c, #2d3748);
            color: white;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            z-index: 50;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        }

        #sidebar.active {
            transform: translateX(0);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .sidebar-logo img {
            height: 40px;
            margin-right: 10px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background 0.3s;
        }

        .sidebar-nav a:hover {
            background: #9b2c2c;
        }

        .sidebar-nav i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Overlay for mobile sidebar */
        #sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
            display: none;
        }

        /* Header Styles */
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

        /* Mobile Menu */
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

        /* Toggle Buttons */
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

        /* Main Content */
        main {
            flex: 1;
            padding-top: 60px;
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            #sidebar {
                transform: translateX(0);
            }

            main {
                margin-left: 250px;
            }

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

            #sidebar-overlay.active {
                display: block;
            }

            .header-nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-logo">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo">
            </div>
            <nav class="sidebar-nav">
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/trips.php">
                        <i class="fas fa-road"></i>
                        <span>Manage Trips</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/vehicles.php">
                        <i class="fas fa-bus"></i>
                        <span>Manage Vehicles</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/bookings.php">
                        <i class="fas fa-ticket-alt"></i>
                        <span>View Bookings</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/users.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Manage Users</span>
                    </a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        <div id="sidebar-overlay"></div>
    <?php endif; ?>

    <div class="flex-1">
        <header class="<?php if (isLoggedIn()): ?> md-hidden <?php endif; ?>">
            <div class="header-container">
                <?php if (isLoggedIn()): ?>
                    <div class="toggle-btn-container">
                        <button id="sidebar-toggle" class="toggle-btn">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button id="sidebar-cancel" class="toggle-btn" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="header-logo">
                    <a href="<?= SITE_URL ?>">
                        <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo">
                    </a>
                </div>

                <nav class="header-nav <?php if (isLoggedIn()): ?> hidden <?php endif; ?>">
                    <?php if (!isLoggedIn()): ?>
                        <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
                        <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
                        <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
                        <a href="<?= SITE_URL ?>/login.php">Login</a>
                    <?php endif; ?>
                </nav>

                <?php if (!isLoggedIn()): ?>
                    <div>
                        <button id="mobile-menu-toggle" class="toggle-btn">
                            <i id="mobile-menu-icon" class="fas fa-bars"></i>
                        </button>
                        <button id="mobile-menu-cancel" class="toggle-btn" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!isLoggedIn()): ?>
                <div id="mobile-menu" class="mobile-menu">
                    <nav class="mobile-nav">
                        <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
                        <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
                        <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
                        <a href="<?= SITE_URL ?>/login.php">Login</a>
                    </nav>
                </div>
            <?php endif; ?>
        </header>

        <main class="<?php if (isLoggedIn()): ?> ml-0 md:ml-64 <?php endif; ?>">
        </main>
    </div>

    <script>
        // Toggle mobile menu (for non-logged-in users)
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

        // Toggle sidebar (for logged-in users)
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarCancel = document.getElementById('sidebar-cancel');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebarCancel && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                sidebarToggle.style.display = 'none';
                sidebarCancel.style.display = 'block';
            });

            const closeSidebar = () => {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                sidebarToggle.style.display = 'block';
                sidebarCancel.style.display = 'none';
            };

            sidebarCancel.addEventListener('click', closeSidebar);
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
    </script>
</body>
</html>