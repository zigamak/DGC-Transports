<?php
// admin/dashboard.php
session_start();
require_once '../includes/db.php'; // This provides $conn
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Get the logged-in user's details from the session
$user = $_SESSION['user'];

// Fetch statistics from the database using MySQLi
$total_trips_result = $conn->query("SELECT COUNT(*) FROM trips");
$total_trips = $total_trips_result ? $total_trips_result->fetch_row()[0] : 0;

$total_vehicles_result = $conn->query("SELECT COUNT(*) FROM vehicles");
$total_vehicles = $total_vehicles_result ? $total_vehicles_result->fetch_row()[0] : 0;

$total_bookings_result = $conn->query("SELECT COUNT(*) FROM bookings");
$total_bookings = $total_bookings_result ? $total_bookings_result->fetch_row()[0] : 0;

$total_users_result = $conn->query("SELECT COUNT(*) FROM users");
$total_users = $total_users_result ? $total_users_result->fetch_row()[0] : 0;

require_once '../templates/sidebar.php';
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
        .gradient-bg {
            background: linear-gradient(135deg, #ffffff, #f5f5f5);
        }
        .red-accent {
            color: #e30613;
        }
        .red-bg {
            background-color: #e30613;
        }
        .red-hover:hover {
            background-color: #c70410;
        }
        .black-text {
            color: #000000;
        }
        .white-bg {
            background-color: #ffffff;
        }
        .black-border {
            border-color: #000000;
        }
    </style>
</head>
<body class="bg-white white-bg">

    <div class="container mx-auto mt-20 p-8 bg-white rounded-lg shadow-xl">
        <p class="text-xl black-text mb-8">
            Welcome, <span class="font-semibold red-accent"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
        </p>

        

        <h2 class="text-2xl font-semibold black-text mb-6">System Overview</h2>

        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="gradient-bg p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-4">
                    <i class="fas fa-road text-3xl red-accent mr-4"></i>
                    <h3 class="text-lg font-semibold black-text">Total Trips</h3>
                </div>
                <p class="text-3xl font-bold black-text"><?= $total_trips ?></p>
            </div>
            <div class="gradient-bg p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-4">
                    <i class="fas fa-bus text-3xl red-accent mr-4"></i>
                    <h3 class="text-lg font-semibold black-text">Total Vehicles</h3>
                </div>
                <p class="text-3xl font-bold black-text"><?= $total_vehicles ?></p>
            </div>
            <div class="gradient-bg p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-4">
                    <i class="fas fa-ticket-alt text-3xl red-accent mr-4"></i>
                    <h3 class="text-lg font-semibold black-text">Total Bookings</h3>
                </div>
                <p class="text-3xl font-bold black-text"><?= $total_vehicles ?></p>
            </div>
            <div class="gradient-bg p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-4">
                    <i class="fas fa-users text-3xl red-accent mr-4"></i>
                    <h3 class="text-lg font-semibold black-text">Total Users</h3>
                </div>
                <p class="text-3xl font-bold black-text"><?= $total_users ?></p>
            </div>
        </div>

        <h2 class="text-2xl font-semibold black-text mb-6">Quick Actions</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="<?= SITE_URL ?>/admin/trips.php" class="block white-bg p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:scale-105">
                <div class="flex items-center mb-4">
                    <i class="fas fa-road text-4xl red-accent mr-4"></i>
                    <h3 class="text-xl font-semibold black-text">Manage Trips</h3>
                </div>
                <p class="black-text">Add, edit, and delete trip schedules and routes.</p>
            </a>

            <a href="<?= SITE_URL ?>/admin/vehicles.php" class="block white-bg p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:scale-105">
                <div class="flex items-center mb-4">
                    <i class="fas fa-bus text-4xl red-accent mr-4"></i>
                    <h3 class="text-xl font-semibold black-text">Manage Vehicles</h3>
                </div>
                <p class="black-text">View and update vehicle details and drivers.</p>
            </a>
            
            <a href="<?= SITE_URL ?>/admin/bookings.php" class="block white-bg p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:scale-105">
                <div class="flex items-center mb-4">
                    <i class="fas fa-ticket-alt text-4xl red-accent mr-4"></i>
                    <h3 class="text-xl font-semibold black-text">View All Bookings</h3>
                </div>
                <p class="black-text">Access and manage all customer bookings and payments.</p>
            </a>

            <a href="<?= SITE_URL ?>/admin/users.php" class="block white-bg p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:scale-105">
                <div class="flex items-center mb-4">
                    <i class="fas fa-users-cog text-4xl red-accent mr-4"></i>
                    <h3 class="text-xl font-semibold black-text">Manage Users</h3>
                </div>
                <p class="black-text">Create or modify staff and customer accounts.</p>
            </a>
        </div>
    </div>

</body>
</html>