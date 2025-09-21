<?php
// bookings/paystack.php - Corrected version for your database schema
ob_start(); // Start output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php';

// Check if all required data is available
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['passenger_details'])) {
    error_log("Missing session data: " . json_encode([
        'selected_trip' => isset($_SESSION['selected_trip']) ? 'set' : 'not set',
        'selected_seats' => isset($_SESSION['selected_seats']) ? 'set' : 'not set',
        'passenger_details' => isset($_SESSION['passenger_details']) ? 'set' : 'not set'
    ]));
    header("Location: search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$passenger_details = $_SESSION['passenger_details'];
$num_seats = count($selected_seats);
$total_amount = $num_seats * $trip['price'];

// Ensure booking IDs exist (should be created by process_booking.php)
if (!isset($_SESSION['booking_ids'])) {
    error_log("Booking IDs not found in session, redirecting to passenger details");
    header("Location: passenger_details.php");
    exit();
}

// Generate a unique reference for this transaction
$reference = 'DGC_' . uniqid() . '_' . time();
$_SESSION['payment_reference'] = $reference;

// Convert amount to kobo (Paystack uses kobo)
$amount_in_kobo = $total_amount * 100;

// Get primary passenger's email for payment
$primary_passenger = reset($passenger_details);
$email = $primary_passenger['email'];
$passenger_name = $primary_passenger['name'];

ob_end_flush(); // End output buffering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?= htmlspecialchars(SITE_NAME) ?></title>
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
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        .spinner {
            border: 4px solid rgba(220, 38, 38, 0.3);
            border-top: 4px solid #dc2626;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold text-black">Complete Payment</h1>
                    <button id="backButton" class="text-primary hover:text-secondary font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Details
                    </button>
                </div>
                <div class="text-sm text-gray-600">
                    Secure payment powered by Paystack
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Payment Section -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold mb-6 flex items-center">
                            <i class="fas fa-credit-card text-primary mr-3"></i>
                            Payment Information
                        </h3>
                        
                        <!-- Security Info -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-green-600 mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-green-800">Secure Payment</h4>
                                    <p class="text-sm text-green-700">Your payment is protected by 256-bit SSL encryption</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="mb-6">
                            <h4 class="font-semibold mb-4">Accepted Payment Methods</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="flex items-center justify-center p-3 border rounded-lg">
                                    <i class="fab fa-cc-visa text-2xl text-blue-600"></i>
                                </div>
                                <div class="flex items-center justify-center p-3 border rounded-lg">
                                    <i class="fab fa-cc-mastercard text-2xl text-orange-600"></i>
                                </div>
                                <div class="flex items-center justify-center p-3 border rounded-lg">
                                    <i class="fas fa-university text-2xl text-gray-600"></i>
                                </div>
                                <div class="flex items-center justify-center p-3 border rounded-lg">
                                    <span class="text-sm font-semibold text-gray-600">USSD</span>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Information Review -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold mb-3">Passenger Information</h4>
                            <div class="space-y-4">
                                <?php foreach ($passenger_details as $index => $passenger): ?>
                                    <div class="border-t pt-2">
                                        <h5 class="font-medium">Passenger <?= $index + 1 ?> - Seat <?= htmlspecialchars($passenger['seat_number']) ?></h5>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Name:</span>
                                                <span class="font-medium"><?= htmlspecialchars($passenger['name']) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Email:</span>
                                                <span class="font-medium"><?= htmlspecialchars($passenger['email']) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Phone:</span>
                                                <span class="font-medium"><?= htmlspecialchars($passenger['phone']) ?></span>
                                            </div>
                                            <?php if (!empty($passenger['emergency_contact'])): ?>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Emergency Contact:</span>
                                                    <span class="font-medium"><?= htmlspecialchars($passenger['emergency_contact']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!empty($passenger_details[0]['special_requests'])): ?>
                                    <div class="border-t pt-2">
                                        <h5 class="font-medium">Special Requests</h5>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($passenger_details[0]['special_requests']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Button -->
                        <div class="space-y-4">
                            <button id="payButton" class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200 shadow-lg hover:shadow-xl">
                                <i class="fas fa-lock mr-2"></i>
                                Pay ₦<?= number_format($total_amount, 0) ?> Now
                            </button>
                            
                            <div class="text-center text-sm text-gray-500 flex items-center justify-center">
                                <span id="spinner" class="spinner mr-2 hidden"></span>
                                <span id="countdown">Payment will start automatically in 3 seconds...</span>
                            </div>
                            
                            <div class="text-center text-sm text-gray-500">
                                By proceeding, you agree to our terms and conditions
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-xl font-bold mb-4">Final Booking Summary</h3>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Route:</span>
                                <span class="font-semibold text-sm"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
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
                                <span class="font-semibold text-sm"><?= htmlspecialchars($trip['vehicle_type']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vehicle No:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['vehicle_number']) ?></span>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <div class="mb-4">
                                <h4 class="font-semibold mb-2">Selected Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($selected_seats as $seat): ?>
                                        <span class="bg-primary text-white px-2 py-1 rounded-full text-xs">
                                            Seat <?= htmlspecialchars($seat) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Price per seat:</span>
                                    <span class="font-semibold">₦<?= number_format($trip['price'], 0) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Number of seats:</span>
                                    <span class="font-semibold"><?= $num_seats ?></span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-semibold">₦<?= number_format($total_amount, 0) ?></span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center text-lg font-bold border-t pt-3 mt-3">
                                <span>Total Amount:</span>
                                <span class="text-primary">₦<?= number_format($total_amount, 0) ?></span>
                            </div>
                        </div>

                        <div class="mt-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-center text-sm text-yellow-800">
                                <i class="fas fa-clock text-yellow-600 mr-2"></i>
                                <span>Payment expires in <span id="timer">20:00</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin text-3xl text-primary mb-4"></i>
                <h3 class="text-lg font-semibold mb-2">Processing Payment</h3>
                <p class="text-gray-600">Please wait while we process your payment...</p>
            </div>
        </div>
    </div>

    <script>
        // Check if PaystackPop is available
        if (typeof PaystackPop === 'undefined') {
            console.error('Paystack script failed to load');
            alert('Payment service is unavailable. Please try again later.');
        }

        function goBack() {
            window.location.href = 'passenger_details.php';
        }

        function showLoading() {
            const modal = document.getElementById('loadingModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideLoading() {
            const modal = document.getElementById('loadingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function initiatePayment() {
            if (typeof PaystackPop === 'undefined') {
                console.error('PaystackPop is not defined');
                alert('Payment service is unavailable. Please try again later.');
                return;
            }

            const payButton = document.getElementById('payButton');
            const spinner = document.getElementById('spinner');
            const countdown = document.getElementById('countdown');
            
            payButton.disabled = true;
            payButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Initializing Payment...';
            spinner.classList.remove('hidden');
            countdown.textContent = 'Initiating payment...';

            const handler = PaystackPop.setup({
                key: <?php echo json_encode(PAYSTACK_PUBLIC_KEY); ?>,
                email: <?php echo json_encode($email); ?>,
                amount: <?php echo json_encode($amount_in_kobo); ?>,
                currency: 'NGN',
                ref: <?php echo json_encode($reference); ?>,
                metadata: {
                    custom_fields: [
                        {
                            display_name: "Primary Passenger Name",
                            variable_name: "passenger_name",
                            value: <?php echo json_encode($passenger_name); ?>
                        },
                        {
                            display_name: "Trip Route",
                            variable_name: "trip_route",
                            value: <?php echo json_encode($trip['pickup_city'] . ' to ' . $trip['dropoff_city']); ?>
                        },
                        {
                            display_name: "Seats",
                            variable_name: "seats",
                            value: <?php echo json_encode(implode(', ', $selected_seats)); ?>
                        },
                        {
                            display_name: "Template ID",
                            variable_name: "template_id",
                            value: <?php echo json_encode($trip['template_id']); ?>
                        },
                        {
                            display_name: "Passenger Count",
                            variable_name: "passenger_count",
                            value: <?php echo json_encode($num_seats); ?>
                        }
                    ]
                },
                callback: function(response) {
                    showLoading();
                    verifyPayment(response.reference);
                },
                onClose: function() {
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="fas fa-lock mr-2"></i>Pay ₦<?php echo number_format($total_amount, 0); ?> Now';
                    spinner.classList.add('hidden');
                    countdown.textContent = 'Payment cancelled. Click "Pay Now" to try again.';
                }
            });

            handler.openIframe();
        }

        function verifyPayment(reference) {
            fetch('verify_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'reference=' + encodeURIComponent(reference)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    window.location.href = data.redirect_url;
                } else {
                    alert('Payment verification failed: ' + (data.message || 'Unknown error'));
                    const payButton = document.getElementById('payButton');
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="fas fa-lock mr-2"></i>Pay ₦<?php echo number_format($total_amount, 0); ?> Now';
                    document.getElementById('spinner').classList.add('hidden');
                    document.getElementById('countdown').textContent = 'Payment failed. Click "Pay Now" to try again.';
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred while verifying payment. Please contact support at <?= htmlspecialchars(SITE_EMAIL) ?>.');
                const payButton = document.getElementById('payButton');
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-lock mr-2"></i>Pay ₦<?php echo number_format($total_amount, 0); ?> Now';
                document.getElementById('spinner').classList.add('hidden');
                document.getElementById('countdown').textContent = 'Error occurred. Click "Pay Now" to try again.';
            });
        }

        // Auto-start payment after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            let seconds = 3;
            const countdownElement = document.getElementById('countdown');
            const interval = setInterval(function() {
                seconds--;
                if (seconds > 0) {
                    countdownElement.textContent = `Payment will start automatically in ${seconds} seconds...`;
                } else {
                    clearInterval(interval);
                    initiatePayment();
                }
            }, 1000);

            // Manual trigger via button
            document.getElementById('payButton').addEventListener('click', function() {
                clearInterval(interval);
                initiatePayment();
            });
            document.getElementById('backButton').addEventListener('click', goBack);
        });

        // Countdown timer for payment timeout
        let timeLeft = 1200; // 20 minutes in seconds
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timerElement = document.getElementById('timer');
            if (timerElement) {
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            
            if (timeLeft <= 0) {
                alert('Payment session has expired. Please start a new booking.');
                window.location.href = 'search_trips.php';
            }
            timeLeft--;
        }

        setInterval(updateTimer, 1000);
    </script>
</body>
</html>