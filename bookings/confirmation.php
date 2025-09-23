<?php
// bookings/confirmation.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../libs/phpqrcode/qrlib.php'; // Include phpqrcode library

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Check if required session data exists
if (!isset($_SESSION['confirmed_pnrs']) || !isset($_SESSION['confirmed_trip']) || !isset($_SESSION['confirmed_total_amount'])) {
    error_log("Missing session data in confirmation.php: " . json_encode([
        'confirmed_pnrs' => isset($_SESSION['confirmed_pnrs']) ? 'set' : 'not set',
        'confirmed_trip' => isset($_SESSION['confirmed_trip']) ? 'set' : 'not set',
        'confirmed_total_amount' => isset($_SESSION['confirmed_total_amount']) ? 'set' : 'not set'
    ]));
    header("Location: " . SITE_URL . "/bookings/search_trips.php");
    exit();
}

$pnrs = $_SESSION['confirmed_pnrs'];
$trip = $_SESSION['confirmed_trip'];
$total_amount = $_SESSION['confirmed_total_amount'];

// Get PNR from URL (supporting both 'pnr' and 'booking_id' for compatibility)
$pnr = filter_input(INPUT_GET, 'pnr', FILTER_SANITIZE_SPECIAL_CHARS) ?? 
       filter_input(INPUT_GET, 'booking_id', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// If no PNR provided, use the first from session and redirect
if (empty($pnr) && !empty($pnrs)) {
    $pnr = $pnrs[0];
    $redirect_url = SITE_URL . "/bookings/confirmation.php?pnr=" . urlencode($pnr);
    error_log("No PNR in URL, redirecting to: $redirect_url");
    header("Location: $redirect_url");
    exit();
}

// Verify that the provided PNR is in the session's PNRs
if (!in_array($pnr, $pnrs)) {
    error_log("Invalid PNR: $pnr. Redirecting to search_trips.php");
    header("Location: " . SITE_URL . "/bookings/search_trips.php");
    exit();
}

// Fetch bookings from database, including payment details
$placeholders = implode(',', array_fill(0, count($pnrs), '?'));
$stmt = $conn->prepare("
    SELECT b.*, 
           tt.pickup_city_id, tt.dropoff_city_id, pc.name AS pickup_city, dc.name AS dropoff_city, 
           vt.type AS vehicle_type, v.vehicle_number, v.driver_name, ts.departure_time,
           pay.payment_method, pay.transaction_reference
    FROM bookings b
    JOIN trip_instances ti ON b.trip_id = ti.id
    JOIN trip_templates tt ON ti.template_id = tt.id
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN payments pay ON b.id = pay.booking_id
    WHERE b.pnr IN ($placeholders) AND b.status = 'confirmed'
");
if (!$stmt) {
    error_log("Prepare booking query failed: " . $conn->error);
    header("Location: " . SITE_URL . "/bookings/search_trips.php?error=database");
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
    header("Location: " . SITE_URL . "/bookings/search_trips.php?error=no_bookings");
    exit();
}

// Organize bookings by PNR
$bookings = [];
$passenger_details = [];
$selected_seats = [];
foreach ($db_bookings as $booking) {
    $bookings[$booking['pnr']] = $booking;
    $passenger_details[] = [
        'name' => $booking['passenger_name'],
        'email' => $booking['email'],
        'phone' => $booking['phone'],
        'emergency_contact' => $booking['emergency_contact'],
        'seat_number' => $booking['seat_number'],
        'special_requests' => $booking['special_requests']
    ];
    $selected_seats[] = $booking['seat_number'];
}

// Generate QR code for the provided PNR
$qr_code_dir = __DIR__ . '/../qrcodes/';
$qr_code_path = $qr_code_dir . 'pnr_' . $pnr . '.png';
try {
    if (!is_dir($qr_code_dir)) {
        mkdir($qr_code_dir, 0755, true);
    }
    QRcode::png($pnr, $qr_code_path, QR_ECLEVEL_L, 4);
    if (!file_exists($qr_code_path)) {
        throw new Exception("Failed to generate QR code for PNR: $pnr");
    }
} catch (Exception $e) {
    error_log("QR code generation failed: " . $e->getMessage());
    $qr_code_path = null; // Fallback to no QR code
}

require_once '../templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - <?= htmlspecialchars(SITE_NAME) ?></title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
        .qr-code-img {
            max-width: 120px;
            border: 2px solid #1a1a1a;
            border-radius: 8px;
        }
        @media (max-width: 640px) {
            .text-4xl { font-size: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-xl { font-size: 1.125rem; }
            .p-6 { padding: 1rem; }
            .max-w-4xl { max-width: 100%; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            .gap-8 { gap: 1.5rem; }
            .py-3 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
            .px-6 { padding-left: 1rem; padding-right: 1rem; }
        }
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .ticket-card { display: block; }
            .gradient-bg { background: #dc2626; }
            .min-h-screen, .py-8, .max-w-4xl, .px-4 { padding: 0; margin: 0; }
            .qr-code-img { max-width: 100px; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Success Header -->
        <div class="text-center mb-8 no-print">
            <div class="checkmark-animation inline-block">
                <i class="fas fa-check-circle text-4xl sm:text-6xl text-green-500 mb-4"></i>
            </div>
            <h1 class="text-2xl sm:text-4xl font-bold text-gray-800 mb-2">Booking Confirmed!</h1>
            <p class="text-base sm:text-lg text-gray-600">Your trip has been successfully booked</p>
        </div>

        <!-- Booking Details Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8 ticket-card" id="ticket-card">
            <!-- Header -->
            <div class="gradient-bg text-white p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row items-center justify-between">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold"><?= htmlspecialchars(SITE_NAME) ?></h2>
                        <p class="text-red-100 text-sm sm:text-base">Electronic Ticket</p>
                    </div>
                    <div class="text-center sm:text-right mt-2 sm:mt-0">
                        <div class="text-xs sm:text-sm text-red-100">PNR(s)</div>
                        <div class="text-lg sm:text-2xl font-bold"><?= htmlspecialchars(implode(', ', $pnrs)) ?></div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                    <!-- Trip Details -->
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-route text-primary mr-2 sm:mr-3"></i>
                            Trip Details
                        </h3>
                        <div class="space-y-2 sm:space-y-3 text-sm sm:text-base">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Route:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date:</span>
                                <span class="font-semibold"><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Departure Time:</span>
                                <span class="font-semibold"><?= date('h:i A', strtotime($trip['departure_time'] ?? '00:00')) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vehicle:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['vehicle_type'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vehicle Number:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['vehicle_number'] ?? 'N/A') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Driver:</span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['driver_name'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                        <div class="mt-4 sm:mt-6">
                            <h4 class="font-semibold mb-2 text-sm sm:text-base">Your Seats:</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($selected_seats as $seat): ?>
                                    <span class="bg-primary text-white px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                                        Seat <?= htmlspecialchars($seat) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger Details -->
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-user text-primary mr-2 sm:mr-3"></i>
                            Passenger Information
                        </h3>
                        <div class="space-y-3 sm:space-y-4 text-sm sm:text-base">
                            <?php foreach ($passenger_details as $index => $passenger): ?>
                                <div class="border-t pt-2">
                                    <h4 class="font-semibold mb-2 text-sm sm:text-base">Passenger <?= $index + 1 ?> - Seat <?= htmlspecialchars($passenger['seat_number']) ?></h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">PNR:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($pnrs[$index]) ?></span>
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
                        <div class="mt-4 sm:mt-6">
                            <h4 class="font-semibold mb-2 text-sm sm:text-base">Payment Details:</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Amount Paid:</span>
                                    <span class="font-semibold text-green-600">₦<?= number_format($total_amount, 0) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-semibold"><?= htmlspecialchars(ucfirst($bookings[$pnr]['payment_method'] ?? 'N/A')) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Reference:</span>
                                    <span class="font-semibold text-xs"><?= htmlspecialchars($bookings[$pnr]['transaction_reference'] ?? $bookings[$pnr]['payment_reference'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div class="border-t pt-4 sm:pt-6 mt-4 sm:mt-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex flex-col sm:flex-row justify-between items-center">
                            <div>
                                <span class="text-base sm:text-lg font-semibold">Total Seats: <?= count($selected_seats) ?></span>
                                <span class="text-gray-600 ml-0 sm:ml-4 text-sm">× ₦<?= number_format($trip['price'] ?? 0, 0) ?> each</span>
                            </div>
                            <div class="text-lg sm:text-2xl font-bold text-primary mt-2 sm:mt-0">
                                ₦<?= number_format($total_amount, 0) ?>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <h4 class="font-semibold mb-2 text-sm sm:text-base">Booking QR Code</h4>
                            <?php if ($qr_code_path && file_exists($qr_code_path)): ?>
                                <img src="<?= htmlspecialchars(SITE_URL . '/qrcodes/pnr_' . $pnr . '.png') ?>" alt="PNR QR Code" class="qr-code-img mx-auto">
                            <?php else: ?>
                                <p class="text-red-600 text-sm">QR code unavailable</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Important Information -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 sm:p-6 mb-8 no-print">
            <h3 class="text-base sm:text-lg font-bold text-yellow-800 mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 sm:mr-3"></i>
                Important Information
            </h3>
            <ul class="space-y-2 text-yellow-800 text-sm sm:text-base">
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
                    Keep your PNR(s) (<?= htmlspecialchars(implode(', ', $pnrs)) ?>) for reference
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                    Contact support if you need to make changes to your booking
                </li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center no-print">
            <button onclick="window.print()" class="bg-primary text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-secondary transition-colors duration-200 flex items-center justify-center text-sm sm:text-base">
                <i class="fas fa-print mr-2"></i>Print Ticket
            </button>
            <button onclick="downloadPDF()" class="bg-gray-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-gray-700 transition-colors duration-200 flex items-center justify-center text-sm sm:text-base">
                <i class="fas fa-download mr-2"></i>Download PDF
            </button>
            <a href="<?= SITE_URL ?>/bookings/search_trips.php" class="bg-green-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-green-700 transition-colors duration-200 flex items-center justify-center text-sm sm:text-base">
                <i class="fas fa-plus mr-2"></i>Book Another Trip
            </a>
        </div>

        <!-- Contact Information -->
        <div class="text-center mt-8 text-gray-600 no-print">
            <p class="mb-2 text-sm sm:text-base">Need help? Contact us:</p>
            <p class="font-semibold text-sm sm:text-base">Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?></p>
            <p class="text-xs sm:text-sm mt-2">Your PNR(s): <span class="font-bold text-primary"><?= htmlspecialchars(implode(', ', $pnrs)) ?></span></p>
        </div>
    </div>
<?php require_once '../templates/footer.php'; ?>

    <script>
        async function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 10;
            let y = 10;

            // Add logo or title
            doc.setFontSize(20);
            doc.setTextColor(220, 38, 38);
            doc.text('<?= htmlspecialchars(SITE_NAME) ?> - Booking Confirmation', margin, y);
            y += 10;

            // PNRs
            doc.setFontSize(12);
            doc.setTextColor(0);
            doc.text('PNR(s): <?= htmlspecialchars(implode(', ', $pnrs)) ?>', margin, y);
            y += 10;

            // Trip Details
            doc.setFontSize(16);
            doc.text('Trip Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Route: <?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?>', margin, y);
            y += 7;
            doc.text('Date: <?= date('M j, Y', strtotime($trip['trip_date'])) ?>', margin, y);
            y += 7;
            doc.text('Departure Time: <?= date('h:i A', strtotime($trip['departure_time'] ?? '00:00')) ?>', margin, y);
            y += 7;
            doc.text('Vehicle: <?= htmlspecialchars($trip['vehicle_type'] ?? 'N/A') ?>', margin, y);
            y += 7;
            doc.text('Vehicle Number: <?= htmlspecialchars($trip['vehicle_number'] ?? 'N/A') ?>', margin, y);
            y += 7;
            doc.text('Driver: <?= htmlspecialchars($trip['driver_name'] ?? 'N/A') ?>', margin, y);
            y += 7;
            doc.text('Seats: <?= implode(', ', array_map('htmlspecialchars', $selected_seats)) ?>', margin, y);
            y += 10;

            // Passenger Details
            doc.setFontSize(16);
            doc.text('Passenger Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            <?php foreach ($passenger_details as $index => $passenger): ?>
                doc.text('Passenger <?= $index + 1 ?> - Seat <?= htmlspecialchars($passenger['seat_number']) ?>', margin, y);
                y += 7;
                doc.text('PNR: <?= htmlspecialchars($pnrs[$index]) ?>', margin, y);
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
            doc.text('Amount Paid: ₦<?= number_format($total_amount, 0) ?>', margin, y);
            y += 7;
            doc.text('Payment Method: <?= htmlspecialchars(ucfirst($bookings[$pnr]['payment_method'] ?? 'N/A')) ?>', margin, y);
            y += 7;
            doc.text('Reference: <?= htmlspecialchars($bookings[$pnr]['transaction_reference'] ?? $bookings[$pnr]['payment_reference'] ?? 'N/A') ?>', margin, y);
            y += 10;

            // Add QR Code
            <?php if ($qr_code_path && file_exists($qr_code_path)): ?>
                try {
                    const qrImage = await fetch('<?= htmlspecialchars(SITE_URL . '/qrcodes/pnr_' . $pnr . '.png') ?>');
                    const qrBlob = await qrImage.blob();
                    const qrUrl = URL.createObjectURL(qrBlob);
                    doc.addImage(qrUrl, 'PNG', margin, y, 40, 40);
                    y += 45;
                } catch (e) {
                    console.error('Failed to add QR code to PDF:', e);
                }
            <?php endif; ?>

            // Important Information
            doc.setFontSize(16);
            doc.text('Important Information', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('• Arrive at the terminal 30 minutes before departure', margin, y);
            y += 7;
            doc.text('• Bring a valid ID for verification', margin, y);
            y += 7;
            doc.text('• Keep your PNR(s) for reference', margin, y);
            y += 7;
            doc.text('• Contact support for booking changes', margin, y);
            y += 10;

            // Footer
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text('Contact: Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?>', margin, pageHeight - 10);

            // Save PDF
            doc.save('DGC_Transport_Booking_Confirmation_<?= htmlspecialchars($pnrs[0]) ?>.pdf');
        }

        // Clear session after 5 minutes
        setTimeout(() => {
            fetch('<?= SITE_URL ?>/bookings/clear_confirmation.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Session cleared');
                    } else {
                        console.error('Failed to clear session:', data.message);
                    }
                })
                .catch(err => console.error('Failed to clear session:', err));
        }, 300000);
    </script>
</body>
</html>