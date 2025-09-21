<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce staff-only access
requireRole('staff', '/login.php');

// Handle PNR search
$pnr_error = '';
$pnr_success = '';
$searched_bookings = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_pnr'])) {
    $pnr = trim($_POST['pnr'] ?? '');
    if (empty($pnr)) {
        $pnr_error = 'Please enter a PNR.';
    } else {
        $stmt = $conn->prepare("
            SELECT b.id, b.pnr, b.passenger_name, b.email, b.phone, b.status,
                   c1.name AS pickup_city, c2.name AS dropoff_city, ts.departure_time, b.trip_date
            FROM bookings b
            JOIN trip_templates tt ON b.template_id = tt.id
            JOIN cities c1 ON tt.pickup_city_id = c1.id
            JOIN cities c2 ON tt.dropoff_city_id = c2.id
            JOIN time_slots ts ON tt.time_slot_id = ts.id
            WHERE b.pnr = ? AND b.trip_date = CURDATE()
        ");
        $stmt->bind_param("s", $pnr);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searched_bookings[] = $row;
        }
        if (empty($searched_bookings)) {
            $pnr_error = 'No bookings found for this PNR on today\'s trips.';
        }
        $stmt->close();
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = trim($_POST['new_status'] ?? '');
    $valid_statuses = ['has boarded', 'arrived'];
    if (!in_array($new_status, $valid_statuses)) {
        $pnr_error = 'Invalid status selected.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $booking_id);
        if ($stmt->execute()) {
            $pnr_success = 'Booking status updated to ' . htmlspecialchars(ucfirst($new_status)) . '.';
        } else {
            $pnr_error = 'Failed to update booking status.';
        }
        $stmt->close();
    }
}

// Fetch trips for today
$today = date('Y-m-d');
$trips_query = "
    SELECT ti.id, ti.trip_date, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type,
           v.vehicle_number, v.driver_name, ts.departure_time, ti.booked_seats, ti.status, tt.id AS template_id
    FROM trip_instances ti
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE ti.trip_date = ? AND ti.status = 'active'
    ORDER BY ts.departure_time ASC
";
$stmt = $conn->prepare($trips_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$trips_result = $stmt->get_result();
$trips = [];
while ($row = $trips_result->fetch_assoc()) {
    // Fetch bookings for each trip
    $bookings_query = "
        SELECT b.id, b.pnr, b.passenger_name, b.email, b.phone, b.status
        FROM bookings b
        WHERE b.template_id = ? AND b.trip_date = ?
    ";
    $bookings_stmt = $conn->prepare($bookings_query);
    $bookings_stmt->bind_param("is", $row['template_id'], $today);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    $row['bookings'] = [];
    while ($booking = $bookings_result->fetch_assoc()) {
        $row['bookings'][] = $booking;
    }
    $bookings_stmt->close();
    $trips[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - DGC Transports</title>
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
        main {
            flex: 1;
            padding-top: 60px;
            background: linear-gradient(135deg, var(--white), var(--gray));
        }
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231a1a1a'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5rem;
        }
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
            width: 100px;
            min-width: 100px;
        }
        .mobile-card-content {
            flex-grow: 1;
        }
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1">
        <header class="md-hidden">
            <div class="header-container">
                <div class="toggle-btn-container">
                    <button id="sidebar-toggle" class="toggle-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button id="sidebar-cancel" class="toggle-btn" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="header-logo">
                    <a href="<?= SITE_URL ?>">
                        <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Logo">
                    </a>
                </div>
                <nav class="header-nav hidden">
                    <a href="<?= SITE_URL ?>/index.php">Book a Trip</a>
                    <a href="<?= SITE_URL ?>/bookings/manage_booking.php">Manage Booking</a>
                    <a href="<?= SITE_URL ?>/contact.php">Contact Us</a>
                    <a href="<?= SITE_URL ?>/login.php">Login</a>
                </nav>
            </div>
        </header>

        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <div class="card p-6 sm:p-8 lg:p-10">
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-8">
                        <i class="fas fa-tachometer-alt text-primary-red mr-3"></i>Staff Dashboard
                    </h1>

                    <!-- PNR Search Section -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-ticket-alt text-primary-red mr-2"></i>Search by PNR
                        </h2>
                        <form method="POST" class="form-group mb-4">
                            <label for="pnr">Enter PNR</label>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <input type="text" id="pnr" name="pnr" placeholder="Enter PNR" class="flex-1" required>
                                <button type="submit" name="search_pnr" class="btn-primary">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                            </div>
                        </form>
                        <?php if ($pnr_error): ?>
                            <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($pnr_error) ?></p>
                        <?php endif; ?>
                        <?php if ($pnr_success): ?>
                            <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($pnr_success) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($searched_bookings)): ?>
                            <div class="card p-4 mb-4">
                                <h3 class="text-lg font-semibold mb-2">Search Results</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full table-auto border-collapse">
                                        <thead>
                                            <tr class="table-header rounded-lg">
                                                <th class="px-4 py-3 text-left">Passenger Name</th>
                                                <th class="px-4 py-3 text-left">PNR</th>
                                                <th class="px-4 py-3 text-left">Route</th>
                                                <th class="px-4 py-3 text-left">Departure</th>
                                                <th class="px-4 py-3 text-left">Status</th>
                                                <th class="px-4 py-3 text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($searched_bookings as $booking): ?>
                                                <tr class="table-row border-b border-gray-200">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-user text-primary-red mr-2"></i>
                                                            <span><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                                                            <span><?= htmlspecialchars($booking['pnr']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                                            <span><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-clock text-primary-red mr-2"></i>
                                                            <span><?= htmlspecialchars($booking['departure_time']) ?> on <?= formatDate($booking['trip_date']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-check-circle text-primary-red mr-2"></i>
                                                            <span><?= htmlspecialchars(ucfirst($booking['status'])) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                            <form method="POST" class="inline-flex">
                                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                <select name="new_status" class="mr-2 p-1 border rounded">
                                                                    <option value="has boarded">Has Boarded</option>
                                                                    <option value="arrived">Arrived</option>
                                                                </select>
                                                                <button type="submit" name="update_status" class="btn-primary text-sm py-1 px-2">
                                                                    <i class="fas fa-save mr-1"></i>Update
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-gray-500">No action</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Today's Trips Section -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-route text-primary-red mr-2"></i>Today's Trips (<?= htmlspecialchars(formatDate($today)) ?>)
                        </h2>
                        <?php if (empty($trips)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-route text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-2xl font-bold text-gray-700 mb-2">No Trips Today</h3>
                                <p class="text-gray-500">No active trips scheduled for today.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trips as $trip): ?>
                                <!-- Mobile Cards -->
                                <div class="space-y-4 md:hidden">
                                    <div class="mobile-card-row bg-white shadow-md rounded-xl">
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-map-marker-alt text-primary-red mr-2"></i>Route:</span>
                                            <span class="mobile-card-content"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-bus text-primary-red mr-2"></i>Vehicle:</span>
                                            <span class="mobile-card-content"><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number'] . ' (' . $trip['driver_name'] . ')') ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-clock text-primary-red mr-2"></i>Time:</span>
                                            <span class="mobile-card-content"><?= htmlspecialchars($trip['departure_time']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-calendar-alt text-primary-red mr-2"></i>Date:</span>
                                            <span class="mobile-card-content"><?= formatDate($trip['trip_date']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-chair text-primary-red mr-2"></i>Seats:</span>
                                            <span class="mobile-card-content"><?= $trip['booked_seats'] ?> booked</span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-users text-primary-red mr-2"></i>Passengers:</span>
                                            <span class="mobile-card-content">
                                                <?php if (empty($trip['bookings'])): ?>
                                                    No bookings
                                                <?php else: ?>
                                                    <ul class="list-disc pl-4">
                                                        <?php foreach ($trip['bookings'] as $booking): ?>
                                                            <li>
                                                                <?= htmlspecialchars($booking['passenger_name']) ?> (<?= htmlspecialchars(ucfirst($booking['status'])) ?>)
                                                                <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                                    <form method="POST" class="mt-1">
                                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                        <select name="new_status" class="mr-2 p-1 border rounded">
                                                                            <option value="has boarded">Has Boarded</option>
                                                                            <option value="arrived">Arrived</option>
                                                                        </select>
                                                                        <button type="submit" name="update_status" class="btn-primary text-sm py-1 px-2">
                                                                            <i class="fas fa-save mr-1"></i>Update
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Desktop Table -->
                                <div class="overflow-x-auto hidden md:block mb-4">
                                    <h3 class="text-lg font-semibold mb-2">
                                        <?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?> at <?= htmlspecialchars($trip['departure_time']) ?>
                                    </h3>
                                    <table class="w-full table-auto border-collapse">
                                        <thead>
                                            <tr class="table-header rounded-lg">
                                                <th class="px-4 py-3 text-left">Passenger Name</th>
                                                <th class="px-4 py-3 text-left">PNR</th>
                                                <th class="px-4 py-3 text-left">Email</th>
                                                <th class="px-4 py-3 text-left">Phone</th>
                                                <th class="px-4 py-3 text-left">Status</th>
                                                <th class="px-4 py-3 text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($trip['bookings'])): ?>
                                                <tr>
                                                    <td colspan="6" class="px-4 py-3 text-center text-gray-500">No bookings for this trip</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($trip['bookings'] as $booking): ?>
                                                    <tr class="table-row border-b border-gray-200">
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-user text-primary-red mr-2"></i>
                                                                <span><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                                                                <span><?= htmlspecialchars($booking['pnr']) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-envelope text-primary-red mr-2"></i>
                                                                <span><?= htmlspecialchars($booking['email']) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-phone text-primary-red mr-2"></i>
                                                                <span><?= htmlspecialchars($booking['phone']) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check-circle text-primary-red mr-2"></i>
                                                                <span><?= htmlspecialchars(ucfirst($booking['status'])) ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                                <form method="POST" class="inline-flex">
                                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                    <select name="new_status" class="mr-2 p-1 border rounded">
                                                                        <option value="has boarded">Has Boarded</option>
                                                                        <option value="arrived">Arrived</option>
                                                                    </select>
                                                                    <button type="submit" name="update_status" class="btn-primary text-sm py-1 px-2">
                                                                        <i class="fas fa-save mr-1"></i>Update
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="text-gray-500">No action</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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