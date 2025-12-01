<?php
// admin/investor_dashboard.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

$investor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($investor_id <= 0) {
    header('Location: investors.php');
    exit;
}

// Get date range from filters (default to current month)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Fetch investor details
$investor_query = "SELECT id, first_name, last_name, email, phone, created_at FROM users WHERE id = ? AND role = 'investor'";
$stmt = $conn->prepare($investor_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$investor_result = $stmt->get_result();

if ($investor_result->num_rows === 0) {
    header('Location: investors.php');
    exit;
}

$investor = $investor_result->fetch_assoc();
$stmt->close();

// Fetch investor's vehicles with P&L
$vehicles_query = "
    SELECT 
        v.id,
        v.vehicle_number,
        v.driver_name,
        vt.type as vehicle_type,
        vt.capacity,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' AND b.status = 'confirmed' AND b.trip_date BETWEEN ? AND ? THEN b.total_amount ELSE 0 END), 0) as revenue,
        COALESCE((SELECT SUM(amount) FROM vehicle_expenses WHERE vehicle_id = v.id AND expense_date BETWEEN ? AND ?), 0) as expenses,
        COUNT(DISTINCT CASE WHEN b.payment_status = 'paid' AND b.status = 'confirmed' AND b.trip_date BETWEEN ? AND ? THEN b.id END) as total_bookings
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    LEFT JOIN trip_templates tt ON v.id = tt.vehicle_id
    LEFT JOIN bookings b ON tt.id = b.template_id
    WHERE v.investor_id = ?
    GROUP BY v.id
    ORDER BY v.vehicle_number
";
$stmt = $conn->prepare($vehicles_query);
$stmt->bind_param("ssssssi", $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $investor_id);
$stmt->execute();
$vehicles_result = $stmt->get_result();
$vehicles = [];
$total_revenue = 0;
$total_expenses = 0;
$total_bookings = 0;

while ($row = $vehicles_result->fetch_assoc()) {
    $row['profit'] = $row['revenue'] - $row['expenses'];
    $vehicles[] = $row;
    $total_revenue += $row['revenue'];
    $total_expenses += $row['expenses'];
    $total_bookings += $row['total_bookings'];
}
$stmt->close();

$total_profit = $total_revenue - $total_expenses;

// Fetch recent expenses
$expenses_query = "
    SELECT 
        ve.id,
        ve.expense_type,
        ve.amount,
        ve.description,
        ve.receipt_path,
        ve.expense_date,
        v.vehicle_number,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    JOIN users u ON ve.created_by = u.id
    WHERE v.investor_id = ? AND ve.expense_date BETWEEN ? AND ?
    ORDER BY ve.expense_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($expenses_query);
$stmt->bind_param("iss", $investor_id, $date_from, $date_to);
$stmt->execute();
$expenses_result = $stmt->get_result();
$recent_expenses = [];
while ($row = $expenses_result->fetch_assoc()) {
    $recent_expenses[] = $row;
}
$stmt->close();

// Fetch revenue trend (last 6 months)
$revenue_trend_query = "
    SELECT 
        DATE_FORMAT(b.trip_date, '%Y-%m') as month,
        SUM(b.total_amount) as revenue
    FROM bookings b
    JOIN trip_templates tt ON b.template_id = tt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    WHERE v.investor_id = ? AND b.payment_status = 'paid' AND b.status = 'confirmed'
        AND b.trip_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(b.trip_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$stmt = $conn->prepare($revenue_trend_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$revenue_trend_result = $stmt->get_result();
$revenue_trend = [];
while ($row = $revenue_trend_result->fetch_assoc()) {
    $revenue_trend[] = $row;
}
$stmt->close();
$revenue_trend = array_reverse($revenue_trend);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investor Dashboard - <?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .table-hover:hover {
            background-color: #f9fafb;
        }
        .profit-positive {
            color: #10b981;
        }
        .profit-negative {
            color: #ef4444;
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
                        <div class="flex items-center mb-2">
                            <a href="investors.php" class="text-primary-red hover:text-dark-red mr-3">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                                <i class="fas fa-user-tie text-primary-red mr-3"></i>
                                <?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?>
                            </h1>
                        </div>
                        <p class="text-gray-600"><?= htmlspecialchars($investor['email']) ?> • <?= htmlspecialchars($investor['phone']) ?></p>
                        <p class="text-sm text-gray-500 mt-1">Member since <?= date('M j, Y', strtotime($investor['created_at'])) ?></p>
                    </div>
                    <button onclick="window.location.href='investor_expenses.php?id=<?= $investor_id ?>'" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-receipt mr-2"></i>
                        Manage Expenses
                    </button>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="p-4 md:p-6">
                    <form method="GET" action="" class="flex flex-col md:flex-row gap-4 items-end">
                        <input type="hidden" name="id" value="<?= $investor_id ?>">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                        <button type="submit" class="px-6 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </form>
                </div>
            </div>

            <!-- P&L Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-green-500 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($total_revenue, 0) ?></p>
                            <p class="text-xs text-gray-500"><?= date('M j', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-red-500 p-3 rounded-lg">
                            <i class="fas fa-receipt text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Expenses</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($total_expenses, 0) ?></p>
                            <p class="text-xs text-gray-500"><?= date('M j', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="<?= $total_profit >= 0 ? 'bg-blue-500' : 'bg-orange-500' ?> p-3 rounded-lg">
                            <i class="fas fa-chart-line text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Profit/Loss</p>
                            <p class="text-xl md:text-2xl font-bold <?= $total_profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                ₦<?= number_format($total_profit, 0) ?>
                            </p>
                            <p class="text-xs text-gray-500">Net for period</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-purple-500 p-3 rounded-lg">
                            <i class="fas fa-ticket-alt text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Bookings</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($total_bookings) ?></p>
                            <p class="text-xs text-gray-500">Total trips</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <?php if (!empty($revenue_trend)): ?>
            <div class="bg-white rounded-xl shadow-md mb-8">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-chart-area text-primary-red mr-2"></i>
                        Revenue Trend (Last 6 Months)
                    </h2>
                </div>
                <div class="p-4 md:p-6">
                    <canvas id="revenueTrendChart" height="80"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Vehicles Performance -->
            <div class="bg-white rounded-xl shadow-md mb-8">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-bus text-primary-red mr-2"></i>
                        Vehicles Performance
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Individual vehicle P&L breakdown</p>
                </div>
                <div class="p-4 md:p-6">
                    <?php if (empty($vehicles)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-bus text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Vehicles Assigned</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">This investor doesn't have any vehicles assigned yet.</p>
                            <a href="investors.php" class="inline-flex items-center px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-bus mr-2"></i>
                                Assign Vehicles
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($vehicles as $vehicle): ?>
                                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($vehicle['vehicle_type']) ?></h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($vehicle['vehicle_number']) ?></p>
                                            <p class="text-xs text-gray-500">Driver: <?= htmlspecialchars($vehicle['driver_name']) ?></p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $vehicle['profit'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $vehicle['profit'] >= 0 ? 'Profit' : 'Loss' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Revenue:</span>
                                            <span class="font-medium text-green-600">₦<?= number_format($vehicle['revenue'], 0) ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Expenses:</span>
                                            <span class="font-medium text-red-600">₦<?= number_format($vehicle['expenses'], 0) ?></span>
                                        </div>
                                        <div class="flex justify-between text-sm pt-2 border-t border-gray-200">
                                            <span class="text-gray-900 font-semibold">Net Profit/Loss:</span>
                                            <span class="font-bold <?= $vehicle['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                ₦<?= number_format($vehicle['profit'], 0) ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Bookings:</span>
                                            <span class="font-medium text-gray-900"><?= $vehicle['total_bookings'] ?> trips</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit/Loss</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($vehicle['vehicle_type']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($vehicle['vehicle_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($vehicle['driver_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="text-sm font-medium text-green-600">₦<?= number_format($vehicle['revenue'], 0) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="text-sm font-medium text-red-600">₦<?= number_format($vehicle['expenses'], 0) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="text-sm font-bold <?= $vehicle['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                    ₦<?= number_format($vehicle['profit'], 0) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="text-sm text-gray-900"><?= $vehicle['total_bookings'] ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="font-bold">
                                        <td colspan="2" class="px-6 py-4 text-sm text-gray-900">TOTAL</td>
                                        <td class="px-6 py-4 text-right text-sm text-green-600">₦<?= number_format($total_revenue, 0) ?></td>
                                        <td class="px-6 py-4 text-right text-sm text-red-600">₦<?= number_format($total_expenses, 0) ?></td>
                                        <td class="px-6 py-4 text-right text-sm <?= $total_profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                            ₦<?= number_format($total_profit, 0) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-900"><?= number_format($total_bookings) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Expenses -->
            <?php if (!empty($recent_expenses)): ?>
            <div class="bg-white rounded-xl shadow-md">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">
                                <i class="fas fa-receipt text-primary-red mr-2"></i>
                                Recent Expenses
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">Latest expense entries for this period</p>
                        </div>
                        <a href="investor_expenses.php?id=<?= $investor_id ?>" class="text-primary-red hover:text-dark-red text-sm font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="p-4 md:p-6">
                    <div class="space-y-3 md:hidden">
                        <?php foreach ($recent_expenses as $expense): ?>
                            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($expense['vehicle_number']) ?></p>
                                        <p class="text-sm text-gray-600"><?= ucfirst($expense['expense_type']) ?></p>
                                    </div>
                                    <p class="font-bold text-red-600">₦<?= number_format($expense['amount'], 0) ?></p>
                                </div>
                                <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($expense['description']) ?></p>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span><?= date('M j, Y', strtotime($expense['expense_date'])) ?></span>
                                    <span>By: <?= htmlspecialchars($expense['created_by_name']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added By</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_expenses as $expense): ?>
                                    <tr class="table-hover">
                                        <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($expense['vehicle_number']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <?= ucfirst($expense['expense_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($expense['description']) ?></td>
                                        <td class="px-6 py-4 text-right text-sm font-medium text-red-600">₦<?= number_format($expense['amount'], 0) ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($expense['created_by_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($revenue_trend)): ?>
    <script>
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($item) { return date('M Y', strtotime($item['month'] . '-01')); }, $revenue_trend)) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode(array_map(function($item) { return $item['revenue']; }, $revenue_trend)) ?>,
                    borderColor: '#e30613',
                    backgroundColor: 'rgba(227, 6, 19, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>