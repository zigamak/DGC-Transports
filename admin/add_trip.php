<?php
// admin/add_trip.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_city_id = (int)$_POST['pickup_city_id'];
    $dropoff_city_id = (int)$_POST['dropoff_city_id'];
    $vehicle_type_id = (int)$_POST['vehicle_type_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $time_slot_id = (int)$_POST['time_slot_id'];
    $price = (float)$_POST['price'];
    $recurrence_type = sanitizeInput($_POST['recurrence_type']);
    $start_date = $_POST['start_date'];
    $recurrence_days = isset($_POST['recurrence_days']) ? implode(',', $_POST['recurrence_days']) : '';
    
    // Calculate end date based on recurrence type
    $start_date_obj = new DateTime($start_date);
    $end_date_obj = clone $start_date_obj;
    switch ($recurrence_type) {
        case 'day':
            break;
        case 'week':
            $end_date_obj->modify('+6 days');
            break;
        case 'month':
            $end_date_obj->modify('+1 month -1 day');
            break;
        case 'year':
            $end_date_obj->modify('+1 year -1 day');
            break;
    }
    $end_date = $end_date_obj->format('Y-m-d');

    // Validate inputs
    if ($pickup_city_id && $dropoff_city_id && $vehicle_type_id && $vehicle_id && $time_slot_id && $price > 0 && $start_date) {
        if ($pickup_city_id === $dropoff_city_id) {
            $error = 'Pickup and dropoff cities cannot be the same.';
        } else {
            // Insert into trip_templates
            $stmt = $conn->prepare("
                INSERT INTO trip_templates (
                    pickup_city_id, dropoff_city_id, vehicle_type_id, vehicle_id, 
                    time_slot_id, price, recurrence_type, recurrence_days, start_date, end_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param(
                "iiiisdssss",
                $pickup_city_id,
                $dropoff_city_id,
                $vehicle_type_id,
                $vehicle_id,
                $time_slot_id,
                $price,
                $recurrence_type,
                $recurrence_days,
                $start_date,
                $end_date
            );
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: ' . SITE_URL . '/admin/trips.php?success=1');
                exit;
            } else {
                $error = 'Failed to create trip template. Please try again.';
                $stmt->close();
            }
        }
    } else {
        $error = 'Please fill all required fields correctly.';
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Trip Template - DGC Transports</title>
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
        
        @media (min-width: 641px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                        <i class="fas fa-plus text-primary-red mr-3"></i>
                        Add New Trip Template
                    </h1>
                </div>

                <!-- Form Card -->
                <div class="bg-white rounded-xl card-shadow p-6">
                    <?php if (isset($error)): ?>
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <span class="text-red-800 text-sm"><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="form-grid grid gap-6" onsubmit="return validateForm()">
                        <!-- Pickup City -->
                        <div>
                            <label for="pickup_city_id" class="form-label block">Pickup City</label>
                            <select id="pickup_city_id" name="pickup_city_id" class="form-input" required>
                                <option value="">Select Pickup City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Dropoff City -->
                        <div>
                            <label for="dropoff_city_id" class="form-label block">Dropoff City</label>
                            <select id="dropoff_city_id" name="dropoff_city_id" class="form-input" required>
                                <option value="">Select Dropoff City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Vehicle Type -->
                        <div>
                            <label for="vehicle_type_id" class="form-label block">Vehicle Type</label>
                            <select id="vehicle_type_id" name="vehicle_type_id" class="form-input" required>
                                <option value="">Select Vehicle Type</option>
                                <?php foreach ($vehicle_types as $vt): ?>
                                    <option value="<?= $vt['id'] ?>"><?= htmlspecialchars($vt['type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Vehicle -->
                        <div>
                            <label for="vehicle_id" class="form-label block">Vehicle</label>
                            <select id="vehicle_id" name="vehicle_id" class="form-input" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['id'] ?>">
                                        <?= htmlspecialchars($vehicle['vehicle_number'] . ' (' . $vehicle['driver_name'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Time Slot -->
                        <div>
                            <label for="time_slot_id" class="form-label block">Time Slot</label>
                            <select id="time_slot_id" name="time_slot_id" class="form-input" required>
                                <option value="">Select Time Slot</option>
                                <?php foreach ($time_slots as $slot): ?>
                                    <option value="<?= $slot['id'] ?>">
                                        <?= htmlspecialchars($slot['departure_time'] . ' - ' . $slot['arrival_time']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price -->
                        <div>
                            <label for="price" class="form-label block">Price (₦)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" class="form-input" required>
                        </div>

                        <!-- Start Date -->
                        <div>
                            <label for="start_date" class="form-label block">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-input" min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Recurrence Type -->
                        <div>
                            <label for="recurrence_type" class="form-label block">Recurrence Type</label>
                            <select id="recurrence_type" name="recurrence_type" class="form-input" onchange="toggleRecurrenceDays()" required>
                                <option value="day">One Day</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                                <option value="year">Yearly</option>
                            </select>
                        </div>

                        <!-- Recurrence Days -->
                        <div id="recurrence_days_section" class="col-span-1 md:col-span-2" style="display: none;">
                            <label class="form-label block">Days (for Weekly Schedule)</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <?php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                foreach ($days as $day): ?>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="recurrence_days[]" value="<?= $day ?>" class="form-checkbox h-4 w-4 text-primary-red">
                                        <span class="ml-2 text-sm text-gray-700"><?= $day ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-span-1 md:col-span-2">
                            <button type="submit" class="btn-primary w-full md:w-auto">
                                <i class="fas fa-plus mr-2"></i>Create Trip Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleRecurrenceDays() {
            const type = document.getElementById('recurrence_type').value;
            document.getElementById('recurrence_days_section').style.display = type === 'week' ? 'block' : 'none';
        }

        function validateForm() {
            const pickupCity = document.getElementById('pickup_city_id').value;
            const dropoffCity = document.getElementById('dropoff_city_id').value;
            if (pickupCity && dropoffCity && pickupCity === dropoffCity) {
                alert('Pickup and dropoff cities cannot be the same.');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>