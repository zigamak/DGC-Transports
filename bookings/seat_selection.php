<?php
// bookings/seat_selection.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php'; 

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
                        primary: '#1f2937', // Dark Gray
                        accent: '#ef4444', // Red
                        'accent-hover': '#dc2626',
                        'accent-selected': '#b91c1c',
                        'accent-booked': '#9ca3af', // Gray for booked seats
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .seat-available {
            background-color: #f9fafb;
            border: 2px solid #e5e7eb;
            color: #4b5563;
            transition: all 0.2s ease-in-out;
        }
        .seat-available:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            transform: scale(1.03);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }
        .seat-selected {
            background-color: #ef4444;
            border: 2px solid #dc2626;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
            animation: pop-in 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        @keyframes pop-in {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1.05); opacity: 1; }
        }
        .seat-booked {
            background-color: #e5e7eb;
            border: 2px solid #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.8;
        }
        .seat-icon {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .submit-button-active {
            background: #ef4444;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .submit-button-active:hover {
            background: #dc2626;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="min-h-screen py-10">
        <div class="max-w-5xl mx-auto px-4">
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
                        <div class="w-5 h-5 rounded-md bg-gray-200 border-2 border-gray-300"></div>
                        <span class="text-sm text-gray-700">Available</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-accent border-2 border-accent-hover"></div>
                        <span class="text-sm text-gray-700">Selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-accent-booked border-2 border-gray-400"></div>
                        <span class="text-sm text-gray-700">Booked</span>
                    </div>
                </div>
                <div class="text-md font-semibold text-primary text-center sm:text-right">
                    <span id="selectedCount" class="text-2xl font-bold text-accent-hover">0</span> / <?= $num_seats ?> seats selected
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form id="seatForm" action="process_seat_selection.php" method="POST">
                    <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6">
                        <?php for ($i = 1; $i <= $trip_details['capacity']; $i++): ?>
                            <?php $is_booked = in_array($i, $booked_seat_numbers); ?>
                            <div class="seat-container">
                                <input type="checkbox" 
                                       id="seat_<?= $i ?>" 
                                       name="seats[]" 
                                       value="<?= $i ?>" 
                                       class="hidden seat-checkbox"
                                       <?= $is_booked ? 'disabled' : '' ?>
                                       onchange="updateSeatSelection(<?= $i ?>)">
                                <label for="seat_<?= $i ?>" 
                                       class="seat-label w-full h-full block text-center p-4 rounded-lg flex flex-col items-center justify-center
                                               <?= $is_booked ? 'seat-booked' : 'seat-available cursor-pointer' ?>"
                                       id="label_<?= $i ?>">
                                    <i class="fas fa-chair seat-icon"></i>
                                    <span class="text-sm font-semibold">Seat <?= $i ?></span>
                                    <?= $is_booked ? '<small class="text-xs font-normal">(Booked)</small>' : '' ?>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mt-10">
                        <div id="selectedSeatsDisplay" class="p-4 bg-gray-50 rounded-xl hidden transition-all duration-300">
                            <h4 class="font-bold text-lg mb-3 text-primary">Your Selected Seats:</h4>
                            <div id="selectedSeatsList" class="flex flex-wrap gap-3"></div>
                        </div>
                        
                        <p id="seatError" class="text-red-600 font-medium mt-4 hidden">Please select exactly <?= $num_seats ?> seat(s).</p>
                        
                        <button type="submit" 
                                id="submitBtn" 
                                disabled
                                class="mt-8 w-full font-bold py-5 px-6 rounded-xl transition-all duration-300 text-lg disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed">
                            <i class="fas fa-arrow-right mr-2"></i>
                            Proceed to Passenger Details
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
                    .map(seat => `<span class="bg-accent-hover text-white px-3 py-1 rounded-full text-sm font-medium">Seat ${seat}</span>`)
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

        updateUI();
    </script>
</body>
</html>