<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Fetch statistics
$total_trips_query = "SELECT COUNT(*) AS total FROM trip_templates WHERE status = 'active'";
$total_trips_result = $conn->query($total_trips_query);
$total_trips = $total_trips_result->fetch_assoc()['total'];

$total_bookings_query = "SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed'";
$total_bookings_result = $conn->query($total_bookings_query);
$total_bookings = $total_bookings_result->fetch_assoc()['total'];

$total_vehicles_query = "SELECT COUNT(*) AS total FROM vehicles";
$total_vehicles_result = $conn->query($total_vehicles_query);
$total_vehicles = $total_vehicles_result->fetch_assoc()['total'];

$total_revenue_query = "SELECT SUM(total_amount) AS total FROM bookings WHERE payment_status = 'paid'";
$total_revenue_result = $conn->query($total_revenue_query);
$total_revenue = $total_revenue_result->fetch_assoc()['total'] ?? 0.00;

// Fetch recent trip instances
$recent_trips_query = "
    SELECT ti.id, ti.trip_date, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ti.booked_seats, ti.status
    FROM trip_instances ti
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE ti.trip_date >= CURDATE() AND ti.status = 'active'
    ORDER BY ti.trip_date ASC, ts.departure_time ASC
    LIMIT 5
";
$recent_trips_result = $conn->query($recent_trips_query);
$recent_trips = [];
while ($row = $recent_trips_result->fetch_assoc()) {
    $recent_trips[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
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
            font-family: 'Inter', sans-serif;
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
        }
        /* Responsive Design */
        @media (min-width: 768px) {
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
        /* Stats Card */
        .stats-card {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-4px);
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
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

        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <div class="card p-6 sm:p-8 lg:p-10">
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-8">
                        <i class="fas fa-tachometer-alt text-primary-red mr-3"></i>Admin Dashboard
                    </h1>

                    <!-- Statistics Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="stats-card">
                            <i class="fas fa-road text-2xl mb-2"></i>
                            <h3 class="text-lg font-semibold">Total Trips</h3>
                            <p class="text-3xl font-bold"><?= $total_trips ?></p>
                        </div>
                        <div class="stats-card">
                            <i class="fas fa-ticket-alt text-2xl mb-2"></i>
                            <h3 class="text-lg font-semibold">Total Bookings</h3>
                            <p class="text-3xl font-bold"><?= $total_bookings ?></p>
                        </div>
                        <div class="stats-card">
                            <i class="fas fa-bus text-2xl mb-2"></i>
                            <h3 class="text-lg font-semibold">Total Vehicles</h3>
                            <p class="text-3xl font-bold"><?= $total_vehicles ?></p>
                        </div>
                        <div class="stats-card">
                            <i class="fas fa-money-bill-wave text-2xl mb-2"></i>
                            <h3 class="text-lg font-semibold">Total Revenue</h3>
                            <p class="text-3xl font-bold">₦<?= number_format($total_revenue, 2) ?></p>
                        </div>
                    </div>

                  <!-- Recent Trips Section -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 sm:mb-0">
            <i class="fas fa-route text-primary-red mr-2"></i>Recent Trips
        </h2>
        <a href="trips.php" class="btn-primary inline-flex items-center">
            <i class="fas fa-eye mr-2"></i>View All Trips
        </a>
    </div>

    <?php if (empty($recent_trips)): ?>
        <div class="text-center py-12">
            <i class="fas fa-route text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-2xl font-bold text-gray-700 mb-2">No Upcoming Trips</h3>
            <p class="text-gray-500 mb-6">Create a new trip template to get started.</p>
            <a href="add_trip.php" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>Create Trip Template
            </a>
        </div>
    <?php else: ?>
        <!-- Mobile Cards -->
        <div class="space-y-4 md:hidden">
            <?php foreach ($recent_trips as $trip): ?>
                <div class="mobile-card-row bg-white shadow-md rounded-xl">
                    <div class="mobile-card-item">
                        <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                        <span><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                    </div>
                    <div class="mobile-card-item">
                        <i class="fas fa-bus text-primary-red mr-2"></i>
                        <span><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number']) ?></span>
                    </div>
                    <div class="mobile-card-item">
                        <i class="fas fa-clock text-primary-red mr-2"></i>
                        <span><?= htmlspecialchars($trip['departure_time']) ?></span>
                    </div>
                    <div class="mobile-card-item">
                        <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                        <span><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                    </div>
                    <div class="mobile-card-item">
                        <i class="fas fa-chair text-primary-red mr-2"></i>
                        <span><?= $trip['booked_seats'] ?> seats booked</span>
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
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Booked Seats</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_trips as $trip): ?>
                        <tr class="table-row border-b border-gray-200">
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                    <span><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-bus text-primary-red mr-2"></i>
                                    <span><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number'] . ' (' . $trip['driver_name'] . ')') ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-clock text-primary-red mr-2"></i>
                                    <span><?= htmlspecialchars($trip['departure_time']) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                    <span><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <i class="fas fa-chair text-primary-red mr-2"></i>
                                    <span><?= $trip['booked_seats'] ?> seats</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

        </main>
    </div>

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
        });
    </script>
</body>
</html>