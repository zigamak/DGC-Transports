<?php
//admin/view_bookings.php
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
            // Add current filters to URL to maintain view after post-redirect-get
            $redirect_query = http_build_query(array_filter([
                'template_id' => $template_id,
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status'] ?? '',
                'payment' => $_GET['payment'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stats-icon {
            background: linear-gradient(135deg, #e30613 0%, #c70410 100%);
        }
        
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .filter-grid {
                grid-template-columns: repeat(1, 1fr);
            }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1025px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
            .filter-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem); /* Adjust based on header height */
        }
        
        @media (min-width: 768px) {
            .content-container {
                max-height: 100vh;
            }
        }
    </style>
</head>
<body class="bg-gray-50 gradient-bg">
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
                        <p class="text-gray-600 mt-1"><?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?></p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="trips.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Trips
                        </a>
                        <button onclick="showFilterModal()" class="md:hidden inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-filter mr-2"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl card-shadow mb-8">
                <div class="p-4 md:p-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-bus text-primary-red mr-2"></i>
                        Trip Details
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Vehicle</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Driver</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($template['driver_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Time</p>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars(date('h:i A', strtotime($template['departure_time'])) . ' - ' . date('h:i A', strtotime($template['arrival_time']))) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Price</p>
                            <p class="text-gray-900 font-medium">₦<?= number_format($template['price'], 0) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Capacity</p>
                            <p class="text-gray-900 font-medium"><?= $template['capacity'] ?> seats</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Recurrence</p>
                            <p class="text-gray-900 font-medium">
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
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-600 uppercase tracking-wide">Period</p>
                            <p class="text-gray-900 font-medium"><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-grid grid mb-8">
                <div class="stats-card rounded-xl p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="stats-icon p-3 rounded-lg">
                            <i class="fas fa-ticket-alt text-white text-lg md:text-xl"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Bookings</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_bookings']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="stats-card rounded-xl p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="stats-icon p-3 rounded-lg">
                            <i class="fas fa-check-circle text-white text-lg md:text-xl"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Confirmed</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['confirmed_bookings']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="stats-card rounded-xl p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="stats-icon p-3 rounded-lg">
                            <i class="fas fa-clock text-white text-lg md:text-xl"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Pending</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['pending_bookings']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="stats-card rounded-xl p-4 md:p-6">
                    <div class="flex items-center">
                        <div class="stats-icon p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-lg md:text-xl"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($stats['total_revenue'], 0) ?></p>
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

            <div id="filterModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-50 md:hidden">
                <div class="bg-white rounded-xl p-6 max-w-lg w-full m-4 max-h-[90vh] overflow-y-auto card-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-900">
                            <i class="fas fa-filter text-primary-red mr-2"></i>
                            Filter Bookings
                        </h3>
                        <button onclick="closeFilterModal()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    <form method="GET" class="filter-grid grid gap-4">
                        <input type="hidden" name="template_id" value="<?= $template_id ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PNR, name, email..." class="form-input">
                        <select name="status" class="form-input">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <select name="payment" class="form-input">
                            <option value="">All Payments</option>
                            <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $payment_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-input">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-input">
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Filter
                            </button>
                            <a href="view_bookings.php?template_id=<?= $template_id ?>" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl card-shadow mb-8 hidden md:block">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-filter text-primary-red mr-2"></i>
                        Filter Bookings
                    </h2>
                </div>
                <div class="p-4 md:p-6">
                    <form method="GET" class="filter-grid grid gap-4">
                        <input type="hidden" name="template_id" value="<?= $template_id ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PNR, name, email..." class="form-input">
                        <select name="status" class="form-input">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <select name="payment" class="form-input">
                            <option value="">All Payments</option>
                            <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $payment_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-input">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-input">
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-search mr-2"></i>
                                Filter
                            </button>
                            <a href="view_bookings.php?template_id=<?= $template_id ?>" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl card-shadow">
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
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Try adjusting your filters or check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($bookings as $booking): ?>
                                <div class="mobile-card p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <span class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($booking['passenger_name']) ?>
                                            </span>
                                            <span class="text-xs font-medium bg-gray-200 text-gray-700 px-2 py-1 rounded-full ml-2">
                                                <?= $booking['pnr'] ?>
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
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-phone text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($booking['phone']) ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-calendar-alt text-primary-red w-4 mr-2"></i>
                                                <span><?= date('M j, Y', strtotime($booking['trip_date'])) ?></span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-chair text-primary-red w-4 mr-2"></i>
                                                <span>Seat: <?= $booking['seat_number'] ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-money-bill-wave text-primary-red w-4 mr-2"></i>
                                                <span class="font-semibold text-gray-900">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                            </div>
                                            <div class="payment-badge payment-<?= $booking['payment_status'] ?> text-xs">
                                                <?= ucfirst($booking['payment_status']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                                        <span class="text-xs text-gray-500">Booked: <?= time_elapsed_string($booking['created_at']) ?></span>
                                        <form method="POST" class="inline flex items-center space-x-2">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <select name="status" class="select-status bg-gray-50 border-gray-300" onchange="this.form.submit()">
                                                <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_booking" value="1">
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="overflow-x-auto hidden md:block">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            PNR / Passenger
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Trip Date / Seat
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Payment
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
                                                <div class="text-xs text-gray-500">
                                                    <span class="font-mono text-primary-red"><?= $booking['pnr'] ?></span>
                                                    | <?= htmlspecialchars($booking['email']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('M j, Y', strtotime($booking['trip_date'])) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Seat: <?= $booking['seat_number'] ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                                ₦<?= number_format($booking['total_amount'], 0) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="payment-badge payment-<?= $booking['payment_status'] ?>">
                                                    <?= ucfirst($booking['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <form method="POST" class="inline flex items-center space-x-2" onchange="this.submit()">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <select name="status" class="select-status bg-gray-50 border-gray-300">
                                                        <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
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

    <script>
        function showFilterModal() {
            document.getElementById('filterModal').classList.remove('hidden');
        }

        function closeFilterModal() {
            document.getElementById('filterModal').classList.add('hidden');
        }
    </script>
</body>
</html>