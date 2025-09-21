<?php
// bookings/booking_confirmation.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Get PNR from URL (supporting both 'pnr' and 'booking_id' for compatibility)
$pnr = $_GET['pnr'] ?? $_GET['booking_id'] ?? '';

// Check if booking confirmation data exists in session
if (!isset($_SESSION['booking_confirmation']) || empty($pnr)) {
    header("Location: search_trips.php");
    exit();
}

$booking_confirmation = $_SESSION['booking_confirmation'];

// Verify that the provided PNR is in the session's PNRs
if (!in_array($pnr, $booking_confirmation['pnrs'])) {
    header("Location: search_trips.php");
    exit();
}

// Fetch bookings from database to double-check using PNRs
$pnrs = $booking_confirmation['pnrs'];
$placeholders = implode(',', array_fill(0, count($pnrs), '?'));
$stmt = $conn->prepare("
    SELECT b.*, tt.pickup_city_id, tt.dropoff_city_id, pc.name AS pickup_city, dc.name AS dropoff_city, 
           vt.type AS vehicle_type, v.vehicle_number, v.driver_name, ts.departure_time
    FROM bookings b
    JOIN trip_instances ti ON b.template_id = ti.template_id AND b.trip_date = ti.trip_date
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    WHERE b.pnr IN ($placeholders) AND b.status = 'confirmed'
");
if (!$stmt) {
    error_log("Prepare booking query failed: " . $conn->error);
    header("Location: search_trips.php");
    exit();
}
$stmt->bind_param(str_repeat('s', count($pnrs)), ...$pnrs);
$stmt->execute();
$result = $stmt->get_result();
$db_bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Validate that bookings exist in the database
if (empty($db_bookings)) {
    error_log("No confirmed bookings found for PNRs: " . implode(', ', $pnrs));
    header("Location: search_trips.php");
    exit();
}

// Organize bookings by PNR for easier access
$db_bookings_by_pnr = [];
foreach ($db_bookings as $db_booking) {
    $db_bookings_by_pnr[$db_booking['pnr']] = $db_booking;
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
                            <div class="text-sm text-red-100">PNR(s)</div>
                            <div class="text-2xl font-bold"><?= htmlspecialchars(implode(', ', $booking_confirmation['pnrs'])) ?></div>
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
                                    <span class="font-semibold"><?= htmlspecialchars($booking_confirmation['trip']['pickup_city']) ?> → <?= htmlspecialchars($booking_confirmation['trip']['dropoff_city']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-semibold"><?= date('M j, Y', strtotime($booking_confirmation['trip']['trip_date'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Departure Time:</span>
                                    <span class="font-semibold"><?= date('H:i', strtotime($booking_confirmation['trip']['departure_time'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking_confirmation['trip']['vehicle_type']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle Number:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking_confirmation['trip']['vehicle_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Driver:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking_confirmation['trip']['driver_name']) ?></span>
                                </div>
                            </div>

                            <!-- Seats -->
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Your Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($booking_confirmation['selected_seats'] as $seat): ?>
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
                            
                            <div class="space-y-4">
                                <?php foreach ($booking_confirmation['passenger_details'] as $index => $passenger): ?>
                                    <div class="border-t pt-2">
                                        <h4 class="font-semibold mb-2">Passenger <?= $index + 1 ?> - Seat <?= $passenger['seat_number'] ?></h4>
                                        <div class="space-y-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">PNR:</span>
                                                <span class="font-semibold"><?= htmlspecialchars($booking_confirmation['pnrs'][$index]) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Name:</span>
                                                <span class="font-semibold"><?= htmlspecialchars($passenger['name']) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Email:</span>
                                                <span class="font-semibold"><?= htmlspecialchars($passenger['email']) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Phone:</span>
                                                <span class="font-semibold"><?= htmlspecialchars($passenger['phone']) ?></span>
                                            </div>
                                            <?php if (!empty($passenger['emergency_contact'])): ?>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Emergency Contact:</span>
                                                    <span class="font-semibold"><?= htmlspecialchars($passenger['emergency_contact']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($index === 0 && !empty($passenger['special_requests'])): ?>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Special Requests:</span>
                                                    <span class="font-semibold"><?= htmlspecialchars($passenger['special_requests']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Payment Details -->
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Payment Details:</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Amount Paid:</span>
                                        <span class="font-semibold text-green-600">₦<?= number_format($booking_confirmation['total_amount'], 0) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Method:</span>
                                        <span class="font-semibold"><?= ucfirst($booking_confirmation['payment_method']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Reference:</span>
                                        <span class="font-semibold text-xs"><?= htmlspecialchars($booking_confirmation['payment_reference']) ?></span>
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
                                    <span class="text-lg font-semibold">Total Seats: <?= count($booking_confirmation['selected_seats']) ?></span>
                                    <span class="text-gray-600 ml-4">× ₦<?= number_format($booking_confirmation['trip']['price'], 0) ?> each</span>
                                </div>
                                <div class="text-2xl font-bold text-primary">
                                    ₦<?= number_format($booking_confirmation['total_amount'], 0) ?>
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
                        Keep your PNR(s) (<?= htmlspecialchars(implode(', ', $booking_confirmation['pnrs'])) ?>) for reference
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
                <p class="font-semibold">Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?></p>
                <p class="text-sm mt-2">Your PNR(s): <span class="font-bold text-primary"><?= htmlspecialchars(implode(', ', $booking_confirmation['pnrs'])) ?></span></p>
            </div>
        </div>
    </div>
<?php require_once '../templates/footer.php'; ?>
    <script>
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 10;
            let y = 10;

            // Add logo or title
            doc.setFontSize(20);
            doc.setTextColor(220, 38, 38); // Primary color
            doc.text('<?= SITE_NAME ?> - Booking Confirmation', margin, y);
            y += 10;

            // PNRs
            doc.setFontSize(12);
            doc.setTextColor(0);
            doc.text('PNR(s): <?= htmlspecialchars(implode(', ', $booking_confirmation['pnrs'])) ?>', margin, y);
            y += 10;

            // Trip Details
            doc.setFontSize(16);
            doc.text('Trip Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Route: <?= htmlspecialchars($booking_confirmation['trip']['pickup_city']) ?> → <?= htmlspecialchars($booking_confirmation['trip']['dropoff_city']) ?>', margin, y);
            y += 7;
            doc.text('Date: <?= date('M j, Y', strtotime($booking_confirmation['trip']['trip_date'])) ?>', margin, y);
            y += 7;
            doc.text('Departure Time: <?= date('H:i', strtotime($booking_confirmation['trip']['departure_time'])) ?>', margin, y);
            y += 7;
            doc.text('Vehicle: <?= htmlspecialchars($booking_confirmation['trip']['vehicle_type']) ?>', margin, y);
            y += 7;
            doc.text('Vehicle Number: <?= htmlspecialchars($booking_confirmation['trip']['vehicle_number']) ?>', margin, y);
            y += 7;
            doc.text('Driver: <?= htmlspecialchars($booking_confirmation['trip']['driver_name']) ?>', margin, y);
            y += 7;
            doc.text('Seats: <?= implode(', ', $booking_confirmation['selected_seats']) ?>', margin, y);
            y += 10;

            // Passenger Details
            doc.setFontSize(16);
            doc.text('Passenger Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            <?php foreach ($booking_confirmation['passenger_details'] as $index => $passenger): ?>
                doc.text('Passenger <?= $index + 1 ?> - Seat <?= $passenger['seat_number'] ?>', margin, y);
                y += 7;
                doc.text('PNR: <?= htmlspecialchars($booking_confirmation['pnrs'][$index]) ?>', margin, y);
                y += 7;
                doc.text('Name: <?= htmlspecialchars($passenger['name']) ?>', margin, y);
                y += 7;
                doc.text('Email: <?= htmlspecialchars($passenger['email']) ?>', margin, y);
                y += 7;
                doc.text('Phone: <?= htmlspecialchars($passenger['phone']) ?>', margin, y);
                y += 7;
                <?php if (!empty($passenger['emergency_contact'])): ?>
                    doc.text('Emergency Contact: <?= htmlspecialchars($passenger['emergency_contact']) ?>', margin, y);
                    y += 7;
                <?php endif; ?>
                <?php if ($index === 0 && !empty($passenger['special_requests'])): ?>
                    doc.text('Special Requests: <?= htmlspecialchars($passenger['special_requests']) ?>', margin, y);
                    y += 7;
                <?php endif; ?>
                y += 5;
            <?php endforeach; ?>

            // Payment Details
            doc.setFontSize(16);
            doc.text('Payment Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Amount Paid: ₦<?= number_format($booking_confirmation['total_amount'], 0) ?>', margin, y);
            y += 7;
            doc.text('Payment Method: <?= ucfirst($booking_confirmation['payment_method']) ?>', margin, y);
            y += 7;
            doc.text('Reference: <?= htmlspecialchars($booking_confirmation['payment_reference']) ?>', margin, y);
            y += 10;

            // Footer
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text('Contact: Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?>', margin, doc.internal.pageSize.getHeight() - 10);

            // Save PDF
            doc.save('DGC_Transport_Booking_Confirmation_<?= htmlspecialchars($booking_confirmation['pnrs'][0]) ?>.pdf');
        }

        // Clear the booking confirmation from session after 5 minutes
        setTimeout(function() {
            fetch('clear_session.php', {method: 'POST'});
        }, 300000); // 5 minutes
    </script>
</body>
</html>

<?php
// Clear the booking confirmation after displaying
unset($_SESSION['booking_confirmation']);
?>