<?php
// admin/investor_expenses.php
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

// Get filter parameters
$vehicle_filter = isset($_GET['vehicle']) ? (int)$_GET['vehicle'] : 0;
$expense_type_filter = isset($_GET['expense_type']) ? $_GET['expense_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Fetch investor details
$investor_query = "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'investor'";
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

// Fetch investor's vehicles for dropdown
$vehicles_query = "
    SELECT v.id, v.vehicle_number, vt.type as vehicle_type
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    WHERE v.investor_id = ?
    ORDER BY v.vehicle_number
";
$stmt = $conn->prepare($vehicles_query);
$stmt->bind_param("i", $investor_id);
$stmt->execute();
$vehicles_result = $stmt->get_result();
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}
$stmt->close();

// Build WHERE clause for filtering
$where_conditions = ["v.investor_id = ?"];
$params = [$investor_id];
$param_types = "i";

if ($vehicle_filter > 0) {
    $where_conditions[] = "ve.vehicle_id = ?";
    $params[] = $vehicle_filter;
    $param_types .= "i";
}

if (!empty($expense_type_filter)) {
    $where_conditions[] = "ve.expense_type = ?";
    $params[] = $expense_type_filter;
    $param_types .= "s";
}

if (!empty($date_from)) {
    $where_conditions[] = "ve.expense_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "ve.expense_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    WHERE $where_clause
";
$stmt = $conn->prepare($count_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_result = $stmt->get_result();
$total_count = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_count / $per_page);
$stmt->close();

// Fetch expenses
$expenses_query = "
    SELECT 
        ve.id,
        ve.expense_type,
        ve.amount,
        ve.description,
        ve.receipt_path,
        ve.expense_date,
        ve.created_at,
        v.vehicle_number,
        vt.type as vehicle_type,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    JOIN users u ON ve.created_by = u.id
    WHERE $where_clause
    ORDER BY ve.expense_date DESC, ve.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt = $conn->prepare($expenses_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$expenses_result = $stmt->get_result();
$expenses = [];
$total_amount = 0;
while ($row = $expenses_result->fetch_assoc()) {
    $expenses[] = $row;
    $total_amount += $row['amount'];
}
$stmt->close();

// Fetch expense summary by type
$summary_query = "
    SELECT 
        ve.expense_type,
        SUM(ve.amount) as total_amount,
        COUNT(*) as count
    FROM vehicle_expenses ve
    JOIN vehicles v ON ve.vehicle_id = v.id
    WHERE $where_clause
    GROUP BY ve.expense_type
    ORDER BY total_amount DESC
";
$stmt = $conn->prepare($summary_query);
// Remove the last two parameters (LIMIT and OFFSET) from params
$summary_params = array_slice($params, 0, -2);
$summary_param_types = substr($param_types, 0, -2);
$stmt->bind_param($summary_param_types, ...$summary_params);
$stmt->execute();
$summary_result = $stmt->get_result();
$expense_summary = [];
while ($row = $summary_result->fetch_assoc()) {
    $expense_summary[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Expenses - <?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?></title>
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
                        <div class="flex items-center mb-2">
                            <a href="investor_dashboard.php?id=<?= $investor_id ?>" class="text-primary-red hover:text-dark-red mr-3">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                                <i class="fas fa-receipt text-primary-red mr-3"></i>
                                Vehicle Expenses
                            </h1>
                        </div>
                        <p class="text-gray-600"><?= htmlspecialchars($investor['first_name'] . ' ' . $investor['last_name']) ?></p>
                    </div>
                    <button onclick="openAddExpenseModal()" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Add Expense
                    </button>
                </div>
            </div>

            <!-- Expense Summary -->
            <?php if (!empty($expense_summary)): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                <?php foreach ($expense_summary as $summary): ?>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">
                            <?= ucfirst($summary['expense_type']) ?>
                        </div>
                        <div class="text-lg font-bold text-gray-900">₦<?= number_format($summary['total_amount'], 0) ?></div>
                        <div class="text-xs text-gray-500"><?= $summary['count'] ?> transactions</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md mb-6">
                <div class="p-4 md:p-6">
                    <form method="GET" action="" class="space-y-4">
                        <input type="hidden" name="id" value="<?= $investor_id ?>">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Vehicle</label>
                                <select name="vehicle" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">All Vehicles</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?= $vehicle['id'] ?>" <?= $vehicle_filter == $vehicle['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vehicle['vehicle_number']) ?> - <?= htmlspecialchars($vehicle['vehicle_type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Expense Type</label>
                                <select name="expense_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">All Types</option>
                                    <option value="fuel" <?= $expense_type_filter == 'fuel' ? 'selected' : '' ?>>Fuel</option>
                                    <option value="maintenance" <?= $expense_type_filter == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                    <option value="insurance" <?= $expense_type_filter == 'insurance' ? 'selected' : '' ?>>Insurance</option>
                                    <option value="repairs" <?= $expense_type_filter == 'repairs' ? 'selected' : '' ?>>Repairs</option>
                                    <option value="other" <?= $expense_type_filter == 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                                <input type="date" name="date_from" value="<?= $date_from ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                                <input type="date" name="date_to" value="<?= $date_to ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="px-6 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                            <a href="investor_expenses.php?id=<?= $investor_id ?>" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Expenses List -->
            <div class="bg-white rounded-xl shadow-md">
                <div class="p-4 md:p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-gray-900">
                                <i class="fas fa-list text-primary-red mr-2"></i>
                                Expense Records
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">
                                Showing <?= count($expenses) ?> of <?= $total_count ?> expenses • Total: ₦<?= number_format($total_amount, 0) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <?php if (empty($expenses)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-receipt text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Expenses Found</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Start tracking expenses by adding your first entry.</p>
                            <button onclick="openAddExpenseModal()" class="inline-flex items-center px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Add Expense
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($expenses as $expense): ?>
                                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($expense['vehicle_number']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($expense['vehicle_type']) ?></p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?= ucfirst($expense['expense_type']) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($expense['description']) ?></p>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-lg font-bold text-red-600">₦<?= number_format($expense['amount'], 0) ?></span>
                                        <span class="text-sm text-gray-500"><?= date('M j, Y', strtotime($expense['expense_date'])) ?></span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t">
                                        <span>By: <?= htmlspecialchars($expense['created_by_name']) ?></span>
                                        <?php if ($expense['receipt_path']): ?>
                                            <a href="../<?= htmlspecialchars($expense['receipt_path']) ?>" target="_blank" class="text-primary-red hover:text-dark-red">
                                                <i class="fas fa-paperclip mr-1"></i>Receipt
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table -->
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
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($expense['vehicle_number']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($expense['vehicle_type']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    <?= ucfirst($expense['expense_type']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($expense['description']) ?></td>
                                            <td class="px-6 py-4 text-right text-sm font-bold text-red-600">₦<?= number_format($expense['amount'], 0) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($expense['created_by_name']) ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <?php if ($expense['receipt_path']): ?>
                                                    <a href="../<?= htmlspecialchars($expense['receipt_path']) ?>" target="_blank" 
                                                       class="text-primary-red hover:text-dark-red" title="View Receipt">
                                                        <i class="fas fa-paperclip"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <button onclick="deleteExpense(<?= $expense['id'] ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

    <!-- Add Expense Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-receipt text-primary-red mr-2"></i>
                        Add Expense
                    </h3>
                    <button onclick="closeExpenseModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <form id="expenseForm" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="investor_id" value="<?= $investor_id ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vehicle *</label>
                        <select name="vehicle_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>">
                                    <?= htmlspecialchars($vehicle['vehicle_number']) ?> - <?= htmlspecialchars($vehicle['vehicle_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Expense Type *</label>
                            <select name="expense_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                <option value="">Select Type</option>
                                <option value="fuel">Fuel</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="insurance">Insurance</option>
                                <option value="repairs">Repairs</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount (₦) *</label>
                            <input type="number" name="amount" step="0.01" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expense Date *</label>
                        <input type="date" name="expense_date" required value="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                        <textarea name="description" rows="3" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Receipt (Optional)</label>
                        <input type="file" name="receipt" accept="image/*,.pdf"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Upload image or PDF (Max 5MB)</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeExpenseModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-dark-red">
                        <i class="fas fa-save mr-2"></i>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddExpenseModal() {
            document.getElementById('expenseModal').classList.add('active');
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').classList.remove('active');
            document.getElementById('expenseForm').reset();
        }

        document.getElementById('expenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('ajax/save_expense.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense added successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        function deleteExpense(expenseId) {
            if (!confirm('Are you sure you want to delete this expense?')) {
                return;
            }
            
            fetch('ajax/delete_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ expense_id: expenseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense deleted successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>