<?php
// bookings/roundtrip_seat_selection.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

if (!isset($_SESSION['roundtrip'])) {
    header("Location: ../index.php");
    exit();
}

$roundtrip = $_SESSION['roundtrip'];
$outbound = $roundtrip['outbound'];
$return = $roundtrip['return'];
$num_seats = $roundtrip['num_seats'];

// Get outbound trip details
$stmt = $conn->prepare("
    SELECT 
        tt.id,
        tt.pickup_city_id,
        pc.name as pickup_city,
        dc.name as dropoff_city,
        vt.capacity,
        vt.type as vehicle_type,
        v.vehicle_number,
        ts.departure_time
    FROM trip_templates tt
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE tt.id = ?
");
$stmt->bind_param("i", $outbound['templateId']);
$stmt->execute();
$outbound_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get return trip details
$stmt = $conn->prepare("
    SELECT 
        tt.id,
        tt.pickup_city_id,
        pc.name as pickup_city,
        dc.name as dropoff_city,
        vt.capacity,
        vt.type as vehicle_type,
        v.vehicle_number,
        ts.departure_time
    FROM trip_templates tt
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE tt.id = ?
");
$stmt->bind_param("i", $return['templateId']);
$stmt->execute();
$return_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch booked seats for outbound trip
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status != 'cancelled'
");
$stmt->bind_param("is", $outbound['templateId'], $outbound['tripDate']);
$stmt->execute();
$outbound_booked = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$outbound_booked_seats = array_column($outbound_booked, 'seat_number');
$stmt->close();

// Fetch booked seats for return trip
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status != 'cancelled'
");
$stmt->bind_param("is", $return['templateId'], $return['tripDate']);
$stmt->execute();
$return_booked = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$return_booked_seats = array_column($return_booked, 'seat_number');
$stmt->close();

// Function to generate seat layout
function generateSeatLayout($capacity) {
    $layouts = [
        5 => [
            'rows' => [
                ['driver', 1],
                [2, 3],
                [4, 5]
            ],
            'type' => 'car'
        ],
        12 => [
            'rows' => [
                ['driver', 1],
                [2, 3],
                [4, 5, 6],
                [7, 8, 9],
                [10, 11, 12]
            ],
            'type' => 'minibus'
        ],
        14 => [
            'rows' => [
                ['driver', 1],
                [2, 3],
                [4, 5, 6],
                [7, 8, 9],
                [10, 11, 12],
                [13, 14]
            ],
            'type' => 'minibus'
        ],
        18 => [
            'rows' => [
                ['driver', 1, 2],
                [3, 4],
                [5, 6, 7],
                [8, 9, 10],
                [11, 12, 13, 14],
                [15, 16, 17, 18]
            ],
            'type' => 'bus'
        ]
    ];
    
    return $layouts[$capacity] ?? $layouts[14];
}

$outbound_layout = generateSeatLayout($outbound_details['capacity']);
$return_layout = generateSeatLayout($return_details['capacity']);

require_once '../templates/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Round Trip Seat Selection - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1f2937',
                        accent: '#ef4444',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .vehicle-container {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border: 3px solid #dee2e6;
            border-radius: 20px;
            padding: 30px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .seat-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
            align-items: center;
        }
        .driver-seat {
            background: linear-gradient(145deg, #495057, #343a40);
            border: 2px solid #6c757d;
            color: white;
            cursor: not-allowed;
            position: relative;
        }
        .driver-seat::after {
            content: "👨‍✈️";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
        }
        .seat-available {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 2px solid #dee2e6;
            color: #495057;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .seat-available:hover {
            background: linear-gradient(145deg, #e3f2fd, #bbdefb);
            border-color: #2196f3;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        .seat-selected {
            background: linear-gradient(145deg, #ef4444, #dc2626);
            border: 2px solid #b91c1c;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
            animation: seat-select 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        @keyframes seat-select {
            0% { transform: scale(0.95) translateY(0); }
            50% { transform: scale(1.05) translateY(-2px); }
            100% { transform: scale(1) translateY(-3px); }
        }
        .seat-booked {
            background: linear-gradient(145deg, #e9ecef, #dee2e6);
            border: 2px solid #adb5bd;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
            position: relative;
        }
        .seat-booked::before {
            content: "✕";
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.7rem;
            color: #dc3545;
        }
        .seat {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
        }
        .seat-number {
            margin-top: 3px;
            font-size: 0.75rem;
        }
        .aisle {
            width: 25px;
            border-left: 2px dashed #dee2e6;
            height: 70px;
        }
        .trip-section {
            border: 3px solid #e5e7eb;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .trip-section.completed {
            border-color: #10b981;
            background: #f0fdf4;
        }
        @media (max-width: 640px) {
            .seat {
                width: 60px;
                height: 60px;
                font-size: 0.7rem;
            }
            .seat-row { gap: 8px; }
            .aisle {
                width: 20px;
                height: 60px;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-10">
        <div class="max-w-6xl mx-auto px-4">
      <div class="bg-white rounded-xl shadow-lg p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-primary flex items-center">
            <i class="fas fa-exchange-alt text-accent mr-2"></i>
            Round Trip Seat Selection
        </h1>

        <a href="../index.php" class="text-primary hover:text-accent font-medium flex items-center gap-2 text-sm">
            <i class="fas fa-arrow-left text-sm"></i>
            <span>New Search</span>
        </a>
    </div>

    <p class="text-base text-gray-600 leading-relaxed">
        Select <span class="font-semibold text-accent"><?= $num_seats ?></span> seat(s) 
        for both your outbound and return trips.
    </p>
</div>

            <!-- Progress Indicator -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div id="outbound-indicator" class="w-10 h-10 rounded-full bg-accent text-white flex items-center justify-center font-bold mr-3">1</div>
                        <span id="outbound-label" class="font-semibold">Select Outbound Seats</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <div class="flex items-center">
                        <div id="return-indicator" class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold mr-3">2</div>
                        <span id="return-label" class="text-gray-600">Select Return Seats</span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold mr-3">3</div>
                        <span class="text-gray-600">Complete Booking</span>
                    </div>
                </div>
            </div>

            <!-- Outbound Trip Section -->
            <div id="outbound-section" class="trip-section">
                <h2 class="text-2xl font-bold mb-4 text-primary">
                    <i class="fas fa-arrow-right text-accent mr-2"></i>
                    Outbound: <?= htmlspecialchars($outbound_details['pickup_city']) ?> → <?= htmlspecialchars($outbound_details['dropoff_city']) ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-sm">
                    <div><i class="fas fa-calendar mr-2 text-accent"></i><strong>Date:</strong> <?= date('D, M j, Y', strtotime($outbound['tripDate'])) ?></div>
                    <div><i class="fas fa-clock mr-2 text-accent"></i><strong>Time:</strong> <?= date('h:i A', strtotime($outbound_details['departure_time'])) ?></div>
                    <div><i class="fas fa-bus mr-2 text-accent"></i><strong>Vehicle:</strong> <?= htmlspecialchars($outbound_details['vehicle_type']) ?></div>
                    <div><i class="fas fa-id-badge mr-2 text-accent"></i><strong>Vehicle No:</strong> <?= htmlspecialchars($outbound_details['vehicle_number']) ?></div>
                </div>

                <div class="vehicle-container mx-auto" style="max-width: 450px;">
                    <div class="text-center font-bold mb-4 text-lg">Select Your Seats</div>
                    <?php foreach ($outbound_layout['rows'] as $rowIndex => $row): ?>
                        <div class="seat-row">
                            <?php 
                            $seatCount = 0;
                            foreach ($row as $seatIndex => $seat): 
                                if ($seat === 'driver'): ?>
                                    <div class="seat driver-seat" title="Driver">
                                        <div class="seat-number">Driver</div>
                                    </div>
                                <?php else:
                                    $is_booked = in_array($seat, $outbound_booked_seats);
                                    $seatCount++;
                                ?>
                                    <input type="checkbox" 
                                           id="outbound_seat_<?= $seat ?>" 
                                           class="hidden outbound-seat-checkbox"
                                           value="<?= $seat ?>" 
                                           <?= $is_booked ? 'disabled' : '' ?>
                                           onchange="updateOutboundSelection(<?= $seat ?>)">
                                    <label for="outbound_seat_<?= $seat ?>" 
                                           class="seat <?= $is_booked ? 'seat-booked' : 'seat-available' ?>"
                                           id="outbound_label_<?= $seat ?>"
                                           title="Seat <?= $seat ?><?= $is_booked ? ' (Booked)' : '' ?>">
                                        <i class="fas fa-user seat-icon"></i>
                                        <div class="seat-number"><?= $seat ?></div>
                                    </label>
                                    
                                    <?php 
                                    if (($outbound_layout['type'] === 'bus' || $outbound_layout['type'] === 'minibus') && 
                                        $seatIndex < count($row) - 1 && 
                                        (($rowIndex == 0 && ($seatCount == 1 || $seatCount == 2)) || 
                                         ($rowIndex > 0 && $seatCount == 2))): ?>
                                        <div class="aisle"></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="outbound-selected-display" class="mt-6 p-4 bg-gray-50 rounded-xl hidden">
                    <h4 class="font-bold text-lg mb-3">Selected Outbound Seats:</h4>
                    <div id="outbound-selected-list" class="flex flex-wrap gap-3"></div>
                </div>
                
                <p id="outbound-error" class="text-red-600 font-medium mt-4 hidden text-center">
                    Please select exactly <?= $num_seats ?> seat(s) for the outbound trip.
                </p>

       <button type="button" id="outbound-continue" disabled
    class="mt-6 w-full font-bold py-5 px-6 rounded-xl transition-all duration-300 text-lg 
           disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed 
           bg-gradient-to-r from-red-500 to-red-600 text-white
           hover:from-red-600 hover:to-red-700"
    onclick="continueToReturn()">
    <i class="fas fa-arrow-right mr-2"></i>Continue to Return Trip
</button>

            </div>

            <!-- Return Trip Section -->
            <div id="return-section" class="trip-section hidden">
                <h2 class="text-2xl font-bold mb-4 text-primary">
                    <i class="fas fa-arrow-left text-accent mr-2"></i>
                    Return: <?= htmlspecialchars($return_details['pickup_city']) ?> → <?= htmlspecialchars($return_details['dropoff_city']) ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-sm">
                    <div><i class="fas fa-calendar mr-2 text-accent"></i><strong>Date:</strong> <?= date('D, M j, Y', strtotime($return['tripDate'])) ?></div>
                    <div><i class="fas fa-clock mr-2 text-accent"></i><strong>Time:</strong> <?= date('h:i A', strtotime($return_details['departure_time'])) ?></div>
                    <div><i class="fas fa-bus mr-2 text-accent"></i><strong>Vehicle:</strong> <?= htmlspecialchars($return_details['vehicle_type']) ?></div>
                    <div><i class="fas fa-id-badge mr-2 text-accent"></i><strong>Vehicle No:</strong> <?= htmlspecialchars($return_details['vehicle_number']) ?></div>
                </div>

                <div class="vehicle-container mx-auto" style="max-width: 450px;">
                    <div class="text-center font-bold mb-4 text-lg">Select Your Return Seats</div>
                    <?php foreach ($return_layout['rows'] as $rowIndex => $row): ?>
                        <div class="seat-row">
                            <?php 
                            $seatCount = 0;
                            foreach ($row as $seatIndex => $seat): 
                                if ($seat === 'driver'): ?>
                                    <div class="seat driver-seat" title="Driver">
                                        <div class="seat-number">Driver</div>
                                    </div>
                                <?php else:
                                    $is_booked = in_array($seat, $return_booked_seats);
                                    $seatCount++;
                                ?>
                                    <input type="checkbox" 
                                           id="return_seat_<?= $seat ?>" 
                                           class="hidden return-seat-checkbox"
                                           value="<?= $seat ?>" 
                                           <?= $is_booked ? 'disabled' : '' ?>
                                           onchange="updateReturnSelection(<?= $seat ?>)">
                                    <label for="return_seat_<?= $seat ?>" 
                                           class="seat <?= $is_booked ? 'seat-booked' : 'seat-available' ?>"
                                           id="return_label_<?= $seat ?>"
                                           title="Seat <?= $seat ?><?= $is_booked ? ' (Booked)' : '' ?>">
                                        <i class="fas fa-user seat-icon"></i>
                                        <div class="seat-number"><?= $seat ?></div>
                                    </label>
                                    
                                    <?php 
                                    if (($return_layout['type'] === 'bus' || $return_layout['type'] === 'minibus') && 
                                        $seatIndex < count($row) - 1 && 
                                        (($rowIndex == 0 && ($seatCount == 1 || $seatCount == 2)) || 
                                         ($rowIndex > 0 && $seatCount == 2))): ?>
                                        <div class="aisle"></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="return-selected-display" class="mt-6 p-4 bg-gray-50 rounded-xl hidden">
                    <h4 class="font-bold text-lg mb-3">Selected Return Seats:</h4>
                    <div id="return-selected-list" class="flex flex-wrap gap-3"></div>
                </div>
                
                <p id="return-error" class="text-red-600 font-medium mt-4 hidden text-center">
                    Please select exactly <?= $num_seats ?> seat(s) for the return trip.
                </p>

                <div class="flex gap-4 mt-6">
                    <button type="button" 
                            class="flex-1 font-bold py-5 px-6 rounded-xl transition-all duration-300 text-lg bg-gray-300 hover:bg-gray-400"
                            onclick="backToOutbound()">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Outbound
                    </button>
                    <button type="button" id="return-continue" disabled
                            class="flex-1 font-bold py-5 px-6 rounded-xl transition-all duration-300 text-lg disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed bg-gradient-to-r from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700"
                            onclick="proceedToBooking()">
                        <i class="fas fa-arrow-right mr-2"></i>Proceed to Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const requiredSeats = <?= $num_seats ?>;
        const outboundBookedSeats = <?= json_encode($outbound_booked_seats) ?>;
        const returnBookedSeats = <?= json_encode($return_booked_seats) ?>;
        
        let outboundSelectedSeats = [];
        let returnSelectedSeats = [];

        function updateOutboundSelection(seatNumber) {
            if (outboundBookedSeats.includes(seatNumber.toString())) {
                return;
            }
            
            const checkbox = document.getElementById('outbound_seat_' + seatNumber);
            const label = document.getElementById('outbound_label_' + seatNumber);
            
            if (checkbox.checked) {
                if (outboundSelectedSeats.length < requiredSeats) {
                    outboundSelectedSeats.push(seatNumber);
                    label.classList.remove('seat-available');
                    label.classList.add('seat-selected');
                } else {
                    checkbox.checked = false;
                    alert('You can only select ' + requiredSeats + ' seat(s).');
                }
            } else {
                outboundSelectedSeats = outboundSelectedSeats.filter(seat => seat != seatNumber);
                label.classList.remove('seat-selected');
                label.classList.add('seat-available');
            }
            
            updateOutboundUI();
        }

        function updateOutboundUI() {
            const continueBtn = document.getElementById('outbound-continue');
            const errorDiv = document.getElementById('outbound-error');
            const selectedDisplay = document.getElementById('outbound-selected-display');
            const selectedList = document.getElementById('outbound-selected-list');
            
            if (outboundSelectedSeats.length > 0) {
                selectedDisplay.classList.remove('hidden');
                selectedList.innerHTML = outboundSelectedSeats
                    .sort((a, b) => a - b)
                    .map(seat => `<span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">Seat ${seat}</span>`)
                    .join('');
            } else {
                selectedDisplay.classList.add('hidden');
            }

            if (outboundSelectedSeats.length === requiredSeats) {
                continueBtn.disabled = false;
                errorDiv.classList.add('hidden');
            } else {
                continueBtn.disabled = true;
                if (outboundSelectedSeats.length > 0) {
                    errorDiv.classList.remove('hidden');
                } else {
                    errorDiv.classList.add('hidden');
                }
            }
        }

        function updateReturnSelection(seatNumber) {
            if (returnBookedSeats.includes(seatNumber.toString())) {
                return;
            }
            
            const checkbox = document.getElementById('return_seat_' + seatNumber);
            const label = document.getElementById('return_label_' + seatNumber);
            
            if (checkbox.checked) {
                if (returnSelectedSeats.length < requiredSeats) {
                    returnSelectedSeats.push(seatNumber);
                    label.classList.remove('seat-available');
                    label.classList.add('seat-selected');
                } else {
                    checkbox.checked = false;
                    alert('You can only select ' + requiredSeats + ' seat(s).');
                }
            } else {
                returnSelectedSeats = returnSelectedSeats.filter(seat => seat != seatNumber);
                label.classList.remove('seat-selected');
                label.classList.add('seat-available');
            }
            
            updateReturnUI();
        }

        function updateReturnUI() {
            const continueBtn = document.getElementById('return-continue');
            const errorDiv = document.getElementById('return-error');
            const selectedDisplay = document.getElementById('return-selected-display');
            const selectedList = document.getElementById('return-selected-list');
            
            if (returnSelectedSeats.length > 0) {
                selectedDisplay.classList.remove('hidden');
                selectedList.innerHTML = returnSelectedSeats
                    .sort((a, b) => a - b)
                    .map(seat => `<span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">Seat ${seat}</span>`)
                    .join('');
            } else {
                selectedDisplay.classList.add('hidden');
            }

            if (returnSelectedSeats.length === requiredSeats) {
                continueBtn.disabled = false;
                errorDiv.classList.add('hidden');
            } else {
                continueBtn.disabled = true;
                if (returnSelectedSeats.length > 0) {
                    errorDiv.classList.remove('hidden');
                } else {
                    errorDiv.classList.add('hidden');
                }
            }
        }

        function continueToReturn() {
            if (outboundSelectedSeats.length !== requiredSeats) {
                alert('Please select exactly ' + requiredSeats + ' seat(s) for the outbound trip.');
                return;
            }

            document.getElementById('outbound-section').classList.add('completed');
            document.getElementById('outbound-indicator').classList.remove('bg-accent');
            document.getElementById('outbound-indicator').classList.add('bg-green-500');
            document.getElementById('outbound-indicator').innerHTML = '<i class="fas fa-check"></i>';
            
            document.getElementById('return-indicator').classList.remove('bg-gray-300', 'text-gray-600');
            document.getElementById('return-indicator').classList.add('bg-accent', 'text-white');
            document.getElementById('return-label').classList.remove('text-gray-600');
            document.getElementById('return-label').classList.add('font-semibold');
            
            document.getElementById('outbound-section').classList.add('hidden');
            document.getElementById('return-section').classList.remove('hidden');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function backToOutbound() {
            document.getElementById('return-section').classList.add('hidden');
            document.getElementById('outbound-section').classList.remove('hidden');
            
            document.getElementById('outbound-indicator').classList.remove('bg-green-500');
            document.getElementById('outbound-indicator').classList.add('bg-accent');
            document.getElementById('outbound-indicator').textContent = '1';
            document.getElementById('return-indicator').classList.remove('bg-accent', 'text-white');
            document.getElementById('return-indicator').classList.add('bg-gray-300', 'text-gray-600');
            document.getElementById('return-label').classList.remove('font-semibold');
            document.getElementById('return-label').classList.add('text-gray-600');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function proceedToBooking() {
            if (returnSelectedSeats.length !== requiredSeats) {
                alert('Please select exactly ' + requiredSeats + ' seat(s) for the return trip.');
                return;
            }

            const btn = document.getElementById('return-continue');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('outbound_seats', JSON.stringify(outboundSelectedSeats));
            formData.append('return_seats', JSON.stringify(returnSelectedSeats));

            fetch('process_roundtrip_seats.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'passenger_details.php';
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Proceed to Booking';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Error processing seats: ' + error.message);
                btn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Proceed to Booking';
                btn.disabled = false;
            });
        }

        updateOutboundUI();
        updateReturnUI();
    </script>
</body>
</html>