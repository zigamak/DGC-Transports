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

require_once '../templates/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Trip Template - DGC Transports</title>
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
        .label {
            font-weight: 600;
            color: var(--black);
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
        function toggleRecurrenceDays() {
            const type = document.getElementById('recurrence_type').value;
            document.getElementById('recurrence_days_section').style.display = (type === 'week') ? 'block' : 'none';
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
</head>
<body>
    <div class="container mx-auto mt-10 p-6">
        <div class="card p-8">
            <h1 class="text-3xl font-bold mb-6">
                <i class="fas fa-plus text-red-600 mr-2"></i>Add New Trip Template
            </h1>

            <?php if (isset($error)): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6" onsubmit="return validateForm()">
                <div>
                    <label for="pickup_city_id" class="label block mb-2">Pickup City</label>
                    <select id="pickup_city_id" name="pickup_city_id" class="input-field" required>
                        <option value="">Select Pickup City</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="dropoff_city_id" class="label block mb-2">Dropoff City</label>
                    <select id="dropoff_city_id" name="dropoff_city_id" class="input-field" required>
                        <option value="">Select Dropoff City</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="vehicle_type_id" class="label block mb-2">Vehicle Type</label>
                    <select id="vehicle_type_id" name="vehicle_type_id" class="input-field" required>
                        <option value="">Select Vehicle Type</option>
                        <?php foreach ($vehicle_types as $vt): ?>
                            <option value="<?= $vt['id'] ?>"><?= htmlspecialchars($vt['type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="vehicle_id" class="label block mb-2">Vehicle</label>
                    <select id="vehicle_id" name="vehicle_id" class="input-field" required>
                        <option value="">Select Vehicle</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>">
                                <?= htmlspecialchars($vehicle['vehicle_number'] . ' (' . $vehicle['driver_name'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="time_slot_id" class="label block mb-2">Time Slot</label>
                    <select id="time_slot_id" name="time_slot_id" class="input-field" required>
                        <option value="">Select Time Slot</option>
                        <?php foreach ($time_slots as $slot): ?>
                            <option value="<?= $slot['id'] ?>">
                                <?= htmlspecialchars($slot['departure_time'] . ' - ' . $slot['arrival_time']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="price" class="label block mb-2">Price (₦)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" class="input-field" required>
                </div>
                <div>
                    <label for="start_date" class="label block mb-2">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="input-field" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label for="recurrence_type" class="label block mb-2">Recurrence Type</label>
                    <select id="recurrence_type" name="recurrence_type" class="input-field" onchange="toggleRecurrenceDays()" required>
                        <option value="day">One Day</option>
                        <option value="week">Weekly</option>
                        <option value="month">Monthly</option>
                        <option value="year">Yearly</option>
                    </select>
                </div>
                <div id="recurrence_days_section" style="display: none;">
                    <label class="label block mb-2">Days (for Weekly Schedule)</label>
                    <select multiple name="recurrence_days[]" class="input-field">
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>Create Trip Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>