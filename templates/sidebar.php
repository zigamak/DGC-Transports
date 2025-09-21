<?php
// Use absolute paths based on document root
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/dgctransports/includes/auth.php';
?>

<?php if (isLoggedIn()): ?>
    <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-black text-white p-6 shadow-xl z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="flex items-center justify-between mb-8">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo" class="h-10">
            </a>
            <button id="sidebar-close" class="md:hidden text-white hover:text-red-600 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="space-y-4">
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-tachometer-alt text-lg mr-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/trips.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-road text-lg mr-4"></i>
                    <span>Manage Trips</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/vehicles.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-bus text-lg mr-4"></i>
                    <span>Manage Vehicles</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/bookings.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-ticket-alt text-lg mr-4"></i>
                    <span>View Bookings</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/users.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-users-cog text-lg mr-4"></i>
                    <span>Manage Users</span>
                </a>
            <?php elseif ($_SESSION['user']['role'] === 'staff'): ?>
                <a href="<?= SITE_URL ?>/staff/dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-tachometer-alt text-lg mr-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= SITE_URL ?>/staff/pnr.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-ticket-alt text-lg mr-4"></i>
                    <span>Check PNR</span>
                </a>
                <a href="<?= SITE_URL ?>/staff/profile.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-user text-lg mr-4"></i>
                    <span>Profile</span>
                </a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/logout.php" class="flex items-center p-3 rounded-lg hover:bg-red-600 transition-colors duration-200">
                <i class="fas fa-sign-out-alt text-lg mr-4"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black opacity-50 z-40 hidden md:hidden"></div>
<?php endif; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root {
        --primary-red: #e30613;
        --dark-red: #c70410;
    }
    body {
        font-family: 'Poppins', sans-serif;
    }
    .text-red-600 { color: #e30613; }
    .bg-red-600 { background-color: #e30613; }
    .hover\:bg-red-600:hover { background-color: #e30613; }
    .hover\:text-red-600:hover { color: #e30613; }
    .bg-red-700 { background-color: #c70410; }
    .hover\:bg-red-700:hover { background-color: #c70410; }
</style>