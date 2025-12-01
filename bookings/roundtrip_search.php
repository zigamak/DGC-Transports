<?php
// bookings/roundtrip_search.php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../templates/header.php';

// ... (PHP processing logic remains exactly the same) ...

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pickup_city_id = isset($_POST['pickup_city_id']) ? (int)$_POST['pickup_city_id'] : 0;
    $dropoff_city_id = isset($_POST['dropoff_city_id']) ? (int)$_POST['dropoff_city_id'] : 0;
    $departure_date = isset($_POST['departure_date']) ? $_POST['departure_date'] : '';
    $return_date = isset($_POST['return_date']) ? $_POST['return_date'] : '';
    $vehicle_type_id = isset($_POST['vehicle_type_id']) ? (int)$_POST['vehicle_type_id'] : 0;
    $num_seats = isset($_POST['num_seats']) ? (int)$_POST['num_seats'] : 0;

    if ($pickup_city_id && $dropoff_city_id && $departure_date && $return_date && $vehicle_type_id && $num_seats > 0) {
        if ($pickup_city_id === $dropoff_city_id) {
            $error = 'Pickup and dropoff cities cannot be the same.';
        } elseif (strtotime($departure_date) < strtotime(date('Y-m-d'))) {
            $error = 'Departure date cannot be in the past.';
        } elseif (strtotime($return_date) < strtotime($departure_date)) {
            $error = 'Return date must be after departure date.';
        } else {
            $_SESSION['roundtrip_search'] = [
                'pickup_city_id' => $pickup_city_id,
                'dropoff_city_id' => $dropoff_city_id,
                'departure_date' => $departure_date,
                'return_date' => $return_date,
                'vehicle_type_id' => $vehicle_type_id,
                'num_seats' => $num_seats
            ];
        }
    } else {
        $error = 'Please fill all required fields correctly.';
    }
}

$search = $_SESSION['roundtrip_search'] ?? [];
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

// Query outbound trips
$outbound_trips = [];
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
$stmt->bind_param("ssssiiissss", 
    $search['departure_date'], $search['departure_date'], $search['departure_date'], $search['departure_date'],
    $search['pickup_city_id'], $search['dropoff_city_id'], $search['vehicle_type_id'],
    $search['departure_date'], $search['departure_date'], $search['departure_date'], $search['departure_date']
);
$stmt->execute();
$outbound_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Query return trips (swap cities)
$return_trips = [];
$stmt = $conn->prepare($trips_query);
$stmt->bind_param("ssssiiissss", 
    $search['return_date'], $search['return_date'], $search['return_date'], $search['return_date'],
    $search['dropoff_city_id'], $search['pickup_city_id'], $search['vehicle_type_id'],
    $search['return_date'], $search['return_date'], $search['return_date'], $search['return_date']
);
$stmt->execute();
$return_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Round Trip Selection - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
        }
        /* Mobile-first card grid: 1 column on small screens, 2 on medium */
        .trip-card-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) { /* md breakpoint */
            .trip-card-grid {
                grid-template-columns: 2fr 1fr 1fr; /* Time, Vehicle/Seats, Price */
            }
        }
        .card {
            background: white;
            border-radius: 12px; /* Slightly smaller radius for a modern feel */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); /* Softer shadow */
            transition: transform 0.2s ease, box-shadow 0.2s ease, border 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent; /* Base border for visual consistency */
        }
        .card:hover {
            transform: translateY(-3px); /* Subtler lift */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .card.selected {
            border-color: var(--primary-red); /* Highlight with the primary color */
            box-shadow: 0 4px 20px rgba(227, 6, 19, 0.2);
            background-color: #fef7f7; /* Light background for selected state */
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: white;
            padding: 12px 24px;
            border-radius: 8px; /* Slightly smaller radius */
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto mt-6 md:mt-12 p-4 md:p-6 max-w-4xl">
        
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border-t-4 border-primary-red">
            <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-exchange-alt text-primary-red mr-3 text-xl"></i>Round Trip Booking
            </h1>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-700 text-sm">
                <div class="flex items-center bg-gray-50 p-3 rounded-lg">
                    <i class="fas fa-map-marker-alt text-primary-red mr-3 text-lg"></i>
                    <div>
                        <span class="font-bold block">Route:</span>
                        <span><?= htmlspecialchars($pickup_city) ?> ⇄ <?= htmlspecialchars($dropoff_city) ?></span>
                    </div>
                </div>
                <div class="flex items-center bg-gray-50 p-3 rounded-lg">
                    <i class="fas fa-calendar-alt text-primary-red mr-3 text-lg"></i>
                    <div>
                        <span class="font-bold block">Dates:</span>
                        <span><?= date('M j', strtotime($search['departure_date'])) ?> &rarr; <?= date('M j, Y', strtotime($search['return_date'])) ?></span>
                    </div>
                </div>
                <div class="flex items-center bg-gray-50 p-3 rounded-lg">
                    <i class="fas fa-users text-primary-red mr-3 text-lg"></i>
                    <div>
                        <span class="font-bold block">Seats:</span>
                        <span><?= $search['num_seats'] ?> Seat(s)</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-12">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-6 border-b-2 border-gray-200 pb-2">
                <i class="fas fa-arrow-right text-primary-red mr-2"></i>
                Outbound Trip: <?= htmlspecialchars($pickup_city) ?> &rarr; <?= htmlspecialchars($dropoff_city) ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= date('D, M j', strtotime($search['departure_date'])) ?>)</span>
            </h2>
            <div id="outboundTrips" class="space-y-4">
                <?php if (empty($outbound_trips)): ?>
                    <div class="text-center py-12 bg-white rounded-xl border border-gray-200">
                        <i class="fas fa-bus-slash text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">No Outbound Trips Available</h3>
                        <p class="text-gray-500">Please try a different date or route.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($outbound_trips as $trip): ?>
                        <div class="card p-4 md:p-6" onclick="selectOutbound('<?= $trip['virtual_trip_id'] ?>', <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>', <?= $trip['price'] ?>)" id="outbound_<?= $trip['virtual_trip_id'] ?>">
                            <div class="trip-card-grid gap-4 md:gap-6 items-center">
                                
                                <div class="order-1">
                                    <div class="text-3xl font-extrabold text-primary-red mb-1"><?= date('g:i A', strtotime($trip['departure_time'])) ?></div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-bus mr-2 text-primary-red"></i><?= htmlspecialchars($trip['vehicle_type']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Vehicle: <?= htmlspecialchars($trip['vehicle_number']) ?>
                                    </div>
                                </div>
                                
                                <div class="order-3 md:order-2 text-left md:text-center">
                                    <?php 
                                    $seats_left = $trip['available_seats'];
                                    $seats_class = 'bg-green-100 text-green-800';
                                    if ($seats_left <= 5) {
                                        $seats_class = 'bg-red-100 text-red-800';
                                    } elseif ($seats_left <= 10) {
                                        $seats_class = 'bg-yellow-100 text-yellow-800';
                                    }
                                    ?>
                                    <span class="inline-block px-3 py-1 <?= $seats_class ?> rounded-full text-sm font-semibold">
                                        <?= $seats_left ?> seat(s) left
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">Capacity: <?= $trip['capacity'] ?></div>
                                </div>
                                
                                <div class="order-2 md:order-3 text-right">
                                    <div class="text-2xl font-extrabold text-gray-900">₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?></div>
                                    <div class="text-sm text-gray-500">Total Price</div>
                                    <div class="text-xs text-primary-red font-semibold">₦<?= number_format($trip['price'], 0) ?> per seat</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-6 border-b-2 border-gray-200 pb-2">
                <i class="fas fa-arrow-left text-primary-red mr-2"></i>
                Return Trip: <?= htmlspecialchars($dropoff_city) ?> &rarr; <?= htmlspecialchars($pickup_city) ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= date('D, M j', strtotime($search['return_date'])) ?>)</span>
            </h2>
            <div id="returnTrips" class="space-y-4">
                <?php if (empty($return_trips)): ?>
                    <div class="text-center py-12 bg-white rounded-xl border border-gray-200">
                        <i class="fas fa-bus-slash text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">No Return Trips Available</h3>
                        <p class="text-gray-500">Please try a different date or route.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($return_trips as $trip): ?>
                        <div class="card p-4 md:p-6" onclick="selectReturn('<?= $trip['virtual_trip_id'] ?>', <?= $trip['template_id'] ?>, '<?= $trip['trip_date'] ?>', <?= $trip['price'] ?>)" id="return_<?= $trip['virtual_trip_id'] ?>">
                            <div class="trip-card-grid gap-4 md:gap-6 items-center">
                                
                                <div class="order-1">
                                    <div class="text-3xl font-extrabold text-primary-red mb-1"><?= date('g:i A', strtotime($trip['departure_time'])) ?></div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-bus mr-2 text-primary-red"></i><?= htmlspecialchars($trip['vehicle_type']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Vehicle: <?= htmlspecialchars($trip['vehicle_number']) ?>
                                    </div>
                                </div>
                                
                                <div class="order-3 md:order-2 text-left md:text-center">
                                    <?php 
                                    $seats_left = $trip['available_seats'];
                                    $seats_class = 'bg-green-100 text-green-800';
                                    if ($seats_left <= 5) {
                                        $seats_class = 'bg-red-100 text-red-800';
                                    } elseif ($seats_left <= 10) {
                                        $seats_class = 'bg-yellow-100 text-yellow-800';
                                    }
                                    ?>
                                    <span class="inline-block px-3 py-1 <?= $seats_class ?> rounded-full text-sm font-semibold">
                                        <?= $seats_left ?> seat(s) left
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">Capacity: <?= $trip['capacity'] ?></div>
                                </div>
                                
                                <div class="order-2 md:order-3 text-right">
                                    <div class="text-2xl font-extrabold text-gray-900">₦<?= number_format($trip['price'] * $search['num_seats'], 0) ?></div>
                                    <div class="text-sm text-gray-500">Total Price</div>
                                    <div class="text-xs text-primary-red font-semibold">₦<?= number_format($trip['price'], 0) ?> per seat</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="sticky bottom-0 left-0 right-0 bg-white p-4 shadow-2xl border-t border-gray-200 z-10">
            <div class="flex flex-col md:flex-row justify-between items-center max-w-4xl mx-auto">
                <p id="selectionStatus" class="text-center md:text-left text-gray-600 mb-3 md:mb-0 text-lg font-medium">
                    <i class="fas fa-info-circle text-primary-red mr-2"></i>Please select both outbound and return trips
                </p>
                <button id="continueBtn" onclick="confirmSelection()" disabled class="btn-primary w-full md:w-auto disabled:opacity-50 disabled:cursor-not-allowed text-lg py-3 px-8">
                    <i class="fas fa-arrow-right mr-2"></i>Continue to Seat Selection
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedOutbound = null;
        let selectedReturn = null;
        const numSeats = <?= $search['num_seats'] ?>; // Get num_seats from PHP

        function selectOutbound(virtualTripId, templateId, tripDate, price) {
            document.querySelectorAll('#outboundTrips .card').forEach(card => card.classList.remove('selected'));
            
            document.getElementById('outbound_' + virtualTripId).classList.add('selected');
            selectedOutbound = {virtualTripId, templateId, tripDate, price};
            
            updateButton();
        }

        function selectReturn(virtualTripId, templateId, tripDate, price) {
            document.querySelectorAll('#returnTrips .card').forEach(card => card.classList.remove('selected'));
            
            document.getElementById('return_' + virtualTripId).classList.add('selected');
            selectedReturn = {virtualTripId, templateId, tripDate, price};
            
            updateButton();
        }

        function updateButton() {
            const btn = document.getElementById('continueBtn');
            const status = document.getElementById('selectionStatus');
            
            if (selectedOutbound && selectedReturn) {
                btn.disabled = false;
                const total = (selectedOutbound.price + selectedReturn.price) * numSeats;
                // Use a proper locale string for currency formatting
                const formattedTotal = total.toLocaleString('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }); 
                status.innerHTML = `<span class="font-extrabold text-green-700"><i class="fas fa-check-circle mr-2"></i>Total Trip Cost: ${formattedTotal}</span>`;
            } else if (selectedOutbound) {
                btn.disabled = true;
                status.innerHTML = '<i class="fas fa-exclamation-circle text-yellow-600 mr-2"></i>Please select a return trip';
            } else if (selectedReturn) {
                btn.disabled = true;
                status.innerHTML = '<i class="fas fa-exclamation-circle text-yellow-600 mr-2"></i>Please select an outbound trip';
            } else {
                btn.disabled = true;
                status.innerHTML = '<i class="fas fa-info-circle text-gray-600 mr-2"></i>Please select both outbound and return trips';
            }
        }

        function confirmSelection() {
            if (!selectedOutbound || !selectedReturn) {
                alert('Please select both outbound and return trips');
                return;
            }

            // Store in session via AJAX
            const data = new FormData();
            data.append('outbound', JSON.stringify(selectedOutbound));
            data.append('return', JSON.stringify(selectedReturn));
            data.append('num_seats', numSeats);

            // Assuming 'process_roundtrip_selection.php' handles the AJAX call
            fetch('process_roundtrip_selection.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'roundtrip_seat_selection.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error processing selection. Please check the console.');
            });
        }
    </script>
</body>
</html>