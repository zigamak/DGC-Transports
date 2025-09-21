<?php
// bookings/verify_payment.php - DGC Transport System Payment Verification

// Start output buffering to prevent JSON corruption
ob_start();

// Configure error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session and include dependencies
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set timezone from config
date_default_timezone_set(DEFAULT_TIMEZONE);

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Helper function to send JSON response
function jsonResponse($data) {
    ob_end_clean(); // Clear output buffer
    if (!is_array($data) || json_encode($data) === false) {
        error_log("Invalid JSON data: " . print_r($data, true));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error: Invalid response data']);
    } else {
        echo json_encode($data);
    }
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

// Validate payment reference
if (!isset($_POST['reference']) || empty($_POST['reference'])) {
    jsonResponse(['success' => false, 'message' => 'Payment reference is required']);
}

$reference = trim($_POST['reference']);
error_log("Starting payment verification for reference: $reference");

// Verify session reference
if (!isset($_SESSION['payment_reference']) || $_SESSION['payment_reference'] !== $reference) {
    error_log("Payment reference mismatch or session expired: $reference");
    jsonResponse(['success' => false, 'message' => 'Payment reference mismatch or session expired']);
}

// Check required session data
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['passenger_details'])) {
    error_log("Missing session data: " . json_encode([
        'selected_trip' => $_SESSION['selected_trip'] ?? 'not set',
        'selected_seats' => $_SESSION['selected_seats'] ?? 'not set',
        'passenger_details' => $_SESSION['passenger_details'] ?? 'not set'
    ]));
    jsonResponse(['success' => false, 'message' => 'Booking details not found in session']);
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$passenger_details = $_SESSION['passenger_details'];

// Validate trip ID
$trip_id = isset($trip['trip_id']) ? (int)$trip['trip_id'] : 0;
if ($trip_id <= 0) {
    error_log("Invalid trip ID: $trip_id");
    jsonResponse(['success' => false, 'message' => 'Invalid trip ID']);
}

// Validate trip existence and status
$trip_data = getTripById($conn, $trip_id);
if (!$trip_data) {
    error_log("Trip not found or inactive: $trip_id");
    jsonResponse(['success' => false, 'message' => 'Invalid or inactive trip']);
}

// Validate seat availability
$conflicting_seats = checkSeatAvailabilityForTrip($conn, $selected_seats, $trip_id);
if (!empty($conflicting_seats)) {
    error_log("Conflicting seats for trip $trip_id: " . implode(', ', $conflicting_seats));
    jsonResponse(['success' => false, 'message' => 'Selected seats are no longer available: ' . implode(', ', $conflicting_seats)]);
}

// Validate passenger details
if (!isValidEmail($passenger_details['email']) || !isValidPhone($passenger_details['phone']) || !isValidName($passenger_details['passenger_name'])) {
    error_log("Invalid passenger details: " . json_encode($passenger_details));
    jsonResponse(['success' => false, 'message' => 'Invalid passenger details']);
}

// Calculate total amount
$total_amount = count($selected_seats) * $trip['price'];

// Generate unique PNR
$pnr = generatePNR();

// Handle user_id (optional)
$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if (!$user_check) {
        error_log("Prepare user check failed: " . $conn->error);
        jsonResponse(['success' => false, 'message' => 'Database error']);
    }
    $user_check->bind_param("i", $_SESSION['user_id']);
    $user_check->execute();
    $user_result = $user_check->get_result();
    if ($user_result->num_rows > 0) {
        $user_id = $_SESSION['user_id'];
    }
    $user_check->close();
}

// Begin database transaction
$conn->begin_transaction();

try {
    // Verify payment with Paystack
    error_log("Calling verifyPaystackPayment for reference: $reference");
    $verification_result = verifyPaystackPayment($reference);
    error_log("Paystack verification result: " . json_encode($verification_result));

    if (!$verification_result['status']) {
        throw new Exception($verification_result['message']);
    }

    $payment_data = $verification_result['data'];
    $paid_amount_in_kobo = $payment_data['amount'];
    $paid_amount = $paid_amount_in_kobo / 100;
    $gateway_reference = $payment_data['reference'];
    $payment_method = isset($payment_data['channel']) ? $payment_data['channel'] : 'Unknown';

    // Verify payment amount
    if ($paid_amount < $total_amount) {
        throw new Exception("Amount mismatch: paid ₦{$paid_amount}, expected ₦{$total_amount}");
    }

    // Extract trip data with defaults
    $pickup_city_id = isset($trip['pickup_city_id']) ? (int)$trip['pickup_city_id'] : 1;
    $dropoff_city_id = isset($trip['dropoff_city_id']) ? (int)$trip['dropoff_city_id'] : 2;
    $vehicle_type_id = isset($trip['vehicle_type_id']) ? (int)$trip['vehicle_type_id'] : 1;
    $trip_date = isset($trip['trip_date']) ? $trip['trip_date'] : date('Y-m-d');

    // Insert booking
    error_log("Inserting booking with PNR: $pnr");
    $stmt = $conn->prepare("
        INSERT INTO `bookings` 
        (pnr, user_id, passenger_name, email, phone, pickup_city_id, dropoff_city_id, vehicle_type_id, trip_id, total_amount, payment_status, departure_date, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 'confirmed', NOW())
    ");
    if (!$stmt) {
        throw new Exception('Prepare booking statement failed: ' . $conn->error);
    }

    $stmt->bind_param("sisssiiiids", 
        $pnr,
        $user_id,
        $passenger_details['passenger_name'],
        $passenger_details['email'],
        $passenger_details['phone'],
        $pickup_city_id,
        $dropoff_city_id,
        $vehicle_type_id,
        $trip_id,
        $total_amount,
        $trip_date
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute booking insert failed: ' . $stmt->error);
    }
    
    $booking_id = $conn->insert_id;
    if (!$booking_id) {
        throw new Exception('Failed to get booking ID');
    }

    // Insert seat bookings
    error_log("Inserting seats for booking ID: $booking_id");
    $stmt_seats = $conn->prepare("INSERT INTO `seat_bookings` (booking_id, seat_number) VALUES (?, ?)");
    if (!$stmt_seats) {
        throw new Exception('Prepare seats statement failed: ' . $conn->error);
    }

    foreach ($selected_seats as $seat) {
        $stmt_seats->bind_param("ii", $booking_id, $seat);
        if (!$stmt_seats->execute()) {
            throw new Exception('Insert seat failed for seat ' . $seat . ': ' . $stmt_seats->error);
        }
    }

    // Update trip booked seats
    error_log("Updating trip ID: $trip_id with booked seats: " . count($selected_seats));
    $stmt_trip = $conn->prepare("UPDATE `trips` SET booked_seats = booked_seats + ? WHERE id = ?");
    if (!$stmt_trip) {
        throw new Exception('Prepare trip update failed: ' . $conn->error);
    }

    $num_seats = count($selected_seats);
    $stmt_trip->bind_param("ii", $num_seats, $trip_id);
    if (!$stmt_trip->execute()) {
        throw new Exception('Update trip failed: ' . $stmt_trip->error);
    }

    // Insert payment record
    error_log("Inserting payment for booking ID: $booking_id");
    $stmt_payment = $conn->prepare("
        INSERT INTO `payments` 
        (booking_id, amount, transaction_reference, gateway_reference, payment_method, status, gateway_response, created_at) 
        VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
    ");
    if (!$stmt_payment) {
        throw new Exception('Prepare payment failed: ' . $conn->error);
    }

    $json_response = json_encode($payment_data);
    $stmt_payment->bind_param("idssss", 
        $booking_id,          // i - integer
        $paid_amount,         // d - decimal
        $reference,           // s - string
        $gateway_reference,   // s - string
        $payment_method,      // s - string
        $json_response        // s - string
    );

    if (!$stmt_payment->execute()) {
        throw new Exception('Insert payment failed: ' . $stmt_payment->error);
    }

    // Commit transaction
    $conn->commit();

    // Store booking confirmation data
    $_SESSION['booking_confirmation'] = [
        'pnr' => $pnr,
        'booking_id' => $booking_id,
        'passenger_name' => $passenger_details['passenger_name'],
        'email' => $passenger_details['email'],
        'phone' => $passenger_details['phone'],
        'selected_seats' => $selected_seats,
        'total_amount' => $total_amount,
        'payment_method' => $payment_method,
        'payment_reference' => $reference,
        'trip' => $trip
    ];

    // Clear booking session data
    unset($_SESSION['selected_trip']);
    unset($_SESSION['selected_seats']);
    unset($_SESSION['passenger_details']);
    unset($_SESSION['payment_reference']);

    // Return success response with redirect URL
    jsonResponse([
        'success' => true,
        'message' => 'Payment verified and booking confirmed successfully',
        'pnr' => $pnr,
        'booking_id' => $booking_id,
        'redirect_url' => "booking_confirmation.php?pnr=" . urlencode($pnr)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Payment verification error for reference $reference: " . $e->getMessage());
    http_response_code(500);
    jsonResponse([
        'success' => false,
        'message' => 'Payment verification failed: ' . $e->getMessage(),
        'reference' => $reference
    ]);
} finally {
    // Close statements and connection
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_seats)) $stmt_seats->close();
    if (isset($stmt_trip)) $stmt_trip->close();
    if (isset($stmt_payment)) $stmt_payment->close();
    $conn->close();
}
?>