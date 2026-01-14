<?php
// investor/dashboard.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('investor', '/login.php');

$investor_id = $_SESSION['user']['id'];
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = date('Y-m-t');

// Fetch investor info
$investor_query = "SELECT id, first_name, last_name, email, phone FROM users WHERE id = ? AND role = 'investor'";
$stmt = $conn->prepare($investor_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$investor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch vehicles with performance data
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
$total_revenue = $total_expenses = $total_bookings = 0;

while ($row = $vehicles_result->fetch_assoc()) {
    $row['profit'] = $row['revenue'] - $row['expenses'];
    $vehicles[] = $row;
    $total_revenue += $row['revenue'];
    $total_expenses += $row['expenses'];
    $total_bookings += $row['total_bookings'];
}
$stmt->close();

$total_profit = $total_revenue - $total_expenses;

// Recent Expenses (Last 10)
$expenses_query = "
    SELECT 
        ve.id, ve.expense_type, ve.amount, ve.description, 
        ve.receipt_path, ve.expense_date, v.vehicle_number
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    WHERE v.investor_id = ? AND ve.expense_date BETWEEN ? AND ?
    ORDER BY ve.expense_date DESC
    LIMIT 10
";
$stmt = $conn->prepare($expenses_query);
$stmt->bind_param("iss", $investor_id, $date_from, $date_to);
$stmt->execute();
$recent_expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Revenue Trend (Last 6 months)
$revenue_trend_query = "
    SELECT 
        DATE_FORMAT(b.trip_date, '%Y-%m') as month,
        SUM(b.total_amount) as revenue
    FROM bookings b
    JOIN trip_templates tt ON b.template_id = tt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    WHERE v.investor_id = ? 
      AND b.payment_status = 'paid' AND b.status = 'confirmed'
      AND b.trip_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
";
$stmt = $conn->prepare($revenue_trend_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$revenue_trend = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investor Dashboard - DGC Transports</title>
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
        .table-hover:hover { background-color: #f9fafb; }
        .profit-positive { color: #10b981; }
        .profit-negative { color: #ef4444; }
        
        @media (max-width: 768px) {
            .mobile-card {
                background: white;
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../templates/sidebar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 ml-0 md:ml-64 pt-16 md:pt-8 pb-24">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                My Investment Dashboard
            </h1>
            <p class="text-sm md:text-base text-gray-600">
                Track your fleet performance, revenue, and expenses
            </p>
            <p class="text-xs md:text-sm text-gray-500 mt-2">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?= date('M j, Y', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?>
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
            <div class="bg-white rounded-xl shadow-md p-4 md:p-6">
                <div class="flex items-center">
                    <div class="bg-green-500 p-3 md:p-4 rounded-xl">
                        <i class="fas fa-money-bill-wave text-white text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-5">
                        <p class="text-xs md:text-sm font-medium text-gray-600 uppercase">Total Revenue</p>
                        <p class="text-xl md:text-3xl font-bold text-gray-900">₦<?= number_format($total_revenue) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-4 md:p-6">
                <div class="flex items-center">
                    <div class="bg-red-500 p-3 md:p-4 rounded-xl">
                        <i class="fas fa-receipt text-white text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-5">
                        <p class="text-xs md:text-sm font-medium text-gray-600 uppercase">Total Expenses</p>
                        <p class="text-xl md:text-3xl font-bold text-gray-900">₦<?= number_format($total_expenses) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-4 md:p-6">
                <div class="flex items-center">
                    <div class="<?= $total_profit >= 0 ? 'bg-blue-500' : 'bg-orange-500' ?> p-3 md:p-4 rounded-xl">
                        <i class="fas fa-chart-line text-white text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-5">
                        <p class="text-xs md:text-sm font-medium text-gray-600 uppercase">Net Profit/Loss</p>
                        <p class="text-xl md:text-3xl font-bold <?= $total_profit >= 0 ? 'profit-positive' : 'profit-negative' ?>">₦<?= number_format($total_profit) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-4 md:p-6">
                <div class="flex items-center">
                    <div class="bg-purple-500 p-3 md:p-4 rounded-xl">
                        <i class="fas fa-bus text-white text-xl md:text-2xl"></i>
                    </div>
                    <div class="ml-3 md:ml-5">
                        <p class="text-xs md:text-sm font-medium text-gray-600 uppercase">Active Vehicles</p>
                        <p class="text-xl md:text-3xl font-bold text-gray-900"><?= count($vehicles) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Trend Chart -->
        <?php if (!empty($revenue_trend)): ?>
        <div class="bg-white rounded-xl shadow-md mb-6 md:mb-8">
            <div class="p-4 md:p-6 border-b">
                <h2 class="text-lg md:text-xl font-bold text-gray-900">Revenue Trend (Last 6 Months)</h2>
            </div>
            <div class="p-4 md:p-6">
                <canvas id="revenueTrendChart" height="100"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Vehicle Performance Table -->
        <div class="bg-white rounded-xl shadow-md mb-6 md:mb-8">
            <div class="p-4 md:p-6 border-b">
                <h2 class="text-lg md:text-xl font-bold text-gray-900">Vehicle Performance</h2>
            </div>
            <div class="p-4 md:p-6">
                <?php if (empty($vehicles)): ?>
                    <div class="text-center py-12 md:py-16">
                        <i class="fas fa-bus text-gray-400 text-4xl md:text-6xl mb-4"></i>
                        <h3 class="text-xl md:text-2xl font-bold text-gray-800">No Vehicles Assigned</h3>
                        <p class="text-sm md:text-base text-gray-600 mt-2">Contact admin to link vehicles to your investor account.</p>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Vehicle</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Driver</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Revenue</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Expenses</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Profit</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Bookings</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($vehicles as $v): ?>
                                <tr class="table-hover transition">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($v['vehicle_type']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($v['vehicle_number']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($v['driver_name'] ?? 'Not Assigned') ?></td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-green-600">₦<?= number_format($v['revenue']) ?></td>
                                    <td class="px-6 py-4 text-right text-sm font-medium text-red-600">₦<?= number_format($v['expenses']) ?></td>
                                    <td class="px-6 py-4 text-right text-sm font-bold <?= $v['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        ₦<?= number_format($v['profit']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-medium text-gray-900"><?= $v['total_bookings'] ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="openExpensesModal(<?= $v['id'] ?>, '<?= htmlspecialchars($v['vehicle_number'], ENT_QUOTES) ?>')"
                                                class="text-primary-red hover:text-dark-red font-semibold text-sm transition">
                                            View Expenses
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 text-left">TOTAL</td>
                                    <td class="px-6 py-4 text-right text-green-600">₦<?= number_format($total_revenue) ?></td>
                                    <td class="px-6 py-4 text-right text-red-600">₦<?= number_format($total_expenses) ?></td>
                                    <td class="px-6 py-4 text-right <?= $total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        ₦<?= number_format($total_profit) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center"><?= $total_bookings ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-3">
                        <?php foreach ($vehicles as $v): ?>
                        <div class="mobile-card border border-gray-200">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($v['vehicle_type']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($v['vehicle_number']) ?></div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        <i class="fas fa-user mr-1"></i><?= htmlspecialchars($v['driver_name'] ?? 'Not Assigned') ?>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-500">Bookings</div>
                                    <div class="text-lg font-bold text-gray-900"><?= $v['total_bookings'] ?></div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-2 mb-3 pt-3 border-t">
                                <div>
                                    <div class="text-xs text-gray-500">Revenue</div>
                                    <div class="text-sm font-semibold text-green-600">₦<?= number_format($v['revenue']) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Expenses</div>
                                    <div class="text-sm font-semibold text-red-600">₦<?= number_format($v['expenses']) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Profit</div>
                                    <div class="text-sm font-bold <?= $v['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        ₦<?= number_format($v['profit']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <button onclick="openExpensesModal(<?= $v['id'] ?>, '<?= htmlspecialchars($v['vehicle_number'], ENT_QUOTES) ?>')"
                                    class="w-full py-2 text-sm font-semibold text-primary-red border border-primary-red rounded-lg hover:bg-primary-red hover:text-white transition">
                                View Expenses
                            </button>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Mobile Total Card -->
                        <div class="mobile-card bg-gray-50 border-2 border-gray-300">
                            <div class="text-sm font-bold text-gray-900 mb-3">TOTAL</div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <div class="text-xs text-gray-500">Revenue</div>
                                    <div class="text-sm font-bold text-green-600">₦<?= number_format($total_revenue) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Expenses</div>
                                    <div class="text-sm font-bold text-red-600">₦<?= number_format($total_expenses) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Profit</div>
                                    <div class="text-sm font-bold <?= $total_profit >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        ₦<?= number_format($total_profit) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Expenses -->
        <?php if (!empty($recent_expenses)): ?>
        <div class="bg-white rounded-xl shadow-md">
            <div class="p-4 md:p-6 border-b">
                <h2 class="text-lg md:text-xl font-bold text-gray-900">Recent Expenses (Last 10)</h2>
            </div>
            <div class="p-4 md:p-6">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_expenses as $e): ?>
                            <tr class="table-hover">
                                <td class="px-6 py-4 text-sm"><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
                                <td class="px-6 py-4 text-sm font-medium"><?= htmlspecialchars($e['vehicle_number']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <?= ucfirst($e['expense_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($e['description'] ?: '-') ?></td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-red-600">₦<?= number_format($e['amount']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($e['receipt_path']): ?>
                                        <a href="../<?= htmlspecialchars($e['receipt_path']) ?>" target="_blank" class="text-primary-red hover:text-dark-red">
                                            View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Cards -->
                <div class="md:hidden space-y-3">
                    <?php foreach ($recent_expenses as $e): ?>
                    <div class="mobile-card border border-gray-200">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="text-xs text-gray-500"><?= date('M j, Y', strtotime($e['expense_date'])) ?></div>
                                <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($e['vehicle_number']) ?></div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                <?= ucfirst($e['expense_type']) ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($e['description'] ?: '-') ?></div>
                        <div class="flex justify-between items-center pt-2 border-t">
                            <div class="text-lg font-bold text-red-600">₦<?= number_format($e['amount']) ?></div>
                            <?php if ($e['receipt_path']): ?>
                                <a href="../<?= htmlspecialchars($e['receipt_path']) ?>" target="_blank" 
                                   class="text-xs text-primary-red hover:text-dark-red font-semibold">
                                    <i class="fas fa-receipt mr-1"></i>View Receipt
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sticky Filter Button (Bottom Right) -->
    <button onclick="openFilterModal()" 
            class="fixed bottom-6 right-6 bg-primary-red text-white p-4 rounded-full shadow-2xl hover:bg-dark-red transition-all z-40 hover:scale-110">
        <i class="fas fa-filter text-xl"></i>
    </button>

    <!-- Filter Modal -->
    <div id="filterModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-6 border-b bg-gray-50">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-calendar-alt text-primary-red mr-2"></i>Filter by Date
                    </h3>
                    <button onclick="closeFilterModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                        ×
                    </button>
                </div>
            </div>
            <form method="GET" class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-primary-red">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-primary-red">
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button type="button" onclick="closeFilterModal()" 
                            class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-6 py-3 bg-primary-red text-white rounded-lg hover:bg-dark-red transition font-semibold">
                        <i class="fas fa-search mr-2"></i>Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Modal -->
    <div id="expensesModal" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-4 md:p-6 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-lg md:text-2xl font-bold text-gray-900">
                    Expenses — <span id="modalVehicleNumber" class="text-primary-red"></span>
                </h3>
                <button onclick="closeExpensesModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    ×
                </button>
            </div>
            <div id="expensesContent" class="p-4 md:p-6 flex-1 overflow-y-auto">
                <p class="text-center text-gray-500 py-12">Loading expenses...</p>
            </div>
            <div class="p-4 md:p-6 border-t bg-gray-50 text-right">
                <button onclick="closeExpensesModal()" class="px-6 md:px-8 py-2 md:py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Filter Modal Functions
        function openFilterModal() {
            document.getElementById('filterModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeFilterModal() {
            document.getElementById('filterModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Expenses Modal Functions
        function openExpensesModal(vehicleId, vehicleNumber) {
            document.getElementById('modalVehicleNumber').textContent = vehicleNumber;
            document.getElementById('expensesModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Reset content to "Loading"
            const content = document.getElementById('expensesContent');
            content.innerHTML = `<p class="text-center text-gray-500 py-12"><i class="fas fa-spinner fa-spin text-3xl mb-3"></i><br>Loading expenses...</p>`;

            fetch(`get_vehicle_expenses.php?vehicle_id=${vehicleId}&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>`)
                .then(r => r.json())
                .then(data => {
                    const content = document.getElementById('expensesContent');
                    
                    if (data.success && data.expenses.length > 0) {
                        const total = data.expenses.reduce((s, e) => s + parseFloat(e.amount), 0);
                        
                        // Desktop Table
                        const desktopRows = data.expenses.map(e => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">${new Date(e.expense_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'})}</td>
                                <td class="px-4 py-3"><span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100">${e.expense_type.charAt(0).toUpperCase() + e.expense_type.slice(1)}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-700">${e.description || '-'}</td>
                                <td class="px-4 py-3 text-right font-medium text-red-600">₦${parseFloat(e.amount).toLocaleString()}</td>
                                <td class="px-4 py-3 text-center">
                                    ${e.receipt_path ? `<a href="../${e.receipt_path}" target="_blank" class="text-primary-red hover:underline">View</a>` : '-'}
                                </td>
                            </tr>
                        `).join('');

                        // Mobile Cards
                        const mobileCards = data.expenses.map(e => `
                            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-3">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="text-xs text-gray-500">${new Date(e.expense_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'})}</div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100">
                                        ${e.expense_type.charAt(0).toUpperCase() + e.expense_type.slice(1)}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-700 mb-2">${e.description || '-'}</div>
                                <div class="flex justify-between items-center pt-2 border-t">
                                    <div class="text-lg font-bold text-red-600">₦${parseFloat(e.amount).toLocaleString()}</div>
                                    ${e.receipt_path ? `<a href="../${e.receipt_path}" target="_blank" class="text-xs text-primary-red hover:text-dark-red font-semibold"><i class="fas fa-receipt mr-1"></i>View</a>` : ''}
                                </div>
                            </div>
                        `).join('');

                        content.innerHTML = `
                            <div class="mb-6 flex justify-between items-center">
                                <span class="text-lg md:text-xl font-bold text-red-600">Total: ₦${total.toLocaleString()}</span>
                                <span class="text-sm text-gray-500">${data.expenses.length} expense${data.expenses.length !== 1 ? 's' : ''}</span>
                            </div>
                            
                            <!-- Desktop Table -->
                            <div class="hidden md:block">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase">Description</th>
                                            <th class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Amount</th>
                                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">${desktopRows}</tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile Cards -->
                            <div class="md:hidden">${mobileCards}</div>
                        `;
                    } else if (data.success && data.expenses.length === 0) {
                        content.innerHTML = `
                            <div class="text-center py-16 text-gray-500">
                                <i class="fas fa-receipt text-6xl mb-4 text-gray-300"></i>
                                <p class="text-lg font-semibold">No expenses recorded</p>
                                <p class="text-sm mt-2">No expenses found for this vehicle in the selected period.</p>
                            </div>`;
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-16">
                                <i class="fas fa-exclamation-circle text-red-500 text-6xl mb-4"></i>
                                <p class="text-red-600 font-semibold">Error: ${data.message || 'Failed to load expenses'}</p>
                            </div>`;
                    }
                })
                .catch(() => {
                    document.getElementById('expensesContent').innerHTML = `
                        <div class="text-center py-16">
                            <i class="fas fa-wifi text-gray-400 text-6xl mb-4"></i>
                            <p class="text-red-600 font-semibold">Connection Error</p>
                            <p class="text-sm text-gray-600 mt-2">Failed to load expenses. Please check your connection.</p>
                        </div>`;
                });
        }

        function closeExpensesModal() {
            document.getElementById('expensesModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modals on background click
        document.getElementById('filterModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeFilterModal();
        });

        document.getElementById('expensesModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeExpensesModal();
        });

        // Close modals on Escape key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeFilterModal();
                closeExpensesModal();
            }
        });
    </script>

    <?php if (!empty($revenue_trend)): ?>
    <script>
        // Revenue Trend Chart
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(fn($m) => date('M Y', strtotime($m . '-01')), array_column($revenue_trend, 'month'))) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode(array_column($revenue_trend, 'revenue')) ?>,
                    borderColor: '#e30613',
                    backgroundColor: 'rgba(227, 6, 19, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e30613',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            callback: v => '₦' + v.toLocaleString()
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>