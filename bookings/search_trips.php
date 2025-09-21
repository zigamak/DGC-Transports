<?php
// bookings/search_trips.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pickup_city_id = isset($_POST['pickup_city_id']) ? (int)$_POST['pickup_city_id'] : 0;
    $dropoff_city_id = isset($_POST['dropoff_city_id']) ? (int)$_POST['dropoff_city_id'] : 0;
    $departure_date = isset($_POST['departure_date']) ? $_POST['departure_date'] : '';
    $vehicle_type_id = isset($_POST['vehicle_type_id']) ? (int)$_POST['vehicle_type_id'] : 0;
    $num_seats = isset($_POST['num_seats']) ? (int)$_POST['num_seats'] : 0;

    // Validate inputs
    if ($pickup_city_id && $dropoff_city_id && $departure_date && $vehicle_type_id && $num_seats > 0) {
        if ($pickup_city_id === $dropoff_city_id) {
            $error = 'Pickup and dropoff cities cannot be the same.';
        } elseif (strtotime($departure_date) < strtotime(date('Y-m-d'))) {
            $error = 'Departure date cannot be in the past.';
        } else {
            // Store search criteria in session
            $_SESSION['search'] = [
                'pickup_city_id' => $pickup_city_id,
                'dropoff_city_id' => $dropoff_city_id,
                'departure_date' => $departure_date,
                'vehicle_type_id' => $vehicle_type_id,
                'num_seats' => $num_seats
            ];
        }
    } else {
        $error = 'Please fill all required fields correctly.';
    }
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
$pickup_city = $pickup_city_query->get_result()->fetch_assoc()['name'] ?? 'Unknown';
$pickup_city_query->close();

$dropoff_city_query = $conn->prepare("SELECT name FROM cities WHERE id = ?");
$dropoff_city_query->bind_param("i", $search['dropoff_city_id']);
$dropoff_city_query->execute();
$dropoff_city = $dropoff_city_query->get_result()->fetch_assoc()['name'] ?? 'Unknown';
$dropoff_city_query->close();

// Query to fetch valid trip templates and instances, including arrival time
$trips_query = "
    SELECT 
        CONCAT(tt.id, '-', ?) AS virtual_trip_id,
        tt.id AS template_id,
        v.id AS vehicle_id,
        v.vehicle_number,
        v.driver_name,
        tt.vehicle_type_id,
        vt.type AS vehicle_type,
        vt.capacity,
        tt.price,
        ts.departure_time,
        ts.arrival_time,
        ? AS trip_date,
        COALESCE(ti.booked_seats, 0) AS booked_seats_count,
        (vt.capacity - COALESCE(ti.booked_seats, 0)) AS available_seats
    FROM trip_templates tt
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN trip_instances ti ON tt.id = ti.template_id AND ti.trip_date = ? AND ti.status = 'active'
    WHERE tt.pickup_city_id = ?
        AND tt.dropoff_city_id = ?
        AND tt.vehicle_type_id = ?
        AND tt.status = 'active'
        AND ? BETWEEN tt.start_date AND tt.end_date
        AND (
            (tt.recurrence_type = 'day' AND ? = tt.start_date)
            OR (tt.recurrence_type = 'week' AND FIND_IN_SET(DAYNAME(?), tt.recurrence_days))
            OR (tt.recurrence_type = 'month')
            OR (tt.recurrence_type = 'year' AND DATE_FORMAT(?, '%m-%d') = DATE_FORMAT(tt.start_date, '%m-%d'))
        )
        AND (vt.capacity - COALESCE(ti.booked_seats, 0)) >= ?
    ORDER BY ts.departure_time
";

$stmt = $conn->prepare($trips_query);
$stmt->bind_param(
    "sssiisssssi",
    $search['departure_date'],
    $search['departure_date'],
    $search['departure_date'],
    $search['pickup_city_id'],
    $search['dropoff_city_id'],
    $search['vehicle_type_id'],
    $search['departure_date'],
    $search['departure_date'],
    $search['departure_date'],
    $search['departure_date'],
    $search['num_seats']
);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Trips - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
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
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .error-message i {
            margin-right: 6px;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 767px) {
            .trip-details-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .trip-details-grid > div:not(:last-child) {
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 1.5rem;
            }
            .time-route-section {
                flex-direction: column;
                align-items: flex-start;
            }
            .time-route-section > div {
                text-align: left;
            }
            .time-route-section .flex-1 {
                display: none; /* Hide horizontal line on mobile */
            }
            .time-route-section .w-3 {
                margin-right: 0.5rem;
            }
            .time-route-section .text-2xl {
                font-size: 1.5rem;
            }
            .trip-card-price, .trip-card-seats {
                text-align: center;
            }
            .trip-card-price .text-3xl {
                font-size: 2rem;
            }
            .trip-card-price .btn-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container mx-auto mt-6 p-4 md:mt-12 md:p-6">
        <div class="card p-6 md:p-10">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 md:mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4 md:mb-0">
                    <i class="fas fa-bus text-primary-red mr-3"></i>Available Trips
                </h1>
                <a href="../index.php" class="text-primary-red hover:text-dark-red font-semibold flex items-center justify-center md:justify-start">
                    <i class="fas fa-arrow-left mr-2"></i>New Search
                </a>
            </div>

            <?php if (isset($error)): ?>
                <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center space-y-4 md:space-y-0 md:space-x-6 text-gray-600 mb-6 md:mb-8">
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                    <span class="font-semibold"><?= htmlspecialchars($pickup_city) ?></span>
                    <i class="fas fa-arrow-right mx-3 text-gray-400"></i>
                    <span class="font-semibold"><?= htmlspecialchars($dropoff_city) ?></span>
                </div>
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-calendar text-primary-red mr-2"></i>
                    <span><?= date('D, M j, Y', strtotime($search['departure_date'])) ?></span>
                </div>
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-users text-primary-red mr-2"></i>
                    <span><?= $search['num_seats'] ?> Seat(s)</span>
                </div>
            </div>

            <?php if (empty($trips)): ?>
                <div class="text-center py-8 md:py-12">
                    <i class="fas fa-bus text-5xl md:text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-700 mb-2">No Trips Available</h3>
                    <p class="text-gray-500 mb-4 md:mb-6">Sorry, no trips found for your selected route, date, and vehicle type.</p>
                    <a href="../index.php" class="btn-primary">
                        <i class="fas fa-search mr-2"></i>Try Different Search
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4 md:space-y-6">
                    <?php foreach ($trips as $trip): ?>
                        <div class="card p-4 md:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 md:gap-6 items-center trip-details-grid">
                                <div class="md:col-span-2 time-route-section">
                                    <div class="flex items-center space-x-4 mb-2">
                                        <div class="text-center">
                                            <div class="text-xl md:text-2xl font-bold text-black">
                                                <?= date('H:i', strtotime($trip['departure_time'])) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= date('h:i A', strtotime($trip['departure_time'])) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">Departure</div>
                                        </div>
                                        <div class="flex-1 flex items-center">
                                            <div class="w-3 h-3 bg-primary-red rounded-full"></div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-2"></div>
                                            <div class="text-center">
                                                <div class="text-xl md:text-2xl font-bold text-black">
                                                    <?= date('H:i', strtotime($trip['arrival_time'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= date('h:i A', strtotime($trip['arrival_time'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">Arrival</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 mt-2 md:mt-0">
                                        <i class="fas fa-bus mr-2 text-primary-red"></i>
                                        <span><?= htmlspecialchars($trip['vehicle_type']) ?> - <?= htmlspecialchars($trip['vehicle_number']) ?> (Driver: <?= htmlspecialchars($trip['driver_name']) ?>)</span>
                                    </div>
                                </div>
                                <div class="text-center trip-card-seats">
                                    <div class="text-lg font-bold <?= $trip['available_seats'] > 3 ? 'text-green-600' : ($trip['available_seats'] > 1 ? 'text-yellow-600' : 'text-red-600') ?> mb-1">
                                        <?= $trip['available_seats'] ?> seats left
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        of <?= $trip['capacity'] ?> total
                                    </div>
                                    <?php if ($trip['booked_seats_count'] > 0): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            (<?= $trip['booked_seats_count'] ?> booked)
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center trip-card-price">
                                    <div class="text-2xl md:text-3xl font-bold text-primary-red mb-2">
                                        ₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mb-3">
                                        ₦<?= number_format($trip['price'], 0) ?> per seat
                                    </div>
                                    <button onclick="selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>, <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>')" 
                                        class="btn-primary w-full">
                                        <i class="fas fa-ticket-alt mr-2"></i>Select Trip
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectTrip(virtualTripId, numSeats, price, templateId, tripDate) {
            // Disable all buttons to prevent multiple clicks
            const buttons = document.querySelectorAll('.btn-primary');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            });

            // Prepare data for the POST request
            const data = new URLSearchParams({
                virtual_trip_id: virtualTripId,
                num_seats: numSeats,
                price: price,
                template_id: templateId,
                trip_date: tripDate
            });

            fetch('select_trip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: data
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().catch(() => response.text()).then(data => {
                        throw new Error(data.error || `HTTP error! Status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                // Redirect to seat selection page
                window.location.href = 'seat_selection.php';
            })
            .catch(error => {
                // Re-enable buttons and restore text
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-ticket-alt mr-2"></i>Select Trip';
                });
                // Display error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message mt-4';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${error.message}`;
                document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.flex.items-center.space-x-6'));
                // Scroll to top to show error
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    </script>
</body>
</html>