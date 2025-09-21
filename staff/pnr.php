<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce staff-only access
requireRole('staff', '/login.php');

// Initialize variables
$pnr = '';
$booking = null;
$error = '';
$success = '';
$qr_code_path = '';

// Handle PNR search (manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $pnr = sanitizeInput($_POST['pnr']);
    if (!empty($pnr)) {
        // Query to get booking details with all related information
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
            WHERE b.pnr = ?
        ");
        $stmt->bind_param("s", $pnr);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            $error = 'No booking found with the provided PNR.';
        }
    } else {
        $error = 'Please enter a valid PNR.';
    }
}

// Handle status update to "Boarded"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_boarded' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $pnr_to_update = trim($_POST['pnr'] ?? '');
    
    $stmt = $conn->prepare("UPDATE bookings SET status = 'has boarded' WHERE id = ? AND status IN ('confirmed', 'pending')");
    $stmt->bind_param("i", $booking_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = 'Passenger has been marked as boarded successfully!';
        
        // Re-fetch booking details to show updated status
        $stmt2 = $conn->prepare("
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
            WHERE b.pnr = ?
        ");
        $stmt2->bind_param("s", $pnr_to_update);
        $stmt2->execute();
        $booking = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $pnr = $pnr_to_update;
    } else {
        $error = 'Failed to update boarding status. Passenger may already be boarded or booking not found.';
    }
    $stmt->close();
}

// Handle general status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = trim($_POST['new_status'] ?? '');
    $pnr_to_update = trim($_POST['pnr'] ?? '');
    $valid_statuses = ['confirmed', 'has boarded', 'arrived', 'cancelled'];
    
    if (!in_array($new_status, $valid_statuses)) {
        $error = 'Invalid status selected.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $booking_id);
        if ($stmt->execute()) {
            $success = 'Booking status updated to ' . htmlspecialchars(ucfirst($new_status)) . '.';
            
            // Re-fetch booking details
            $stmt2 = $conn->prepare("
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
                WHERE b.pnr = ?
            ");
            $stmt2->bind_param("s", $pnr_to_update);
            $stmt2->execute();
            $booking = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $pnr = $pnr_to_update;
        } else {
            $error = 'Failed to update booking status.';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff PNR Management - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #ffffff; /* Solid white background */
            --light-gray: #e5e7eb;
            --accent-blue: #3b82f6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray); /* Solid white background */
            color: #1a1a1a; /* Adjusted text color for contrast */
            min-height: 100vh;
            overscroll-behavior: none;
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(to right, #10b981, #059669);
            color: var(--white);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-success:hover {
            background: linear-gradient(to right, #059669, #047857);
            transform: translateY(-2px);
        }
        
        .btn-stop {
            background: #f8b400;
            color: #0f2027;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 20px;
            display: none; /* Initially hidden */
        }
        
        .btn-stop:hover {
            background: #e0a800;
        }
        
        .input-field {
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
        }
        
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        
        .success-message {
            color: #10b981;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        
        .scanner-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
        }
        
        #video {
            width: 100%;
            height: auto;
            border: 4px solid #f8b400;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(248, 180, 0, 0.7);
            display: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-boarded {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-arrived {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }

        #scan-overlay {
            position: absolute;
            top: 20%;
            left: 20%;
            right: 20%;
            bottom: 20%;
            border: 3px solid #f8b400;
            border-radius: 8px;
            pointer-events: none;
            background: rgba(248, 180, 0, 0.2);
            box-sizing: border-box;
            display: none;
        }
        
        #scan-overlay::before {
            content: 'Place QR Code Here';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: #f8b400;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 0 20px rgba(248, 180, 0, 0.5);
            color: #0f2027;
            position: relative;
        }
        
        .modal-content p {
            font-size: 24px;
            line-height: 1.5;
            margin: 10px 0;
        }
        
        .modal-content .status.success {
            font-size: 28px;
            font-weight: bold;
            color: #4caf50;
        }
        
        .modal-content .status.warning {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .modal-content .status.failed {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .modal-content .passenger-name,
        .modal-content .route-details,
        .modal-content .vehicle-details,
        .modal-content .seat-number {
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-content button {
            background: #f8b400;
            color: #0f2027;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        
        .modal-content button:hover {
            background: #e0a800;
        }
        
        #start-camera-button {
            background: #f8b400;
            color: #0f2027;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 20px;
        }
        
        #start-camera-button:hover {
            background: #e0a800;
        }
        
        #start-button-container {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .modal-content {
                padding: 20px;
            }
            .modal-content p {
                font-size: 20px;
            }
            .modal-content .status.success,
            .modal-content .status.warning,
            .modal-content .status.failed {
                font-size: 24px;
            }
            .modal-content .passenger-name,
            .modal-content .route-details,
            .modal-content .vehicle-details,
            .modal-content .seat-number {
                font-size: 18px;
            }
            .modal-content button, #start-camera-button, .btn-stop {
                padding: 10px 20px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="flex-1 ml-0 md:ml-64 p-4 sm:p-6 lg:p-10">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="card p-6 sm:p-8 mb-8">
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-qrcode text-red-600 mr-3"></i>Staff PNR Management
                </h1>
                <p class="text-gray-600 text-lg">Search by PNR or scan QR code to manage passenger bookings</p>
            </div>

            <!-- Search Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Manual PNR Search -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-search text-red-600 mr-2"></i>Manual PNR Search
                    </h2>
                    
                    <?php if ($error): ?>
                        <p class="error-message"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <p class="success-message"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></p>
                    <?php endif; ?>

                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="search">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <input type="text" name="pnr" value="<?= htmlspecialchars($pnr) ?>" class="input-field flex-1" placeholder="Enter PNR (e.g., DGC123456)" required>
                            <button type="submit" class="btn-primary flex items-center justify-center">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- QR Code Scanner -->
                <div class="card p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-camera text-red-600 mr-2"></i>QR Code Scanner
                    </h2>
                    <div class="text-center">
                        <div id="start-button-container">
                            <button id="start-camera-button">Start Scanning</button>
                        </div>
                        <div class="scanner-container">
                            <video id="video" autoplay playsinline></video>
                            <div id="scan-overlay"></div>
                            <button id="stop-camera-button" class="btn-stop">Stop Scanning</button>
                        </div>
                        <canvas id="canvas" style="display:none;"></canvas>
                        <div id="output">Press 'Start Scanning' to begin...</div>
                    </div>
                </div>
            </div>

            <!-- Scan Result Modal -->
            <div id="scan-modal" class="modal">
                <div class="modal-content">
                    <p id="modal-status" class="status"></p>
                    <p id="modal-passenger-name" class="passenger-name"></p>
                    <p id="modal-route-details" class="route-details"></p>
                    <p id="modal-vehicle-details" class="vehicle-details"></p>
                    <p id="modal-seat-number" class="seat-number"></p>
                    <button id="modal-close">Scan Next QR Code</button>
                </div>
            </div>

            <!-- Booking Details -->
            <?php if ($booking): ?>
                <div class="card mb-8">
                    <!-- Header with status -->
                    <div class="gradient-bg text-white p-6 rounded-t-2xl">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold"><?= SITE_NAME ?></h2>
                                <p class="text-red-100">Passenger Booking Details</p>
                            </div>
                            <div class="mt-4 sm:mt-0 text-center sm:text-right">
                                <div class="text-sm text-red-100">PNR</div>
                                <div class="text-2xl font-bold"><?= htmlspecialchars($booking['pnr']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Content -->
                    <div class="p-6">
                        <!-- Status and Action Buttons -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 p-4 bg-gray-50 rounded-lg">
                            <div class="mb-4 sm:mb-0">
                                <span class="text-gray-600 text-sm">Current Status:</span>
                                <span class="status-badge status-<?= $booking['status'] === 'has boarded' ? 'boarded' : ($booking['status'] === 'confirmed' ? 'confirmed' : ($booking['status'] === 'arrived' ? 'arrived' : ($booking['status'] === 'cancelled' ? 'cancelled' : 'pending'))) ?> ml-2">
                                    <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                </span>
                            </div>
                            
                            <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'pending'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="mark_boarded">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <input type="hidden" name="pnr" value="<?= htmlspecialchars($booking['pnr']) ?>">
                                    <button type="submit" class="btn-success flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>Mark as Boarded
                                    </button>
                                </form>
                            <?php elseif ($booking['status'] === 'has boarded'): ?>
                                <div class="flex items-center text-green-600">
                                    <i class="fas fa-check-circle mr-2 text-xl"></i>
                                    <span class="font-semibold">Passenger has boarded</span>
                                </div>
                            <?php elseif ($booking['status'] === 'arrived'): ?>
                                <div class="flex items-center text-blue-600">
                                    <i class="fas fa-flag-checkered mr-2 text-xl"></i>
                                    <span class="font-semibold">Journey completed</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Trip and Passenger Details Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Trip Details -->
                            <div>
                                <h3 class="text-xl font-bold mb-4 flex items-center">
                                    <i class="fas fa-route text-red-600 mr-3"></i>Trip Details
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Route:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-semibold"><?= formatDate($booking['trip_date'], 'j M, Y') ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Departure:</span>
                                        <span class="font-semibold"><?= formatTime($booking['departure_time'], 'g:i A') ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Arrival:</span>
                                        <span class="font-semibold"><?= $booking['arrival_time'] ? formatTime($booking['arrival_time'], 'g:i A') : 'N/A' ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Vehicle:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['vehicle_type']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Vehicle No:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['vehicle_number']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Driver:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['driver_name']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-gray-600">Seat:</span>
                                        <span class="font-semibold bg-red-100 text-red-800 px-2 py-1 rounded"><?= htmlspecialchars($booking['seat_number']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Passenger Details -->
                            <div>
                                <h3 class="text-xl font-bold mb-4 flex items-center">
                                    <i class="fas fa-user text-red-600 mr-3"></i>Passenger Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Phone:</span>
                                        <span class="font-semibold"><?= htmlspecialchars($booking['phone']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Booking Date:</span>
                                        <span class="font-semibold"><?= formatDate($booking['booking_date'], 'j M, Y g:i A') ?></span>
                                    </div>
                                </div>

                                <!-- Payment Details -->
                                <h4 class="text-lg font-bold mt-6 mb-3 flex items-center">
                                    <i class="fas fa-credit-card text-red-600 mr-2"></i>Payment Details
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Amount:</span>
                                        <span class="font-semibold text-green-600">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-gray-600">Payment Status:</span>
                                        <span class="font-semibold"><?= ucfirst(htmlspecialchars($booking['payment_status'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Status Management -->
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-lg font-bold mb-4">Advanced Status Management</h4>
                            <form method="POST" class="flex flex-col sm:flex-row gap-4">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="pnr" value="<?= htmlspecialchars($booking['pnr']) ?>">
                                <select name="new_status" class="input-field flex-1" required>
                                    <option value="">Select new status</option>
                                    <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="has boarded" <?= $booking['status'] === 'has boarded' ? 'selected' : '' ?>>Has Boarded</option>
                                    <option value="arrived" <?= $booking['status'] === 'arrived' ? 'selected' : '' ?>>Arrived</option>
                                    <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i>Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elements = {
                video: document.getElementById('video'),
                canvas: document.getElementById('canvas'),
                output: document.getElementById('output'),
                context: document.getElementById('canvas').getContext('2d', { willReadFrequently: true }),
                modal: document.getElementById('scan-modal'),
                modalStatus: document.getElementById('modal-status'),
                modalPassengerName: document.getElementById('modal-passenger-name'),
                modalRouteDetails: document.getElementById('modal-route-details'),
                modalVehicleDetails: document.getElementById('modal-vehicle-details'),
                modalSeatNumber: document.getElementById('modal-seat-number'),
                modalClose: document.getElementById('modal-close'),
                startCameraButton: document.getElementById('start-camera-button'),
                stopCameraButton: document.getElementById('stop-camera-button'),
                scanOverlay: document.getElementById('scan-overlay'),
                startButtonContainer: document.getElementById('start-button-container')
            };

            let canScan = true;
            let audioContext;
            let stream = null;
            let lastScanTime = 0;
            const minScanInterval = 2000;

            if (elements.startCameraButton) {
                elements.startCameraButton.addEventListener('click', initCamera);
            }
            
            if (elements.stopCameraButton) {
                elements.stopCameraButton.addEventListener('click', stopCamera);
            }
            
            if (elements.modalClose) {
                elements.modalClose.addEventListener('click', hideModal);
            }

            document.addEventListener('click', () => {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
            }, { once: true });

            function beep(frequency = 800, duration = 100, type = 'success') {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(
                    type === 'success' ? frequency : frequency * 0.8,
                    audioContext.currentTime
                );
                
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                oscillator.start();
                oscillator.stop(audioContext.currentTime + duration / 1000);
            }

            function playFeedback(success) {
                if ('vibrate' in navigator) {
                    navigator.vibrate(success ? [100] : [100, 50, 100]);
                }
                if (success) {
                    beep(1000, 150, 'success');
                } else {
                    beep(800, 100, 'error');
                }
            }

            function showModal(status, passengerName, routeDetails, vehicleDetails, seatNumber) {
                elements.modalStatus.textContent = status;
                elements.modalStatus.className = 'status ' + (
                    status === 'Boarded Successfully' ? 'success' :
                    status === 'Passenger Already Boarded' ? 'warning' : 'failed'
                );
                elements.modalPassengerName.textContent = passengerName ? `Passenger: ${passengerName}` : '';
                elements.modalRouteDetails.textContent = routeDetails ? `Route: ${routeDetails}` : '';
                elements.modalVehicleDetails.textContent = vehicleDetails ? `Vehicle: ${vehicleDetails}` : '';
                elements.modalSeatNumber.textContent = seatNumber ? `Seat: ${seatNumber}` : '';
                elements.modal.style.display = 'flex';
                canScan = false;
            }

            function hideModal() {
                elements.modal.style.display = 'none';
                elements.output.innerHTML = 'Position QR code within the rectangle...';
                canScan = true;
                lastScanTime = Date.now();
            }

            async function recordScan(qrCodeData) {
                if (!canScan) return;
                const currentTime = Date.now();
                if (currentTime - lastScanTime < minScanInterval) {
                    return;
                }

                canScan = false;
                lastScanTime = currentTime;

                elements.output.innerHTML = 'Processing QR code...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'scanQR');
                    formData.append('qrCode', qrCodeData);
                    
                    const response = await fetch('qr_processor.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        playFeedback(true);
                        elements.output.innerHTML = `<span class="success">Boarded Successfully</span><br>Boarding Time: ${result.boarding_time || new Date().toLocaleString()}`;
                        showModal(
                            'Boarded Successfully',
                            result.passenger_name,
                            `${result.pickup_city} → ${result.dropoff_city}`,
                            `${result.vehicle_type} (${result.vehicle_number})`,
                            result.seat_number
                        );
                    } else if (result.status === 'warning') {
                        playFeedback(false);
                        elements.output.innerHTML = `<span class="warning">Passenger Already Boarded</span><br>Boarding Time: ${result.boarding_time}`;
                        showModal(
                            'Passenger Already Boarded',
                            result.passenger_name,
                            `${result.pickup_city} → ${result.dropoff_city}`,
                            `${result.vehicle_type} (${result.vehicle_number})`,
                            result.seat_number
                        );
                    } else {
                        playFeedback(false);
                        elements.output.innerHTML = `<span class="failed">${result.message}</span>`;
                        showModal(result.message, null, null, null, null);
                    }
                    
                } catch (error) {
                    console.error('Error during fetch or processing:', error);
                    playFeedback(false);
                    elements.output.innerHTML = `<span class="failed">Scanner Error: ${error.message}</span>`;
                    showModal('Scanner Error: ' + error.message, null, null, null, null);
                }
            }

            function processFrame() {
                if (!elements.video.srcObject || elements.video.readyState !== elements.video.HAVE_ENOUGH_DATA) {
                    requestAnimationFrame(processFrame);
                    return;
                }

                if (!canScan || elements.modal.style.display === 'flex') {
                    requestAnimationFrame(processFrame);
                    return;
                }

                const videoWidth = elements.video.videoWidth;
                const videoHeight = elements.video.videoHeight;
                
                const roiX = videoWidth * 0.2;
                const roiY = videoHeight * 0.2;
                const roiWidth = videoWidth * 0.6;
                const roiHeight = videoHeight * 0.6;

                elements.canvas.width = roiWidth;
                elements.canvas.height = roiHeight;
                
                elements.context.drawImage(
                    elements.video,
                    roiX, roiY, roiWidth, roiHeight,
                    0, 0, roiWidth, roiHeight
                );

                const imageData = elements.context.getImageData(0, 0, roiWidth, roiHeight);
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "attemptBoth"
                });

                if (code) {
                    console.log("Scanned QR Code:", code.data);
                    recordScan(code.data);
                } else {
                    requestAnimationFrame(processFrame);
                }
            }

            async function initCamera() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        }
                    });
                    elements.video.srcObject = stream;
                    await elements.video.play();
                    
                    // Show video, overlay, and stop button; hide start button
                    elements.video.style.display = 'block';
                    elements.scanOverlay.style.display = 'block';
                    elements.startCameraButton.style.display = 'none';
                    elements.startButtonContainer.style.display = 'none';
                    elements.stopCameraButton.style.display = 'block';
                    
                    elements.output.textContent = 'Camera initialized. Position QR code...';
                    requestAnimationFrame(processFrame);

                } catch (error) {
                    console.error('Detailed camera access error:', error.name, error.message);
                    let errorMessage = 'Error accessing camera. Please ensure permissions are granted and try refreshing the page.';
                    if (error.name === 'NotAllowedError') {
                        errorMessage = 'Camera access denied. Please allow camera access in your browser settings.';
                    } else if (error.name === 'NotFoundError') {
                        errorMessage = 'No camera found. Please ensure a camera is connected and available.';
                    } else if (error.name === 'NotReadableError') {
                        errorMessage = 'Camera is in use by another application. Please close other apps using the camera.';
                    }
                    elements.output.textContent = errorMessage;
                    showModal(errorMessage, null, null, null, null);
                }
            }

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                elements.video.srcObject = null;
                elements.video.style.display = 'none';
                elements.scanOverlay.style.display = 'none';
                elements.stopCameraButton.style.display = 'none';
                elements.startCameraButton.style.display = 'block';
                elements.startButtonContainer.style.display = 'block';
                elements.output.innerHTML = "Press 'Start Scanning' to begin...";
                canScan = true;
            }

            window.addEventListener('unload', () => {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
            });

            // Auto-focus on PNR input
            const pnrInput = document.querySelector('input[name="pnr"]');
            if (pnrInput && !pnrInput.value) {
                pnrInput.focus();
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'q') {
                    e.preventDefault();
                    elements.startCameraButton.click();
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    elements.stopCameraButton.click();
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    document.querySelector('input[name="pnr"]').focus();
                }
            });
        });
    </script>
</body>
</html>