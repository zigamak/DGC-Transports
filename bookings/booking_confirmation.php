<?php
// bookings/booking_confirmation.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Get PNR from URL (keeping booking_id parameter name for compatibility)
$pnr = $_GET['booking_id'] ?? $_GET['pnr'] ?? '';

// Check if booking confirmation data exists in session
if (!isset($_SESSION['booking_confirmation']) || empty($pnr)) {
    header("Location: search_trips.php");
    exit();
}

$booking = $_SESSION['booking_confirmation'];

// Verify the PNR matches
if ($booking['pnr'] !== $pnr) {
    header("Location: search_trips.php");
    exit();
}

// Optional: Fetch booking from database to double-check using PNR
$stmt = $conn->prepare("SELECT * FROM bookings WHERE pnr = ?");
$stmt->bind_param("s", $pnr);
$stmt->execute();
$db_booking = $stmt->get_result()->fetch_assoc();

if (!$db_booking) {
    header("Location: search_trips.php");
    exit();
}
require_once '../templates/header.php';

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
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .checkmark-animation {
            animation: checkmark 0.6s ease-in-out;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .gradient-bg { background: #dc2626; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Success Header -->
            <div class="text-center mb-8">
                <div class="checkmark-animation inline-block">
                    <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                </div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">Booking Confirmed!</h1>
                <p class="text-lg text-gray-600">Your trip has been successfully booked</p>
            </div>

            <!-- Booking Details Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
                <!-- Header -->
                <div class="gradient-bg text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold"><?= SITE_NAME ?></h2>
                            <p class="text-red-100">Electronic Ticket</p>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-red-100">PNR</div>
                            <div class="text-2xl font-bold"><?= htmlspecialchars($booking['pnr']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Trip Details -->
                        <div>
                            <h3 class="text-xl font-bold mb-4 flex items-center">
                                <i class="fas fa-route text-primary mr-3"></i>
                                Trip Details
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Route:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['trip']['pickup_city']) ?> → <?= htmlspecialchars($booking['trip']['dropoff_city']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-semibold"><?= date('M j, Y', strtotime($booking['trip']['trip_date'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Departure Time:</span>
                                    <span class="font-semibold"><?= date('H:i', strtotime($booking['trip']['departure_time'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['trip']['vehicle_type']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle Number:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['trip']['vehicle_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Driver:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['trip']['driver_name']) ?></span>
                                </div>
                            </div>

                            <!-- Seats -->
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Your Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($booking['selected_seats'] as $seat): ?>
                                        <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-semibold">
                                            Seat <?= $seat ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Details -->
                        <div>
                            <h3 class="text-xl font-bold mb-4 flex items-center">
                                <i class="fas fa-user text-primary mr-3"></i>
                                Passenger Information
                            </h3>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['email']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Phone:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['phone']) ?></span>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Payment Details:</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Amount Paid:</span>
                                        <span class="font-semibold text-green-600">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Method:</span>
                                        <span class="font-semibold"><?= ucfirst($booking['payment_method']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Reference:</span>
                                        <span class="font-semibold text-xs"><?= htmlspecialchars($booking['payment_reference']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Summary -->
                    <div class="border-t pt-6 mt-6">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-lg font-semibold">Total Seats: <?= count($booking['selected_seats']) ?></span>
                                    <span class="text-gray-600 ml-4">× ₦<?= number_format($booking['trip']['price'], 0) ?> each</span>
                                </div>
                                <div class="text-2xl font-bold text-primary">
                                    ₦<?= number_format($booking['total_amount'], 0) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Information -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-8">
                <h3 class="text-lg font-bold text-yellow-800 mb-3 flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                    Important Information
                </h3>
                <ul class="space-y-2 text-yellow-800">
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Please arrive at the terminal at least 30 minutes before departure
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Bring a valid ID for verification
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Keep your PNR (<?= htmlspecialchars($booking['pnr']) ?>) for reference
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Contact support if you need to make changes to your booking
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center no-print">
                <button onclick="window.print()" class="bg-primary text-white font-bold py-3 px-6 rounded-xl hover:bg-secondary transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-print mr-2"></i>Print Ticket
                </button>
                
                <button onclick="downloadPDF()" class="bg-gray-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-gray-700 transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </button>
                
                <a href="search_trips.php" class="bg-green-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-green-700 transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i>Book Another Trip
                </a>
            </div>

            <!-- Contact Information -->
            <div class="text-center mt-8 text-gray-600">
                <p class="mb-2">Need help? Contact us:</p>
                <p class="font-semibold">Email: support@dgctransports.com | Phone: +234 XXX XXX XXXX</p>
                <p class="text-sm mt-2">Your PNR: <span class="font-bold text-primary"><?= htmlspecialchars($pnr) ?></span></p>
            </div>
        </div>
    </div>
<?php require_once '../templates/footer.php'; ?>
    <script>
        function downloadPDF() {
            // Simple PDF download using browser print functionality
            // You can integrate a proper PDF library later
            window.print();
        }

        // Clear the booking confirmation from session after 5 minutes
        // This prevents back button issues
        setTimeout(function() {
            fetch('clear_session.php', {method: 'POST'});
        }, 300000); // 5 minutes
    </script>
</body>
</html>

<?php
// Clear the booking confirmation after displaying
// unset($_SESSION['booking_confirmation']);
?>