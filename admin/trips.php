<?php
//admin/trips.php
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
    <title>Manage Trips - DGC Transports</title>
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
        
        /* Dropdown menu styling */
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
        
        /* Modal overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
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
                            <i class="fas fa-road text-primary-red mr-3"></i>
                            Manage Trips
                        </h1>
                        <p class="text-gray-600 mt-1">View and manage all active trip templates.</p>
                    </div>
                    <a href="add_trip.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Template
                    </a>
                     <a href="timeslot.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Manage Timeslots
                    </a>
                </div>
            </div>

            <!-- Messages -->
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

            <!-- Content Card -->
            <div class="bg-white rounded-xl card-shadow">
                <?php if (empty($trip_templates)): ?>
                    <div class="p-8 md:p-12 text-center">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-road text-3xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2">No Trip Templates Found</h3>
                        <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create a new trip template to get started.</p>
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
                                <div class="absolute top-2 right-2 dropdown-toggle">
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none rounded-full hover:bg-gray-100" onclick="toggleDropdown('mobile-<?= $template['id'] ?>')">
                                        <i class="fas fa-ellipsis-v text-sm"></i>
                                    </button>
                                    <div id="mobile-<?= $template['id'] ?>" class="dropdown-menu mt-1">
                                        <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                            <i class="fas fa-eye"></i>View Bookings
                                        </a>
                                        <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                            <i class="fas fa-edit"></i>Edit
                                        </a>
                                        <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash"></i>Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 bg-primary-red rounded-full mr-2"></div>
                                        <span class="font-semibold text-gray-900 text-sm">
                                            <?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?>
                                        </span>
                                    </div>
                                    <span class="text-xs font-medium bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                        <?= $template['total_bookings'] ?> bookings
                                    </span>
                                </div>
                                
                                <div class="space-y-2 text-sm text-gray-600">
                                    <div class="flex items-center">
                                        <i class="fas fa-bus text-primary-red w-4 mr-2"></i>
                                        <span><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-user text-primary-red w-4 mr-2"></i>
                                        <span><?= htmlspecialchars($template['driver_name']) ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock text-primary-red w-4 mr-2"></i>
                                        <span><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-money-bill-wave text-primary-red w-4 mr-2"></i>
                                        <span>₦<?= number_format($template['price'], 0) ?></span>
                                    </div>
                                    <div class="flex items-center">
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
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-alt text-primary-red w-4 mr-2"></i>
                                        <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Desktop Table (Hidden on mobile) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle & Driver</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($trip_templates as $template): ?>
                                    <tr class="table-hover">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-2 h-2 bg-primary-red rounded-full mr-3"></div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($template['driver_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">₦<?= number_format($template['price'], 0) ?></div>
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
                                            <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-users text-primary-red mr-2"></i>
                                                <span class="text-sm font-medium text-gray-900"><?= $template['total_bookings'] ?> / <?= $template['capacity'] ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="dropdown-toggle inline-block">
                                                <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none rounded-full hover:bg-gray-100" onclick="toggleDropdown('desktop-<?= $template['id'] ?>')">
                                                    <i class="fas fa-ellipsis-v text-sm"></i>
                                                </button>
                                                <div id="desktop-<?= $template['id'] ?>" class="dropdown-menu mt-1">
                                                    <a href="view_bookings.php?template_id=<?= $template['id'] ?>" class="dropdown-item">
                                                        <i class="fas fa-eye"></i>View Bookings
                                                    </a>
                                                    <a href="edit_trip.php?id=<?= $template['id'] ?>" class="dropdown-item">
                                                        <i class="fas fa-edit"></i>Edit
                                                    </a>
                                                    <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city'], ENT_QUOTES) ?>')" class="dropdown-item text-red-600 hover:bg-red-50">
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center modal-overlay">
        <div class="bg-white rounded-xl p-6 max-w-sm mx-auto shadow-2xl">
            <h3 class="text-xl font-bold mb-4 text-center text-gray-900">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="px-4 py-2 bg-gray-300 text-gray-800 hover:bg-gray-400 rounded-lg transition-colors">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors">Delete</a>
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
