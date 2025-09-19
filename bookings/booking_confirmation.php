<?php
// bookings/booking_confirmation.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php';

// Check if booking ID is provided and user has payment success session
if (!isset($_GET['booking_id']) || !isset($_SESSION['payment_success'])) {
    header("Location: search_trips.php");
    exit();
}

$booking_id = $_GET['booking_id'];

// Fetch booking details
$stmt = $conn->prepare("
    SELECT b.*, t.pickup_city, t.dropoff_city, t.trip_date, t.departure_time, 
           t.vehicle_type, t.vehicle_number, t.driver_name, t.driver_phone,
           GROUP_CONCAT(sb.seat_number ORDER BY sb.seat_number) as seat_numbers
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    LEFT JOIN seat_bookings sb ON b.id = sb.booking_id
    WHERE b.id = ? AND b.payment_status = 'paid'
    GROUP BY b.id
");

$stmt->bind_param("s", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: search_trips.php");
    exit();
}

// Clear the payment success session
unset($_SESSION['payment_success']);

$seat_numbers = explode(',', $booking['seat_numbers']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - <?= SITE_NAME ?></title>
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
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
                opacity: 1;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .checkmark-animation {
            animation: checkmark 0.6s ease-in-out;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Success Header -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 text-center">
                <div class="checkmark-animation">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-3xl text-green-600"></i>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-green-600 mb-2">Booking Confirmed!</h1>
                <p class="text-gray-600 mb-4">Your payment has been processed successfully</p>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 inline-block">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-green-600 mr-2"></i>
                        <span class="font-semibold text-green-800">Booking ID: <?= htmlspecialchars($booking['id']) ?></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Booking Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Trip Information -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-route text-primary mr-3"></i>
                            Trip Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Route</label>
                                    <p class="text-lg font-semibold"><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Date</label>
                                    <p class="font-semibold"><?= date('l, F j, Y', strtotime($booking['trip_date'])) ?></p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Departure Time</label>
                                    <p class="font-semibold"><?= date('h:i A', strtotime($booking['departure_time'])) ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Vehicle</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['vehicle_type']) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($booking['vehicle_number']) ?></p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Driver</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['driver_name']) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($booking['driver_phone']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Information -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-user text-primary mr-3"></i>
                            Passenger Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Full Name</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['passenger_name']) ?></p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Email</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['email']) ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Phone</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['phone']) ?></p>
                                </div>
                                <?php if (!empty($booking['emergency_contact'])): ?>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Emergency Contact</label>
                                    <p class="font-semibold"><?= htmlspecialchars($booking['emergency_contact']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($booking['special_requests'])): ?>
                        <div class="mt-4 pt-4 border-t">
                            <label class="text-sm font-semibold text-gray-600">Special Requests</label>
                            <p class="mt-1 text-gray-700"><?= htmlspecialchars($booking['special_requests']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Information -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-credit-card text-primary mr-3"></i>
                            Payment Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Payment Method</label>
                                    <p class="font-semibold capitalize"><?= htmlspecialchars($booking['payment_method']) ?></p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Payment Reference</label>
                                    <p class="font-mono text-sm"><?= htmlspecialchars($booking['payment_reference']) ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Payment Status</label>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Paid
                                    </span>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-600">Booking Date</label>
                                    <p class="font-semibold"><?= date('M j, Y h:i A', strtotime($booking['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Summary & Actions -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-xl font-bold mb-4">Booking Summary</h3>
                        
                        <!-- Seats -->
                        <div class="mb-6">
                            <label class="text-sm font-semibold text-gray-600 mb-2 block">Selected Seats</label>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($seat_numbers as $seat): ?>
                                    <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-medium">
                                        Seat <?= $seat ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Amount -->
                        <div class="border-t pt-4 mb-6">
                            <div class="flex justify-between items-center text-lg font-bold">
                                <span>Total Paid:</span>
                                <span class="text-primary">₦<?= number_format($booking['total_amount'], 0) ?></span>
                            </div>
                        </div>

                        <!-- QR Code (Optional) -->
                        <div class="mb-6 text-center">
                            <div class="bg-gray-100 rounded-lg p-4">
                                <i class="fas fa-qrcode text-4xl text-gray-400 mb-2"></i>
                                <p class="text-sm text-gray-600">Show this booking to the driver</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3 no-print">
                            <button onclick="window.print()" 
                                    class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-3 px-4 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200">
                                <i class="fas fa-print mr-2"></i>
                                Print Ticket
                            </button>
                            
                            <button onclick="downloadPDF()" 
                                    class="w-full bg-gray-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-gray-700 transition-all duration-200">
                                <i class="fas fa-download mr-2"></i>
                                Download PDF
                            </button>
                            
                            <button onclick="shareBooking()" 
                                    class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-blue-700 transition-all duration-200">
                                <i class="fas fa-share mr-2"></i>
                                Share Booking
                            </button>
                        </div>

                        <!-- Important Notes -->
                        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h4 class="font-semibold text-yellow-800 mb-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Important Notes
                            </h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Arrive 15 minutes before departure</li>
                                <li>• Bring a valid ID for verification</li>
                                <li>• Contact driver if running late</li>
                                <li>• Keep this booking reference handy</li>
                            </ul>
                        </div>

                        <!-- Contact Information -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-semibold text-gray-800 mb-2">
                                <i class="fas fa-headset mr-2"></i>
                                Need Help?
                            </h4>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p><i class="fas fa-phone mr-2 text-primary"></i>Support: +234 xxx xxx xxxx</p>
                                <p><i class="fas fa-envelope mr-2 text-primary"></i>Email: support@dgctransports.com</p>
                            </div>
                        </div>

                        <!-- New Booking Button -->
                        <div class="mt-6 no-print">
                            <a href="search_trips.php" 
                               class="w-full bg-white border-2 border-primary text-primary font-bold py-3 px-4 rounded-xl hover:bg-primary hover:text-white transition-all duration-200 block text-center">
                                <i class="fas fa-plus mr-2"></i>
                                Book Another Trip
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Confirmation Notice -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6 no-print">
                <div class="flex items-center">
                    <i class="fas fa-envelope text-blue-600 text-2xl mr-4"></i>
                    <div>
                        <h3 class="font-semibold text-blue-800">Confirmation Email Sent</h3>
                        <p class="text-blue-700">A confirmation email with your booking details has been sent to <?= htmlspecialchars($booking['email']) ?>. Please check your inbox and spam folder.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // This would integrate with a PDF generation library
            alert('PDF download feature will be implemented with a PDF library like TCPDF or mPDF');
        }

        function shareBooking() {
            if (navigator.share) {
                navigator.share({
                    title: 'My Bus Booking - <?= SITE_NAME ?>',
                    text: 'Booking confirmed for <?= htmlspecialchars($booking['pickup_city']) ?> to <?= htmlspecialchars($booking['dropoff_city']) ?> on <?= date('M j, Y', strtotime($booking['trip_date'])) ?>',
                    url: window.location.href
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const bookingUrl = window.location.href;
                navigator.clipboard.writeText(bookingUrl).then(() => {
                    alert('Booking link copied to clipboard!');
                }).catch(() => {
                    alert('Unable to copy link. Please copy manually: ' + bookingUrl);
                });
            }
        }

        // Auto-focus and animate on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to top
            window.scrollTo(0, 0);
            
            // Show success message
            setTimeout(() => {
                const successElements = document.querySelectorAll('.checkmark-animation');
                successElements.forEach(el => {
                    el.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        el.style.transform = 'scale(1)';
                    }, 200);
                });
            }, 500);
        });

        // Prevent back button after successful booking
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>