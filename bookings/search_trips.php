<?php
// bookings/search_trips.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pickup_city_id = $_POST['pickup_city_id'];
    $dropoff_city_id = $_POST['dropoff_city_id'];
    $departure_date = $_POST['departure_date'];
    $vehicle_type_id = $_POST['vehicle_type_id'];
    $num_seats = (int)$_POST['num_seats'];
    
    // Store search criteria in session
    $_SESSION['search'] = [
        'pickup_city_id' => $pickup_city_id,
        'dropoff_city_id' => $dropoff_city_id,
        'departure_date' => $departure_date,
        'vehicle_type_id' => $vehicle_type_id,
        'num_seats' => $num_seats
    ];
}

// Get search criteria from session
$search = $_SESSION['search'] ?? [];
if (empty($search)) {
    header("Location: ../index.php");
    exit();
}

// Get city names
$pickup_city_query = $conn->prepare("SELECT name FROM cities WHERE id = ?");
$pickup_city_query->bind_param("i", $search['pickup_city_id']);
$pickup_city_query->execute();
$pickup_city = $pickup_city_query->get_result()->fetch_assoc()['name'];

$dropoff_city_query = $conn->prepare("SELECT name FROM cities WHERE id = ?");
$dropoff_city_query->bind_param("i", $search['dropoff_city_id']);
$dropoff_city_query->execute();
$dropoff_city = $dropoff_city_query->get_result()->fetch_assoc()['name'];

// Generate available trips dynamically based on vehicle-time slot combinations
// This query creates virtual trips for any date by combining vehicles and time slots
$trips_query = "
    SELECT 
        CONCAT(v.id, '-', ts.id, '-', ?) as virtual_trip_id,
        v.id as vehicle_id,
        v.vehicle_number,
        v.driver_name,
        vt.id as vehicle_type_id,
        vt.type as vehicle_type,
        vt.capacity,
        vt.price,
        ts.id as time_slot_id,
        ts.departure_time,
        ts.arrival_time,
        ? as trip_date,
        (vt.capacity - COALESCE(booked_seats.seats_booked, 0)) as available_seats
    FROM vehicles v
    JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
    CROSS JOIN time_slots ts
    LEFT JOIN (
        SELECT 
            t.vehicle_id,
            t.time_slot_id,
            COUNT(sb.seat_number) as seats_booked
        FROM trips t
        JOIN bookings b ON t.id = b.trip_id
        JOIN seat_bookings sb ON b.id = sb.booking_id
        WHERE b.payment_status = 'paid'
        AND t.pickup_city_id = ?
        AND t.dropoff_city_id = ?
        AND t.trip_date = ?
        GROUP BY t.vehicle_id, t.time_slot_id
    ) booked_seats ON v.id = booked_seats.vehicle_id AND ts.id = booked_seats.time_slot_id
    WHERE v.vehicle_type_id = ?
    AND (vt.capacity - COALESCE(booked_seats.seats_booked, 0)) >= ?
    ORDER BY ts.departure_time
";

$stmt = $conn->prepare($trips_query);
$stmt->bind_param("ssiiisi", 
    $search['departure_date'],
    $search['departure_date'], 
    $search['pickup_city_id'], 
    $search['dropoff_city_id'], 
    $search['departure_date'],
    $search['vehicle_type_id'], 
    $search['num_seats']
);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Trips - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#dc2626',
                        secondary: '#991b1b',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-6xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold text-black">Available Trips</h1>
                    <a href="../index.php" class="text-primary hover:text-secondary font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>New Search
                    </a>
                </div>
                <div class="flex items-center space-x-6 text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt text-primary mr-2"></i>
                        <span class="font-semibold"><?= htmlspecialchars($pickup_city) ?></span>
                        <i class="fas fa-arrow-right mx-3 text-gray-400"></i>
                        <span class="font-semibold"><?= htmlspecialchars($dropoff_city) ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar text-primary mr-2"></i>
                        <span><?= date('D, M j, Y', strtotime($search['departure_date'])) ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-users text-primary mr-2"></i>
                        <span><?= $search['num_seats'] ?> Seat(s)</span>
                    </div>
                </div>
            </div>

            <!-- Trips List -->
            <?php if (empty($trips)): ?>
                <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                    <i class="fas fa-bus text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-700 mb-2">No Trips Available</h3>
                    <p class="text-gray-500 mb-6">Sorry, no trips found for your selected route, date, and vehicle type.</p>
                    <a href="../index.php" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-secondary transition-colors">
                        Try Different Search
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($trips as $trip): ?>
                        <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                            <div class="p-6">
                                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-center">
                                    <!-- Time and Route -->
                                    <div class="lg:col-span-2">
                                        <div class="flex items-center space-x-4 mb-2">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-black">
                                                    <?= date('H:i', strtotime($trip['departure_time'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">Departure</div>
                                            </div>
                                            <div class="flex-1 flex items-center">
                                                <div class="w-3 h-3 bg-primary rounded-full"></div>
                                                <div class="flex-1 h-1 bg-gray-300 mx-2"></div>
                                                <div class="w-3 h-3 bg-secondary rounded-full"></div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-black">
                                                    <?= date('H:i', strtotime($trip['arrival_time'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">Arrival</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-bus mr-2 text-primary"></i>
                                            <span><?= htmlspecialchars($trip['vehicle_type']) ?> - <?= htmlspecialchars($trip['vehicle_number']) ?> (Driver: <?= htmlspecialchars($trip['driver_name']) ?>)</span>
                                        </div>
                                    </div>

                                    <!-- Seats and Price -->
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-green-600 mb-1">
                                            <?= $trip['available_seats'] ?> seats left
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            of <?= $trip['capacity'] ?> total
                                        </div>
                                    </div>

                                    <!-- Price and Book Button -->
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-primary mb-2">
                                            ₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?>
                                        </div>
                                        <button onclick="selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>)" 
                                                class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-3 px-6 rounded-lg hover:from-secondary hover:to-primary transition-all duration-200">
                                            Select Trip
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectTrip(virtualTripId, numSeats, price) {
            fetch('select_trip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'virtual_trip_id=' + encodeURIComponent(virtualTripId) + '&num_seats=' + numSeats + '&price=' + price
            }).then(response => {
                if (response.ok) {
                    window.location.href = 'seat_selection.php';
                }
            });
        }
    </script>
</body>
</html>