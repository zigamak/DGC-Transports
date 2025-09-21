<?php
// admin/trips.php
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

// Fetch all trip templates
$query = "
    SELECT tt.id, c1.name AS pickup_city, c2.name AS dropoff_city, vt.type AS vehicle_type, 
           v.vehicle_number, v.driver_name, ts.departure_time, ts.arrival_time, 
           tt.price, tt.recurrence_type, tt.recurrence_days, tt.start_date, tt.end_date, tt.status
    FROM trip_templates tt
    JOIN cities c1 ON tt.pickup_city_id = c1.id
    JOIN cities c2 ON tt.dropoff_city_id = c2.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE tt.status = 'active'
    ORDER BY tt.start_date, ts.departure_time
";
$result = $conn->query($query);
$trip_templates = [];
while ($row = $result->fetch_assoc()) {
    $trip_templates[] = $row;
}

require_once '../templates/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Templates - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --light-gray: #e5e7eb;
            --accent-blue: #3b82f6;
        }
        body {
            background: linear-gradient(135deg, var(--white), var(--gray));
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
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
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        /* Mobile-first card layout for table rows */
        .mobile-card-row {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 0.75rem;
        }
        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .mobile-card-item:last-child {
            margin-bottom: 0;
        }
        .mobile-card-label {
            font-weight: 600;
            color: #4b5563;
            width: 80px;
            min-width: 80px;
        }
        .mobile-card-content {
            flex-grow: 1;
        }
        /* Desktop table layout */
        @media (min-width: 768px) {
            .mobile-card-row {
                display: none;
            }
            .table-container {
                display: block;
            }
            .table-header {
                background: var(--gray);
                color: var(--black);
                font-weight: 600;
            }
            .table-row:hover {
                background: #f9fafb;
            }
        }
        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-6 lg:p-10 flex flex-col items-center">
    <div class="container mx-auto">
        <div class="card p-6 sm:p-8 lg:p-10">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8">
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4 sm:mb-0">
                    <i class="fas fa-route text-primary-red mr-3"></i>Trip Templates
                </h1>
                <a href="add_trip.php" class="btn-primary inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>Add New Template
                </a>
            </div>

            <?php if (isset($success)): ?>
                <p class="success-message"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="error-message"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <?php if (empty($trip_templates)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-route text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-700 mb-2">No Trip Templates Found</h3>
                    <p class="text-gray-500 mb-6">Create a new trip template to get started.</p>
                    <a href="add_trip.php" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>Create Trip Template
                    </a>
                </div>
            <?php else: ?>
                <!-- Mobile View - Card Layout -->
                <div class="space-y-4 md:hidden">
                    <?php foreach ($trip_templates as $template): ?>
                        <div class="mobile-card-row bg-white shadow-md rounded-xl relative">
                            <div class="absolute top-4 right-4">
                                <div class="relative inline-block text-left">
                                    <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $template['id'] ?>">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $template['id'] ?>">
                                        <div class="py-1">
                                            <a href="edit_trip.php?id=<?= $template['id'] ?>" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                <i class="fas fa-edit mr-2"></i>Edit
                                            </a>
                                            <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                <i class="fas fa-trash mr-2"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mobile-card-item">
                                <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                <span><?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?></span>
                            </div>
                            <div class="mobile-card-item">
                                <i class="fas fa-bus text-primary-red mr-2"></i>
                                <span><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number']) ?></span>
                            </div>
                            <div class="mobile-card-item">
                                <i class="fas fa-clock text-primary-red mr-2"></i>
                                <span><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></span>
                            </div>
                            <div class="mobile-card-item">
                                <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                <span>₦<?= number_format($template['price'], 2) ?></span>
                            </div>
                            <div class="mobile-card-item">
                                <i class="fas fa-redo text-primary-red mr-2"></i>
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
                            <div class="mobile-card-item">
                                <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop View - Table Layout -->
                <div class="overflow-x-auto hidden md:block">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="table-header rounded-lg">
                                <th class="px-4 py-3 text-left">Route</th>
                                <th class="px-4 py-3 text-left">Vehicle</th>
                                <th class="px-4 py-3 text-left">Time</th>
                                <th class="px-4 py-3 text-left">Price</th>
                                <th class="px-4 py-3 text-left">Schedule</th>
                                <th class="px-4 py-3 text-left">Period</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trip_templates as $template): ?>
                                <tr class="table-row border-b border-gray-200">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                            <span><?= htmlspecialchars($template['pickup_city']) ?> → <?= htmlspecialchars($template['dropoff_city']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-bus text-primary-red mr-2"></i>
                                            <span><?= htmlspecialchars($template['vehicle_type'] . ' - ' . $template['vehicle_number'] . ' (' . $template['driver_name'] . ')') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock text-primary-red mr-2"></i>
                                            <span><?= htmlspecialchars($template['departure_time'] . ' - ' . $template['arrival_time']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-money-bill-wave text-primary-red mr-2"></i>
                                            <span>₦<?= number_format($template['price'], 2) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-redo text-primary-red mr-2"></i>
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
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                            <span><?= date('M j, Y', strtotime($template['start_date'])) ?> - <?= date('M j, Y', strtotime($template['end_date'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right relative">
                                        <div class="relative inline-block text-left">
                                            <button type="button" class="inline-flex justify-center items-center w-8 h-8 text-gray-400 hover:text-gray-600 focus:outline-none menu-toggle" data-id="<?= $template['id'] ?>">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $template['id'] ?>">
                                                <div class="py-1">
                                                    <a href="edit_trip.php?id=<?= $template['id'] ?>" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-edit mr-2"></i>Edit
                                                    </a>
                                                    <button onclick="showDeleteModal(<?= $template['id'] ?>, '<?= htmlspecialchars($template['pickup_city'] . ' → ' . $template['dropoff_city']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
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
    
    <!-- Custom Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
        <div class="modal-content bg-white rounded-lg p-6 max-w-sm mx-auto shadow-xl">
            <h3 class="text-xl font-bold mb-4 text-center">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-700 mb-6 text-center"></p>
            <div class="flex justify-center space-x-4">
                <button id="cancelButton" class="btn-primary bg-gray-300 text-gray-800 hover:bg-gray-400 transform-none">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="btn-primary bg-red-600 hover:bg-red-700">Delete</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const deleteModal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const confirmDeleteLink = document.getElementById('confirmDeleteLink');
            const cancelButton = document.getElementById('cancelButton');

            window.showDeleteModal = (id, name) => {
                deleteMessage.textContent = `Are you sure you want to delete the trip template for ${name}?`;
                confirmDeleteLink.href = `trip_templates.php?delete=${id}`;
                deleteModal.classList.remove('hidden');
            };

            cancelButton.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });

            // Close modal when clicking outside of it
            window.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                }
            });

            // Handle dropdown menus
            const menuToggles = document.querySelectorAll('.menu-toggle');
            menuToggles.forEach(toggle => {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const id = toggle.dataset.id;
                    const menu = document.querySelector(`.menu-content[data-id="${id}"]`);
                    // Close all other menus
                    document.querySelectorAll('.menu-content').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.add('hidden');
                        }
                    });
                    // Toggle the clicked menu
                    menu.classList.toggle('hidden');
                });
            });

            // Close dropdowns when clicking anywhere else on the page
            window.addEventListener('click', (event) => {
                document.querySelectorAll('.menu-content').forEach(menu => {
                    if (!menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</body>
</html>
