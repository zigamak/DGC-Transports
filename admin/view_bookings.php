<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Get template ID from URL
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

if (!$template_id) {
    header('Location: trips.php');
    exit;
}

// Handle booking status update
if (isset($_POST['update_booking']) && isset($_POST['booking_id']) && isset($_POST['status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    $allowed_statuses = ['pending', 'confirmed', 'cancelled'];
    if (in_array($status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $booking_id);
        if ($stmt->execute()) {
            $success = 'Booking status updated successfully.';
        } else {
            $error = 'Failed to update booking status.';
        }
        $stmt->close();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get trip template details
$template_query = "
    SELECT tt.id, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time, 
           tt.price, tt.recurrence_type, tt.recurrence_days, tt.start_date, tt.end_date,
           vt.capacity
    FROM trip_templates tt
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE tt.id = ? AND tt.status = 'active'
";

$stmt = $conn->prepare($template_query);
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template_result = $stmt->get_result();
$template = $template_result->fetch_assoc();
$stmt->close();

if (!$template) {
    header('Location: trips.php');
    exit;
}

// Build the WHERE clause for filtering
$where_conditions = ["b.template_id = ?"];
$params = [$template_id];
$param_types = "i";

if (!empty($search)) {
    $where_conditions[] = "(b.pnr LIKE ? OR b.passenger_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
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

if (!empty($date_from)) {
    $where_conditions[] = "b.trip_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "b.trip_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE $where_clause
";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_bookings = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_bookings / $per_page);
$stmt->close();

// Get filtered bookings with pagination
$bookings_query = "
    SELECT b.id, b.pnr, b.passenger_name, b.email, b.phone, b.trip_date, 
           b.seat_number, b.total_amount, b.payment_status, b.status, b.created_at,
           b.emergency_contact, b.special_requests,
           u.first_name, u.last_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE $where_clause
    ORDER BY b.trip_date DESC, b.created_at DESC
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

// Get statistics (for all bookings, not just filtered)
$stats_query = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        SUM(CASE WHEN status != 'cancelled' AND payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue
    FROM bookings 
    WHERE template_id = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $template_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
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
                margin-left: 256px;
            }
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stats-card.revenue {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stats-card.confirmed {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stats-card.cancelled {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
        
        .table-header {
            background: var(--gray);
            color: var(--black);
            font-weight: 600;
        }
        
        .table-row:hover {
            background: #f9fafb;
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
            border-color: var(--primary-red);
            ring: 2px;
            ring-color: rgba(227, 6, 19, 0.2);
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
            background: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
        }
        
        .text-red-600 { color: #e30613; }
        .bg-red-600 { background-color: #e30613; }
        .hover\:bg-red-600:hover { background-color: #e30613; }
        
        /* Mobile responsive cards */
        .mobile-booking-card {
            display: none;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 16px;
            background: white;
        }
        
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }
            .mobile-booking-card {
                display: block;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .stats-card {
                padding: 12px;
                min-height: 100px;
            }
            .stats-card i {
                font-size: 1.5rem !important;
                margin-bottom: 8px !important;
            }
            .stats-card h3 {
                font-size: 1.25rem !important;
            }
        }
        
        @media (min-width: 769px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 24px;
            }
        }
        
        @media (min-width: 1024px) {
            .stats-grid {
                gap: 32px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1">
        <main class="ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
            <div class="container mx-auto">
                <!-- Header -->
                <div class="card p-6 mb-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                                <i class="fas fa-ticket-alt text-red-600 mr-3"></i>Trip Bookings
                            </h1>
                            <p class="text-gray-600 text-sm sm:text-base lg:text-lg">
                                <?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?>
                                (<?= htmlspecialchars($template['vehicle_type']) ?> - <?= htmlspecialchars($template['vehicle_number']) ?>)
                            </p>
                        </div>
                        <a href="trips.php" class="btn-primary mt-4 sm:mt-0">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Templates
                        </a>
                    </div>

                    <!-- Trip Details -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-6">
                        <div class="bg-gray-50 p-3 lg:p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-clock text-red-600 mr-2 text-sm lg:text-base"></i>
                                <div>
                                    <p class="text-xs lg:text-sm text-gray-600">Time</p>
                                    <p class="font-semibold text-sm lg:text-base"><?= htmlspecialchars($template['departure_time']) ?> - <?= htmlspecialchars($template['arrival_time']) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 lg:p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-money-bill-wave text-red-600 mr-2 text-sm lg:text-base"></i>
                                <div>
                                    <p class="text-xs lg:text-sm text-gray-600">Price</p>
                                    <p class="font-semibold text-sm lg:text-base">₦<?= number_format($template['price'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 lg:p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-users text-red-600 mr-2 text-sm lg:text-base"></i>
                                <div>
                                    <p class="text-xs lg:text-sm text-gray-600">Capacity</p>
                                    <p class="font-semibold text-sm lg:text-base"><?= $template['capacity'] ?> seats</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 lg:p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-user text-red-600 mr-2 text-sm lg:text-base"></i>
                                <div>
                                    <p class="text-xs lg:text-sm text-gray-600">Driver</p>
                                    <p class="font-semibold text-sm lg:text-base"><?= htmlspecialchars($template['driver_name']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid stats-grid mb-8">
                    <div class="stats-card confirmed">
                        <i class="fas fa-chart-line text-2xl lg:text-3xl mb-2 lg:mb-3"></i>
                        <h3 class="text-xl lg:text-2xl font-bold"><?= $stats['total_bookings'] ?></h3>
                        <p class="text-xs lg:text-sm opacity-90">Total Bookings</p>
                    </div>
                    <div class="stats-card pending">
                        <i class="fas fa-check-circle text-2xl lg:text-3xl mb-2 lg:mb-3"></i>
                        <h3 class="text-xl lg:text-2xl font-bold"><?= $stats['confirmed_bookings'] ?></h3>
                        <p class="text-xs lg:text-sm opacity-90">Confirmed</p>
                    </div>
                    <div class="stats-card cancelled">
                        <i class="fas fa-clock text-2xl lg:text-3xl mb-2 lg:mb-3"></i>
                        <h3 class="text-xl lg:text-2xl font-bold"><?= $stats['pending_bookings'] ?></h3>
                        <p class="text-xs lg:text-sm opacity-90">Pending</p>
                    </div>
                    <div class="stats-card revenue">
                        <i class="fas fa-naira-sign text-2xl lg:text-3xl mb-2 lg:mb-3"></i>
                        <h3 class="text-xl lg:text-2xl font-bold">₦<?= number_format($stats['total_revenue'], 0) ?></h3>
                        <p class="text-xs lg:text-sm opacity-90">Revenue</p>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($success)): ?>
                    <div class="card p-4 mb-6">
                        <p class="success-message"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></p>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="card p-4 mb-6">
                        <p class="error-message"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-search text-red-600 mr-2"></i>Search & Filter
                    </h2>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                        <input type="hidden" name="template_id" value="<?= $template_id ?>">
                        
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="PNR, name, email, phone..." class="form-input">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="form-input">
                                <option value="">All Status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment</label>
                            <select name="payment" class="form-input">
                                <option value="">All Payments</option>
                                <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="cancelled" <?= $payment_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-input">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-input">
                        </div>
                        
                        <div class="xl:col-span-6 flex gap-2">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                            <a href="view_bookings.php?template_id=<?= $template_id ?>" class="btn-secondary">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bookings -->
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl lg:text-2xl font-bold text-gray-800">
                            <i class="fas fa-list text-red-600 mr-3"></i>All Bookings
                        </h2>
                        <div class="text-sm text-gray-600">
                            Showing <?= count($bookings) ?> of <?= $total_bookings ?> bookings
                        </div>
                    </div>

                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-ticket-alt text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">No Bookings Found</h3>
                            <p class="text-gray-500">No bookings match your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <!-- Desktop Table -->
                        <div class="desktop-table overflow-x-auto">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="table-header">
                                        <th class="px-4 py-3 text-left">PNR</th>
                                        <th class="px-4 py-3 text-left">Passenger</th>
                                        <th class="px-4 py-3 text-left">Contact</th>
                                        <th class="px-4 py-3 text-left">Trip Date</th>
                                        <th class="px-4 py-3 text-left">Seat</th>
                                        <th class="px-4 py-3 text-left">Amount</th>
                                        <th class="px-4 py-3 text-left">Payment</th>
                                        <th class="px-4 py-3 text-left">Status</th>
                                        <th class="px-4 py-3 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <tr class="table-row border-b border-gray-200">
                                            <td class="px-4 py-3">
                                                <span class="font-mono text-sm font-semibold"><?= htmlspecialchars($booking['pnr']) ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div>
                                                    <p class="font-semibold"><?= htmlspecialchars($booking['passenger_name']) ?></p>
                                                    <?php if ($booking['first_name'] && $booking['last_name']): ?>
                                                        <p class="text-sm text-gray-500">User: <?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div>
                                                    <p class="text-sm"><?= htmlspecialchars($booking['email']) ?></p>
                                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($booking['phone']) ?></p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-sm font-medium"><?= date('M j, Y', strtotime($booking['trip_date'])) ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="bg-gray-100 px-3 py-1 rounded-full text-sm font-semibold"><?= $booking['seat_number'] ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="font-semibold">₦<?= number_format($booking['total_amount'], 2) ?></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="payment-badge payment-<?= $booking['payment_status'] ?>">
                                                    <?= ucfirst($booking['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <select name="status" class="select-status" onchange="this.form.submit()">
                                                        <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                        <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                    <input type="hidden" name="update_booking" value="1">
                                                </form>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button onclick="showBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <?php foreach ($bookings as $booking): ?>
                            <div class="mobile-booking-card">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($booking['passenger_name']) ?></h3>
                                        <p class="text-sm text-gray-600 font-mono"><?= htmlspecialchars($booking['pnr']) ?></p>
                                    </div>
                                    <button onclick="showBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye text-lg"></i>
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Trip Date</p>
                                        <p class="font-medium"><?= date('M j, Y', strtotime($booking['trip_date'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Seat</p>
                                        <span class="bg-gray-100 px-2 py-1 rounded-full text-sm font-semibold"><?= $booking['seat_number'] ?></span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Amount</p>
                                        <p class="font-semibold">₦<?= number_format($booking['total_amount'], 2) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Payment</p>
                                        <span class="payment-badge payment-<?= $booking['payment_status'] ?>">
                                            <?= ucfirst($booking['payment_status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Contact</p>
                                    <p class="text-sm"><?= htmlspecialchars($booking['email']) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($booking['phone']) ?></p>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <select name="status" class="select-status mt-1" onchange="this.form.submit()">
                                                <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_booking" value="1">
                                        </form>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">Booked</p>
                                        <p class="text-xs"><?= date('M j, g:i A', strtotime($booking['created_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php 
                                $current_url = $_SERVER['REQUEST_URI'];
                                $url_parts = parse_url($current_url);
                                parse_str($url_parts['query'] ?? '', $query_params);
                                
                                // Helper function to build pagination URL
                                function buildPaginationUrl($page, $query_params) {
                                    $query_params['page'] = $page;
                                    return '?' . http_build_query($query_params);
                                }
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildPaginationUrl(1, $query_params) ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="<?= buildPaginationUrl($page - 1, $query_params) ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= buildPaginationUrl($i, $query_params) ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?= buildPaginationUrl($page + 1, $query_params) ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                    <a href="<?= buildPaginationUrl($total_pages, $query_params) ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg p-6 max-w-2xl w-full max-h-screen overflow-y-auto shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Booking Details</h3>
                <button onclick="closeBookingModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="bookingDetails" class="space-y-4">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function showBookingDetails(booking) {
            const modal = document.getElementById('bookingModal');
            const detailsContainer = document.getElementById('bookingDetails');
            
            const emergencyContact = booking.emergency_contact ? booking.emergency_contact : 'Not provided';
            const specialRequests = booking.special_requests ? booking.special_requests : 'None';
            const userInfo = (booking.first_name && booking.last_name) ? 
                `${booking.first_name} ${booking.last_name}` : 'Guest booking';
            
            detailsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">PNR</label>
                        <p class="font-mono font-semibold text-lg">${booking.pnr}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Booking Type</label>
                        <p class="font-semibold">${userInfo}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passenger Name</label>
                        <p class="font-semibold">${booking.passenger_name}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <p>${booking.email}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <p>${booking.phone}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trip Date</label>
                        <p class="font-semibold">${new Date(booking.trip_date + 'T00:00:00').toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Seat Number</label>
                        <p class="font-semibold text-lg">${booking.seat_number}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                        <p class="font-semibold text-lg">₦${parseFloat(booking.total_amount).toLocaleString()}</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                        <span class="payment-badge payment-${booking.payment_status}">
                            ${booking.payment_status.charAt(0).toUpperCase() + booking.payment_status.slice(1)}
                        </span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Booking Status</label>
                        <span class="status-badge status-${booking.status}">
                            ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}
                        </span>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
                    <p>${emergencyContact}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                    <p>${specialRequests}</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Booking Date & Time</label>
                    <p>${new Date(booking.created_at).toLocaleString()}</p>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBookingModal();
            }
        });

        // Auto-submit search form on Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.closest('form').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>