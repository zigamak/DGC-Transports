<?php
// bookings/seat_selection.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

if (!isset($_SESSION['selected_trip'])) {
    header("Location: search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$num_seats = $trip['num_seats'];

// Get trip details for display
$stmt = $conn->prepare("
    SELECT 
        tt.id,
        tt.pickup_city_id,
        pc.name as pickup_city,
        dc.name as dropoff_city,
        vt.capacity,
        vt.type as vehicle_type
    FROM trip_templates tt
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    WHERE tt.id = ?
");
$stmt->bind_param("i", $trip['template_id']);
$stmt->execute();
$trip_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip_details) {
    header("Location: search_trips.php");
    exit();
}

// Fetch booked seats using correct schema
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status != 'cancelled'
");
$stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
$stmt->execute();
$booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$booked_seat_numbers = array_column($booked_seats, 'seat_number');
$stmt->close();

// Function to generate seat layout based on capacity
function generateSeatLayout($capacity) {
    $layouts = [
        5 => [
            'rows' => [
                ['driver', 1], // Front row: driver + 1 passenger
                [2, 3],        // Second row: 2 seats
                [4, 5]         // Third row: 2 seats
            ],
            'type' => 'car'
        ],
        12 => [
            'rows' => [
                ['driver', 1],     // Front row: driver + 1 passenger
                [2, 3],            // Row 2: 2 seats
                [4, 5, 6],         // Row 3: 3 seats
                [7, 8, 9],         // Row 4: 3 seats
                [10, 11, 12]       // Row 5: 3 seats
            ],
            'type' => 'minibus'
        ],
        18 => [
            'rows' => [
                ['driver', 1, 2],  // Front row: driver + 2 passengers
                [3, 4],            // Row 2: 2 seats
                [5, 6, 7],         // Row 3: 3 seats
                [8, 9, 10],        // Row 4: 3 seats
                [11, 12, 13, 14],  // Row 5: 4 seats
                [15, 16, 17, 18]   // Row 6: 4 seats (back row)
            ],
            'type' => 'bus'
        ]
    ];
    
    return $layouts[$capacity] ?? $layouts[12]; // Default to 12-seater if not found
}

$layout = generateSeatLayout($trip_details['capacity']);
require_once '../templates/header.php'; 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Selection - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1f2937',
                        accent: '#ef4444',
                        'accent-hover': '#dc2626',
                        'accent-selected': '#b91c1c',
                        'accent-booked': '#9ca3af',
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
            position: relative;
        }
        
        .submit-button-active {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .submit-button-active:hover {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
            transform: translateY(-1px);
        }
        
        .vehicle-type-badge {
            display: none;
        }
        
        @media (max-width: 640px) {
            .seat {
                width: 60px;
                height: 60px;
                font-size: 0.7rem;
            }
            
            .seat-row {
                gap: 8px;
            }
            
            .aisle {
                width: 20px;
                height: 60px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="min-h-screen py-10">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-4xl font-extrabold text-primary">Choose Your Seats</h1>
                    <a href="search_trips.php" class="text-primary hover:text-accent font-semibold flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Trips</span>
                    </a>
                </div>
                <p class="text-lg text-gray-600 mb-6">
                    Selecting <span class="font-bold text-accent"><?= $num_seats ?></span> seat(s) for your trip from <span class="font-bold"><?= htmlspecialchars($trip_details['pickup_city']) ?></span> to <span class="font-bold"><?= htmlspecialchars($trip_details['dropoff_city']) ?></span>
                    on <span class="font-bold"><?= date('D, M j, Y', strtotime($trip['trip_date'])) ?></span>.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center bg-white rounded-2xl shadow-lg p-6 mb-8">
                <div class="flex flex-wrap justify-center sm:justify-start gap-6 mb-4 sm:mb-0">
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-gradient-to-br from-white to-gray-100 border-2 border-gray-300"></div>
                        <span class="text-sm text-gray-700">Available</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-gradient-to-br from-red-500 to-red-600 border-2 border-red-700"></div>
                        <span class="text-sm text-gray-700">Selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-gradient-to-br from-gray-300 to-gray-400 border-2 border-gray-500"></div>
                        <span class="text-sm text-gray-700">Booked</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-gradient-to-br from-gray-600 to-gray-700 border-2 border-gray-800"></div>
                        <span class="text-sm text-gray-700">Driver</span>
                    </div>
                </div>
                <div class="text-md font-semibold text-primary text-center sm:text-right">
                    <span id="selectedCount" class="text-2xl font-bold text-accent-hover">0</span> / <?= $num_seats ?> seats selected
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form id="seatForm" action="process_seat_selection.php" method="POST">
                    <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">
                    
                    <div class="vehicle-container mx-auto" style="max-width: 450px;">
                        
              
                        
                        <?php foreach ($layout['rows'] as $rowIndex => $row): ?>
                            <div class="seat-row">
                                <?php 
                                $seatCount = 0;
                                foreach ($row as $seatIndex => $seat): 
                                    if ($seat === 'driver'): ?>
                                        <div class="seat driver-seat" title="Driver">
                                            <div class="seat-number">Driver</div>
                                        </div>
                                    <?php else:
                                        $is_booked = in_array($seat, $booked_seat_numbers);
                                        $seatCount++;
                                    ?>
                                        <input type="checkbox" 
                                               id="seat_<?= $seat ?>" 
                                               name="seats[]" 
                                               value="<?= $seat ?>" 
                                               class="hidden seat-checkbox"
                                               <?= $is_booked ? 'disabled' : '' ?>
                                               onchange="updateSeatSelection(<?= $seat ?>)">
                                        <label for="seat_<?= $seat ?>" 
                                               class="seat <?= $is_booked ? 'seat-booked' : 'seat-available' ?>"
                                               id="label_<?= $seat ?>"
                                               title="Seat <?= $seat ?><?= $is_booked ? ' (Booked)' : '' ?>">
                                            <i class="fas fa-user seat-icon" style="font-size: 1rem;"></i>
                                            <div class="seat-number"><?= $seat ?></div>
                                        </label>
                                        
                                        <?php 
                                        // Add aisle after specific seats in larger vehicles
                                        if (($layout['type'] === 'bus' || $layout['type'] === 'minibus') && 
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
                    
                    <div class="mt-10">
                        <div id="selectedSeatsDisplay" class="p-4 bg-gray-50 rounded-xl hidden transition-all duration-300">
                            <h4 class="font-bold text-lg mb-3 text-primary">Your Selected Seats:</h4>
                            <div id="selectedSeatsList" class="flex flex-wrap gap-3 justify-center"></div>
                        </div>
                        
                        <p id="seatError" class="text-red-600 font-medium mt-4 hidden text-center">Please select exactly <?= $num_seats ?> seat(s).</p>
                        
                        <button type="submit" 
                                id="submitBtn" 
                                disabled
                                class="mt-8 w-full font-bold py-5 px-6 rounded-xl transition-all duration-300 text-lg disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed">
                            <i class="fas fa-arrow-right mr-2"></i>
                            Proceed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const requiredSeats = <?= $num_seats ?>;
        let selectedSeats = [];
        const bookedSeats = <?= json_encode($booked_seat_numbers) ?>;
        
        console.log('Required seats:', requiredSeats);
        console.log('Booked seats:', bookedSeats);

        function updateSeatSelection(seatNumber) {
            if (bookedSeats.includes(seatNumber)) {
                return;
            }
            
            const checkbox = document.getElementById('seat_' + seatNumber);
            const label = document.getElementById('label_' + seatNumber);
            
            if (checkbox.checked) {
                if (selectedSeats.length < requiredSeats) {
                    selectedSeats.push(seatNumber);
                    label.classList.remove('seat-available');
                    label.classList.add('seat-selected');
                } else {
                    checkbox.checked = false;
                    alert('You can only select ' + requiredSeats + ' seat(s).');
                }
            } else {
                selectedSeats = selectedSeats.filter(seat => seat != seatNumber);
                label.classList.remove('seat-selected');
                label.classList.add('seat-available');
            }
            
            updateUI();
        }

        function updateUI() {
            const submitBtn = document.getElementById('submitBtn');
            const errorDiv = document.getElementById('seatError');
            const selectedCount = document.getElementById('selectedCount');
            const selectedSeatsDisplay = document.getElementById('selectedSeatsDisplay');
            const selectedSeatsList = document.getElementById('selectedSeatsList');
            const selectedSeatsInput = document.getElementById('selectedSeatsInput');
            
            selectedCount.textContent = selectedSeats.length;
            selectedSeatsInput.value = JSON.stringify(selectedSeats);
            
            if (selectedSeats.length > 0) {
                selectedSeatsDisplay.classList.remove('hidden');
                selectedSeatsList.innerHTML = selectedSeats
                    .sort((a, b) => a - b)
                    .map(seat => `<span class="bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg">Seat ${seat}</span>`)
                    .join('');
            } else {
                selectedSeatsDisplay.classList.add('hidden');
                selectedSeatsList.innerHTML = '';
            }

            if (selectedSeats.length === requiredSeats) {
                submitBtn.disabled = false;
                submitBtn.classList.add('submit-button-active');
                errorDiv.classList.add('hidden');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.remove('submit-button-active');
                if (selectedSeats.length > 0) {
                    errorDiv.classList.remove('hidden');
                } else {
                    errorDiv.classList.add('hidden');
                }
            }
        }

        document.getElementById('seatForm').addEventListener('submit', function(e) {
            if (selectedSeats.length !== requiredSeats) {
                e.preventDefault();
                alert('Please select exactly ' + requiredSeats + ' seat(s).');
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            submitBtn.disabled = true;
        });

        // Initialize UI
        updateUI();
    </script>
</body>
</html>