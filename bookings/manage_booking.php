<?php
// bookings/manage_booking.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../libs/phpqrcode/qrlib.php'; // Include phpqrcode library

// Initialize variables
$pnr = '';
$booking = null;
$error = '';
$success = '';
$qr_code_path = '';

// Handle PNR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $pnr = sanitizeInput($_POST['pnr']);
    if (!empty($pnr)) {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   t.trip_date, t.departure_time, t.arrival_time, t.vehicle_number, t.driver_name, t.price, t.vehicle_type_id,
                   pc.name as pickup_city, dc.name as dropoff_city, vt.type as vehicle_type
            FROM bookings b
            JOIN trips t ON b.trip_id = t.id
            JOIN cities pc ON t.pickup_city_id = pc.id
            JOIN cities dc ON t.dropoff_city_id = dc.id
            JOIN vehicle_types vt ON t.vehicle_type_id = vt.id
            WHERE b.pnr = ?
        ");
        $stmt->bind_param("s", $pnr);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            $error = 'No booking found with the provided PNR.';
        } else {
            // Fetch selected seats
            $stmt = $conn->prepare("SELECT seat_number FROM seat_bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $booking['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $booking['selected_seats'] = [];
            while ($row = $result->fetch_assoc()) {
                $booking['selected_seats'][] = $row['seat_number'];
            }
            $stmt->close();

            // Generate QR code for PNR
            $qr_code_dir = '../qrcodes/';
            if (!is_dir($qr_code_dir)) {
                mkdir($qr_code_dir, 0755, true);
            }
            $qr_code_path = $qr_code_dir . 'pnr_' . $pnr . '.png';
            QRcode::png($pnr, $qr_code_path, QR_ECLEVEL_L, 4);
        }
    } else {
        $error = 'Please enter a valid PNR.';
    }
}

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status != 'cancelled'");
    $stmt->bind_param("i", $booking_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = 'Booking cancelled successfully.';
        $booking['status'] = 'cancelled';
    } else {
        $error = 'Unable to cancel booking. It may already be cancelled or invalid.';
    }
    $stmt->close();
}

require_once '../templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Booking - <?= SITE_NAME ?></title>
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
        :root {
            --primary-red: #dc2626;
            --secondary-red: #991b1b;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
        }
        body {
            background: linear-gradient(to bottom right, var(--white), var(--gray));
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
        }
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: var(--primary-red);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--secondary-red);
        }
        .btn-secondary {
            background: var(--gray);
            color: var(--black);
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .input-field {
            border: 1px solid var(--black);
            border-radius: 6px;
            padding: 8px;
            width: 100%;
            color: var(--black);
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 5px rgba(220, 38, 38, 0.3);
        }
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
        }
        .qr-code-img {
            max-width: 150px;
            border: 2px solid var(--black);
            border-radius: 8px;
        }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .gradient-bg { background: var(--primary-red); }
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <!-- PNR Search Form -->
        <div class="card p-8 mb-8">
            <h1 class="text-3xl font-bold mb-6">
                <i class="fas fa-ticket-alt text-primary mr-2"></i>Manage Your Booking
            </h1>
            <p class="text-gray-600 mb-6">Enter your PNR to view and manage your booking details.</p>

            <?php if ($error): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success-message mb-4"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>

            <form method="POST" class="mb-6">
                <input type="hidden" name="action" value="search">
                <div class="flex flex-col sm:flex-row gap-4">
                    <input type="text" name="pnr" value="<?= htmlspecialchars($pnr) ?>" class="input-field" placeholder="Enter your PNR" required>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search mr-2"></i>Find Booking
                    </button>
                </div>
            </form>
        </div>

        <!-- Booking Details (if found) -->
        <?php if ($booking): ?>
            <div class="card mb-8">
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
                                    <span class="font-semibold"><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Date:</span>
                                    <span class="font-semibold"><?= formatDate($booking['trip_date'], 'j, M, Y') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Departure Time:</span>
                                    <span class="font-semibold"><?= formatTime($booking['departure_time'], 'g:i A') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Arrival Time:</span>
                                    <span class="font-semibold"><?= $booking['arrival_time'] ? formatTime($booking['arrival_time'], 'g:i A') : '-' ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['vehicle_type']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Vehicle Number:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['vehicle_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Driver:</span>
                                    <span class="font-semibold"><?= htmlspecialchars($booking['driver_name']) ?></span>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Your Seats:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($booking['selected_seats'] as $seat): ?>
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
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-semibold <?= $booking['status'] === 'confirmed' ? 'text-green-600' : ($booking['status'] === 'cancelled' ? 'text-red-600' : 'text-yellow-600') ?>">
                                        <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h4 class="font-semibold mb-2">Payment Details:</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Amount Paid:</span>
                                        <span class="font-semibold text-green-600">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment Status:</span>
                                        <span class="font-semibold"><?= ucfirst(htmlspecialchars($booking['payment_status'])) ?></span>
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
                                    <span class="text-gray-600 ml-4">× ₦<?= number_format($booking['price'], 0) ?> each</span>
                                </div>
                                <div class="text-2xl font-bold text-primary">
                                    ₦<?= number_format($booking['total_amount'], 0) ?>
                                </div>
                            </div>
                            <?php if ($qr_code_path): ?>
                                <div class="mt-4 text-center">
                                    <h4 class="font-semibold mb-2">Booking QR Code</h4>
                                    <img src="<?= SITE_URL . '/qrcodes/pnr_' . htmlspecialchars($pnr) . '.png' ?>" alt="PNR QR Code" class="qr-code-img mx-auto">
                                </div>
                            <?php endif; ?>
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
                        Please arrive at the terminal at least 30 minutes before departure.
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Bring a valid ID for verification.
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Keep your PNR (<?= htmlspecialchars($booking['pnr']) ?>) for reference.
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                        Contact support for further assistance.
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center no-print">
                <button onclick="window.print()" class="btn-primary flex items-center justify-center">
                    <i class="fas fa-print mr-2"></i>Print Ticket
                </button>
                <button onclick="downloadPDF()" class="btn-secondary flex items-center justify-center">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </button>
                <?php if ($booking['status'] !== 'cancelled' && strtotime($booking['trip_date']) > time()): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <button type="submit" class="bg-red-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-red-700 transition-colors duration-200 flex items-center justify-center" onclick="return confirm('Are you sure you want to cancel this booking?');">
                            <i class="fas fa-times mr-2"></i>Cancel Booking
                        </button>
                    </form>
                <?php endif; ?>
                <a href="search_trips.php" class="bg-green-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-green-700 transition-colors duration-200 flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i>Book Another Trip
                </a>
            </div>

            <!-- Contact Information -->
            <div class="text-center mt-8 text-gray-600">
                <p class="mb-2">Need help? Contact us:</p>
                <p class="font-semibold">Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?></p>
                <p class="text-sm mt-2">Your PNR: <span class="font-bold text-primary"><?= htmlspecialchars($booking['pnr']) ?></span></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function downloadPDF() {
            window.print(); // Placeholder for PDF generation
        }
    </script>
<?php require_once '../templates/footer.php'; ?>
</body>
</html>