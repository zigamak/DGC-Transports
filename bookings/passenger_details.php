<?php
// bookings/passenger_details.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Check if trip and seats are selected
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['selected_num_seats'])) {
    header("Location: search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$total_amount = count($selected_seats) * $trip['price'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Details - <?= SITE_NAME ?></title>
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
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold text-black">Passenger Details</h1>
                    <button onclick="goBack()" class="text-primary hover:text-secondary font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Seat Selection
                    </button>
                </div>
                <div class="text-sm text-gray-600">
                    Please provide passenger information for your booking
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Passenger Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold mb-6">Enter Passenger Information</h3>
                        
                        <form id="passengerForm" action="process_booking.php" method="POST">
                            <div class="space-y-6">
                                <div>
                                    <label for="passenger_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user text-primary mr-2"></i>Full Name *
                                    </label>
                                    <input type="text" 
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                           id="passenger_name" name="passenger_name" required
                                           placeholder="Enter your full name as it appears on ID">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address *
                                        </label>
                                        <input type="email" 
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                               id="email" name="email" required
                                               placeholder="your@email.com">
                                    </div>

                                    <div>
                                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-phone text-primary mr-2"></i>Phone Number *
                                        </label>
                                        <input type="tel" 
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                               id="phone" name="phone" required
                                               placeholder="+234 xxx xxx xxxx">
                                    </div>
                                </div>

                                <div>
                                    <label for="emergency_contact" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user-friends text-primary mr-2"></i>Emergency Contact (Optional)
                                    </label>
                                    <input type="text" 
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                           id="emergency_contact" name="emergency_contact"
                                           placeholder="Emergency contact name and phone">
                                </div>

                                <div>
                                    <label for="special_requests" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-comment text-primary mr-2"></i>Special Requests (Optional)
                                    </label>
                                    <textarea 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                        id="special_requests" name="special_requests" rows="3"
                                        placeholder="Any special requirements or requests..."></textarea>
                                </div>

                                <!-- Terms and Conditions -->
                                <div class="border-t pt-6">
                                    <div class="flex items-start">
                                        <input type="checkbox" id="terms" name="terms" required class="mt-1 mr-3">
                                        <label for="terms" class="text-sm text-gray-600">
                                            I agree to the <a href="#" class="text-primary hover:underline">Terms and Conditions</a> 
                                            and <a href="#" class="text-primary hover:underline">Privacy Policy</a>. 
                                            I understand that this booking is subject to availability and payment confirmation.
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8">
                                <button type="submit" 
                                        class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-credit-card mr-2"></i>
                                    Proceed to Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-xl font-bold mb-4">Booking Summary</h3>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Route:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date:</span>
                                <span class="font-semibold"><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Time:</span>
                                <span class="font-semibold"><?= date('H:i', strtotime($trip['departure_time'])) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vehicle:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['vehicle_type']) ?> (<?= htmlspecialchars($trip['vehicle_number']) ?>)</span>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <div class="mb-4">
                                <h4 class="font-semibold mb-2">Selected Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($selected_seats as $seat): ?>
                                        <span class="bg-primary text-white px-3 py-1 rounded-full text-sm">
                                            Seat <?= $seat ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Price per seat:</span>
                                    <span class="font-semibold">₦<?= number_format($trip['price'], 0) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Number of seats:</span>
                                    <span class="font-semibold"><?= count($selected_seats) ?></span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center text-lg font-bold border-t pt-2 mt-2">
                                <span>Total Amount:</span>
                                <span class="text-primary">₦<?= number_format($total_amount, 0) ?></span>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-info-circle text-primary mr-2"></i>
                                <span>Your seats will be reserved for 15 minutes while you complete payment.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            window.location.href = 'seat_selection.php';
        }

        document.getElementById('passengerForm').addEventListener('submit', function(e) {
            const name = document.getElementById('passenger_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const terms = document.getElementById('terms').checked;

            if (!name || !email || !phone || !terms) {
                e.preventDefault();
                alert('Please fill in all required fields and accept the terms and conditions.');
                return false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }

            const phoneRegex = /^[\+]?[0-9\s\-\(\)]+$/;
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number.');
                return false;
            }
        });
    </script>
</body>
</html>