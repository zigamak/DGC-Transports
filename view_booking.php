<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'libs/phpqrcode/qrlib.php';

// Require login
requireLogin();

// Get current user
$user = $_SESSION['user'];

// Initialize variables
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$booking = null;
$error = '';
$success = '';
$qr_code_path = '';

// Fetch booking details
if ($booking_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            vt.type as vehicle_type,
            v.vehicle_number,
            v.driver_name,
            ts.departure_time,
            ts.arrival_time,
            pc.name as pickup_city,
            dc.name as dropoff_city,
            tt.price
        FROM bookings b
        JOIN trip_templates tt ON b.template_id = tt.id
        JOIN cities pc ON tt.pickup_city_id = pc.id
        JOIN cities dc ON tt.dropoff_city_id = dc.id
        JOIN time_slots ts ON tt.time_slot_id = ts.id
        JOIN vehicles v ON tt.vehicle_id = v.id
        JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
        WHERE b.id = ? AND (b.user_id = ? OR b.email = ?)
    ");
    $stmt->bind_param("iis", $booking_id, $user['id'], $user['email']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        $error = 'No booking found or you do not have access to this booking.';
    } else {
        // Generate QR code for PNR
        $qr_code_dir = 'qrcodes/';
        if (!is_dir($qr_code_dir)) {
            mkdir($qr_code_dir, 0755, true);
        }
        $qr_code_path = $qr_code_dir . 'pnr_' . $booking['pnr'] . '.png';
        QRcode::png($booking['pnr'], $qr_code_path, QR_ECLEVEL_L, 4);
    }
}

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $pnr_to_cancel = '';

    // First, get the PNR and template ID for the booking
    $stmt = $conn->prepare("SELECT pnr, template_id, trip_date FROM bookings WHERE id = ? AND (user_id = ? OR email = ?)");
    $stmt->bind_param("iis", $booking_id, $user['id'], $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking_to_cancel = $result->fetch_assoc();
    $stmt->close();

    if ($booking_to_cancel) {
        $pnr_to_cancel = $booking_to_cancel['pnr'];
        $template_id = $booking_to_cancel['template_id'];
        $trip_date = $booking_to_cancel['trip_date'];

        // Start a transaction for atomicity
        $conn->begin_transaction();
        $success_flag = false;

        try {
            // Update booking status
            $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'cancelled' WHERE id = ? AND status != 'cancelled'");
            $stmt->bind_param("i", $booking_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Update booked_seats in trip_instances
                $stmt2 = $conn->prepare("UPDATE trip_instances SET booked_seats = booked_seats - 1 WHERE template_id = ? AND trip_date = ?");
                $stmt2->bind_param("is", $template_id, $trip_date);
                $stmt2->execute();
                $stmt2->close();
                
                $success = 'Booking cancelled successfully. A refund will be processed to your original payment method.';
                $success_flag = true;
            } else {
                $error = 'Unable to cancel booking. It may already be cancelled or invalid.';
                $conn->rollback();
            }
            $stmt->close();

            if ($success_flag) {
                $conn->commit();
                // Re-fetch booking details to display the updated status
                $stmt = $conn->prepare("
                    SELECT 
                        b.*,
                        vt.type as vehicle_type,
                        v.vehicle_number,
                        v.driver_name,
                        ts.departure_time,
                        ts.arrival_time,
                        pc.name as pickup_city,
                        dc.name as dropoff_city,
                        tt.price
                    FROM bookings b
                    JOIN trip_templates tt ON b.template_id = tt.id
                    JOIN cities pc ON tt.pickup_city_id = pc.id
                    JOIN cities dc ON tt.dropoff_city_id = dc.id
                    JOIN time_slots ts ON tt.time_slot_id = ts.id
                    JOIN vehicles v ON tt.vehicle_id = v.id
                    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
                    WHERE b.pnr = ? AND (b.user_id = ? OR b.email = ?)
                ");
                $stmt->bind_param("sis", $pnr_to_cancel, $user['id'], $user['email']);
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "An error occurred during cancellation: " . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking ID or you do not have access to this booking.';
    }
}

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Booking - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        :root {
            --primary-blue: #2563eb;
            --secondary-blue: #1e40af;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
        }
        body {
            background: linear-gradient(to bottom right, var(--gray), #e5e7eb);
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--secondary-blue);
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
        .error-message {
            color: #dc2626;
            font-size: 0.9rem;
        }
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
        }
        .qr-code-img {
            max-width: 120px;
            border: 2px solid var(--black);
            border-radius: 8px;
        }
        @media (max-width: 640px) {
            .text-3xl { font-size: 1.5rem; }
            .text-2xl { font-size: 1.25rem; }
            .text-xl { font-size: 1.125rem; }
            .p-8 { padding: 1rem; }
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
            .gradient-bg { background: var(--primary-blue); }
            .min-h-screen, .py-8, .max-w-4xl, .px-4 { padding: 0; margin: 0; }
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="card p-4 sm:p-8 mb-8 no-print">
            <h1 class="text-2xl sm:text-3xl font-bold mb-4 sm:mb-6">
                <i class="fas fa-ticket-alt text-primary-blue mr-2"></i>View Booking Details
            </h1>
            <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base">View and manage your booking details below.</p>

            <?php if ($error): ?>
                <p class="error-message mb-4"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success-message mb-4"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($booking): ?>
            <div class="card mb-8 ticket-card">
                <div class="gradient-bg text-white p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold">DGC Transports</h2>
                            <p class="text-blue-100 text-sm sm:text-base">Electronic Ticket</p>
                        </div>
                        <div class="text-center sm:text-right mt-2 sm:mt-0">
                            <div class="text-xs sm:text-sm text-blue-100">PNR</div>
                            <div class="text-lg sm:text-2xl font-bold"><?= htmlspecialchars($booking['pnr']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold mb-4 flex items-center">
                                <i class="fas fa-route text-primary-blue mr-2 sm:mr-3"></i>
                                Trip Details
                            </h3>
                            <div class="space-y-2 sm:space-y-3 text-sm sm:text-base">
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
                            <div class="mt-4 sm:mt-6">
                                <h4 class="font-semibold mb-2 text-sm sm:text-base">Your Seat:</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span class="bg-primary-blue text-white px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                                        Seat <?= htmlspecialchars($booking['seat_number']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg sm:text-xl font-bold mb-4 flex items-center">
                                <i class="fas fa-user text-primary-blue mr-2 sm:mr-3"></i>
                                Passenger Information
                            </h3>
                            <div class="space-y-2 sm:space-y-3 text-sm sm:text-base">
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
                            <div class="mt-4 sm:mt-6">
                                <h4 class="font-semibold mb-2 text-sm sm:text-base">Payment Details:</h4>
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

                    <div class="border-t pt-4 sm:pt-6 mt-4 sm:mt-6">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex flex-col sm:flex-row justify-between items-center">
                                <div>
                                    <span class="text-base sm:text-lg font-semibold">Total Seats: 1</span>
                                    <span class="text-gray-600 ml-0 sm:ml-4 text-sm">× ₦<?= number_format($booking['price'], 0) ?> each</span>
                                </div>
                                <div class="text-lg sm:text-2xl font-bold text-primary-blue mt-2 sm:mt-0">
                                    ₦<?= number_format($booking['total_amount'], 0) ?>
                                </div>
                            </div>
                            <?php if ($qr_code_path): ?>
                                <div class="mt-4 text-center">
                                    <h4 class="font-semibold mb-2 text-sm sm:text-base">Booking QR Code</h4>
                                    <img src="<?= SITE_URL . '/qrcodes/pnr_' . htmlspecialchars($booking['pnr']) . '.png' ?>" alt="PNR QR Code" class="qr-code-img mx-auto">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 sm:p-6 mb-8 no-print">
                <h3 class="text-base sm:text-lg font-bold text-yellow-800 mb-3 flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 sm:mr-3"></i>
                    Important Information
                </h3>
                <ul class="space-y-2 text-yellow-800 text-sm sm:text-base">
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

            <div class="flex flex-col sm:flex-row gap-4 justify-center no-print">
                <button onclick="window.print()" class="btn-primary flex items-center justify-center text-sm sm:text-base">
                    <i class="fas fa-print mr-2"></i>Print Ticket
                </button>
                <button onclick="downloadPDF()" class="btn-primary flex items-center justify-center text-sm sm:text-base">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </button>
                <?php if ($booking['status'] !== 'cancelled' && strtotime($booking['trip_date']) > time()): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <button type="submit" class="bg-red-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-red-700 transition-colors duration-200 flex items-center justify-center text-sm sm:text-base" onclick="return confirm('Are you sure you want to cancel this booking?');">
                            <i class="fas fa-times mr-2"></i>Cancel Booking
                        </button>
                    </form>
                <?php endif; ?>
                <a href="dashboard.php" class="bg-green-600 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-xl hover:bg-green-700 transition-colors duration-200 flex items-center justify-center text-sm sm:text-base">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>

            <div class="text-center mt-8 text-gray-600 no-print">
                <p class="mb-2 text-sm sm:text-base">Need help? Contact us:</p>
                <p class="font-semibold text-sm sm:text-base">Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?></p>
                <p class="text-xs sm:text-sm mt-2">Your PNR: <span class="font-bold text-primary-blue"><?= htmlspecialchars($booking['pnr']) ?></span></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 10;
            let y = 10;

            // Add logo or title
            doc.setFontSize(20);
            doc.setTextColor(37, 99, 235);
            doc.text('DGC Transports - Booking Confirmation', margin, y);
            y += 10;

            // PNR
            doc.setFontSize(12);
            doc.setTextColor(0);
            doc.text('PNR: <?= htmlspecialchars($booking['pnr'] ?? '') ?>', margin, y);
            y += 10;

            // Trip Details
            doc.setFontSize(16);
            doc.text('Trip Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Route: <?= htmlspecialchars($booking['pickup_city'] ?? '') ?> → <?= htmlspecialchars($booking['dropoff_city'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Date: <?= formatDate($booking['trip_date'] ?? '', 'j, M, Y') ?>', margin, y);
            y += 7;
            doc.text('Departure Time: <?= formatTime($booking['departure_time'] ?? '', 'g:i A') ?>', margin, y);
            y += 7;
            doc.text('Arrival Time: <?= $booking['arrival_time'] ? formatTime($booking['arrival_time'], 'g:i A') : '-' ?>', margin, y);
            y += 7;
            doc.text('Vehicle: <?= htmlspecialchars($booking['vehicle_type'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Vehicle Number: <?= htmlspecialchars($booking['vehicle_number'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Driver: <?= htmlspecialchars($booking['driver_name'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Seat: <?= htmlspecialchars($booking['seat_number'] ?? '') ?>', margin, y);
            y += 10;

            // Passenger Details
            doc.setFontSize(16);
            doc.text('Passenger Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Name: <?= htmlspecialchars($booking['passenger_name'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Email: <?= htmlspecialchars($booking['email'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Phone: <?= htmlspecialchars($booking['phone'] ?? '') ?>', margin, y);
            y += 7;
            doc.text('Status: <?= ucfirst(htmlspecialchars($booking['status'] ?? '')) ?>', margin, y);
            y += 10;

            // Payment Details
            doc.setFontSize(16);
            doc.text('Payment Details', margin, y);
            y += 10;
            doc.setFontSize(12);
            doc.text('Amount Paid: ₦<?= number_format($booking['total_amount'] ?? 0, 0) ?>', margin, y);
            y += 7;
            doc.text('Payment Status: <?= ucfirst(htmlspecialchars($booking['payment_status'] ?? '')) ?>', margin, y);
            y += 10;

            // Footer
            doc.setFontSize(10);
            doc.setTextColor(100);
            doc.text('Contact: Email: <?= htmlspecialchars(SITE_EMAIL) ?> | Phone: <?= htmlspecialchars(SITE_PHONE) ?>', margin, doc.internal.pageSize.getHeight() - 10);

            // Save PDF
            doc.save('DGC_Transport_Booking_<?= htmlspecialchars($booking['pnr'] ?? 'ticket') ?>.pdf');
        }
    </script>
<?php require_once 'templates/footer.php'; ?>
</body>
</html>