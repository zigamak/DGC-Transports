<?php
// bookings/seat_selection.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php'; 

if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_num_seats'])) {
    header("Location: search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$num_seats = $_SESSION['selected_num_seats'];

// Debug: Check what's in the trip data
// Uncomment the line below to see the trip structure
// echo "<pre>" . print_r($trip, true) . "</pre>";

// Fetch booked seats - Fix the field name here
$trip_id = isset($trip['trip_id']) ? $trip['trip_id'] : $trip['id'];
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM seat_bookings sb
    JOIN bookings b ON sb.booking_id = b.id
    WHERE b.trip_id = ? AND b.payment_status = 'paid'
");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$booked_seat_numbers = array_column($booked_seats, 'seat_number');

// Debug: Check what seats are being fetched as booked
// Uncomment the line below to see booked seats
// echo "<pre>Booked seats: " . print_r($booked_seat_numbers, true) . "</pre>";
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
                        primary: '#dc2626',
                        secondary: '#991b1b',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .seat-available {
            background: linear-gradient(135deg, #e5e7eb, #f3f4f6);
            border: 2px solid #d1d5db;
            color: #374151;
            transition: all 0.3s ease;
        }
        
        .seat-available:hover {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .seat-selected {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: 2px solid #991b1b;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }
        
        .seat-booked {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            border: 2px solid #4b5563;
            color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            }
            50% {
                box-shadow: 0 6px 16px rgba(220, 38, 38, 0.5);
            }
        }
        
        .seat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold text-black">Select Your Seats</h1>
                    <a href="search_trips.php" class="text-primary hover:text-secondary font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Trips
                    </a>
                </div>
                <div class="text-sm text-gray-600">
                    Select <?= $num_seats ?> seat(s) for your trip from <?= htmlspecialchars($trip['pickup_city']) ?> to <?= htmlspecialchars($trip['dropoff_city']) ?>
                </div>
            </div>

            <!-- Seat Legend -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Seat Legend</h3>
                <div class="flex flex-wrap gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded seat-available"></div>
                        <span class="text-sm text-gray-600">Available</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded seat-selected"></div>
                        <span class="text-sm text-gray-600">Selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded seat-booked"></div>
                        <span class="text-sm text-gray-600">Booked</span>
                    </div>
                </div>
            </div>

            <!-- Debug Information (remove in production) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <h4 class="font-bold mb-2">Debug Information:</h4>
                    <p><strong>Trip ID:</strong> <?= $trip_id ?></p>
                    <p><strong>Booked Seats:</strong> <?= implode(', ', $booked_seat_numbers) ?></p>
                    <p><strong>Total Capacity:</strong> <?= $trip['capacity'] ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Available Seats</h3>
                    <div class="text-sm font-medium">
                        <span id="selectedCount" class="text-primary">0</span> / <?= $num_seats ?> seats selected
                    </div>
                </div>
                
                <form id="seatForm" action="passenger_details.php" method="POST">
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                        <?php for ($i = 1; $i <= $trip['capacity']; $i++): ?>
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
                                       class="seat-label block text-center p-4 rounded-lg min-h-[80px] flex flex-col items-center justify-center
                                              <?= $is_booked ? 'seat-booked cursor-not-allowed' : 'seat-available cursor-pointer' ?>"
                                       id="label_<?= $i ?>">
                                    <i class="fas fa-chair seat-icon"></i>
                                    <span class="text-sm font-medium">
                                        Seat <?= $i ?>
                                        <?= $is_booked ? '<br><small>(Booked)</small>' : '' ?>
                                    </span>
                                </label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mt-8">
                        <div id="selectedSeatsDisplay" class="mb-4 p-4 bg-gray-50 rounded-lg hidden">
                            <h4 class="font-semibold mb-2">Selected Seats:</h4>
                            <div id="selectedSeatsList" class="flex flex-wrap gap-2"></div>
                        </div>
                        
                        <p id="seatError" class="text-red-600 mb-4 hidden">Please select exactly <?= $num_seats ?> seat(s).</p>
                        
                        <button type="submit" 
                                id="submitBtn" 
                                disabled
                                class="w-full bg-gradient-to-r from-gray-400 to-gray-500 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 shadow-lg disabled:cursor-not-allowed">
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
        
        console.log('Booked seats:', bookedSeats); // Debug line

        function updateSeatSelection(seatNumber) {
            // Check if seat is booked
            if (bookedSeats.includes(seatNumber)) {
                alert('This seat is already booked!');
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
                    return;
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
            
            selectedCount.textContent = selectedSeats.length;
            
            if (selectedSeats.length > 0) {
                selectedSeatsDisplay.classList.remove('hidden');
                selectedSeatsList.innerHTML = selectedSeats
                    .sort((a, b) => a - b)
                    .map(seat => `<span class="bg-primary text-white px-3 py-1 rounded-full text-sm">Seat ${seat}</span>`)
                    .join('');
            } else {
                selectedSeatsDisplay.classList.add('hidden');
            }

            if (selectedSeats.length === requiredSeats) {
                submitBtn.disabled = false;
                submitBtn.className = "w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-300 shadow-lg hover:shadow-xl";
                errorDiv.classList.add('hidden');
            } else {
                submitBtn.disabled = true;
                submitBtn.className = "w-full bg-gradient-to-r from-gray-400 to-gray-500 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 shadow-lg disabled:cursor-not-allowed";
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
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            submitBtn.disabled = true;
            
            // Store selected seats in session via AJAX
            fetch('store_seats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'seats=' + JSON.stringify(selectedSeats)
            }).then(response => {
                if (!response.ok) {
                    e.preventDefault();
                    alert('Failed to save seat selection. Please try again.');
                    submitBtn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Proceed to Passenger Details';
                    submitBtn.disabled = false;
                }
            }).catch(error => {
                e.preventDefault();
                alert('Network error. Please try again.');
                submitBtn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Proceed to Passenger Details';
                submitBtn.disabled = false;
            });
        });

        // Initialize UI
        updateUI();
    </script>
</body>
</html>