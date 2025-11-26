<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Get trip ID and trip date from URL
$trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$trip_date = isset($_GET['trip_date']) ? $_GET['trip_date'] : '';

if (!$trip_id || !$trip_date || !DateTime::createFromFormat('Y-m-d', $trip_date)) {
    header('Location: dashboard.php');
    exit;
}

// Handle booking status update
if (isset($_POST['update_booking']) && isset($_POST['booking_id']) && isset($_POST['status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    $allowed_statuses = ['pending', 'confirmed', 'boarded', 'cancelled'];
    if (in_array($status, $allowed_statuses)) {
        // If status is 'confirmed', also set payment_status to 'paid'
        if ($status === 'confirmed') {
            $payment_status = 'paid';
            $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE id = ? AND trip_id = ? AND trip_date = ?");
            $stmt->bind_param("ssiss", $status, $payment_status, $booking_id, $trip_id, $trip_date);
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND trip_id = ? AND trip_date = ?");
            $stmt->bind_param("siss", $status, $booking_id, $trip_id, $trip_date);
        }
        if ($stmt->execute()) {
            $success = 'Booking status updated successfully.';
            // Maintain filters in URL
            $redirect_query = http_build_query(array_filter([
                'trip_id' => $trip_id,
                'trip_date' => $trip_date,
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status_filter'] ?? '',
                'payment' => $_GET['payment'] ?? '',
                'page' => $_GET['page'] ?? 1
            ]));
            header("Location: view_bookings.php?" . $redirect_query);
            exit;
        } else {
            $error = 'Failed to update booking status.';
        }
        $stmt->close();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get trip instance details
$trip_query = "
    SELECT ti.id, ti.trip_date, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time, 
           tt.price, tt.recurrence_type, tt.recurrence_days, tt.start_date, tt.end_date,
           vt.capacity, 
           (SELECT COUNT(*) FROM bookings b WHERE b.trip_id = ti.id AND b.trip_date = ti.trip_date AND b.status IN ('confirmed', 'boarded')) as booked_seats
    FROM trip_instances ti
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE ti.id = ? AND ti.trip_date = ? AND ti.status = 'active'
";

$stmt = $conn->prepare($trip_query);
$stmt->bind_param("is", $trip_id, $trip_date);
$stmt->execute();
$trip_result = $stmt->get_result();
$trip = $trip_result->fetch_assoc();
$stmt->close();

if (!$trip) {
    header('Location: dashboard.php');
    exit;
}

// Build the WHERE clause for filtering bookings
$where_conditions = ["b.trip_id = ?", "b.trip_date = ?"];
$params = [$trip_id, $trip_date];
$param_types = "is";

if (!empty($search)) {
    $where_conditions[] = "(b.passenger_name LIKE ? OR b.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= "ss";
}

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($payment_filter)) {
    $where_conditions[] = "b.payment_status = ?";
    $params[] = $payment_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM bookings b
    WHERE $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_bookings = $total_result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_bookings / $per_page);
$stmt->close();

// Get filtered bookings with pagination
$bookings_query = "
    SELECT b.id, b.passenger_name, b.email, b.status
    FROM bookings b
    WHERE $where_clause
    ORDER BY b.passenger_name ASC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($bookings_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];
while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

// Get statistics for this specific trip instance
$stats_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'boarded' THEN 1 ELSE 0 END) as boarded_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_revenue
    FROM bookings 
    WHERE trip_id = ? AND trip_date = ?
";

$stats_params = [$trip_id, $trip_date];
$stats_param_types = "is";

if (!empty($search)) {
    $stats_query .= " AND (passenger_name LIKE ? OR email LIKE ?)";
    $stats_params = array_merge($stats_params, [$search_param, $search_param]);
    $stats_param_types .= "ss";
}
if (!empty($status_filter)) {
    $stats_query .= " AND status = ?";
    $stats_params[] = $status_filter;
    $stats_param_types .= "s";
}
if (!empty($payment_filter)) {
    $stats_query .= " AND payment_status = ?";
    $stats_params[] = $payment_filter;
    $stats_param_types .= "s";
}

$stmt = $conn->prepare($stats_query);
$stmt->bind_param($stats_param_types, ...$stats_params);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc() ?? [
    'total_bookings' => 0,
    'confirmed_bookings' => 0,
    'boarded_bookings' => 0,
    'cancelled_bookings' => 0,
    'total_revenue' => 0,
    'pending_revenue' => 0
];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Bookings - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#e30613',
                        'dark-red': '#c70410',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-boarded {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .payment-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .payment-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .payment-paid {
            background: #d1fae5;
            color: #065f46;
        }
        .payment-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .select-status {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
        }
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            width: 100%;
        }
        .form-input:focus {
            outline: none;
            border-color: #e30613;
            box-shadow: 0 0 0 2px rgba(227, 6, 19, 0.2);
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: #f3f4f6;
        }
        .pagination .current {
            background: #e30613;
            color: white;
            border-color: #e30613;
        }
        .table-hover:hover {
            background-color: #f8fafc;
        }
        .mobile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        .mobile-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }
        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem);
        }
        @media (min-width: 768px) {
            .content-container {
                max-height: 100vh;
            }
        }
        @media (max-width: 640px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (min-width: 641px) {
            .filter-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="pt-20 md:pt-0 p-4 md:p-6 lg:p-8 content-container">
            <div class="mb-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-ticket-alt text-primary-red mr-3"></i>
                            Trip Bookings
                        </h1>
                        <p class="text-gray-600 mt-1"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?> on <?= date('M j, Y', strtotime($trip['trip_date'])) ?></p>
                    </div>
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md mb-8">
                <div class="p-4 md:p-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-bus text-primary-red mr-2"></i>
                        Trip Details
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Vehicle</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Driver</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($trip['driver_name'] ?: 'Not Assigned') ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Time</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars(date('h:i A', strtotime($trip['departure_time'])) . ' - ' . date('h:i A', strtotime($trip['arrival_time']))) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Price</p>
                            <p class="text-gray-900 font-medium">₦<?= number_format($trip['price'], 0) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Capacity</p>
                            <p class="text-gray-900 font-medium"><?= $trip['capacity'] ?> seats</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Booked Seats</p>
                            <p class="text-gray-900 font-medium"><?= $trip['booked_seats'] ?> seats</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Trip Date</p>
                            <p class="text-gray-900 font-medium"><?= date('l, M j, Y', strtotime($trip['trip_date'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revamped Statistics Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <!-- Total Bookings Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-ticket-alt text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Bookings</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_bookings']) ?></p>
                        </div>
                    </div>
                </div>
                <!-- Confirmed Bookings Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-check-circle text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Confirmed</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['confirmed_bookings']) ?></p>
                            <p class="text-xs text-gray-500">Boarded: <?= number_format($stats['boarded_bookings']) ?></p>
                        </div>
                    </div>
                </div>
                <!-- Revenue Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($stats['total_revenue'], 0) ?></p>
                            <p class="text-xs text-gray-500">Pending: ₦<?= number_format($stats['pending_revenue'], 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="text-green-800"><?= htmlspecialchars($success) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                        <span class="text-red-800"><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-md mb-8">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-filter text-primary-red mr-2"></i>
                        Filter Bookings
                    </h2>
                </div>
                <div class="p-4 md:p-6">
                    <form method="GET" class="filter-grid grid gap-4">
                        <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                        <input type="hidden" name="trip_date" value="<?= $trip_date ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email..." class="form-input">
                        <select name="status_filter" class="form-input">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="boarded" <?= $status_filter === 'boarded' ? 'selected' : '' ?>>Boarded</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <select name="payment" class="form-input">
                            <option value="">All Payments</option>
                            <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $payment_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Filter
                            </button>
                            <a href="view_bookings.php?trip_id=<?= $trip_id ?>&trip_date=<?= $trip_date ?>" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">
                                <i class="fas fa-list text-primary-red mr-2"></i>
                                Bookings List
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">Showing <?= count($bookings) ?> of <?= $total_bookings ?> bookings</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-ticket-alt text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Bookings Found</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">No bookings match your filter criteria for this trip.</p>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($bookings as $booking): ?>
                                <div class="mobile-card p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <span class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($booking['passenger_name']) ?>
                                            </span>
                                        </div>
                                        <span class="status-badge status-<?= $booking['status'] ?> text-xs">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-envelope text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($booking['email']) ?></span>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                                        <form method="POST" class="inline flex items-center space-x-2">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <select name="status" class="select-status bg-gray-50 border-gray-300" onchange="this.form.submit()">
                                                <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="boarded" <?= $booking['status'] === 'boarded' ? 'selected' : '' ?>>Boarded</option>
                                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_booking" value="1">
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
                        <div class="overflow-x-auto hidden md:block">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Passenger Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Email
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($booking['passenger_name']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($booking['email']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <form method="POST" class="inline flex items-center space-x-2" onchange="this.submit()">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <select name="status" class="select-status bg-gray-50 border-gray-300">
                                                        <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                        <option value="boarded" <?= $booking['status'] === 'boarded' ? 'selected' : '' ?>>Boarded</option>
                                                        <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                    <input type="hidden" name="update_booking" value="1">
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="p-4 md:p-6 border-t border-gray-200">
                        <div class="pagination">
                            <?php
                            $current_query = $_GET;
                            // Previous Page
                            if ($page > 1) {
                                $current_query['page'] = $page - 1;
                                echo '<a href="view_bookings.php?' . http_build_query($current_query) . '"><i class="fas fa-chevron-left"></i> Previous</a>';
                            }
                            // Page Numbers
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            if ($start > 1) {
                                $current_query['page'] = 1;
                                echo '<a href="view_bookings.php?' . http_build_query($current_query) . '">1</a>';
                                if ($start > 2) {
                                    echo '<span>...</span>';
                                }
                            }
                            for ($i = $start; $i <= $end; $i++) {
                                $current_query['page'] = $i;
                                $class = ($i == $page) ? 'current' : '';
                                echo '<a href="view_bookings.php?' . http_build_query($current_query) . '" class="' . $class . '">' . $i . '</a>';
                            }
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<span>...</span>';
                                }
                                $current_query['page'] = $total_pages;
                                echo '<a href="view_bookings.php?' . http_build_query($current_query) . '">' . $total_pages . '</a>';
                            }
                            // Next Page
                            if ($page < $total_pages) {
                                $current_query['page'] = $page + 1;
                                echo '<a href="view_bookings.php?' . http_build_query($current_query) . '">Next <i class="fas fa-chevron-right"></i></a>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>