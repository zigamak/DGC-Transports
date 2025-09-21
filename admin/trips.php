<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $template_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE trip_templates SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $success = 'Trip template deleted successfully.';
    } else {
        $error = 'Failed to delete trip template.';
    }
    $stmt->close();
}

// Fetch all trip templates with booking count
$query = "
    SELECT tt.id, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time, 
           tt.price, tt.recurrence_type, tt.recurrence_days, tt.start_date, tt.end_date, tt.status,
           vt.capacity,
           COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN 1 ELSE 0 END), 0) as total_bookings
    FROM trip_templates tt
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN bookings b ON tt.id = b.template_id
    WHERE tt.status = 'active'
    GROUP BY tt.id, c1.name, c2.name, vt.type, v.vehicle_number, v.driver_name, 
             ts.departure_time, ts.arrival_time, tt.price, tt.recurrence_type, 
             tt.recurrence_days, tt.start_date, tt.end_date, tt.status, vt.capacity
    ORDER BY tt.start_date, ts.departure_time
";
$result = $conn->query($query);
$trip_templates = [];
while ($row = $result->fetch_assoc()) {
    $trip_templates[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Templates - DGC Transports</title>
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
            background: linear-gradient(135deg, var(--white), var(--gray));
            transition: margin-left 0.3s ease-in-out;
        }
        /* Responsive Design */
        @media (min-width: 768px) {
            main {
                margin-left: 256px; /* Matches w-64 (64 * 4px = 256px) */
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
            .header-nav {
                display: none;
            }
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
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
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        /* Mobile-first card layout for table rows */
        .mobile-card-row {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.75rem;
        }
        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .mobile-card-item:last-child {
            margin-bottom: 0;
        }
        .mobile-card-label {
            font-weight: 600;
            color: #4b5563;
            width: 80px;
            min-width: 80px;
        }
        .mobile-card-content {
            flex-grow: 1;
        }
        /* Desktop table layout */
        @media (min-width: 768px) {
            .mobile-card-row {
                display: none;
            }
            .table-container {
                display: block;
            }
            .table-header {
                background: var(--gray);
                color: var(--black);
                font-weight: 600;
            }
            .table-row:hover {
                background: #f9fafb;
            }
        }
        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
        }
        .text-red-600 { color: #e30613; }
        .bg-red-600 { background-color: #e30613; }
        .hover\:bg-red-600:hover { background-color: #e30613; }
        .hover\:text-red-600:hover { color: #e30613; }
        .bg-red-700 { background-color: #c70410; }
        .hover\:bg-red-700:hover { background-color: #c70410; }
        
        /* Dropdown menu styling */
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 50;
            min-width: 160px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease-in-out;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.15s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        
        .dropdown-item:hover {
            background-color: #f3f4f6;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
        }
        
        .dropdown-toggle {
            position: relative;
        }
        
        .booking-badge {
            background: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1">
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

        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <div class="card p-6 sm:p-8 lg:p-10">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4 sm:mb-0">
                            <i class="fas fa-route text-primary-red mr-3"></i>Trip Templates
                        </h1>
                        <a href="add_trip.php" class="btn-primary inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i>Add New Template
                        </a>
                    </div>
                    <?php if (isset($success)): ?>
                        <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <?php if (empty($trip_templates)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-route text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">No Trip Templates Found</h3>
                            <p class="text-gray-500 mb-6">Create a new trip template to get started.</p>
                            <a href="add_trip.php" class="btn-primary">
                                <i class="fas fa-plus mr-2"></i>Create Trip Template
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($trip_templates as $template): ?>
                                <div class="mobile-card-row bg-white shadow-md rounded-xl relative">
                                    <div class="absolute top-4 right-4">
                                        <div class="dropdown-toggle">
                                            <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="toggleDropdown('mobile-<?= $template['id'] ?>')">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div id="mobile-<?= $template['id'] ?>" class="dropdown-menu">
                                                <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>View Bookings
                                                </a>
                                                <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                                    <i class="fas fa-edit"></i>Edit
                                                </a>
                                                <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item">
                                                    <i class="fas fa-trash"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                        <span><?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?></span>
                                        <span class="booking-badge"><?= $template['total_bookings'] ?> bookings</span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-bus text-primary-red mr-2"></i>
                                        <span><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-clock text-primary-red mr-2"></i>
                                        <span><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                        <span>₦<?= number_format($template['price'], 2) ?></span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-redo text-primary-red mr-2"></i>
                                        <span>
                                            <?php
                                            if ($template['recurrence_type'] === 'day') {
                                                echo 'One Day';
                                            } elseif ($template['recurrence_type'] === 'week') {
                                                echo 'Weekly (' . htmlspecialchars($template['recurrence_days'] ?: 'None') . ')';
                                            } elseif ($template['recurrence_type'] === 'month') {
                                                echo 'Monthly (Day ' . date('j', strtotime($template['start_date'])) . ')';
                                            } else {
                                                echo 'Yearly (' . date('M j', strtotime($template['start_date'])) . ')';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                        <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
                        <div class="overflow-x-auto hidden md:block">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="table-header rounded-lg">
                                        <th class="px-4 py-3 text-left">Route</th>
                                        <th class="px-4 py-3 text-left">Vehicle</th>
                                        <th class="px-4 py-3 text-left">Time</th>
                                        <th class="px-4 py-3 text-left">Price</th>
                                        <th class="px-4 py-3 text-left">Schedule</th>
                                        <th class="px-4 py-3 text-left">Period</th>
                                        <th class="px-4 py-3 text-left">Bookings</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trip_templates as $template): ?>
                                        <tr class="table-row border-b border-gray-200">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-bus text-primary-red mr-2"></i>
                                                    <div>
                                                        <div><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($template['driver_name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-clock text-primary-red mr-2"></i>
                                                    <span><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                                    <span>₦<?= number_format($template['price'], 2) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-redo text-primary-red mr-2"></i>
                                                    <span>
                                                        <?php
                                                        if ($template['recurrence_type'] === 'day') {
                                                            echo 'One Day';
                                                        } elseif ($template['recurrence_type'] === 'week') {
                                                            echo 'Weekly (' . htmlspecialchars($template['recurrence_days'] ?: 'None') . ')';
                                                        } elseif ($template['recurrence_type'] === 'month') {
                                                            echo 'Monthly (Day ' . date('j', strtotime($template['start_date'])) . ')';
                                                        } else {
                                                            echo 'Yearly (' . date('M j', strtotime($template['start_date'])) . ')';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                                    <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center">
                                                    <i class="fas fa-users text-primary-red mr-2"></i>
                                                    <span><?= $template['total_bookings'] ?> / <?= $template['capacity'] ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="dropdown-toggle">
                                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="toggleDropdown('desktop-<?= $template['id'] ?>')">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div id="desktop-<?= $template['id'] ?>" class="dropdown-menu">
                                                        <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-eye"></i>View Bookings
                                                        </a>
                                                        <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-edit"></i>Edit
                                                        </a>
                                                        <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item">
                                                            <i class="fas fa-trash"></i>Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
        <div class="modal-content bg-white rounded-lg p-6 max-w-sm mx-auto shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-center">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="btn-primary bg-red-600 hover:bg-red-700">Delete</a>
            </div>
        </div>
    </div>

    <script>
        // Global variable to track currently open dropdown
        let currentOpenDropdown = null;

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            
            // Close any currently open dropdown
            if (currentOpenDropdown && currentOpenDropdown !== dropdown) {
                currentOpenDropdown.classList.remove('show');
            }
            
            // Toggle the clicked dropdown
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                currentOpenDropdown = null;
            } else {
                dropdown.classList.add('show');
                currentOpenDropdown = dropdown;
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown-toggle')) {
                if (currentOpenDropdown) {
                    currentOpenDropdown.classList.remove('show');
                    currentOpenDropdown = null;
                }
            }
        });

        // Delete modal functionality
        function showDeleteModal(id, name) {
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDeleteLink = document.getElementById('confirmDeleteLink');
            
            deleteMessage.textContent = `Are you sure you want to delete the trip template for ${name}?`;
            confirmDeleteLink.href = `trips.php?delete=${id}`;
            deleteModal.classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const deleteModal = document.getElementById('deleteModal');
            const cancelButton = document.getElementById('cancelButton');

            if (cancelButton) {
                cancelButton.addEventListener('click', () => {
                    deleteModal.classList.add('hidden');
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                }
            });

            // Sidebar functionality
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarToggleMobile = document.getElementById('sidebar-toggle-mobile');
            const sidebarClose = document.getElementById('sidebar-close');

            if (sidebarToggleMobile && sidebarClose && sidebar && sidebarOverlay) {
                sidebarToggleMobile.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                });

                const closeSidebar = () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                };

                sidebarClose.addEventListener('click', closeSidebar);
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Mobile menu functionality
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