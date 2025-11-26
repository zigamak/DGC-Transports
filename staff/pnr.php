<?php
// staff/pnr.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce staff-only access
requireRole('staff', '/login.php');

// Initialize variables
$search_query = '';
$search_type = 'pnr';
$booking = null;
$error = '';
$success = '';
$qr_code_path = '';

// Get current date for comparison
$current_date = date('Y-m-d');

// Handle search (PNR, Name, or Email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $search_query = sanitizeInput($_POST['search_query']);
    $search_type = sanitizeInput($_POST['search_type']);
    
    if (!empty($search_query)) {
        $query = "
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
            WHERE ";

        if ($search_type === 'pnr') {
            $query .= "b.pnr = ?";
        } elseif ($search_type === 'name') {
            $query .= "b.passenger_name LIKE ?";
            $search_query = "%$search_query%";
        } elseif ($search_type === 'email') {
            $query .= "b.email LIKE ?";
            $search_query = "%$search_query%";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $search_query);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            $error = 'No booking found with the provided ' . htmlspecialchars($search_type) . '.';
        }
    } else {
        $error = 'Please enter a valid search query.';
    }
}

// Handle status update to "Boarded"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_boarded' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $pnr_to_update = trim($_POST['pnr'] ?? '');
    
    // Verify booking date before updating
    $stmt = $conn->prepare("SELECT trip_date FROM bookings WHERE id = ? AND pnr = ?");
    $stmt->bind_param("is", $booking_id, $pnr_to_update);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        $booking_date = date('Y-m-d', strtotime($result['trip_date']));
        if ($booking_date !== $current_date) {
            $error = $booking_date < $current_date 
                ? 'Cannot update: This booking is for a past date (' . formatDate($result['trip_date'], 'j M, Y') . ').'
                : 'Cannot update: This booking is for a future date (' . formatDate($result['trip_date'], 'j M, Y') . ').';
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'boarded' WHERE id = ? AND status IN ('confirmed', 'pending')");
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
                $search_query = $pnr_to_update;
                $search_type = 'pnr';
            } else {
                $error = 'Failed to update boarding status. Passenger may already be boarded or booking not found.';
            }
            $stmt->close();
        }
    } else {
        $error = 'Booking not found.';
    }
}

// Handle general status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = trim($_POST['new_status'] ?? '');
    $pnr_to_update = trim($_POST['pnr'] ?? '');
    $valid_statuses = ['confirmed', 'boarded', 'arrived', 'cancelled'];
    
    // Verify booking date before updating
    $stmt = $conn->prepare("SELECT trip_date FROM bookings WHERE id = ? AND pnr = ?");
    $stmt->bind_param("is", $booking_id, $pnr_to_update);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        $booking_date = date('Y-m-d', strtotime($result['trip_date']));
        if ($booking_date !== $current_date) {
            $error = $booking_date < $current_date 
                ? 'Cannot update: This booking is for a past date (' . formatDate($result['trip_date'], 'j M, Y') . ').'
                : 'Cannot update: This booking is for a future date (' . formatDate($result['trip_date'], 'j M, Y') . ').';
        } else {
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
                    $search_query = $pnr_to_update;
                    $search_type = 'pnr';
                } else {
                    $error = 'Failed to update booking status.';
                }
                $stmt->close();
            }
        }
    } else {
        $error = 'Booking not found.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNR Management - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --black: #111827;
            --white: #ffffff;
            --gray: #f3f4f6;
            --light-gray: #e5e7eb;
            --accent-blue: #2563eb;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray);
            color: var(--black);
            min-height: 100vh;
            margin: 0;
            overscroll-behavior: none;
        }

        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-red), var(--dark-red));
            color: var(--white);
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--dark-red), var(--primary-red));
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(to right, #10b981, #059669);
            color: var(--white);
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-success:hover {
            background: linear-gradient(to right, #059669, #047857);
            transform: translateY(-1px);
        }

        .btn-print {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            color: var(--white);
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-print:hover {
            background: linear-gradient(to right, #2563eb, #1d4ed8);
            transform: translateY(-1px);
        }

        .btn-stop {
            background: #f59e0b;
            color: var(--black);
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 16px;
            display: none;
        }

        .btn-stop:hover {
            background: #d97706;
        }

        .input-field {
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            padding: 10px;
            width: 100%;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.1);
        }

        .error-message {
            color: var(--primary-red);
            font-size: 0.875rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .success-message {
            color: #10b981;
            font-size: 0.875rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .scanner-container {
            position: relative;
            width: 100%;
            max-width: 320px;
            margin: 16px auto;
        }

        #video {
            width: 100%;
            height: auto;
            border: 3px solid #f59e0b;
            border-radius: 8px;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.3);
            display: none;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-boarded { background: #d1fae5; color: #065f46; }
        .status-arrived { background: #e0e7ff; color: #3730a3; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }

        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
        }

        #scan-overlay {
            position: absolute;
            top: 15%;
            left: 15%;
            right: 15%;
            bottom: 15%;
            border: 2px solid #f59e0b;
            border-radius: 6px;
            pointer-events: none;
            background: rgba(245, 158, 11, 0.15);
            display: none;
        }

        #scan-overlay::before {
            content: 'Place QR Code Here';
            position: absolute;
            top: -28px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: #f59e0b;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--white);
            border-radius: 8px;
            padding: 24px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 0 16px rgba(0, 0, 0, 0.2);
            color: var(--black);
        }

        .modal-content p {
            font-size: 1.125rem;
            line-height: 1.5;
            margin: 8px 0;
        }

        .modal-content .status.success { color: #10b981; font-size: 1.5rem; font-weight: 600; }
        .modal-content .status.warning { color: #f59e0b; font-size: 1.5rem; font-weight: 600; }
        .modal-content .status.failed { color: #dc2626; font-size: 1.5rem; font-weight: 600; }
        .modal-content .passenger-name,
        .modal-content .route-details,
        .modal-content .vehicle-details,
        .modal-content .seat-number {
            font-size: 1rem;
            font-weight: 500;
        }

        .modal-content button {
            background: #f59e0b;
            color: var(--black);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 16px;
            transition: background 0.2s ease;
        }

        .modal-content button:hover {
            background: #d97706;
        }

        #start-camera-button {
            background: #f59e0b;
            color: var(--black);
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-top: 16px;
        }

        #start-camera-button:hover {
            background: #d97706;
        }

        #start-button-container {
            width: 100%;
            max-width: 320px;
            margin: 16px auto;
        }

        @media (max-width: 768px) {
            body { padding-top: 64px; }
            .modal-content { padding: 16px; }
            .modal-content p { font-size: 1rem; }
            .modal-content .status.success,
            .modal-content .status.warning,
            .modal-content .status.failed { font-size: 1.25rem; }
            .modal-content .passenger-name,
            .modal-content .route-details,
            .modal-content .vehicle-details,
            .modal-content .seat-number { font-size: 0.875rem; }
            .modal-content button, #start-camera-button, .btn-stop {
                padding: 8px 16px;
                font-size: 0.875rem;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #ticket-section, #ticket-section * {
                visibility: visible;
            }
            #ticket-section {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                margin: 0;
                padding: 20px;
                box-shadow: none;
            }
            .btn-print, .btn-success, .status-update-form {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
        <?php include '../templates/sidebar.php'; ?>
    <?php endif; ?>

    <div class="min-h-screen flex-1 md:ml-64 p-4 sm:p-6 lg:p-8 bg-gray-100">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <p class="text-gray-600 text-sm sm:text-base">Manage passenger bookings by searching PNR, name, or email, or scanning QR codes</p>
            </div>

            <!-- Search and Scanner Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Manual Search -->
                <div class="card p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-search text-red-600 mr-2"></i> Search Booking
                    </h2>
                    
                    <?php if ($error): ?>
                        <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <p class="success-message"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></p>
                    <?php endif; ?>

                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="search">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <select name="search_type" class="input-field w-full sm:w-40" required>
                                <option value="pnr" <?= $search_type === 'pnr' ? 'selected' : '' ?>>PNR</option>
                                <option value="name" <?= $search_type === 'name' ? 'selected' : '' ?>>Name</option>
                                <option value="email" <?= $search_type === 'email' ? 'selected' : '' ?>>Email</option>
                            </select>
                            <input type="text" name="search_query" value="<?= htmlspecialchars($search_query) ?>" class="input-field flex-1" placeholder="Enter PNR, Name, or Email" required>
                            <button type="submit" class="btn-primary flex items-center justify-center gap-2">
                                <i class="fas fa-search"></i>Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- QR Code Scanner -->
                <div class="card p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-camera text-red-600 mr-2"></i> Scan QR Code
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
                        <div id="output" class="text-sm text-gray-600">Press 'Start Scanning' to begin...</div>
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
                <div class="card mb-8" id="ticket-section">
                    <!-- Header -->
                    <div class="gradient-bg text-white p-5 rounded-t-xl">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-semibold"><?= SITE_NAME ?> Booking</h2>
                                <p class="text-red-100 text-sm">Passenger Details</p>
                            </div>
                            <div class="text-center sm:text-right">
                                <div class="text-xs text-red-100">PNR</div>
                                <div class="text-lg font-semibold"><?= htmlspecialchars($booking['pnr']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Content -->
                    <div class="p-5 sm:p-6">
                        <!-- Status and Actions -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 p-4 bg-gray-50 rounded-lg gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-600 text-sm">Status:</span>
                                <span class="status-badge status-<?= $booking['status'] === 'boarded' ? 'boarded' : ($booking['status'] === 'confirmed' ? 'confirmed' : ($booking['status'] === 'arrived' ? 'arrived' : ($booking['status'] === 'cancelled' ? 'cancelled' : 'pending'))) ?>">
                                    <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                                </span>
                            </div>
                            
                            <div class="flex gap-3">
                                <?php if ($booking['status'] === 'confirmed' || $booking['status'] === 'pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="mark_boarded">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <input type="hidden" name="pnr" value="<?= htmlspecialchars($booking['pnr']) ?>">
                                        <button type="submit" class="btn-success flex items-center gap-2">
                                            <i class="fas fa-check-circle"></i>Mark as Boarded
                                        </button>
                                    </form>
                                <?php elseif ($booking['status'] === 'boarded'): ?>
                                    <div class="flex items-center text-green-600 gap-2">
                                        <i class="fas fa-check-circle text-lg"></i>
                                        <span class="font-medium">Passenger boarded</span>
                                    </div>
                                <?php elseif ($booking['status'] === 'arrived'): ?>
                                    <div class="flex items-center text-blue-600 gap-2">
                                        <i class="fas fa-flag-checkered text-lg"></i>
                                        <span class="font-medium">Journey completed</span>
                                    </div>
                                <?php endif; ?>
                              <button id="print-ticket-btn" class="btn-print flex items-center gap-2">
    <i class="fas fa-print"></i>Print Ticket
</button>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Trip Details -->
                            <div>
                                <h3 class="text-lg font-semibold mb-3 flex items-center">
                                    <i class="fas fa-route text-red-600 mr-2"></i>Trip Details
                                </h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Route:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['pickup_city']) ?> → <?= htmlspecialchars($booking['dropoff_city']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Date:</span>
                                        <span class="font-medium"><?= formatDate($booking['trip_date'], 'j M, Y') ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Departure:</span>
                                        <span class="font-medium"><?= formatTime($booking['departure_time'], 'g:i A') ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Arrival:</span>
                                        <span class="font-medium"><?= $booking['arrival_time'] ? formatTime($booking['arrival_time'], 'g:i A') : 'N/A' ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Vehicle:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['vehicle_type']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Vehicle No:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['vehicle_number']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Driver:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['driver_name']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-gray-600">Seat:</span>
                                        <span class="font-medium bg-red-50 text-red-700 px-2 py-1 rounded"><?= htmlspecialchars($booking['seat_number']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Passenger Details -->
                            <div>
                                <h3 class="text-lg font-semibold mb-3 flex items-center">
                                    <i class="fas fa-user text-red-600 mr-2"></i>Passenger Information
                                </h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Name:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['passenger_name']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Phone:</span>
                                        <span class="font-medium"><?= htmlspecialchars($booking['phone']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Booking Date:</span>
                                        <span class="font-medium"><?= formatDate($booking['created_at'], 'j M, Y g:i A') ?></span>
                                    </div>
                                </div>

                                <!-- Payment Details -->
                                <h4 class="text-base font-semibold mt-5 mb-2 flex items-center">
                                    <i class="fas fa-credit-card text-red-600 mr-2"></i>Payment Details
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-600">Amount:</span>
                                        <span class="font-medium text-green-600">₦<?= number_format($booking['total_amount'], 0) ?></span>
                                    </div>
                                    <div class="flex justify-between py-2">
                                        <span class="text-gray-600">Payment Status:</span>
                                        <span class="font-medium"><?= ucfirst(htmlspecialchars($booking['payment_status'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Status Management -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg status-update-form">
                            <h4 class="text-base font-semibold mb-3">Update Booking Status</h4>
                            <form method="POST" class="flex flex-col sm:flex-row gap-3">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="pnr" value="<?= htmlspecialchars($booking['pnr']) ?>">
                                <select name="new_status" class="input-field flex-1" required>
                                    <option value="">Select new status</option>
                                    <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="boarded" <?= $booking['status'] === 'boarded' ? 'selected' : '' ?>>Boarded</option>
                                    <option value="arrived" <?= $booking['status'] === 'arrived' ? 'selected' : '' ?>>Arrived</option>
                                    <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <button type="submit" class="btn-primary flex items-center gap-2">
                                    <i class="fas fa-save"></i>Update Status
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

        // Print button event listener
        const printBtn = document.getElementById('print-ticket-btn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
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
                    elements.output.innerHTML = `<span class="text-green-600">Boarded Successfully</span><br>Boarding Time: ${result.boarding_time || new Date().toLocaleString()}`;
                    showModal(
                        'Boarded Successfully',
                        result.passenger_name,
                        `${result.pickup_city} → ${result.dropoff_city}`,
                        `${result.vehicle_type} (${result.vehicle_number})`,
                        result.seat_number
                    );
                } else if (result.status === 'warning') {
                    playFeedback(false);
                    elements.output.innerHTML = `<span class="text-yellow-600">Passenger Already Boarded</span><br>Boarding Time: ${result.boarding_time}`;
                    showModal(
                        'Passenger Already Boarded',
                        result.passenger_name,
                        `${result.pickup_city} → ${result.dropoff_city}`,
                        `${result.vehicle_type} (${result.vehicle_number})`,
                        result.seat_number
                    );
                } else if (result.status === 'date_error') {
                    playFeedback(false);
                    elements.output.innerHTML = `<span class="text-red-600">${result.message}</span>`;
                    showModal(result.message, null, null, null, null);
                } else {
                    playFeedback(false);
                    elements.output.innerHTML = `<span class="text-red-600">${result.message}</span>`;
                    showModal(result.message, null, null, null, null);
                }
                
            } catch (error) {
                console.error('Error during fetch or processing:', error);
                playFeedback(false);
                elements.output.innerHTML = `<span class="text-red-600">Scanner Error: ${error.message}</span>`;
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
            
            const roiX = videoWidth * 0.15;
            const roiY = videoHeight * 0.15;
            const roiWidth = videoWidth * 0.7;
            const roiHeight = videoHeight * 0.7;

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
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const videoConstraints = {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: isMobile ? { exact: "environment" } : "user"
                };

                stream = await navigator.mediaDevices.getUserMedia({
                    video: videoConstraints
                });
                elements.video.srcObject = stream;
                await elements.video.play();
                
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
                } else if (error.name === 'OverconstrainedError') {
                    errorMessage = 'Requested camera not available. Trying default camera...';
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({
                            video: { width: { ideal: 1280 }, height: { ideal: 720 } }
                        });
                        elements.video.srcObject = stream;
                        await elements.video.play();
                        
                        elements.video.style.display = 'block';
                        elements.scanOverlay.style.display = 'block';
                        elements.startCameraButton.style.display = 'none';
                        elements.startButtonContainer.style.display = 'none';
                        elements.stopCameraButton.style.display = 'block';
                        
                        elements.output.textContent = 'Camera initialized. Position QR code...';
                        requestAnimationFrame(processFrame);
                        return;
                    } catch (fallbackError) {
                        errorMessage = 'Fallback camera failed: ' + fallbackError.message;
                    }
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

        const searchInput = document.querySelector('input[name="search_query"]');
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }

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
                document.querySelector('input[name="search_query"]').focus();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                if (printBtn) {
                    printBtn.click();
                }
            }
        });
    });
</script>
</body>
</html>