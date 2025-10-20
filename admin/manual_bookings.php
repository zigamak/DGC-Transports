<?php
// admin/manual_booking.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce admin-only access
requireRole('admin', '/login.php');

// Handle form submission
$success_message = '';
$error_message = '';
$booking_details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
    $passenger_name = trim($_POST['passenger_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $special_requests = trim($_POST['special_requests']);
    $template_id = (int)$_POST['template_id'];
    $trip_date = $_POST['trip_date'];
    $seat_numbers = json_decode($_POST['seat_numbers'], true);
    $payment_status = $_POST['payment_status']; // 'paid' or 'pending'
    $bypass_payment = isset($_POST['bypass_payment']) ? 1 : 0;
    
    // Validate inputs
    if (empty($passenger_name) || empty($email) || empty($phone) || empty($template_id) || empty($trip_date) || empty($seat_numbers)) {
        $error_message = 'Please fill in all required fields and select at least one seat.';
    } else {
        // Get template details
        $template_query = $conn->prepare("
            SELECT tt.price, vt.capacity, tt.pickup_city_id, tt.dropoff_city_id
            FROM trip_templates tt
            JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
            WHERE tt.id = ? AND tt.status = 'active'
        ");
        $template_query->bind_param("i", $template_id);
        $template_query->execute();
        $template = $template_query->get_result()->fetch_assoc();
        $template_query->close();
        
        if (!$template) {
            $error_message = 'Invalid trip template selected.';
        } else {
            // Check if trip instance exists, if not create it
            $instance_check = $conn->prepare("SELECT id FROM trip_instances WHERE template_id = ? AND trip_date = ?");
            $instance_check->bind_param("is", $template_id, $trip_date);
            $instance_check->execute();
            $instance_result = $instance_check->get_result();
            
            if ($instance_result->num_rows > 0) {
                $trip_instance = $instance_result->fetch_assoc();
                $trip_id = $trip_instance['id'];
            } else {
                // Create trip instance
                $create_instance = $conn->prepare("INSERT INTO trip_instances (template_id, trip_date, booked_seats, status) VALUES (?, ?, 0, 'active')");
                $create_instance->bind_param("is", $template_id, $trip_date);
                $create_instance->execute();
                $trip_id = $create_instance->insert_id;
                $create_instance->close();
            }
            $instance_check->close();
            
            // Calculate total amount
            $num_seats = count($seat_numbers);
            $total_amount = $template['price'] * $num_seats;
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                $created_bookings = [];
                
                foreach ($seat_numbers as $seat_number) {
                    // Check if seat is already booked
                    $seat_check = $conn->prepare("
                        SELECT id FROM bookings 
                        WHERE template_id = ? AND trip_date = ? AND seat_number = ? 
                        AND status != 'cancelled' AND payment_status != 'cancelled'
                    ");
                    $seat_check->bind_param("isi", $template_id, $trip_date, $seat_number);
                    $seat_check->execute();
                    if ($seat_check->get_result()->num_rows > 0) {
                        throw new Exception("Seat {$seat_number} is already booked.");
                    }
                    $seat_check->close();
                    
                    // Generate unique PNR
                    do {
                        $pnr = 'DGC' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 7));
                        $pnr_check = $conn->prepare("SELECT id FROM bookings WHERE pnr = ?");
                        $pnr_check->bind_param("s", $pnr);
                        $pnr_check->execute();
                        $pnr_exists = $pnr_check->get_result()->num_rows > 0;
                        $pnr_check->close();
                    } while ($pnr_exists);
                    
                    // Insert booking
                    $booking_status = ($payment_status === 'paid') ? 'confirmed' : 'pending';
                    $insert_booking = $conn->prepare("
                        INSERT INTO bookings 
                        (user_id, pnr, passenger_name, email, phone, emergency_contact, special_requests, 
                         template_id, trip_id, trip_date, seat_number, total_amount, payment_status, status, created_at)
                        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insert_booking->bind_param(
                        "ssssssiisidss",
                        $pnr,
                        $passenger_name,
                        $email,
                        $phone,
                        $emergency_contact,
                        $special_requests,
                        $template_id,
                        $trip_id,
                        $trip_date,
                        $seat_number,
                        $template['price'],
                        $payment_status,
                        $booking_status
                    );
                    $insert_booking->execute();
                    $booking_id = $insert_booking->insert_id;
                    $insert_booking->close();
                    
                    // If payment status is paid, create payment record
                    if ($payment_status === 'paid') {
                        $transaction_ref = 'MANUAL-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));
                        $insert_payment = $conn->prepare("
                            INSERT INTO payments 
                            (booking_id, amount, transaction_reference, payment_method, status, gateway_response, created_at)
                            VALUES (?, ?, ?, 'Manual Entry', 'completed', 'Manually created by admin', NOW())
                        ");
                        $insert_payment->bind_param("ids", $booking_id, $template['price'], $transaction_ref);
                        $insert_payment->execute();
                        $insert_payment->close();
                    }
                    
                    $created_bookings[] = [
                        'pnr' => $pnr,
                        'seat' => $seat_number,
                        'booking_id' => $booking_id
                    ];
                }
                
                // Update trip instance booked seats count
                $update_instance = $conn->prepare("
                    UPDATE trip_instances 
                    SET booked_seats = (
                        SELECT COUNT(*) FROM bookings 
                        WHERE trip_id = ? AND status = 'confirmed' AND payment_status = 'paid'
                    )
                    WHERE id = ?
                ");
                $update_instance->bind_param("ii", $trip_id, $trip_id);
                $update_instance->execute();
                $update_instance->close();
                
                $conn->commit();
                
                $success_message = "Successfully created {$num_seats} booking(s)!";
                $booking_details = [
                    'bookings' => $created_bookings,
                    'passenger_name' => $passenger_name,
                    'email' => $email,
                    'phone' => $phone,
                    'trip_date' => $trip_date,
                    'total_amount' => $total_amount,
                    'payment_status' => $payment_status
                ];
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Error creating booking: ' . $e->getMessage();
            }
        }
    }
}

// Fetch cities for dropdown
$cities_query = "SELECT id, name FROM cities ORDER BY name";
$cities_result = $conn->query($cities_query);
$cities = [];
while ($city = $cities_result->fetch_assoc()) {
    $cities[] = $city;
}

// Fetch vehicle types
$vehicle_types_query = "SELECT id, type, capacity FROM vehicle_types ORDER BY type";
$vehicle_types_result = $conn->query($vehicle_types_query);
$vehicle_types = [];
while ($vt = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[] = $vt;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Booking - DGC Transports Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#e30613',
                        'dark-red': '#c70410',
                    }
                }
            }
        }
    </script>
    <style>
        .seat {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
        }
        .seat-available {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            color: #495057;
        }
        .seat-available:hover {
            background: linear-gradient(145deg, #e3f2fd, #bbdefb);
            border-color: #2196f3;
            transform: translateY(-2px);
        }
        .seat-selected {
            background: linear-gradient(145deg, #ef4444, #dc2626);
            border-color: #b91c1c;
            color: white;
            transform: translateY(-3px);
        }
        .seat-booked {
            background: linear-gradient(145deg, #e9ecef, #dee2e6);
            border-color: #adb5bd;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .driver-seat {
            background: linear-gradient(145deg, #495057, #343a40);
            border: 2px solid #6c757d;
            color: white;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../templates/sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen">
        <div class="pt-20 md:pt-0 p-4 md:p-6 lg:p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                            <i class="fas fa-user-plus text-primary-red mr-3"></i>
                            Manual Booking
                        </h1>
                        <p class="text-gray-600 mt-1">Create bookings manually for walk-in or phone customers</p>
                    </div>
                    <a href="bookings.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Bookings
                    </a>
                </div>
            </div>

            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                        <div class="flex-1">
                            <h3 class="text-green-800 font-semibold"><?= htmlspecialchars($success_message) ?></h3>
                            <?php if ($booking_details): ?>
                                <div class="mt-3 text-sm text-green-700">
                                    <p><strong>Passenger:</strong> <?= htmlspecialchars($booking_details['passenger_name']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($booking_details['email']) ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($booking_details['phone']) ?></p>
                                    <p><strong>Trip Date:</strong> <?= date('M j, Y', strtotime($booking_details['trip_date'])) ?></p>
                                    <p><strong>Total Amount:</strong> ₦<?= number_format($booking_details['total_amount'], 0) ?></p>
                                    <p><strong>Payment Status:</strong> <?= ucfirst($booking_details['payment_status']) ?></p>
                                    <div class="mt-2">
                                        <strong>PNR Codes:</strong>
                                        <ul class="list-disc list-inside">
                                            <?php foreach ($booking_details['bookings'] as $booking): ?>
                                                <li>Seat <?= $booking['seat'] ?>: <strong><?= $booking['pnr'] ?></strong></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error_message): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                        <div>
                            <h3 class="text-red-800 font-semibold">Error</h3>
                            <p class="text-red-700 text-sm mt-1"><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Booking Form -->
            <form id="bookingForm" method="POST" class="space-y-6">
                <input type="hidden" name="create_booking" value="1">
                <input type="hidden" name="seat_numbers" id="seatNumbersInput" value="">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Passenger Information -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-2">
                            <i class="fas fa-user text-primary-red mr-2"></i>
                            Passenger Information
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input type="text" name="passenger_name" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" name="phone" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                                <input type="text" name="emergency_contact" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Special Requests</label>
                                <textarea name="special_requests" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Trip Selection -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-2">
                            <i class="fas fa-route text-primary-red mr-2"></i>
                            Trip Details
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">From *</label>
                                <select name="pickup_city_id" id="pickupCity" required onchange="loadTrips()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">Select pickup city</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">To *</label>
                                <select name="dropoff_city_id" id="dropoffCity" required onchange="loadTrips()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">Select destination</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Type *</label>
                                <select name="vehicle_type_id" id="vehicleType" required onchange="loadTrips()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">Select vehicle type</option>
                                    <?php foreach ($vehicle_types as $vt): ?>
                                        <option value="<?= $vt['id'] ?>" data-capacity="<?= $vt['capacity'] ?>">
                                            <?= htmlspecialchars($vt['type']) ?> (<?= $vt['capacity'] ?> seats)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Travel Date *</label>
                                <input type="date" name="trip_date" id="tripDate" required onchange="loadTrips()"
                                       min="<?= date('Y-m-d') ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Trip *</label>
                                <select name="template_id" id="tripTemplate" required onchange="loadSeats()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="">Select trip time</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status *</label>
                                <select name="payment_status" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-red focus:border-transparent">
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending Payment</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seat Selection -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-2">
                        <i class="fas fa-chair text-primary-red mr-2"></i>
                        Seat Selection
                    </h2>
                    <div id="seatSelectionArea" class="text-center text-gray-500 py-8">
                        Please select a trip to view available seats
                    </div>
                    <div id="selectedSeatsDisplay" class="mt-4 hidden">
                        <h3 class="font-semibold text-gray-700 mb-2">Selected Seats:</h3>
                        <div id="selectedSeatsList" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <a href="bookings.php" class="px-6 py-3 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" disabled
                            class="px-6 py-3 bg-primary-red text-white font-medium rounded-lg hover:bg-dark-red transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed">
                        <i class="fas fa-save mr-2"></i>Create Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedSeats = [];
        let bookedSeats = [];
        let currentCapacity = 0;

        function loadTrips() {
            const pickupCity = document.getElementById('pickupCity').value;
            const dropoffCity = document.getElementById('dropoffCity').value;
            const vehicleType = document.getElementById('vehicleType').value;
            const tripDate = document.getElementById('tripDate').value;
            const tripTemplate = document.getElementById('tripTemplate');

            if (!pickupCity || !dropoffCity || !vehicleType || !tripDate) {
                tripTemplate.innerHTML = '<option value="">Select trip time</option>';
                return;
            }

            if (pickupCity === dropoffCity) {
                alert('Pickup and dropoff cities cannot be the same');
                return;
            }

            // Fetch available trips
            fetch('../api/get_trips.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    pickup_city_id: pickupCity,
                    dropoff_city_id: dropoffCity,
                    vehicle_type_id: vehicleType,
                    trip_date: tripDate
                })
            })
            .then(response => response.json())
            .then(data => {
                tripTemplate.innerHTML = '<option value="">Select trip time</option>';
                if (data.trips && data.trips.length > 0) {
                    data.trips.forEach(trip => {
                        const option = document.createElement('option');
                        option.value = trip.template_id;
                        option.textContent = `${trip.departure_time} - ${trip.arrival_time} (₦${trip.price}) - ${trip.available_seats} seats available`;
                        option.dataset.price = trip.price;
                        option.dataset.capacity = trip.capacity;
                        tripTemplate.appendChild(option);
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No trips available for this route and date';
                    tripTemplate.appendChild(option);
                }
            })
            .catch(error => {
                console.error('Error loading trips:', error);
                alert('Error loading trips. Please try again.');
            });
        }

        function loadSeats() {
            const templateId = document.getElementById('tripTemplate').value;
            const tripDate = document.getElementById('tripDate').value;
            const vehicleTypeSelect = document.getElementById('vehicleType');
            const selectedOption = vehicleTypeSelect.options[vehicleTypeSelect.selectedIndex];
            currentCapacity = parseInt(selectedOption.dataset.capacity);

            if (!templateId || !tripDate) {
                document.getElementById('seatSelectionArea').innerHTML = 'Please select a trip to view available seats';
                return;
            }

            // Fetch booked seats
            fetch('../api/get_booked_seats.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    template_id: templateId,
                    trip_date: tripDate
                })
            })
            .then(response => response.json())
            .then(data => {
                bookedSeats = data.booked_seats || [];
                selectedSeats = [];
                renderSeats();
                updateSubmitButton();
            })
            .catch(error => {
                console.error('Error loading seats:', error);
                alert('Error loading seats. Please try again.');
            });
        }

        function renderSeats() {
            const seatArea = document.getElementById('seatSelectionArea');
            const layout = getSeatLayout(currentCapacity);
            
            let html = '<div class="max-w-md mx-auto">';
            layout.rows.forEach((row, rowIndex) => {
                html += '<div class="flex justify-center gap-2 mb-3">';
                row.forEach(seat => {
                    if (seat === 'driver') {
                        html += '<div class="seat driver-seat"><i class="fas fa-steering-wheel"></i></div>';
                    } else {
                        const isBooked = bookedSeats.includes(seat);
                        const isSelected = selectedSeats.includes(seat);
                        const seatClass = isBooked ? 'seat-booked' : (isSelected ? 'seat-selected' : 'seat-available');
                        const onclick = isBooked ? '' : `onclick="toggleSeat(${seat})"`;
                        html += `<div class="seat ${seatClass}" ${onclick}>
                                    <i class="fas fa-user"></i>
                                    <div class="text-xs">${seat}</div>
                                 </div>`;
                    }
                });
                html += '</div>';
            });
            html += '</div>';
            
            seatArea.innerHTML = html;
        }

        function getSeatLayout(capacity) {
            const layouts = {
                5: {rows: [['driver', 1], [2, 3], [4, 5]]},
                12: {rows: [['driver', 1], [2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12]]},
                14: {rows: [['driver', 1], [2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12], [13, 14]]},
                18: {rows: [['driver', 1, 2], [3, 4], [5, 6, 7], [8, 9, 10], [11, 12, 13, 14], [15, 16, 17, 18]]}
            };
            return layouts[capacity] || layouts[5];
        }

        function toggleSeat(seatNumber) {
            const index = selectedSeats.indexOf(seatNumber);
            if (index > -1) {
                selectedSeats.splice(index, 1);
            } else {
                selectedSeats.push(seatNumber);
            }
            selectedSeats.sort((a, b) => a - b);
            renderSeats();
            updateSelectedSeatsDisplay();
            updateSubmitButton();
        }

        function updateSelectedSeatsDisplay() {
            const display = document.getElementById('selectedSeatsDisplay');
            const list = document.getElementById('selectedSeatsList');
            
            if (selectedSeats.length > 0) {
                display.classList.remove('hidden');
                list.innerHTML = selectedSeats.map(seat => 
                    `<span class="bg-primary-red text-white px-3 py-1 rounded-full text-sm">Seat ${seat}</span>`
                ).join('');
            } else {
                display.classList.add('hidden');
            }
            
            document.getElementById('seatNumbersInput').value = JSON.stringify(selectedSeats);
        }

        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const templateId = document.getElementById('tripTemplate').value;
            
            if (selectedSeats.length > 0 && templateId) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Initialize
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            if (selectedSeats.length === 0) {
                e.preventDefault();
                alert('Please select at least one seat');
                return false;
            }
        });
    </script>
</body>
</html>