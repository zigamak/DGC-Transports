<?php
// templates/sidebar.php
// Include required files with error handling
try {
    require_once __DIR__ . '/../includes/db.php';
} catch (Exception $e) {
    error_log("Failed to include db.php: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/../includes/config.php';
} catch (Exception $e) {
    error_log("Failed to include config.php: " . $e->getMessage());
    define('SITE_URL', 'https://booking.dgctransports.com'); // Fallback
}

try {
    require_once __DIR__ . '/../includes/auth.php';
} catch (Exception $e) {
    error_log("Failed to include auth.php: " . $e->getMessage());
    if (!function_exists('isLoggedIn')) {
        function isLoggedIn() {
            return false; // Default to not logged in
        }
    }
}

// Only show sidebar if user is logged in
if (function_exists('isLoggedIn') && isLoggedIn()): ?>
    <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-black text-white p-6 shadow-xl z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="flex items-center justify-between mb-8">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="DGC Transports Logo" class="h-10" onerror="this.src='https://via.placeholder.com/150x40?text=Logo';">
            </a>
            <button id="sidebar-close" class="md:hidden text-white hover:text-red-600 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="space-y-4">
            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
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
            <?php elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'staff'): ?>
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

    <script>
        // Sidebar toggle for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarClose = document.getElementById('sidebar-close');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (sidebarClose && sidebar && sidebarOverlay) {
                sidebarClose.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });

                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }
        });
    </script>
<?php endif; ?>