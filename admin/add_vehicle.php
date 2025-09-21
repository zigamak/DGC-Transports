<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Fetch vehicle types for dropdown
$vehicle_types_query = "SELECT id, type FROM vehicle_types ORDER BY type";
$vehicle_types_result = $conn->query($vehicle_types_query);
$vehicle_types = [];
while ($row = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $row;
}

// Handle form submission
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $driver_name = trim($_POST['driver_name'] ?? '');
    $vehicle_type_id = (int)($_POST['vehicle_type_id'] ?? 0);

    // Validate inputs
    if (empty($vehicle_number)) {
        $errors[] = 'Vehicle number is required.';
    } else {
        // Check if vehicle_number is unique
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number = ?");
        $stmt->bind_param("s", $vehicle_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Vehicle number already exists.';
        }
        $stmt->close();
    }
    if (empty($driver_name)) {
        $errors[] = 'Driver name is required.';
    }
    if ($vehicle_type_id <= 0) {
        $errors[] = 'Please select a valid vehicle type.';
    } else {
        // Verify vehicle_type_id exists
        $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE id = ?");
        $stmt->bind_param("i", $vehicle_type_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $errors[] = 'Invalid vehicle type selected.';
        }
        $stmt->close();
    }

    // Insert vehicle if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_number, driver_name, vehicle_type_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $vehicle_number, $driver_name, $vehicle_type_id);
        if ($stmt->execute()) {
            $success = 'Vehicle added successfully.';
            // Redirect to vehicles.php after 2 seconds
            header("Refresh: 2; url=vehicles.php");
        } else {
            $errors[] = 'Failed to add vehicle. Please try again.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle - DGC Transports</title>
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
            font-family: 'Poppins', sans-serif;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231a1a1a'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5rem;
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
                            <i class="fas fa-bus text-primary-red mr-3"></i>Add New Vehicle
                        </h1>
                        <a href="vehicles.php" class="btn-primary inline-flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Vehicles
                        </a>
                    </div>
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?> Redirecting...</p>
                    <?php endif; ?>
                    <form method="POST" class="space-y-6">
                        <div class="form-group">
                            <label for="vehicle_number">Vehicle Number</label>
                            <input type="text" id="vehicle_number" name="vehicle_number" value="<?= isset($vehicle_number) ? htmlspecialchars($vehicle_number) : '' ?>" placeholder="Enter vehicle number (e.g., ABC123)" required>
                        </div>
                        <div class="form-group">
                            <label for="driver_name">Driver Name</label>
                            <input type="text" id="driver_name" name="driver_name" value="<?= isset($driver_name) ? htmlspecialchars($driver_name) : '' ?>" placeholder="Enter driver name" required>
                        </div>
                        <div class="form-group">
                            <label for="vehicle_type_id">Vehicle Type</label>
                            <select id="vehicle_type_id" name="vehicle_type_id" required>
                                <option value="">Select a vehicle type</option>
                                <?php foreach ($vehicle_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= isset($vehicle_type_id) && $vehicle_type_id == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-4">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Add Vehicle
                            </button>
                            <a href="vehicles.php" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
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
        });
    </script>
</body>
</html>