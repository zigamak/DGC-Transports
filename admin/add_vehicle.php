<?php
//admin/add_vehicle.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Fetch vehicle types for dropdown
$vehicle_types_query = "SELECT id, type FROM vehicle_types ORDER BY type";
$vehicle_types_result = $conn->query($vehicle_types_query);
$vehicle_types = [];
while ($row = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $row;
}

// Handle form submission
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $driver_name = trim($_POST['driver_name'] ?? '');
    $vehicle_type_id = (int)($_POST['vehicle_type_id'] ?? 0);

    // Validate inputs
    if (empty($vehicle_number)) {
        $errors[] = 'Vehicle number is required.';
    } else {
        // Check if vehicle_number is unique
        $stmt = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number = ?");
        $stmt->bind_param("s", $vehicle_number);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Vehicle number already exists.';
        }
        $stmt->close();
    }
    if (empty($driver_name)) {
        $errors[] = 'Driver name is required.';
    }
    if ($vehicle_type_id <= 0) {
        $errors[] = 'Please select a valid vehicle type.';
    } else {
        // Verify vehicle_type_id exists
        $stmt = $conn->prepare("SELECT id FROM vehicle_types WHERE id = ?");
        $stmt->bind_param("i", $vehicle_type_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $errors[] = 'Invalid vehicle type selected.';
        }
        $stmt->close();
    }

    // Insert vehicle if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_number, driver_name, vehicle_type_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $vehicle_number, $driver_name, $vehicle_type_id);
        if ($stmt->execute()) {
            $success = 'Vehicle added successfully. Redirecting...';
            header("Refresh: 2; url=vehicles.php");
        } else {
            $errors[] = 'Failed to add vehicle. Please try again.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle - DGC Transports</title>
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
        
        .content-container {
            overflow-y: auto;
            max-height: calc(100vh - 4rem);
        }
        
        @media (min-width: 768px) {
            .content-container {
                max-height: 100vh;
            }
        }
        
        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="content-container p-4 md:p-6 lg:p-8">
            <div class="max-w-2xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-bus text-primary-red mr-3"></i>
                            Add New Vehicle
                        </h1>
                        <a href="vehicles.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Vehicles
                        </a>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-xl card-shadow p-6">
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $error): ?>
                            <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                    <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                <span class="text-green-800 text-sm"><?= htmlspecialchars($success) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="form-grid grid gap-6">
                            <!-- Vehicle Number -->
                            <div>
                                <label for="vehicle_number" class="form-label block">Vehicle Number</label>
                                <input type="text" id="vehicle_number" name="vehicle_number" value="<?= isset($vehicle_number) ? htmlspecialchars($vehicle_number) : '' ?>" placeholder="e.g., ABC123" class="form-input" required>
                            </div>

                            <!-- Driver Name -->
                            <div>
                                <label for="driver_name" class="form-label block">Driver Name</label>
                                <input type="text" id="driver_name" name="driver_name" value="<?= isset($driver_name) ? htmlspecialchars($driver_name) : '' ?>" placeholder="Enter driver name" class="form-input" required>
                            </div>

                            <!-- Vehicle Type -->
                            <div>
                                <label for="vehicle_type_id" class="form-label block">Vehicle Type</label>
                                <select id="vehicle_type_id" name="vehicle_type_id" class="form-input" required>
                                    <option value="">Select a vehicle type</option>
                                    <?php foreach ($vehicle_types as $type): ?>
                                        <option value="<?= $type['id'] ?>" <?= isset($vehicle_type_id) && $vehicle_type_id == $type['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex justify-end gap-4">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Add Vehicle
                            </button>
                            <a href="vehicles.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>