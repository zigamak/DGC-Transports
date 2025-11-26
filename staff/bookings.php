<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce staff-only access
requireRole('staff', '/login.php');

// Initialize variables
$pnr_error = '';
$pnr_success = '';
$searched_bookings = [];
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : '';
$filter_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : 'all';
$filter_pnr = isset($_POST['pnr']) ? trim($_POST['pnr']) : '';

// Build the query for bookings
$query = "
    SELECT b.id, b.pnr, b.passenger_name, b.email, b.phone, b.status, b.payment_status, b.created_at,
           c1.name AS pickup_city, c2.name AS dropoff_city, b.trip_date
    FROM bookings b
    JOIN trip_templates tt ON b.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    WHERE 1=1
";
$params = [];
$param_types = '';

if ($filter_pnr) {
    $query .= " AND b.pnr = ?";
    $params[] = $filter_pnr;
    $param_types .= 's';
}

if ($filter_date) {
    $query .= " AND b.trip_date = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

if ($filter_status !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$searched_bookings = [];
while ($row = $result->fetch_assoc()) {
    $searched_bookings[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($searched_bookings)) {
    $pnr_error = 'No bookings found matching the criteria.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($searched_bookings)) {
    $pnr_success = 'Booking(s) found.';
}

// Handle confirm action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = 'confirmed';
    $new_payment_status = 'paid';
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $new_payment_status, $booking_id);
    if ($stmt->execute()) {
        $pnr_success = 'Booking confirmed and payment marked as paid.';
        // Refresh the bookings data
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $searched_bookings = [];
        while ($row = $result->fetch_assoc()) {
            $searched_bookings[] = $row;
        }
        $stmt->close();
    } else {
        $pnr_error = 'Failed to confirm booking.';
    }
}

// Handle check-in action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = 'boarded';
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $booking_id);
    if ($stmt->execute()) {
        $pnr_success = 'Booking status updated to boarded.';
        // Refresh the bookings data
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $searched_bookings = [];
        while ($row = $result->fetch_assoc()) {
            $searched_bookings[] = $row;
        }
        $stmt->close();
    } else {
        $pnr_error = 'Failed to update booking status.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#dc2626',
                        'primary-black': '#1a1a1a',
                        'dark-gray': '#2d2d2d',
                        'light-gray': '#e5e7eb',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .form-input, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease, opacity 0.2s ease;
            width: 100%;
            text-align: center;
            position: relative;
            font-size: 0.875rem;
            min-height: 48px;
        }

        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary .spinner {
            display: none;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #dc2626;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .btn-primary.loading .spinner {
            display: block;
        }

        .btn-primary.loading span {
            visibility: hidden;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }

        .success-message {
            color: #059669;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }

        .table-hover:hover {
            background-color: #f8fafc;
        }

        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem);
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        .mobile-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .mobile-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .mobile-card-label {
            font-weight: 500;
            color: #374151;
            min-width: 100px;
            width: 100px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending, .status-pending-payment {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #15803d;
        }

        .status-boarded {
            background: #e5e7eb;
            color: #1a1a1a;
        }

        .status-paid {
            background: #dcfce7;
            color: #15803d;
        }

        .status-unpaid {
            background: #fef2f2;
            color: #dc2626;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        @media (min-width: 768px) {
            .content-container {
                max-height: calc(100vh - 2rem);
            }
            .mobile-card {
                display: none;
            }
            .table-container {
                display: block;
            }
            .btn-primary {
                width: auto;
                min-width: 120px;
            }
            .form-input, .form-select {
                min-width: 200px;
            }
        }

        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
            .header-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            .search-form {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            .form-input, .form-select {
                width: 100%;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen pt-16 md:pt-0">
        <div class="content-container p-4 md:p-6 lg:p-8">
            <div class="max-w-5xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                                <i class="fas fa-ticket-alt text-primary-red mr-3"></i>
                                Bookings Management
                            </h1>
                            <p class="text-sm text-gray-600 mt-1">Manage all bookings including pending and confirmed</p>
                        </div>
                        <div class="header-actions w-full md:w-auto">
                            <form method="POST" class="search-form" id="pnr-search-form">
                                <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2">
                                    <input type="text" id="pnr" name="pnr" placeholder="Enter PNR" class="form-input" aria-label="Search by PNR">
                                    <input type="date" id="filter_date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" class="form-input" aria-label="Filter by Date">
                                    <select id="filter_status" name="filter_status" class="form-select" aria-label="Filter by Status">
                                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All</option>
                                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="pending payment" <?= $filter_status === 'pending payment' ? 'selected' : '' ?>>Pending Payment</option>
                                        <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="boarded" <?= $filter_status === 'boarded' ? 'selected' : '' ?>>Boarded</option>
                                    </select>
                                    <button type="submit" class="btn-primary" id="pnr-search-btn">
                                        <span><i class="fas fa-search mr-2"></i>Filter</span>
                                        <div class="spinner"></div>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="bg-white rounded-xl card-shadow">
                    <div class="p-4 md:p-6">
                        <!-- Filter Results -->
                        <?php if ($pnr_error): ?>
                            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                    <span class="text-red-800 text-sm"><?= htmlspecialchars($pnr_error) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($pnr_success): ?>
                            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                    <span class="text-green-800 text-sm"><?= htmlspecialchars($pnr_success) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($searched_bookings)): ?>
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-ticket-alt text-primary-red mr-2"></i>Bookings List
                            </h2>
                            <!-- Mobile View -->
                            <div class="space-y-4 md:hidden">
                                <?php foreach ($searched_bookings as $booking): ?>
                                    <div class="mobile-card">
                                        <div class="flex items-start justify-between mb-3">
                                            <div class="flex items-center">
                                                <div class="w-2 h-2 bg-primary-red rounded-full mr-2"></div>
                                                <span class="font-semibold text-gray-900 text-sm">
                                                    <?= htmlspecialchars($booking['passenger_name']) ?>
                                                </span>
                                            </div>
                                            <span class="status-badge status-<?= str_replace(' ', '-', $booking['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                            </span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-ticket-alt text-primary-red mr-2"></i>PNR:</span>
                                            <span><?= htmlspecialchars($booking['pnr']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-map-marker-alt text-primary-red mr-2"></i>Route:</span>
                                            <span><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-clock text-primary-red mr-2"></i>Reservation Time:</span>
                                            <span><?= date('g:iA', strtotime($booking['created_at'])) ?> on <?= formatDate($booking['trip_date']) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-money-bill-wave text-primary-red mr-2"></i>Payment:</span>
                                            <span class="status-badge status-<?= str_replace(' ', '-', $booking['payment_status']) ?>"><?= htmlspecialchars(ucfirst($booking['payment_status'])) ?></span>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-cog text-primary-red mr-2"></i>Action:</span>
                                            <div class="w-full flex flex-col gap-2">
                                                <?php if (in_array($booking['status'], ['pending', 'pending payment'])): ?>
                                                    <form method="POST" class="w-full" onsubmit="handleFormSubmit(event, this)">
                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                        <input type="hidden" name="confirm" value="1">
                                                        <button type="submit" class="btn-primary">
                                                            <span><i class="fas fa-check mr-2"></i>Confirm</span>
                                                            <div class="spinner"></div>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <form method="POST" class="w-full" onsubmit="handleFormSubmit(event, this)">
                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                        <input type="hidden" name="check_in" value="1">
                                                        <button type="submit" class="btn-primary">
                                                            <span><i class="fas fa-check mr-2"></i>Check In</span>
                                                            <div class="spinner"></div>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <button onclick="showBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" class="text-primary-black hover:text-primary-red text-sm font-medium">
                                                <i class="fas fa-eye mr-1"></i>Details
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Desktop View -->
                            <div class="table-container hidden md:block">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Passenger Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PNR</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($searched_bookings as $booking): ?>
                                            <tr class="table-hover">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock text-primary-red mr-2"></i>
                                                        <span class="text-sm text-gray-900"><?= date('g:iA', strtotime($booking['created_at'])) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-user text-primary-red mr-2"></i>
                                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($booking['pnr']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-check-circle text-primary-red mr-2"></i>
                                                        <span class="status-badge status-<?= str_replace(' ', '-', $booking['status']) ?>"><?= htmlspecialchars(ucfirst($booking['status'])) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                                        <span class="status-badge status-<?= str_replace(' ', '-', $booking['payment_status']) ?>"><?= htmlspecialchars(ucfirst($booking['payment_status'])) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        <?php if (in_array($booking['status'], ['pending', 'pending payment'])): ?>
                                                            <form method="POST" class="inline-flex" onsubmit="handleFormSubmit(event, this)">
                                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                <input type="hidden" name="confirm" value="1">
                                                                <button type="submit" class="btn-primary text-sm px-4 py-2">
                                                                    <span><i class="fas fa-check mr-1"></i>Confirm</span>
                                                                    <div class="spinner"></div>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                                            <form method="POST" class="inline-flex" onsubmit="handleFormSubmit(event, this)">
                                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                                <input type="hidden" name="check_in" value="1">
                                                                <button type="submit" class="btn-primary text-sm px-4 py-2">
                                                                    <span><i class="fas fa-check mr-1"></i>Check In</span>
                                                                    <div class="spinner"></div>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button onclick="showBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" class="text-primary-black hover:text-primary-red text-sm">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-ticket-alt text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Bookings</h3>
                                <p class="text-gray-600 mb-4">No bookings found for the selected criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-50 transition-opacity duration-300">
        <div class="bg-white rounded-xl p-6 max-w-lg w-full m-4 max-h-[90vh] overflow-y-auto card-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-900">Booking Details</h3>
                <button onclick="closeBookingModal()" class="text-gray-500 hover:text-primary-red focus:outline-none">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div id="bookingDetails" class="space-y-4 text-sm"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarClose = document.getElementById('sidebar-close');

            if (sidebar && sidebarOverlay && sidebarToggle && sidebarClose) {
                sidebarToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                });

                const closeSidebar = () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                };

                sidebarClose.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });

                sidebarOverlay.addEventListener('click', closeSidebar);

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        closeSidebar();
                    }
                });

                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 768) {
                        closeSidebar();
                    }
                });
            }

            // Override sidebar styles to match red, black, and white theme
            const navLinks = document.querySelectorAll('#sidebar nav a');
            navLinks.forEach(link => {
                link.classList.remove('hover:bg-gradient-to-r', 'hover:from-red-50', 'hover:to-red-100', 'hover:text-red-700');
                link.classList.add('hover:bg-gray-100', 'hover:text-primary-red');
                if (link.classList.contains('nav-active')) {
                    link.classList.remove('bg-gradient-to-r', 'from-red-50', 'to-red-100', 'text-red-700', 'border-red-200');
                    link.classList.add('bg-gray-100', 'text-primary-red', 'border-light-gray');
                }
                const iconDiv = link.querySelector('div');
                if (iconDiv) {
                    iconDiv.classList.remove('bg-red-100', 'group-hover:bg-red-200');
                    iconDiv.classList.add('bg-gray-200', 'group-hover:bg-light-gray');
                }
            });

            const logoutLink = document.querySelector('#sidebar .p-4 a');
            if (logoutLink) {
                logoutLink.classList.remove('bg-red-600', 'hover:bg-red-700');
                logoutLink.classList.add('bg-primary-red', 'hover:bg-dark-gray');
                const logoutIconDiv = logoutLink.querySelector('div');
                if (logoutIconDiv) {
                    logoutIconDiv.classList.remove('bg-red-500', 'group-hover:bg-red-600');
                    logoutIconDiv.classList.add('bg-dark-gray', 'group-hover:bg-gray-700');
                }
            }
        });

        function handleFormSubmit(event, form) {
            event.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.classList.add('loading');
            submitButton.disabled = true;
            setTimeout(() => {
                form.submit();
            }, 500); // Simulate async submission
        }

        function showBookingDetails(booking) {
            const details = document.getElementById('bookingDetails');
            details.innerHTML = `
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <p class="text-gray-600 font-medium">Passenger Name</p>
                        <p class="text-gray-900">${booking.passenger_name}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">PNR</p>
                        <p class="text-gray-900">${booking.pnr}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Route</p>
                        <p class="text-gray-900">${booking.pickup_city} → ${booking.dropoff_city}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Reservation Time</p>
                        <p class="text-gray-900">${new Date(booking.created_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Trip Date</p>
                        <p class="text-gray-900">${new Date(booking.trip_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Email</p>
                        <p class="text-gray-900">${booking.email}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Phone</p>
                        <p class="text-gray-900">${booking.phone}</p>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Status</p>
                        <span class="status-badge status-${booking.status.replace(' ', '-')}">${booking.status.toUpperCase()}</span>
                    </div>
                    <div>
                        <p class="text-gray-600 font-medium">Payment Status</p>
                        <span class="status-badge status-${booking.payment_status.replace(' ', '-')}">${booking.payment_status.toUpperCase()}</span>
                    </div>
                </div>
            `;
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
        }

        document.getElementById('bookingModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('bookingModal')) {
                closeBookingModal();
            }
        });
    </script>
</body>
</html>