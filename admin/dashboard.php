<?php
// admin/dashboard.php
// Existing PHP logic remains unchanged
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Get template ID from URL, if provided
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Fetch overall statistics
$total_trips_query = "SELECT COUNT(*) AS total FROM trip_templates WHERE status = 'active'";
$total_trips_result = $conn->query($total_trips_query);
$total_trips = $total_trips_result->fetch_assoc()['total'];

$total_bookings_query = "SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed' AND payment_status = 'paid'";
$total_bookings_result = $conn->query($total_bookings_query);
$total_bookings = $total_bookings_result->fetch_assoc()['total'];

$total_vehicles_query = "SELECT COUNT(*) AS total FROM vehicles";
$total_vehicles_result = $conn->query($total_vehicles_query);
$total_vehicles = $total_vehicles_result->fetch_assoc()['total'];

$total_revenue_query = "SELECT SUM(total_amount) AS total FROM bookings WHERE payment_status = 'paid' AND status = 'confirmed'";
$total_revenue_result = $conn->query($total_revenue_query);
$total_revenue = $total_revenue_result->fetch_assoc()['total'] ?? 0.00;

$stats = [
    'total_trips' => $total_trips,
    'total_bookings' => $total_bookings,
    'total_vehicles' => $total_vehicles,
    'total_revenue' => $total_revenue
];

// Fetch bookings per day
$bookings_per_day_query = "
    SELECT b.trip_date, COUNT(*) as booking_count, SUM(b.total_amount) as revenue
    FROM bookings b
    JOIN trip_instances ti ON b.trip_id = ti.id
    WHERE b.status = 'confirmed' AND b.payment_status = 'paid'
    GROUP BY b.trip_date
    ORDER BY b.trip_date DESC
    LIMIT 5
";
$bookings_per_day_result = $conn->query($bookings_per_day_query);
$bookings_per_day = [];
while ($row = $bookings_per_day_result->fetch_assoc()) {
    $bookings_per_day[] = $row;
}

// Fetch trip template details if template_id is provided
$template = null;
if ($template_id) {
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
    $stmt->bind_param("i", $templateNIE_id);
    $stmt->execute();
    $template_result = $stmt->get_result();
    $template = $template_result->fetch_assoc();
    $stmt->close();
}

// Build the WHERE clause for filtering recent trips
$where_conditions = ["ti.status = 'active'", "ti.trip_date >= CURRENT_DATE"];
$params = [];
$param_types = "";
if ($template_id) {
    $where_conditions[] = "ti.template_id = ?";
    $params[] = $template_id;
    $param_types .= "i";
}
if (!empty($search)) {
    $where_conditions[] = "(c1.name LIKE ? OR c2.name LIKE ? OR v.vehicle_number LIKE ? OR v.driver_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}
if (!empty($status_filter)) {
    $where_conditions[] = "ti.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}
if (!empty($date_from)) {
    $where_conditions[] = "ti.trip_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}
if (!empty($date_to)) {
    $where_conditions[] = "ti.trip_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}
$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM trip_instances ti
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE $where_clause
";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_trips_count = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_trips_count / $per_page);
$stmt->close();

// Fetch recent trip instances with accurate booked seats
$recent_trips_query = "
    SELECT ti.id, ti.trip_date, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, 
           (SELECT COUNT(*) FROM bookings b WHERE b.trip_id = ti.id AND b.status = 'confirmed' AND b.payment_status = 'paid') as booked_seats,
           ti.status, vt.capacity, tt.price, ts.arrival_time, tt.id AS template_id
    FROM trip_instances ti
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE $where_clause
    ORDER BY ti.trip_date ASC, ts.departure_time ASC
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($recent_trips_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$recent_trips_result = $stmt->get_result();
$recent_trips = [];
while ($row = $recent_trips_result->fetch_assoc()) {
    $recent_trips[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DGC Transports</title>
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
        /* Custom styles for status badges and table hover */
        .status-badge.status-active {
            @apply bg-green-100 text-green-800 px-2 py-1 rounded-full;
        }
        .status-badge.status-cancelled {
            @apply bg-red-100 text-red-800 px-2 py-1 rounded-full;
        }
        .table-hover:hover {
            @apply bg-gray-50;
        }
        .pagination {
            @apply flex items-center justify-center space-x-2 mt-4;
        }
        .pagination a, .pagination span {
            @apply px-3 py-1 text-sm font-medium rounded-md;
        }
        .pagination a {
            @apply text-primary-red hover:bg-gray-100;
        }
        .pagination span.current {
            @apply bg-primary-red text-white;
        }
        .mobile-card {
            @apply bg-gray-50 rounded-lg border border-gray-200;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="pt-20 md:pt-0 p-4 md:p-6 lg:p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-tachometer-alt text-primary-red mr-3"></i>
                            Dashboard
                        </h1>
                        <p class="text-gray-600 mt-1">
                            <?php echo $template ? htmlspecialchars($template['pickup_city']) . ' → ' . htmlspecialchars($template['dropoff_city']) : 'Welcome back! Here\'s what\'s happening.'; ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-500"><?= date('l, F j, Y') ?></span>
                        <?php if ($template): ?>
                            <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Trip Details -->
            <?php if ($template): ?>
                <div class="bg-white rounded-xl shadow-md mb-8">
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
                                <p class="text-gray-900 font-medium"><?= htmlspecialchars($template['driver_name'] ?: 'Not Assigned') ?></p>
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
            <?php endif; ?>

            <!-- Revamped Statistics Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <!-- Total Trips Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-road text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Trips</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_trips']) ?></p>
                            <p class="text-xs text-green-600 font-medium">Active Templates</p>
                        </div>
                    </div>
                </div>
                <!-- Total Bookings Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-ticket-alt text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Bookings</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_bookings']) ?></p>
                            <p class="text-xs text-blue-600 font-medium">Confirmed & Paid</p>
                        </div>
                    </div>
                </div>
                <!-- Total Vehicles Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-bus text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Vehicles</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_vehicles']) ?></p>
                            <p class="text-xs text-purple-600 font-medium">Total Fleet</p>
                        </div>
                    </div>
                </div>
                <!-- Total Revenue Card -->
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($stats['total_revenue'], 0) ?></p>
                            <p class="text-xs text-green-600 font-medium">Total Earned</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Per Day -->
            <div class="bg-white rounded-xl shadow-md mb-8">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-ticket-alt text-primary-red mr-2"></i>
                        Bookings Per Day
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Recent confirmed and paid bookings grouped by date</p>
                </div>
                <div class="p-4 md:p-6">
                    <?php if (empty($bookings_per_day)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-ticket-alt text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Recent Bookings</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">No confirmed and paid bookings found for recent dates.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($bookings_per_day as $day): ?>
                                <div class="mobile-card p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-primary-red rounded-full mr-2"></div>
                                            <span class="font-semibold text-gray-900 text-sm">
                                                <?= date('M j, Y', strtotime($day['trip_date'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-ticket-alt text-primary-red w-4 mr-2"></i>
                                            <span><?= $day['booking_count'] ?> Bookings</span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-money-bill-wave text-primary-red w-4 mr-2"></i>
                                            <span>₦<?= number_format($day['revenue'], 0) ?></span>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <a href="bookings.php?date_from=<?= $day['trip_date'] ?>&date_to=<?= $day['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                            <i class="fas fa-eye"></i> View Bookings
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($bookings_per_day as $day): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= date('M j, Y', strtotime($day['trip_date'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= $day['booking_count'] ?> Bookings</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">₦<?= number_format($day['revenue'], 0) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="bookings.php?date_from=<?= $day['trip_date'] ?>&date_to=<?= $day['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Trips -->
            <div class="bg-white rounded-xl shadow-md">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">
                                <i class="fas fa-route text-primary-red mr-2"></i>
                                Upcoming Trips
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">
                                Showing <?= count($recent_trips) ?> of <?= $total_trips_count ?> upcoming scheduled trips
                            </p>
                        </div>
                        <a href="trips.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-eye mr-2"></i>
                            View All
                        </a>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($recent_trips)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-route text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Upcoming Trips</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create a new trip template to start scheduling trips for your passengers.</p>
                            <a href="add_trip.php" class="inline-flex items-center px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Create Trip Template
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($recent_trips as $trip): ?>
                                <div class="mobile-card p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-primary-red rounded-full mr-2"></div>
                                            <span class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?>
                                            </span>
                                        </div>
                                        <span class="status-badge status-<?= $trip['status'] ?> text-xs">
                                            <?= ucfirst($trip['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-bus text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number']) ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-user text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($trip['driver_name'] ?: 'Not Assigned') ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-clock text-primary-red w-4 mr-2"></i>
                                                <span><?= date('g:i A', strtotime($trip['departure_time'])) ?></span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-calendar-alt text-primary-red w-4 mr-2"></i>
                                                <span><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-chair text-primary-red w-4 mr-2"></i>
                                            <span><?= $trip['booked_seats'] ?> of <?= $trip['capacity'] ?> seats booked</span>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-between">
                                        <a href="view_trip.php?trip_id=<?= $trip['id'] ?>&trip_date=<?= $trip['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        <a href="view_bookings.php?trip_id=<?= $trip['id'] ?>&trip_date=<?= $trip['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                            <i class="fas fa-ticket-alt"></i> View Bookings
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle & Driver</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recent_trips as $trip): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4">
                                                <a href="view_trip.php?trip_id=<?= $trip['id'] ?>&trip_date=<?= $trip['trip_date'] ?>" class="flex items-center">
                                                    <div class="w-2 h-2 bg-primary-red rounded-full mr-3"></div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900 hover:text-primary-red">
                                                            <?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['vehicle_number']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($trip['driver_name'] ?: 'Not Assigned') ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($trip['trip_date'])) ?></div>
                                                <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($trip['departure_time'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <i class="fas fa-chair text-primary-red mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900"><?= $trip['booked_seats'] ?> of <?= $trip['capacity'] ?> seats</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="status-badge status-<?= $trip['status'] ?> text-xs">
                                                    <?= ucfirst($trip['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 flex space-x-4">
                                                <a href="view_trip.php?trip_id=<?= $trip['id'] ?>&trip_date=<?= $trip['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="view_bookings.php?trip_id=<?= $trip['id'] ?>&trip_date=<?= $trip['trip_date'] ?>" class="text-primary-red hover:text-dark-red">
                                                    <i class="fas fa-ticket-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="p-4 border-t border-gray-200">
                                <div class="pagination">
                                    <?php 
                                    $query_params = $_GET;
                                    unset($query_params['page']);
                                    $base_url = '?' . http_build_query($query_params) . (empty($query_params) ? '' : '&') . 'page=';
                                    ?>
                                    <?php if ($page > 1): ?>
                                        <a href="<?= $base_url ?>1"><i class="fas fa-angle-double-left"></i></a>
                                        <a href="<?= $base_url . ($page - 1) ?>"><i class="fas fa-angle-left"></i></a>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="current"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="<?= $base_url . $i ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?= $base_url . ($page + 1) ?>"><i class="fas fa-angle-right"></i></a>
                                        <a href="<?= $base_url . $total_pages ?>"><i class="fas fa-angle-double-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>