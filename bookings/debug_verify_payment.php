<?php
// bookings/debug_verify_payment.php - Temporary debugging version
// Use this to identify the exact error, then switch back to the fixed version

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Log all errors to a file for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_debug.log');

try {
    require_once '../includes/db.php';
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit();
}

// Clear any output that might have been generated
ob_clean();

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if reference is provided
if (!isset($_POST['reference']) || empty($_POST['reference'])) {
    echo json_encode(['success' => false, 'message' => 'Payment reference is required']);
    exit();
}

$reference = trim($_POST['reference']);

// Check session variables
if (!isset($_SESSION['payment_reference'])) {
    echo json_encode(['success' => false, 'message' => 'No payment reference in session']);
    exit();
}

if ($_SESSION['payment_reference'] !== $reference) {
    echo json_encode(['success' => false, 'message' => 'Payment reference mismatch']);
    exit();
}

// Check required session data
$required_session_keys = ['selected_trip', 'selected_seats', 'passenger_details'];
foreach ($required_session_keys as $key) {
    if (!isset($_SESSION[$key])) {
        echo json_encode(['success' => false, 'message' => "Missing session data: $key"]);
        exit();
    }
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$passenger_details = $_SESSION['passenger_details'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$total_amount = count($selected_seats) * $trip['price'];

// Check if required functions exist
if (!function_exists('generatePNR')) {
    echo json_encode(['success' => false, 'message' => 'generatePNR function not found']);
    exit();
}

if (!function_exists('verifyPaystackPayment')) {
    echo json_encode(['success' => false, 'message' => 'verifyPaystackPayment function not found']);
    exit();
}

// Check Paystack configuration
if (!defined('PAYSTACK_SECRET_KEY') || empty(PAYSTACK_SECRET_KEY)) {
    echo json_encode(['success' => false, 'message' => 'Paystack secret key not configured']);
    exit();
}

$pnr = generatePNR();

// Test database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Verify payment with Paystack
    $verification_result = verifyPaystackPayment($reference);

    if (!$verification_result['status']) {
        throw new Exception('Paystack verification failed: ' . $verification_result['message']);
    }

    $payment_data = $verification_result['data'];
    $paid_amount_in_kobo = $payment_data['amount'];
    $paid_amount = $paid_amount_in_kobo / 100;
    $gateway_reference = $payment_data['reference'];
    $payment_method = isset($payment_data['channel']) ? $payment_data['channel'] : 'Unknown';

    // Verify amount
    if ($paid_amount < $total_amount) {
        throw new Exception("Amount mismatch: paid $paid_amount, expected $total_amount");
    }

    // Insert booking - test the query first
    $insert_booking_sql = "INSERT INTO `bookings` (pnr, user_id, passenger_name, email, phone, pickup_city_id, dropoff_city_id, vehicle_type_id, trip_id, total_amount, payment_status, departure_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 'confirmed', NOW())";
    
    $stmt = $conn->prepare($insert_booking_sql);
    if (!$stmt) {
        throw new Exception('Prepare booking statement failed: ' . $conn->error);
    }

    $bind_result = $stmt->bind_param("ssisssiids", 
        $pnr, 
        $user_id, 
        $passenger_details['passenger_name'], 
        $passenger_details['email'], 
        $passenger_details['phone'],
        $trip['pickup_city_id'],
        $trip['dropoff_city_id'],
        $trip['vehicle_type_id'],
        $trip['trip_id'],
        $total_amount,
        $trip['trip_date']
    );

    if (!$bind_result) {
        throw new Exception('Bind parameters failed: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute booking insert failed: ' . $stmt->error);
    }
    
    $booking_id = $conn->insert_id;
    if (!$booking_id) {
        throw new Exception('Failed to get booking ID');
    }

    // Insert seat bookings
    $stmt_seats = $conn->prepare("INSERT INTO `seat_bookings` (booking_id, seat_number) VALUES (?, ?)");
    if (!$stmt_seats) {
        throw new Exception('Prepare seats statement failed: ' . $conn->error);
    }

    foreach ($selected_seats as $seat) {
        $stmt_seats->bind_param("ii", $booking_id, $seat);
        if (!$stmt_seats->execute()) {
            throw new Exception('Insert seat failed: ' . $stmt_seats->error);
        }
    }
    
    // Update trip
    $stmt_trip = $conn->prepare("UPDATE `trips` SET booked_seats = booked_seats + ? WHERE id = ?");
    if (!$stmt_trip) {
        throw new Exception('Prepare trip update failed: ' . $conn->error);
    }

    $num_seats = count($selected_seats);
    $stmt_trip->bind_param("ii", $num_seats, $trip['trip_id']);
    if (!$stmt_trip->execute()) {
        throw new Exception('Update trip failed: ' . $stmt_trip->error);
    }

    // Insert payment record
    $stmt_payment = $conn->prepare("INSERT INTO `payments` (booking_id, amount, transaction_reference, gateway_reference, payment_method, status, gateway_response, created_at) VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())");
    if (!$stmt_payment) {
        throw new Exception('Prepare payment failed: ' . $conn->error);
    }

    $json_response = json_encode($payment_data);
    $stmt_payment->bind_param("idsss", 
        $booking_id,
        $paid_amount,
        $reference,
        $gateway_reference,
        $payment_method,
        $json_response
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

    // Success response
    echo json_encode([
        'success' => true, 
        'message' => 'Payment verified successfully', 
        'pnr' => $pnr,
        'booking_id' => $booking_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    // Log detailed error
    error_log("Payment verification error for reference $reference: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Payment verification failed: ' . $e->getMessage(),
        'reference' => $reference
    ]);

} finally {
    // Close statements
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_seats)) $stmt_seats->close();
    if (isset($stmt_trip)) $stmt_trip->close();
    if (isset($stmt_payment)) $stmt_payment->close();
    $conn->close();
}
?>