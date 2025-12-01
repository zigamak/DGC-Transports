<?php
// admin/investors.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build WHERE clause for filtering
$where_conditions = ["u.role = 'investor'"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_count = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);
$stmt->close();

// Fetch investors with their vehicle count
$investors_query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.created_at,
        COUNT(DISTINCT v.id) as vehicle_count,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' AND b.status = 'confirmed' THEN b.total_amount ELSE 0 END), 0) as total_revenue
    FROM users u
    LEFT JOIN vehicles v ON u.id = v.investor_id
    LEFT JOIN trip_templates tt ON v.id = tt.vehicle_id
    LEFT JOIN bookings b ON tt.id = b.template_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($investors_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$investors_result = $stmt->get_result();
$investors = [];
while ($row = $investors_result->fetch_assoc()) {
    $investors[] = $row;
}
$stmt->close();

// Fetch all vehicles for assignment dropdown
$vehicles_query = "
    SELECT v.id, v.vehicle_number, v.driver_name, vt.type as vehicle_type, v.investor_id,
           CONCAT(u.first_name, ' ', u.last_name) as current_investor
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    LEFT JOIN users u ON v.investor_id = u.id AND u.role = 'investor'
    ORDER BY v.vehicle_number
";
$vehicles_result = $conn->query($vehicles_query);
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}

// Fetch statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT u.id) as total_investors,
        COUNT(DISTINCT v.id) as total_assigned_vehicles,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' AND b.status = 'confirmed' THEN b.total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(ve.amount), 0) as total_expenses
    FROM users u
    LEFT JOIN vehicles v ON u.id = v.investor_id
    LEFT JOIN trip_templates tt ON v.id = tt.vehicle_id
    LEFT JOIN bookings b ON tt.id = b.template_id
    LEFT JOIN vehicle_expenses ve ON v.id = ve.vehicle_id
    WHERE u.role = 'investor'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investors Management - DGC Transports</title>
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .table-hover:hover {
            background-color: #f9fafb;
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
                            <i class="fas fa-handshake text-primary-red mr-3"></i>
                            Investors Management
                        </h1>
                        <p class="text-gray-600 mt-1">Manage investors, their vehicles, and track financial performance</p>
                    </div>
                    <button onclick="openAddInvestorModal()" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Add Investor
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-handshake text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Investors</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_investors']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-bus text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Assigned Vehicles</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900"><?= number_format($stats['total_assigned_vehicles']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Revenue</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($stats['total_revenue'], 0) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 md:p-6 hover:shadow-lg transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="bg-primary-red p-3 rounded-lg">
                            <i class="fas fa-receipt text-white text-lg"></i>
                        </div>
                        <div class="ml-4 flex-1">
                            <p class="text-xs md:text-sm font-medium text-gray-600 uppercase tracking-wide">Total Expenses</p>
                            <p class="text-xl md:text-2xl font-bold text-gray-900">₦<?= number_format($stats['total_expenses'], 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="p-4 md:p-6">
                    <form method="GET" action="" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search by name, email, or phone..." 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                        <button type="submit" class="px-6 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="investors.php" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Investors List -->
            <div class="bg-white rounded-xl shadow-md">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900">
                        <i class="fas fa-users text-primary-red mr-2"></i>
                        Investors List
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">Showing <?= count($investors) ?> of <?= $total_count ?> investors</p>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($investors)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-handshake text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Investors Found</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Start by adding your first investor to the system.</p>
                            <button onclick="openAddInvestorModal()" class="inline-flex items-center px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Investor
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($investors as $investor): ?>
                                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900">
                                                <?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($investor['email']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-phone text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($investor['phone']) ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-bus text-primary-red w-4 mr-2"></i>
                                            <span><?= $investor['vehicle_count'] ?> Vehicle(s)</span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-money-bill-wave text-primary-red w-4 mr-2"></i>
                                            <span>₦<?= number_format($investor['total_revenue'], 0) ?> Revenue</span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-calendar text-primary-red w-4 mr-2"></i>
                                            <span>Joined <?= date('M j, Y', strtotime($investor['created_at'])) ?></span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button onclick="viewInvestorDetails(<?= $investor['id'] ?>)" class="flex-1 px-3 py-1.5 bg-primary-red text-white text-sm rounded hover:bg-dark-red">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                        <button onclick="openEditInvestorModal(<?= $investor['id'] ?>)" class="flex-1 px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </button>
                                        <button onclick="manageVehicles(<?= $investor['id'] ?>)" class="flex-1 px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                            <i class="fas fa-bus mr-1"></i> Vehicles
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicles</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($investors as $investor): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-primary-red rounded-full flex items-center justify-center text-white font-bold">
                                                        <?= strtoupper(substr($investor['first_name'], 0, 1) . substr($investor['last_name'], 0, 1)) ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($investor['email']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($investor['phone']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <i class="fas fa-bus text-primary-red mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900"><?= $investor['vehicle_count'] ?> Vehicle(s)</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">₦<?= number_format($investor['total_revenue'], 0) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-500"><?= date('M j, Y', strtotime($investor['created_at'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex space-x-3">
                                                    <button onclick="viewInvestorDetails(<?= $investor['id'] ?>)" class="text-primary-red hover:text-dark-red" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button onclick="openEditInvestorModal(<?= $investor['id'] ?>)" class="text-blue-600 hover:text-blue-800" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="manageVehicles(<?= $investor['id'] ?>)" class="text-green-600 hover:text-green-800" title="Manage Vehicles">
                                                        <i class="fas fa-bus"></i>
                                                    </button>
                                                    <button onclick="manageExpenses(<?= $investor['id'] ?>)" class="text-purple-600 hover:text-purple-800" title="View Expenses">
                                                        <i class="fas fa-receipt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="p-4 border-t border-gray-200 mt-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <?php 
                                    $query_params = $_GET;
                                    unset($query_params['page']);
                                    $base_url = '?' . http_build_query($query_params) . (empty($query_params) ? '' : '&') . 'page=';
                                    ?>
                                    <?php if ($page > 1): ?>
                                        <a href="<?= $base_url ?>1" class="px-3 py-1 text-sm font-medium text-primary-red hover:bg-gray-100 rounded-md">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="<?= $base_url . ($page - 1) ?>" class="px-3 py-1 text-sm font-medium text-primary-red hover:bg-gray-100 rounded-md">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="px-3 py-1 text-sm font-medium bg-primary-red text-white rounded-md"><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="<?= $base_url . $i ?>" class="px-3 py-1 text-sm font-medium text-primary-red hover:bg-gray-100 rounded-md"><?= $i ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?= $base_url . ($page + 1) ?>" class="px-3 py-1 text-sm font-medium text-primary-red hover:bg-gray-100 rounded-md">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="<?= $base_url . $total_pages ?>" class="px-3 py-1 text-sm font-medium text-primary-red hover:bg-gray-100 rounded-md">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Investor Modal -->
    <div id="investorModal" class="modal">
        <div class="modal-content">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-handshake text-primary-red mr-2"></i>
                        <span id="modalTitle">Add Investor</span>
                    </h3>
                    <button onclick="closeInvestorModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <form id="investorForm" class="p-6">
                <input type="hidden" id="investor_id" name="investor_id">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                        <input type="tel" id="phone" name="phone" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                    </div>
                    <div id="passwordField">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" id="password" name="password"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password (when editing)</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeInvestorModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">
                        <i class="fas fa-save mr-2"></i>Save Investor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vehicle Management Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-bus text-primary-red mr-2"></i>
                        Manage Vehicles
                    </h3>
                    <button onclick="closeVehicleModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="vehicleContent"></div>
            </div>
        </div>
    </div>

    <!-- Expense Management Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-receipt text-primary-red mr-2"></i>
                        Vehicle Expenses
                    </h3>
                    <button onclick="closeExpenseModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div id="expenseContent"></div>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openAddInvestorModal() {
            document.getElementById('modalTitle').textContent = 'Add Investor';
            document.getElementById('investorForm').reset();
            document.getElementById('investor_id').value = '';
            document.getElementById('password').required = true;
            document.getElementById('investorModal').classList.add('active');
        }

        function openEditInvestorModal(investorId) {
            document.getElementById('modalTitle').textContent = 'Edit Investor';
            document.getElementById('investor_id').value = investorId;
            document.getElementById('password').required = false;
            
            // Fetch investor data
            fetch(`ajax/get_investor.php?id=${investorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('first_name').value = data.investor.first_name;
                        document.getElementById('last_name').value = data.investor.last_name;
                        document.getElementById('email').value = data.investor.email;
                        document.getElementById('phone').value = data.investor.phone;
                        document.getElementById('investorModal').classList.add('active');
                    }
                });
        }

        function closeInvestorModal() {
            document.getElementById('investorModal').classList.remove('active');
        }

        function closeVehicleModal() {
            document.getElementById('vehicleModal').classList.remove('active');
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').classList.remove('active');
        }

        // Form Submission
        document.getElementById('investorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('ajax/save_investor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Investor saved successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });

        function viewInvestorDetails(investorId) {
            window.location.href = `investor_dashboard.php?id=${investorId}`;
        }

        function manageVehicles(investorId) {
            fetch(`ajax/get_investor_vehicles.php?id=${investorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayVehicles(data, investorId);
                        document.getElementById('vehicleModal').classList.add('active');
                    }
                });
        }

        function displayVehicles(data, investorId) {
            const content = document.getElementById('vehicleContent');
            let html = `
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Investor: ${data.investor_name}</h4>
                    <p class="text-sm text-gray-600">Assign or unassign vehicles to this investor</p>
                </div>
                <div class="space-y-3">
            `;
            
            // Get vehicles from the data returned by the AJAX call
            if (data.vehicles && data.vehicles.length > 0) {
                data.vehicles.forEach(vehicle => {
                    const isAssigned = vehicle.investor_id == investorId;
                    const currentInvestorWarning = vehicle.investor_id && vehicle.investor_id != investorId ? 
                        `<p class="text-xs text-red-600">Currently assigned to: ${vehicle.current_investor || 'N/A'}</p>` : '';
                    
                    html += `
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">${vehicle.vehicle_type} - ${vehicle.vehicle_number}</p>
                                <p class="text-sm text-gray-600">Driver: ${vehicle.driver_name}</p>
                                ${currentInvestorWarning}
                            </div>
                            <button onclick="toggleVehicleAssignment(${vehicle.id}, ${investorId}, ${isAssigned})"
                                    class="px-4 py-2 text-sm rounded-lg ${isAssigned ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'} text-white">
                                ${isAssigned ? '<i class="fas fa-times mr-1"></i> Unassign' : '<i class="fas fa-check mr-1"></i> Assign'}
                            </button>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-center text-gray-500 py-4">No vehicles available</p>';
            }
            
            html += '</div>';
            content.innerHTML = html;
        }

        function toggleVehicleAssignment(vehicleId, investorId, isAssigned) {
            const action = isAssigned ? 'unassign' : 'assign';
            
            fetch('ajax/manage_vehicle_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicle_id: vehicleId, investor_id: investorId, action: action })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    manageVehicles(investorId); // Refresh the modal
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function manageExpenses(investorId) {
            window.location.href = `investor_expenses.php?id=${investorId}`;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>