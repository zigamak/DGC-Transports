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
    $start_date = new DateTime($_POST['start_date']);
    $recurrence_days = isset($_POST['recurrence_days']) ? implode(',', $_POST['recurrence_days']) : NULL;

    // Calculate end date based on recurrence type from the start date
    $end_date = clone $start_date;
    switch ($recurrence_type) {
        case 'day':
            $end_date->modify('+1 day');
            break;
        case 'week':
            $end_date->modify('+1 week');
            break;
        case 'month':
            $end_date->modify('+1 month');
            break;
        case 'year':
            $end_date->modify('+1 year');
            break;
    }
    // Correct the date to be inclusive, a day, week, month, or year from start_date
    $end_date->modify('-1 day'); 

    // Validate inputs
    if ($pickup_city_id && $dropoff_city_id && $vehicle_type_id && $vehicle_id && $time_slot_id && $price > 0 && $start_date) {
        $stmt = $conn->prepare("
            INSERT INTO trip_schedules (
                pickup_city_id, 
                dropoff_city_id, 
                vehicle_type_id, 
                vehicle_id, 
                time_slot_id, 
                price, 
                start_date, 
                end_date,
                recurrence_type,
                recurrence_days
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiiidsdsss",
            $pickup_city_id,
            $dropoff_city_id,
            $vehicle_type_id,
            $vehicle_id,
            $time_slot_id,
            $price,
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d'),
            $recurrence_type,
            $recurrence_days
        );
        $stmt->execute();
        $stmt->close();
        
        header('Location: ' . SITE_URL . '/admin/trips.php?success=1');
        exit;
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
    <title>Add Trip - DGC Transports</title>
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
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }
    </style>
    <script>
        function toggleRecurrenceDays() {
            const type = document.getElementById('recurrence_type').value;
            document.getElementById('recurrence_days_section').style.display = (type === 'week') ? 'block' : 'none';
        }

        function filterDropoffCities() {
            const pickupCityId = document.getElementById('pickup_city_id').value;
            const dropoffCitySelect = document.getElementById('dropoff_city_id');
            const dropoffOptions = dropoffCitySelect.options;

            // Loop through all options in the dropoff city select
            for (let i = 0; i < dropoffOptions.length; i++) {
                const option = dropoffOptions[i];
                // Show all options by default
                option.style.display = 'block';

                // Hide the option that matches the selected pickup city
                if (option.value === pickupCityId && pickupCityId !== '') {
                    option.style.display = 'none';
                }
            }

            // If the selected dropoff city is the same as the pickup city, reset it
            if (dropoffCitySelect.value === pickupCityId) {
                dropoffCitySelect.value = '';
            }
        }
    </script>
</head>
<body>
    <div class="container mx-auto mt-10 p-6">
        <div class="card p-8">
            <h1 class="text-3xl font-bold mb-6">
                <i class="fas fa-plus text-red-600 mr-2"></i>Add New Trip
            </h1>

            <?php if (isset($error)): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="pickup_city_id" class="label block mb-2">Pickup City</label>
                    <select id="pickup_city_id" name="pickup_city_id" class="input-field" onchange="filterDropoffCities()" required>
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
                    <input type="number" id="price" name="price" step="0.01" class="input-field" required>
                </div>
                <div>
                    <label for="start_date" class="label block mb-2">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="input-field" required>
                </div>
                <div>
                    <label for="recurrence_type" class="label block mb-2">Schedule For</label>
                    <select id="recurrence_type" name="recurrence_type" class="input-field" onchange="toggleRecurrenceDays()" required>
                        <option value="day">One Day</option>
                        <option value="week">One Week</option>
                        <option value="month">One Month</option>
                        <option value="year">One Year</option>
                    </select>
                </div>
                <div id="recurrence_days_section" style="display: none;">
                    <label class="label block mb-2">Days (for Weekly Schedule)</label>
                    <div class="checkbox-grid">
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day): ?>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="recurrence_days[]" value="<?= $day ?>" class="text-red-600 focus:ring-red-600">
                                <span><?= $day ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>Create Trip Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
