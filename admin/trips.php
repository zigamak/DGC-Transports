<?php
// admin/trips.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle inline edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['id'];
    $price = (float)$_POST['price'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $time_slot_id = (int)$_POST['time_slot_id'];
    $status = sanitizeInput($_POST['status']);

    // Fetch vehicle details
    $vehicle_stmt = $conn->prepare("SELECT vehicle_number, driver_name FROM vehicles WHERE id = ?");
    $vehicle_stmt->bind_param("i", $vehicle_id);
    $vehicle_stmt->execute();
    $vehicle = $vehicle_stmt->get_result()->fetch_assoc();
    $vehicle_stmt->close();

    // Fetch time slot details
    $time_stmt = $conn->prepare("SELECT departure_time, arrival_time FROM time_slots WHERE id = ?");
    $time_stmt->bind_param("i", $time_slot_id);
    $time_stmt->execute();
    $time_slot = $time_stmt->get_result()->fetch_assoc();
    $time_stmt->close();

    if ($vehicle && $time_slot && in_array($status, ['active', 'inactive'])) {
        $stmt = $conn->prepare("
            UPDATE trips 
            SET price = ?, vehicle_id = ?, time_slot_id = ?, departure_time = ?, arrival_time = ?, 
                vehicle_number = ?, driver_name = ?, status = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "dissssssi",
            $price,
            $vehicle_id,
            $time_slot_id,
            $time_slot['departure_time'],
            $time_slot['arrival_time'],
            $vehicle['vehicle_number'],
            $vehicle['driver_name'],
            $status,
            $id
        );
        $stmt->execute();
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/trips.php?updated=1');
        exit;
    } else {
        $error = 'Invalid input data.';
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . SITE_URL . '/admin/trips.php?deleted=1');
    exit;
}

// Fetch cities, vehicle types, vehicles, and time slots
$cities_result = $conn->query("SELECT id, name FROM cities ORDER BY name");
$cities = [];
while ($row = $cities_result->fetch_assoc()) {
    $cities[] = $row;
}

$vehicle_types_result = $conn->query("SELECT id, type FROM vehicle_types ORDER BY type");
$vehicle_types = [];
while ($row = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $row;
}

$vehicles_result = $conn->query("SELECT id, vehicle_number, driver_name FROM vehicles ORDER BY vehicle_number");
$vehicles = [];
while ($row = $vehicles_result->fetch_assoc()) {
    $vehicles[] = $row;
}

$time_slots_result = $conn->query("SELECT id, departure_time, arrival_time FROM time_slots ORDER BY departure_time");
$time_slots = [];
while ($row = $time_slots_result->fetch_assoc()) {
    $time_slots[] = $row;
}

// Fetch existing trips
$trips_result = $conn->query("
    SELECT t.id, t.trip_date, t.departure_time, t.arrival_time, t.vehicle_number, t.driver_name, t.price, t.status,
           pc.name as pickup_city, dc.name as dropoff_city, vt.type as vehicle_type, t.vehicle_id, t.time_slot_id
    FROM trips t
    JOIN cities pc ON t.pickup_city_id = pc.id
    JOIN cities dc ON t.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON t.vehicle_type_id = vt.id
    ORDER BY t.trip_date DESC, t.departure_time DESC
");
$trips = [];
while ($row = $trips_result->fetch_assoc()) {
    $trips[] = $row;
}

require_once '../templates/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trips - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
        }
        body {
            background: linear-gradient(to bottom right, var(--white), var(--gray));
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: var(--primary-red);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--dark-red);
        }
        .btn-secondary {
            background: var(--gray);
            color: var(--black);
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .table th {
            background: var(--gray);
            color: var(--black);
            font-weight: 600;
            padding: 12px;
            border-bottom: 2px solid var(--black);
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: var(--black);
        }
        .table tr:hover {
            background: #f9f9f9;
        }
        .inline-edit-form {
            display: none;
        }
        .inline-edit-form.active {
            display: table-row;
        }
        .input-field {
            border: 1px solid var(--black);
            border-radius: 6px;
            padding: 8px;
            width: 100%;
            color: var(--black);
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 5px rgba(227, 6, 19, 0.3);
        }
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
        }
    </style>
    <script>
        function toggleEditForm(id) {
            const viewRow = document.getElementById(`view-${id}`);
            const editForm = document.getElementById(`edit-${id}`);
            viewRow.classList.toggle('hidden');
            editForm.classList.toggle('active');
        }
    </script>
</head>
<body>
    <div class="container mx-auto mt-10 p-6">
        <div class="card p-8 mb-8">
            <h1 class="text-3xl font-bold mb-6">
                <i class="fas fa-road text-red-600 mr-2"></i>Manage Trips
            </h1>

            <?php if (isset($error)): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <p class="success-message mb-4">Trip updated successfully.</p>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <p class="success-message mb-4">Trip deleted successfully.</p>
            <?php endif; ?>

            <a href="<?= SITE_URL ?>/admin/add_trip.php" class="btn-primary inline-flex items-center mb-6">
                <i class="fas fa-plus mr-2"></i>Add New Trip
            </a>

            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Trip Date</th>
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Pickup</th>
                            <th>Dropoff</th>
                            <th>Vehicle Type</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trips)): ?>
                            <tr><td colspan="11" class="text-center">No trips found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($trips as $trip): ?>
                                <!-- View Row -->
                                <tr id="view-<?= $trip['id'] ?>">
                                    <td><?= htmlspecialchars(formatDate($trip['trip_date'], 'j, M, Y')) ?></td>
                                    <td><?= htmlspecialchars(formatTime($trip['departure_time'], 'g:i A')) ?></td>
                                    <td><?= htmlspecialchars($trip['arrival_time'] ? formatTime($trip['arrival_time'], 'g:i A') : '-') ?></td>
                                    <td><?= htmlspecialchars($trip['pickup_city']) ?></td>
                                    <td><?= htmlspecialchars($trip['dropoff_city']) ?></td>
                                    <td><?= htmlspecialchars($trip['vehicle_type']) ?></td>
                                    <td><?= htmlspecialchars($trip['vehicle_number']) ?></td>
                                    <td><?= htmlspecialchars($trip['driver_name']) ?></td>
                                    <td><?= formatCurrency($trip['price']) ?></td>
                                    <td><?= htmlspecialchars($trip['status']) ?></td>
                                    <td>
                                        <button onclick="toggleEditForm(<?= $trip['id'] ?>)" class="btn-secondary mr-2">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="<?= SITE_URL ?>/admin/trips.php?action=delete&id=<?= $trip['id'] ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Are you sure you want to delete this trip?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <!-- Edit Form Row -->
                                <tr id="edit-<?= $trip['id'] ?>" class="inline-edit-form">
                                    <td colspan="11">
                                        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?= $trip['id'] ?>">
                                            <div>
                                                <label class="block font-semibold mb-1">Price (₦)</label>
                                                <input type="number" name="price" value="<?= $trip['price'] ?>" step="0.01" class="input-field" required>
                                            </div>
                                            <div>
                                                <label class="block font-semibold mb-1">Vehicle</label>
                                                <select name="vehicle_id" class="input-field" required>
                                                    <?php foreach ($vehicles as $vehicle): ?>
                                                        <option value="<?= $vehicle['id'] ?>" <?= $vehicle['id'] == $trip['vehicle_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($vehicle['vehicle_number'] . ' (' . $vehicle['driver_name'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-semibold mb-1">Time Slot</label>
                                                <select name="time_slot_id" class="input-field" required>
                                                    <?php foreach ($time_slots as $slot): ?>
                                                        <option value="<?= $slot['id'] ?>" <?= $slot['id'] == $trip['time_slot_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars(formatTime($slot['departure_time'], 'g:i A') . ' - ' . formatTime($slot['arrival_time'], 'g:i A')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block font-semibold mb-1">Status</label>
                                                <select name="status" class="input-field" required>
                                                    <option value="active" <?= $trip['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $trip['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-3 flex space-x-2">
                                                <button type="submit" class="btn-primary">
                                                    <i class="fas fa-save mr-2"></i>Save
                                                </button>
                                                <button type="button" onclick="toggleEditForm(<?= $trip['id'] ?>)" class="btn-secondary">
                                                    <i class="fas fa-times mr-2"></i>Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>