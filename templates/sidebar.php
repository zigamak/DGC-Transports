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
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/images/icon-2.png" sizes="32x32">
<link rel="shortcut icon" href="<?= SITE_URL ?>/assets/images/icon.png" type="image/png">
    <!-- Mobile Header (visible only on mobile) -->
    <header class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white shadow-lg border-b border-gray-200">
        <div class="flex items-center justify-between h-16 px-4">
            <!-- Menu Toggle Button -->
            <button id="sidebar-toggle" class="p-2 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <!-- Logo (centered) -->
            <div class="flex-1 flex justify-center">
                <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                    <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-8" onerror="this.src='https://via.placeholder.com/120x32?text=DGC';">
                </a>
            </div>
            
            <!-- User Avatar/Profile (placeholder for future use) -->
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <i class="fas fa-user text-red-600 text-sm"></i>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white text-red-600 shadow-xl z-40 transform -translate-x-full transition-transform duration-300 ease-in-out md:translate-x-0 md:shadow-2xl">
        <!-- Sidebar Header (desktop only) -->
        <div class="hidden md:flex items-center justify-center py-6 px-6 border-b border-gray-200 bg-gradient-to-r from-red-50 to-white">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-10" onerror="this.src='https://via.placeholder.com/150x40?text=DGC+Transports';">
            </a>
        </div>
        
        <!-- Mobile Sidebar Header -->
        <div class="md:hidden flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-red-50 to-white">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-8" onerror="this.src='https://via.placeholder.com/120x32?text=DGC';">
            </a>
            <button id="sidebar-close" class="p-2 rounded-lg text-red-600 hover:bg-red-100 hover:text-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto">
            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/trips.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-road text-sm"></i>
                    </div>
                    <span>Manage Trips</span>
                </a>
                      
                <a href="<?= SITE_URL ?>/admin/trip_templates.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-road text-sm"></i>
                    </div>
                    <span>Manage Trip Route</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/vehicles.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-bus text-sm"></i>
                    </div>
                    <span>Manage Vehicles</span>
                </a>
                
                
                <a href="<?= SITE_URL ?>/admin/bookings.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                    <span>View Bookings</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/users.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-users-cog text-sm"></i>
                    </div>
                    <span>Manage Users</span>
                </a>
                
            <?php elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'staff'): ?>
                <a href="<?= SITE_URL ?>/staff/dashboard.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?= SITE_URL ?>/staff/pnr.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                    <span>Check PNR</span>
                </a>
                 <a href="<?= SITE_URL ?>/staff/pending_bookings.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                    <span>Pending Bookings</span>
                </a>
                
                <a href="<?= SITE_URL ?>/staff/profile.php" class="group flex items-center px-3 py-3 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-red-50 hover:to-red-100 hover:text-red-700 transition-all duration-200 border border-transparent hover:border-red-200">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <span>Profile</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Logout Button (at bottom) -->
        <div class="p-4 border-t border-gray-200 bg-gray-50">
            <a href="<?= SITE_URL ?>/logout.php" class="group flex items-center w-full px-3 py-3 text-sm font-medium rounded-xl bg-red-600 text-white hover:bg-red-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                <div class="p-2 bg-red-500 rounded-lg mr-3 group-hover:bg-red-600 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </div>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Overlay for mobile sidebar -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden transition-opacity duration-300"></div>

    <style>
        /* Custom scrollbar for sidebar */
        #sidebar nav::-webkit-scrollbar {
            width: 4px;
        }
        
        #sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #sidebar nav::-webkit-scrollbar-thumb {
            background: #fca5a5;
            border-radius: 2px;
        }
        
        #sidebar nav::-webkit-scrollbar-thumb:hover {
            background: #f87171;
        }

        /* Smooth transitions */
        .transition-all {
            transition: all 0.2s ease;
        }

        /* Active state styling */
        .nav-active {
            background: linear-gradient(to right, #fef2f2, #fee2e2) !important;
            color: #dc2626 !important;
            border-color: #fca5a5 !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarClose = document.getElementById('sidebar-close');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            // Function to open sidebar
            function openSidebar() {
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden'); // Prevent body scroll
                }
            }

            // Function to close sidebar
            function closeSidebar() {
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden'); // Restore body scroll
                }
            }

            // Toggle sidebar on mobile menu button click
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSidebar();
                });
            }

            // Close sidebar when close button is clicked
            if (sidebarClose) {
                sidebarClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            // Close sidebar when overlay is clicked
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    closeSidebar();
                });
            }

            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) { // md breakpoint
                    closeSidebar();
                }
            });

            // Add active state to current page
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('#sidebar nav a');
            
            navLinks.forEach(link => {
                const linkPath = new URL(link.href).pathname;
                if (currentPath === linkPath || (currentPath.includes(linkPath) && linkPath !== '/')) {
                    link.classList.add('nav-active');
                }
            });

            // Add smooth hover effects
            navLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('nav-active')) {
                        this.style.transform = 'translateX(4px)';
                    }
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
<?php endif; ?>