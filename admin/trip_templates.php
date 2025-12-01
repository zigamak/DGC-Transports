<?php
//admin/trip_templates.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $template_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE trip_templates SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $success = 'Trip template deleted successfully.';
    } else {
        $error = 'Failed to delete trip template.';
    }
    $stmt->close();
}

// Fetch all trip templates with booking count
$query = "
    SELECT tt.id, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time, 
           tt.price, tt.recurrence_type, tt.recurrence_days, tt.start_date, tt.end_date, tt.status,
           vt.capacity,
           COALESCE(SUM(CASE WHEN b.status != 'cancelled' THEN 1 ELSE 0 END), 0) as total_bookings
    FROM trip_templates tt
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN bookings b ON tt.id = b.template_id
    WHERE tt.status = 'active'
    GROUP BY tt.id, c1.name, c2.name, vt.type, v.vehicle_number, v.driver_name, 
             ts.departure_time, ts.arrival_time, tt.price, tt.recurrence_type, 
             tt.recurrence_days, tt.start_date, tt.end_date, tt.status, vt.capacity
    ORDER BY tt.start_date, ts.departure_time
";
$result = $conn->query($query);
$trip_templates = [];
while ($row = $result->fetch_assoc()) {
    $trip_templates[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Templates - DGC Transports</title>
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
        
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        @media (min-width: 1025px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 50;
            min-width: 160px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease-in-out;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.15s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        
        .dropdown-item:hover {
            background-color: #f3f4f6;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
        }
        
        .dropdown-toggle {
            position: relative;
        }
        
        .booking-badge {
            background: #10b981;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
        }
    </style>
</head>
<body class="bg-gray-50 gradient-bg">
    <?php include '../templates/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen">
        <div class="pt-20 md:pt-0 p-4 md:p-6 lg:p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-route text-primary-red mr-3"></i>
                            Trip Templates
                        </h1>
                        <p class="text-gray-600 mt-1">Manage your trip schedules and routes</p>
                    </div>
                </div>
            </div>
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-lg md:text-xl font-bold text-gray-900">
            <i class="fas fa-route text-primary-red mr-2"></i>
            All Trip Templates
        </h2>
        <p class="text-sm text-gray-600 mt-1">Active trip schedules</p>
    </div>
    <div class="flex gap-3">
        <a href="add_trip.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition">
            <i class="fas fa-plus mr-2"></i>Add New Template
        </a>
        <a href="city.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
            <i class="fas fa-city mr-2"></i>Add City
        </a>
    </div>
</div>

                <div class="p-4 md:p-6">
                    <?php if (isset($success)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($trip_templates)): ?>
                        <div class="text-center py-12">
                            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-route text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Trip Templates</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create a new trip template to start scheduling trips for your passengers.</p>
                            <a href="add_trip.php" class="inline-flex items-center px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i>
                                Create Trip Template
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mobile Cards (Hidden on desktop) -->
                        <div class="space-y-4 md:hidden">
                            <?php foreach ($trip_templates as $template): ?>
                                <div class="mobile-card p-4 relative">
                                    <div class="absolute top-4 right-4">
                                        <div class="dropdown-toggle">
                                            <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="toggleDropdown('mobile-<?= $template['id'] ?>')">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div id="mobile-<?= $template['id'] ?>" class="dropdown-menu">
                                                <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                                    <i class="fas fa-eye"></i>View Bookings
                                                </a>
                                                <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                                    <i class="fas fa-edit"></i>Edit
                                                </a>
                                                <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item">
                                                    <i class="fas fa-trash"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 bg-primary-red rounded-full mr-2"></div>
                                            <span class="font-semibold text-gray-900 text-sm">
                                                <?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?>
                                            </span>
                                        </div>
                                        <span class="text-xs font-medium text-green-600 bg-green-100 px-2 py-1 rounded-full">
                                            Active
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-bus text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-user text-primary-red w-4 mr-2"></i>
                                            <span><?= htmlspecialchars($template['driver_name']) ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-clock text-primary-red w-4 mr-2"></i>
                                                <span><?= htmlspecialchars($template['departure_time']) ?></span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-calendar-alt text-primary-red w-4 mr-2"></i>
                                                <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-redo text-primary-red w-4 mr-2"></i>
                                                <span>
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
                                                </span>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-money-bill-wave text-primary-red w-4 mr-2"></i>
                                                <span>₦<?= number_format($template['price'], 2) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-users text-primary-red w-4 mr-2"></i>
                                            <span><?= $template['total_bookings'] ?> / <?= $template['capacity'] ?> bookings</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop Table (Hidden on mobile) -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle & Driver</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recurrence</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($trip_templates as $template): ?>
                                        <tr class="table-hover">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-2 h-2 bg-primary-red rounded-full mr-3"></div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($template['driver_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
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
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900">₦<?= number_format($template['price'], 2) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <i class="fas fa-users text-primary-red mr-2"></i>
                                                    <span class="text-sm font-medium text-gray-900"><?= $template['total_bookings'] ?> / <?= $template['capacity'] ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="dropdown-toggle">
                                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="toggleDropdown('desktop-<?= $template['id'] ?>')">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div id="desktop-<?= $template['id'] ?>" class="dropdown-menu">
                                                        <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-eye"></i>View Bookings
                                                        </a>
                                                        <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                                            <i class="fas fa-edit"></i>Edit
                                                        </a>
                                                        <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item">
                                                            <i class="fas fa-trash"></i>Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
        <div class="modal-content bg-white rounded-lg p-6 max-w-sm mx-auto shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-center">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-800 text-sm font-medium rounded-lg hover:bg-gray-400 transition-colors duration-200">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">Delete</a>
            </div>
        </div>
    </div>

    <script>
        // Global variable to track currently open dropdown
        let currentOpenDropdown = null;

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            
            // Close any currently open dropdown
            if (currentOpenDropdown && currentOpenDropdown !== dropdown) {
                currentOpenDropdown.classList.remove('show');
            }
            
            // Toggle the clicked dropdown
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                currentOpenDropdown = null;
            } else {
                dropdown.classList.add('show');
                currentOpenDropdown = dropdown;
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown-toggle')) {
                if (currentOpenDropdown) {
                    currentOpenDropdown.classList.remove('show');
                    currentOpenDropdown = null;
                }
            }
        });

        // Delete modal functionality
        function showDeleteModal(id, name) {
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDeleteLink = document.getElementById('confirmDeleteLink');
            
            deleteMessage.textContent = `Are you sure you want to delete the trip template for ${name}?`;
            confirmDeleteLink.href = `trips.php?delete=${id}`;
            deleteModal.classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const deleteModal = document.getElementById('deleteModal');
            const cancelButton = document.getElementById('cancelButton');

            if (cancelButton) {
                cancelButton.addEventListener('click', () => {
                    deleteModal.classList.add('hidden');
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>