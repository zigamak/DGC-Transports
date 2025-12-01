<?php
// bookings/confirmation.php - ORIGINAL DESIGN - FULLY COMPLETED
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../libs/phpqrcode/qrlib.php'; // Include phpqrcode library

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// DETECT ROUND TRIP
$is_roundtrip = isset($_SESSION['is_roundtrip_confirmation']) && $_SESSION['is_roundtrip_confirmation'];

if ($is_roundtrip) {
    // Round trip validation
    if (!isset($_SESSION['confirmed_pnrs']) || !isset($_SESSION['confirmed_outbound_trip']) || !isset($_SESSION['confirmed_return_trip']) || !isset($_SESSION['confirmed_total_amount'])) {
        error_log("Missing round trip confirmation session data");
        header("Location: " . SITE_URL . "/bookings/search_trips.php");
        exit();
    }
   
    $pnrs = $_SESSION['confirmed_pnrs'];
    $outbound_trip = $_SESSION['confirmed_outbound_trip'];
    $return_trip = $_SESSION['confirmed_return_trip'];
    $total_amount = $_SESSION['confirmed_total_amount'];
   
} else {
    // One-way validation
    if (!isset($_SESSION['confirmed_pnrs']) || !isset($_SESSION['confirmed_trip']) || !isset($_SESSION['confirmed_total_amount'])) {
        error_log("Missing session data in confirmation.php");
        header("Location: " . SITE_URL . "/bookings/search_trips.php");
        exit();
    }
   
    $pnrs = $_SESSION['confirmed_pnrs'];
    $trip = $_SESSION['confirmed_trip'];
    $total_amount = $_SESSION['confirmed_total_amount'];
}

// Get PNR from URL
$pnr = filter_input(INPUT_GET, 'pnr', FILTER_SANITIZE_SPECIAL_CHARS) ??
       filter_input(INPUT_GET, 'booking_id', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// If no PNR provided, use the first from session and redirect
if (empty($pnr) && !empty($pnrs)) {
    $pnr = $pnrs[0];
    header("Location: " . SITE_URL . "/bookings/confirmation.php?pnr=" . urlencode($pnr));
    exit();
}

// Verify that the provided PNR is in the session's PNRs
if (!in_array($pnr, $pnrs)) {
    error_log("Invalid PNR: $pnr");
    header("Location: " . SITE_URL . "/bookings/search_trips.php");
    exit();
}

// Fetch bookings from database
$placeholders = implode(',', array_fill(0, count($pnrs), '?'));
$stmt = $conn->prepare("
    SELECT b.*,
           tt.pickup_city_id, tt.dropoff_city_id, pc.name AS pickup_city, dc.name AS dropoff_city,
           vt.type AS vehicle_type, v.vehicle_number, v.driver_name, ts.departure_time,
           pay.payment_method, pay.transaction_reference,
           ti.trip_date
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
    ORDER BY ti.trip_date ASC, b.id ASC
");
$stmt->bind_param(str_repeat('s', count($pnrs)), ...$pnrs);
$stmt->execute();
$result = $stmt->get_result();
$db_bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($db_bookings)) {
    error_log("No confirmed bookings found for PNRs: " . implode(', ', $pnrs));
    header("Location: " . SITE_URL . "/bookings/search_trips.php?error=no_bookings");
    exit();
}

// Organize bookings
if ($is_roundtrip) {
    // ROUND TRIP: Separate bookings by trip date
    $outbound_bookings = [];
    $return_bookings = [];
   
    $trip_dates = array_unique(array_column($db_bookings, 'trip_date'));
    sort($trip_dates);
   
    if (count($trip_dates) >= 2) {
        $outbound_date = $trip_dates[0];
        $return_date = $trip_dates[1];
       
        foreach ($db_bookings as $booking) {
            if ($booking['trip_date'] === $outbound_date) {
                $outbound_bookings[] = $booking;
            } else if ($booking['trip_date'] === $return_date) {
                $return_bookings[] = $booking;
            }
        }
    } else {
        $half = ceil(count($db_bookings) / 2);
        $outbound_bookings = array_slice($db_bookings, 0, $half);
        $return_bookings = array_slice($db_bookings, $half);
    }
   
    $bookings = $outbound_bookings;
    $selected_seats = array_column($outbound_bookings, 'seat_number');
   
} else {
    // ONE-WAY: Standard organization
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
    $qr_code_path = null;
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
        .|qr-code-img {
            max-width: 120px;
            border: 2px solid #1a1a1a;
            border-radius: 8px;
        }
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .gradient-bg { background: #dc2626; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Success Header -->
        <div class="text-center mb-8 no-print">
            <div class="checkmark-animation inline-block">
                <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
            </div>
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <?= $is_roundtrip ? 'Round Trip ' : '' ?>Booking Confirmed!
            </h1>
            <p class="text-lg text-gray-600">Your trip has been successfully booked</p>
        </div>

        <!-- Booking Details Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8" id="ticket-card">
            <!-- Header -->
            <div class="gradient-bg text-white p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars(SITE_NAME) ?></h2>
                        <p class="text-red-100"><?= $is_roundtrip ? 'Round Trip Electronic Ticket' : 'Electronic Ticket' ?></p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-red-100">PNR(s)</div>
                        <div class="text-2xl font-bold"><?= htmlspecialchars(implode(', ', $pnrs)) ?></div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="p-6">
                <?php if ($is_roundtrip): ?>
                    <!-- ROUND TRIP DISPLAY -->
                    <div class="space-y-8">
                        <!-- Outbound Trip -->
                        <?php if (!empty($outbound_bookings)): ?>
                        <div class="p-4 bg-blue-50 border-2 border-blue-200 rounded-lg">
                            <h3 class="text-xl font-bold mb-4 flex items-center text-blue-800">
                                Outbound Trip
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Route:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($outbound_bookings[0]['pickup_city']) ?> → <?= htmlspecialchars($outbound_bookings[0]['dropoff_city']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-semibold"><?= date('M j, Y', strtotime($outbound_bookings[0]['trip_date'])) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Time:</span>
                                        <span class="font-semibold"><?= date('h:i A', strtotime($outbound_bookings[0]['departure_time'])) ?></span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Vehicle:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($outbound_bookings[0]['vehicle_type']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Vehicle No:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($outbound_bookings[0]['vehicle_number']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Driver:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($outbound_bookings[0]['driver_name'] ?? 'TBA') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-semibold mb-2">Outbound Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($outbound_bookings as $booking): ?>
                                        <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                            Seat <?= htmlspecialchars($booking['seat_number']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                       
                        <!-- Return Trip -->
                        <?php if (!empty($return_bookings)): ?>
                        <div class="p-4 bg-green-50 border-2 border-green-200 rounded-lg">
                            <h3 class="text-xl font-bold mb-4 flex items-center text-green-800">
                                Return Trip
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Route:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($return_bookings[0]['pickup_city']) ?> → <?= htmlspecialchars($return_bookings[0]['dropoff_city']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-semibold"><?= date('M j, Y', strtotime($return_bookings[0]['trip_date'])) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Time:</span>
                                        <span class="font-semibold"><?= date('h:i A', strtotime($return_bookings[0]['departure_time'])) ?></span>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Vehicle:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($return_bookings[0]['vehicle_type']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Vehicle No:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($return_bookings[0]['vehicle_number']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Driver:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($return_bookings[0]['driver_name'] ?? 'TBA') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-semibold mb-2">Return Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($return_bookings as $booking): ?>
                                        <span class="bg-green-600 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                            Seat <?= htmlspecialchars($booking['seat_number']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Passenger Information (Round Trip) -->
                        <div>
                            <h3 class="text-xl font-bold mb-4 flex items-center">
                                Passenger Information
                            </h3>
                            <div class="space-y-4">
                                <?php
                                $unique_passengers = [];
                                $passenger_index = 0;
                                foreach ($outbound_bookings as $index => $ob):
                                    $passenger_key = $ob['passenger_name'] . '_' . $ob['email'];
                                    if (!isset($unique_passengers[$passenger_key])):
                                        $unique_passengers[$passenger_key] = true;
                                        $passenger_index++;
                                        $rb = isset($return_bookings[$index]) ? $return_bookings[$index] : null;
                                ?>
                                <div class="border-t pt-3">
                                    <h4 class="font-semibold mb-2">Passenger <?= $passenger_index ?></h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Name:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($ob['passenger_name']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Email:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($ob['email']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Phone:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($ob['phone']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Outbound Seat:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($ob['seat_number']) ?></span>
                                        </div>
                                        <?php if ($rb): ?>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Return Seat:</span>
                                            <span class="font-semibold"><?= htmlspecialchars($rb['seat_number']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">PNRs:</span>
                                            <span class="font-semibold text-xs"><?= htmlspecialchars($ob['pnr']) ?>, <?= htmlspecialchars($rb['pnr']) ?></span>
                                        </div>
                                        <?php else: ?>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">PNR:</span>
                                            <span class="font-semibold text-xs"><?= htmlspecialchars($ob['pnr']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>

                        <!-- Payment Details (Round Trip) -->
                        <?php if (!empty($outbound_bookings)): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold mb-3">Payment Details:</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Passengers:</span>
                                    <span class="font-semibold"><?= count($outbound_bookings) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Trip Type:</span>
                                    <span class="font-semibold text-primary">Round Trip</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Amount Paid:</span>
                                    <span class="font-semibold text-green-600 text-lg">₦<?= number_format($total_amount, 0) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-semibold"><?= htmlspecialchars(ucfirst($outbound_bookings[0]['payment_method'] ?? 'N/A')) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Reference:</span>
                                    <span class="font-semibold text-xs"><?= htmlspecialchars($outbound_bookings[0]['transaction_reference'] ?? $outbound_bookings[0]['payment_reference'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                   
                <?php else: ?>
                    <!-- ONE-WAY DISPLAY (COMPLETED) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Trip Details -->
                        <div>
                            <h3 class="text-xl font-bold mb-4 flex items-center">
                                Trip Details
                            </h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Route:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($db_bookings[0]['pickup_city']) ?> → <?= htmlspecialchars($db_bookings[0]['dropoff_city']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-semibold"><?= date('M j, Y', strtotime($db_bookings[0]['trip_date'])) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Departure Time:</span>
                                    <span class="font-semibold"><?= date('h:i A', strtotime($db_bookings[0]['departure_time'] ?? '00:00')) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($db_bookings[0]['vehicle_type'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle Number:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($db_bookings[0]['vehicle_number'] ?? 'N/A') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Driver:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($db_bookings[0]['driver_name'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Your Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($selected_seats as $seat): ?>
                                        <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-semibold">
                                            Seat <?= htmlspecialchars($seat) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Details -->
                        <div>
                            <h3 class="text-xl font-bold mb-4 flex items-center">
                                Passenger Information
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($passenger_details as $index => $passenger): ?>
                                    <div class="border-t pt-2">
                                        <h4 class="font-semibold mb-2">Passenger <?= $index + 1 ?> - Seat <?= htmlspecialchars($passenger['seat_number']) ?></h4>
                                        <div class="space-y-2 text-sm">
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
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Payment Details (One Way) - THIS WAS MISSING -->
                            <?php if (!empty($db_bookings)): ?>
                            <div class="mt-6 bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold mb-2">Payment Details:</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Amount Paid:</span>
                                        <span class="font-semibold text-green-600">₦<?= number_format($total_amount, 0) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Method:</span>
                                        <span class="font-semibold"><?= htmlspecialchars(ucfirst($db_bookings[0]['payment_method'] ?? 'N/A')) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Transaction Ref:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($db_bookings[0]['transaction_reference'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- QR Code Section (Same for both) -->
                <div class="text-center mt-10">
                    <p class="text-lg font-semibold mb-4">Scan QR Code at Boarding Point</p>
                    <?php if ($qr_code_path && file_exists($qr_code_path)): ?>
                        <img src="<?= SITE_URL ?>/qrcodes/pnr_<?= htmlspecialchars($pnr) ?>.png" alt="QR Code" class="qr-code-img mx-auto">
                        <p class="text-sm text-gray-600 mt-2">PNR: <strong><?= htmlspecialchars($pnr) ?></strong></p>
                    <?php else: ?>
                        <p class="text-red-600">QR Code temporarily unavailable</p>
                    <?php endif; ?>
                </div>

                <!-- Print & Back Buttons -->
                <div class="text-center mt-8 space-x-4 no-print">
                    <button onclick="window.print()" class="bg-primary hover:bg-secondary text-white font-bold py-3 px-8 rounded-lg transition">
                        Print Ticket
                    </button>
                    <a href="<?= SITE_URL ?>/bookings/my_bookings.php" class="bg-gray-600 hover:bg-gray-800 text-white font-bold py-3 px-8 rounded-lg transition">
                        My Bookings
                    </a>
                </div>
            </div>
        </div>

        <div class="text-center mt-8 text-gray-600 text-sm no-print">
            Thank you for booking with <?= htmlspecialchars(SITE_NAME) ?>. Have a safe journey!
        </div>
    </div>
</body>
</html>