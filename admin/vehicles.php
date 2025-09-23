<?php
//admin/vehicles.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $vehicle_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $vehicle_id);
    if ($stmt->execute()) {
        $success = 'Vehicle deleted successfully.';
    } else {
        $error = 'Failed to delete vehicle.';
    }
    $stmt->close();
}

// Handle inline edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vehicle'])) {
    $vehicle_id = (int)$_POST['id'];
    $vehicle_number = trim($_POST['vehicle_number']);
    $driver_name = trim($_POST['driver_name']);
    $vehicle_type_id = (int)$_POST['vehicle_type_id'];

    $stmt = $conn->prepare("UPDATE vehicles SET vehicle_number = ?, driver_name = ?, vehicle_type_id = ? WHERE id = ?");
    $stmt->bind_param("ssii", $vehicle_number, $driver_name, $vehicle_type_id, $vehicle_id);
    if ($stmt->execute()) {
        $success = 'Vehicle updated successfully.';
    } else {
        $error = 'Failed to update vehicle.';
    }
    $stmt->close();
}

// Fetch vehicle types for dropdown
$vehicle_types_query = "SELECT id, type FROM vehicle_types ORDER BY type";
$vehicle_types_result = $conn->query($vehicle_types_query);
$vehicle_types = [];
while ($row = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $row;
}

// Fetch vehicles with search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT v.id, v.vehicle_number, v.driver_name, v.vehicle_type_id, vt.type, vt.capacity
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    WHERE v.vehicle_number LIKE ? OR v.driver_name LIKE ?
    ORDER BY v.vehicle_number
";
$search_param = "%$search%";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicles - DGC Transports</title>
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
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #e30613;
            box-shadow: 0 0 0 2px rgba(227, 6, 19, 0.2);
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: #e30613;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            width: 100%;
            text-align: center;
        }
        
        .btn-primary:hover {
            background: #c70410;
            transform: translateY(-1px);
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .success-message {
            color: #059669;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }
        
        .table-hover:hover {
            background-color: #f8fafc;
        }
        
        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem);
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        
        .mobile-card-row {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .mobile-card-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .mobile-card-label {
            font-weight: 500;
            color: #4b5563;
            width: 100px;
            min-width: 100px;
        }
        
        @media (min-width: 768px) {
            .content-container {
                max-height: calc(100vh - 2rem);
            }
            .mobile-card-row {
                display: none;
            }
            .table-container {
                display: block;
            }
            .btn-primary {
                width: auto;
            }
        }
        
        @media (max-width: 767px) {
            .table-container {
                display: none;
            }
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .search-form {
                width: 100%;
                margin-bottom: 1rem;
            }
        }
        
        .edit-form input, .edit-form select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="content-container p-4 md:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between flex-wrap gap-4">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-bus text-primary-red mr-3"></i>
                            Manage Vehicles
                        </h1>
                        <div class="header-actions flex flex-col md:flex-row items-stretch md:items-center gap-4 w-full md:w-auto">
                            <form method="GET" class="search-form flex items-center w-full">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by vehicle or driver" class="form-input w-full" aria-label="Search vehicles">
                                <button type="submit" class="btn-primary ml-2">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                            </form>
                            <div class="flex flex-col gap-2 w-full md:w-auto">
                                <a href="add_vehicle.php" class="btn-primary inline-flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i>Add Vehicle
                                </a>
                                <a href="vehicle_type" class="btn-primary inline-flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i>Add Vehicle Type
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="bg-white rounded-xl card-shadow">
                    <div class="p-4 md:p-6">
                        <?php if (isset($success)): ?>
                            <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                    <span class="text-green-800 text-sm"><?= htmlspecialchars($success) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                    <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-12">
                                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-bus text-3xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Vehicles Found</h3>
                                <p class="text-gray-600 mb-4">Add a new vehicle to get started.</p>
                                <a href="add_vehicle.php" class="btn-primary inline-flex items-center justify-center">
                                    <i class="fas fa-plus mr-2"></i>Add Vehicle
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Mobile View -->
                            <div class="space-y-4 md:hidden">
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="mobile-card-row relative">
                                        <div class="absolute top-4 right-4">
                                            <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none menu-toggle" data-id="<?= $vehicle['id'] ?>">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $vehicle['id'] ?>">
                                                <div class="py-1">
                                                    <button onclick="toggleEditForm(<?= $vehicle['id'] ?>)" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-edit mr-2"></i>Edit
                                                    </button>
                                                    <button onclick="showDeleteModal(<?= $vehicle['id'] ?>, '<?= htmlspecialchars($vehicle['vehicle_number']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-bus text-primary-red mr-2"></i>Vehicle:</span>
                                            <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['vehicle_number']) ?></span>
                                            <input type="text" name="vehicle_number" value="<?= htmlspecialchars($vehicle['vehicle_number']) ?>" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-user text-primary-red mr-2"></i>Driver:</span>
                                            <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['driver_name']) ?></span>
                                            <input type="text" name="driver_name" value="<?= htmlspecialchars($vehicle['driver_name']) ?>" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                        </div>
                                        <div class="mobile-card-item">
                                            <span class="mobile-card-label"><i class="fas fa-car text-primary-red mr-2"></i>Type:</span>
                                            <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['type']) ?> (<?= $vehicle['capacity'] ?> seats)</span>
                                            <select name="vehicle_type_id" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                                <?php foreach ($vehicle_types as $type): ?>
                                                    <option value="<?= $type['id'] ?>" <?= $type['id'] == $vehicle['vehicle_type_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($type['type']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <form id="edit-form-<?= $vehicle['id'] ?>" class="edit-form hidden mt-4" method="POST">
                                            <input type="hidden" name="edit_vehicle" value="1">
                                            <input type="hidden" name="id" value="<?= $vehicle['id'] ?>">
                                            <div class="flex gap-2">
                                                <button type="submit" class="btn-primary flex-1">Save</button>
                                                <button type="button" onclick="toggleEditForm(<?= $vehicle['id'] ?>)" class="btn-primary flex-1 bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Desktop View -->
                            <div class="table-container hidden md:block">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle Number</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle Type</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr class="table-hover" id="row-<?= $vehicle['id'] ?>">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-bus text-primary-red mr-2"></i>
                                                        <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['vehicle_number']) ?></span>
                                                        <input type="text" name="vehicle_number" value="<?= htmlspecialchars($vehicle['vehicle_number']) ?>" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-user text-primary-red mr-2"></i>
                                                        <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['driver_name']) ?></span>
                                                        <input type="text" name="driver_name" value="<?= htmlspecialchars($vehicle['driver_name']) ?>" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-car text-primary-red mr-2"></i>
                                                        <span class="view-mode-<?= $vehicle['id'] ?>"><?= htmlspecialchars($vehicle['type']) ?> (<?= $vehicle['capacity'] ?> seats)</span>
                                                        <select name="vehicle_type_id" class="form-input edit-form hidden edit-form-<?= $vehicle['id'] ?>" required>
                                                            <?php foreach ($vehicle_types as $type): ?>
                                                                <option value="<?= $type['id'] ?>" <?= $type['id'] == $vehicle['vehicle_type_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($type['type']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <button type="button" class="text-gray-500 hover:text-gray-700 focus:outline-none menu-toggle" data-id="<?= $vehicle['id'] ?>">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="origin-top-right absolute right-6 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 hidden menu-content" data-id="<?= $vehicle['id'] ?>">
                                                        <div class="py-1">
                                                            <button onclick="toggleEditForm(<?= $vehicle['id'] ?>)" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100">
                                                                <i class="fas fa-edit mr-2"></i>Edit
                                                            </button>
                                                            <button onclick="showDeleteModal(<?= $vehicle['id'] ?>, '<?= htmlspecialchars($vehicle['vehicle_number']) ?>')" class="text-gray-700 w-full text-left block px-4 py-2 text-sm hover:bg-gray-100">
                                                                <i class="fas fa-trash mr-2"></i>Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <form id="edit-form-<?= $vehicle['id'] ?>" class="edit-form hidden mt-2" method="POST">
                                                        <input type="hidden" name="edit_vehicle" value="1">
                                                        <input type="hidden" name="id" value="<?= $vehicle['id'] ?>">
                                                        <div class="flex justify-end gap-2">
                                                            <button type="submit" class="btn-primary text-sm px-3 py-1">Save</button>
                                                            <button type="button" onclick="toggleEditForm(<?= $vehicle['id'] ?>)" class="btn-primary text-sm px-3 py-1 bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
                                                        </div>
                                                    </form>
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
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 mb-4 text-center">Confirm Deletion</h3>
            <p id="deleteMessage" class="text-gray-600 mb-6 text-center"></p>
            <div class="flex justify-center gap-4">
                <button id="cancelButton" class="btn-primary bg-gray-200 text-gray-800 hover:bg-gray-300">Cancel</button>
                <a id="confirmDeleteLink" href="#" class="btn-primary bg-red-600 hover:bg-red-700">Delete</a>
            </div>
        </div>
    </div>

    <script>
        function showDeleteModal(id, name) {
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete the vehicle ${name}?`;
            document.getElementById('confirmDeleteLink').href = `vehicles.php?delete=${id}`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function toggleEditForm(id) {
            const row = document.getElementById(`row-${id}`);
            const viewModes = row.querySelectorAll(`.view-mode-${id}`);
            const editForms = row.querySelectorAll(`.edit-form-${id}`);
            const form = document.getElementById(`edit-form-${id}`);
            viewModes.forEach(el => el.classList.toggle('hidden'));
            editForms.forEach(el => el.classList.toggle('hidden'));
            form.classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Modal handling
            const deleteModal = document.getElementById('deleteModal');
            const cancelButton = document.getElementById('cancelButton');
            cancelButton.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
            deleteModal.addEventListener('click', (event) => {
                if (event.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                }
            });

            // Dropdown menu handling
            document.querySelectorAll('.menu-toggle').forEach(toggle => {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const id = toggle.dataset.id;
                    const menu = document.querySelector(`.menu-content[data-id="${id}"]`);
                    document.querySelectorAll('.menu-content').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.add('hidden');
                        }
                    });
                    menu.classList.toggle('hidden');
                });
            });
            document.addEventListener('click', () => {
                document.querySelectorAll('.menu-content').forEach(menu => {
                    menu.classList.add('hidden');
                });
            });
        });
    </script>
</body>
</html>