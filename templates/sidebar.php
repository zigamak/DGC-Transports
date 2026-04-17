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
    // Fallback definition for SITE_URL
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'https://booking.dgctransports.com'); 
    }
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
    
    <header class="md:hidden fixed top-0 left-0 right-0 z-50 bg-white shadow-lg border-b border-gray-200">
        <div class="flex items-center justify-between h-16 px-4">
            <button id="sidebar-toggle" class="p-2 rounded-lg text-[#990b0a] hover:bg-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#990b0a] focus:ring-opacity-50">
                <i class="fas fa-bars text-xl"></i>
            </button>
            
            <div class="flex-1 flex justify-center">
                <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                    <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-8" onerror="this.src='https://via.placeholder.com/120x32?text=DGC';">
                </a>
            </div>
            
            <div class="w-10 h-10 rounded-full bg-[#990b0a]/10 flex items-center justify-center">
                <i class="fas fa-user text-[#990b0a] text-sm"></i>
            </div>
        </div>
    </header>

    <aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white text-[#990b0a] shadow-xl z-40 transform -translate-x-full transition-transform duration-300 ease-in-out md:translate-x-0 md:shadow-2xl flex flex-col overflow-hidden">
        
        <div class="hidden md:flex items-center justify-center py-6 px-6 border-b border-gray-200 bg-gradient-to-r from-[#990b0a]/5 to-white flex-shrink-0">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-10" onerror="this.src='https://via.placeholder.com/150x40?text=DGC+Transports';">
            </a>
        </div>
        
        <div class="md:hidden flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-[#990b0a]/5 to-white flex-shrink-0">
            <a href="<?= SITE_URL ?>/index.php" class="flex items-center">
                <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="DGC Transports Logo" class="h-8" onerror="this.src='https://via.placeholder.com/120x32?text=DGC';">
            </a>
            <button id="sidebar-close" class="p-2 rounded-lg text-[#990b0a] hover:bg-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#990b0a] focus:ring-opacity-50">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <nav class="flex-1 py-4 px-4 space-y-1 overflow-y-auto">
            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/trips.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-road text-sm"></i>
                    </div>
                    <span>Manage Trips</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/trip_templates.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-route text-sm"></i>
                    </div>
                    <span>Manage Trip Route</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/city.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-route text-sm"></i>
                    </div>
                    <span>Manage Cities</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/vehicles.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-bus text-sm"></i>
                    </div>
                    <span>Manage Vehicles</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/review.php" 
   class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl 
          hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] 
          transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
       
    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
        <i class="fas fa-star text-sm"></i>
    </div>

    <span>Manage Reviews</span>
</a>

                
                <a href="<?= SITE_URL ?>/admin/manual_bookings.php" 
                   class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl 
                        hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] 
                        transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-pen-to-square text-sm"></i>
                    </div>
                    <span>Manual Bookings</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/bookings.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                    <span>View Bookings</span>
                </a>

                <a href="<?= SITE_URL ?>/admin/analytics.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-chart-bar text-sm"></i>
                    </div>
                    <span>Analytics</span>
                </a>
                
                <a href="<?= SITE_URL ?>/admin/users.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-users-cog text-sm"></i>
                    </div>
                    <span>Manage Users</span>
                </a>
                          <a href="<?= SITE_URL ?>/admin/investors.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-users-cog text-sm"></i>
                    </div>
                    <span>Investors</span>
                </a>
                
            <?php elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'staff'): ?>
                <a href="<?= SITE_URL ?>/staff/dashboard.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?= SITE_URL ?>/staff/pnr.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-ticket-alt text-sm"></i>
                    </div>
                    <span>Check PNR</span>
                </a>
                
                <a href="<?= SITE_URL ?>/staff/bookings.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-receipt text-sm"></i>
                    </div>
                    <span>Bookings</span>
                </a>
                
                <a href="<?= SITE_URL ?>/staff/profile.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <span>Profile</span>
                </a>
            
            <?php elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'investor'): ?>
                <a href="<?= SITE_URL ?>/investor/dashboard.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt text-sm"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="<?= SITE_URL ?>/investor/expenses.php" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl hover:bg-gradient-to-r hover:from-[#990b0a]/5 hover:to-[#990b0a]/10 hover:text-[#7a0908] transition-all duration-200 border border-transparent hover:border-[#990b0a]/20">
                    <div class="p-2 bg-[#990b0a]/10 rounded-lg mr-3 group-hover:bg-[#990b0a]/20 transition-colors duration-200">
                        <i class="fas fa-file-invoice-dollar text-sm"></i>
                    </div>
                    <span>Expenses</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="p-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
            <a href="<?= SITE_URL ?>/logout.php" class="group flex items-center w-full px-3 py-3 text-sm font-medium rounded-xl bg-[#990b0a] text-white hover:bg-[#7a0908] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#990b0a] focus:ring-opacity-50">
                <div class="p-2 bg-[#7a0908] rounded-lg mr-3 group-hover:bg-[#5a0605] transition-colors duration-200">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </div>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden transition-opacity duration-300"></div>

    <style>
        /* Custom scrollbar for sidebar - scoped to sidebar only */
        #sidebar nav::-webkit-scrollbar {
            width: 4px;
        }
        
        #sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #sidebar nav::-webkit-scrollbar-thumb {
            background: rgba(153, 11, 10, 0.3);
            border-radius: 2px;
        }
        
        #sidebar nav::-webkit-scrollbar-thumb:hover {
            background: rgba(153, 11, 10, 0.5);
        }

        /* Active state styling - scoped to sidebar nav links only */
        #sidebar nav a.nav-active {
            background: linear-gradient(to right, rgba(153, 11, 10, 0.05), rgba(153, 11, 10, 0.1));
            color: #990b0a;
            border-color: rgba(153, 11, 10, 0.2);
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
                // Ensure the path comparison is robust for index.php or directory index
                const linkPath = new URL(link.href).pathname.replace(/\/$/, '/index.php');
                const cleanCurrentPath = currentPath.replace(/\/$/, '/index.php');
                
                if (cleanCurrentPath === linkPath || (cleanCurrentPath.startsWith(linkPath.substring(0, linkPath.lastIndexOf('/')) + '/') && linkPath.lastIndexOf('/') > 0)) {
                    link.classList.add('nav-active');
                }
            });

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