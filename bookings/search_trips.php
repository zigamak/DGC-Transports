<?php
// bookings/search_trips.php
session_start();

// Verify config inclusion
if (!file_exists('../includes/config.php')) {
    die('Error: Configuration file not found at ../includes/config.php');
}
require_once '../includes/config.php';
require_once '../includes/db.php';
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
if (!$pickup_city_query) {
    error_log("Pickup city query prepare failed: " . $conn->error);
    $error = "An error occurred while fetching city data.";
} else {
    $pickup_city_query->bind_param("i", $search['pickup_city_id']);
    $pickup_city_query->execute();
    $pickup_city = $pickup_city_query->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $pickup_city_query->close();
}

$dropoff_city_query = $conn->prepare("SELECT name FROM cities WHERE id = ?");
if (!$dropoff_city_query) {
    error_log("Dropoff city query prepare failed: " . $conn->error);
    $error = "An error occurred while fetching city data.";
} else {
    $dropoff_city_query->bind_param("i", $search['dropoff_city_id']);
    $dropoff_city_query->execute();
    $dropoff_city = $dropoff_city_query->get_result()->fetch_assoc()['name'] ?? 'Unknown';
    $dropoff_city_query->close();
}

// Query to fetch valid trip templates and instances
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
        ? AS trip_date,
        COALESCE(COUNT(b.id), 0) AS booked_seats_count,
        (vt.capacity - COALESCE(COUNT(b.id), 0)) AS available_seats
    FROM trip_templates tt
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN trip_instances ti ON tt.id = ti.template_id AND ti.trip_date = ? AND ti.status = 'active'
    LEFT JOIN bookings b ON ti.id = b.trip_id AND b.trip_date = ? AND b.payment_status = 'paid' AND b.status != 'cancelled'
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
    GROUP BY tt.id, v.id, vt.id, ts.id, ti.id
    ORDER BY ts.departure_time
";

$stmt = $conn->prepare($trips_query);
if (!$stmt) {
    error_log("Trips query prepare failed: " . $conn->error);
    $error = "An error occurred while fetching trips. Please try again.";
} else {
    $stmt->bind_param(
        "ssssiiissss",
        $search['departure_date'],
        $search['departure_date'],
        $search['departure_date'],
        $search['departure_date'],
        $search['pickup_city_id'],
        $search['dropoff_city_id'],
        $search['vehicle_type_id'],
        $search['departure_date'],
        $search['departure_date'],
        $search['departure_date'],
        $search['departure_date']
    );
    if (!$stmt->execute()) {
        error_log("Trips query execute failed: " . $stmt->error);
        $error = "An error occurred while fetching trips. Please try again.";
    } else {
        $trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Query for similar trips (same route, different date or vehicle type)
$similar_trips = [];
if (empty($trips)) {
    $date_range_start = date('Y-m-d', strtotime($search['departure_date'] . ' -7 days'));
    $date_range_end = date('Y-m-d', strtotime($search['departure_date'] . ' +7 days'));
    $similar_trips_query = "
        SELECT 
            CONCAT(tt.id, '-', ti.trip_date) AS virtual_trip_id,
            tt.id AS template_id,
            v.id AS vehicle_id,
            v.vehicle_number,
            v.driver_name,
            tt.vehicle_type_id,
            vt.type AS vehicle_type,
            vt.capacity,
            tt.price,
            ts.departure_time,
            ti.trip_date,
            COALESCE(COUNT(b.id), 0) AS booked_seats_count,
            (vt.capacity - COALESCE(COUNT(b.id), 0)) AS available_seats
        FROM trip_templates tt
        JOIN vehicles v ON tt.vehicle_id = v.id
        JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
        JOIN time_slots ts ON tt.time_slot_id = ts.id
        LEFT JOIN trip_instances ti ON tt.id = ti.template_id AND ti.trip_date BETWEEN ? AND ? AND ti.status = 'active'
        LEFT JOIN bookings b ON ti.id = b.trip_id AND b.trip_date BETWEEN ? AND ? AND b.payment_status = 'paid' AND b.status != 'cancelled'
        WHERE tt.pickup_city_id = ?
            AND tt.dropoff_city_id = ?
            AND tt.status = 'active'
            AND ti.trip_date BETWEEN ? AND ?
        GROUP BY tt.id, v.id, vt.id, ts.id, ti.id, ti.trip_date
        ORDER BY ti.trip_date, ts.departure_time
        LIMIT 5
    ";
    $stmt = $conn->prepare($similar_trips_query);
    if (!$stmt) {
        error_log("Similar trips query prepare failed: " . $conn->error);
    } else {
        $stmt->bind_param(
            "ssiissii",
            $date_range_start,
            $date_range_end,
            $date_range_start,
            $date_range_end,
            $search['pickup_city_id'],
            $search['dropoff_city_id'],
            $date_range_start,
            $date_range_end
        );
        if (!$stmt->execute()) {
            error_log("Similar trips query execute failed: " . $stmt->error);
        } else {
            $similar_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Available Trips - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --accent-blue: #3b82f6;
            --slate-800: #1e293b;
        }
        body {
            background: linear-gradient(135deg, #f0f4f8, #e2e8f0);
            font-family: 'Poppins', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1);
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 15px rgba(227, 6, 19, 0.4);
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(227, 6, 19, 0.5);
        }
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            background: #ffe6e6;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--primary-red);
        }
        .error-message i {
            margin-right: 8px;
        }
        .seat-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .seat-badge.high {
            background-color: #d1fae5;
            color: #065f46;
        }
        .seat-badge.medium {
            background-color: #fef3c7;
            color: #b45309;
        }
        .seat-badge.low {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .seat-badge.fully-booked {
            background-color: #e5e7eb;
            color: #4b5563;
        }
        .info-panel {
            background-color: var(--white);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="container mx-auto mt-6 p-4 md:mt-12 md:p-6">
        <div class="card p-6 md:p-10 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 md:mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4 md:mb-0">
                    <i class="fas fa-bus text-primary-red mr-3"></i>Available Trips
                </h1>
                <a href="../index.php" class="text-primary-red hover:text-dark-red font-semibold flex items-center transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>New Search
                </a>
            </div>

            <?php if (isset($error)): ?>
                <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <div class="info-panel flex flex-wrap items-center justify-between space-y-4 md:space-y-0 md:space-x-6 text-gray-600 mb-6 md:mb-8">
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($pickup_city) ?></span>
                    <i class="fas fa-long-arrow-alt-right mx-3 text-gray-400"></i>
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($dropoff_city) ?></span>
                </div>
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                    <span><?= date('D, M j, Y', strtotime($search['departure_date'])) ?></span>
                </div>
                <div class="flex items-center w-full md:w-auto">
                    <i class="fas fa-users text-primary-red mr-2"></i>
                    <span><?= $search['num_seats'] ?> Seat(s)</span>
                </div>
            </div>

            <?php if (empty($trips)): ?>
                <div class="text-center py-8 md:py-12">
                    <i class="fas fa-bus text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-700 mb-2">No Trips Available</h3>
                    <p class="text-gray-500 mb-6">Sorry, no trips found for your selected route, date, and vehicle type.</p>
                    <a href="../index.php" class="btn-primary">
                        <i class="fas fa-search mr-2"></i>Try Different Search
                    </a>
                </div>
                <?php if (!empty($similar_trips)): ?>
                    <div class="mt-8">
                        <h3 class="text-2xl font-bold text-slate-800 mb-4 border-l-4 border-primary-red pl-4">Similar Trips Available</h3>
                        <div class="space-y-6">
                            <?php foreach ($similar_trips as $trip): ?>
                                <div class="card p-4 md:p-6" <?php if ($trip['available_seats'] >= $search['num_seats']): ?>onclick="selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>, <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>')"<?php endif; ?>>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                                        <div class="md:col-span-2">
                                            <div class="flex items-center space-x-4 mb-2">
                                                <div>
                                                    <div class="text-2xl font-bold text-slate-800"><?= date('gA', strtotime($trip['departure_time'])) ?></div>
                                                    <div class="text-sm text-gray-500">Departure</div>
                                                </div>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-2">
                                                <i class="fas fa-bus mr-2 text-primary-red"></i>
                                                <span><?= htmlspecialchars($trip['vehicle_type']) ?> - <?= htmlspecialchars($trip['vehicle_number']) ?> (Driver: <?= htmlspecialchars($trip['driver_name']) ?>)</span>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-calendar-alt mr-2 text-primary-red"></i>
                                                <span>Date: <?= date('D, M j, Y', strtotime($trip['trip_date'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <?php
                                                $seats_class = $trip['available_seats'] == 0 ? 'fully-booked' : 'low';
                                                if ($trip['available_seats'] > 5) {
                                                    $seats_class = 'high';
                                                } elseif ($trip['available_seats'] > 2) {
                                                    $seats_class = 'medium';
                                                }
                                            ?>
                                            <span class="seat-badge <?= $seats_class ?>">
                                                <i class="fas fa-chair mr-2"></i>
                                                <?= $trip['available_seats'] == 0 ? 'Fully Booked' : $trip['available_seats'] . ' seats left' ?>
                                            </span>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-3xl font-bold text-primary-red mb-1">
                                                ₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                ₦<?= number_format($trip['price'], 0) ?> per seat
                                            </div>
                                            <?php if ($trip['available_seats'] >= $search['num_seats']): ?>
                                                <button onclick="event.stopPropagation(); selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>, <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>')" class="btn-primary w-full mt-3">
                                                    <i class="fas fa-ticket-alt mr-2"></i>Book Now
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($trips as $trip): ?>
                        <div class="card p-4 md:p-6" <?php if ($trip['available_seats'] >= $search['num_seats']): ?>onclick="selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>, <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>')"<?php endif; ?>>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                                <div class="md:col-span-2">
                                    <div class="flex items-center space-x-4 mb-2">
                                        <div>
                                            <div class="text-2xl font-bold text-slate-800"><?= date('gA', strtotime($trip['departure_time'])) ?></div>
                                            <div class="text-sm text-gray-500">Departure</div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-2">
                                        <i class="fas fa-bus mr-2 text-primary-red"></i>
                                        <span><?= htmlspecialchars($trip['vehicle_type']) ?> - <?= htmlspecialchars($trip['vehicle_number']) ?> (Driver: <?= htmlspecialchars($trip['driver_name']) ?>)</span>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <?php
                                        $seats_class = $trip['available_seats'] == 0 ? 'fully-booked' : 'low';
                                        if ($trip['available_seats'] > 5) {
                                            $seats_class = 'high';
                                        } elseif ($trip['available_seats'] > 2) {
                                            $seats_class = 'medium';
                                        }
                                    ?>
                                    <span class="seat-badge <?= $seats_class ?>">
                                        <i class="fas fa-chair mr-2"></i>
                                        <?= $trip['available_seats'] == 0 ? 'Fully Booked' : $trip['available_seats'] . ' seats left' ?>
                                    </span>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-primary-red mb-1">
                                        ₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        ₦<?= number_format($trip['price'], 0) ?> per seat
                                    </div>
                                    <?php if ($trip['available_seats'] >= $search['num_seats']): ?>
                                        <button onclick="event.stopPropagation(); selectTrip('<?= $trip['virtual_trip_id'] ?>', <?= $search['num_seats'] ?>, <?= $trip['price'] ?>, <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>')" class="btn-primary w-full mt-3">
                                            <i class="fas fa-ticket-alt mr-2"></i>Book Now
                                        </button>
                                    <?php endif; ?>
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
            const buttons = document.querySelectorAll('.btn-primary');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            });

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
                window.location.href = 'seat_selection.php';
            })
            .catch(error => {
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-ticket-alt mr-2"></i>Book Now';
                });
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message mt-4';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${error.message}`;
                document.querySelector('.container .card').insertBefore(errorDiv, document.querySelector('.info-panel'));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    </script>
</body>
</html>