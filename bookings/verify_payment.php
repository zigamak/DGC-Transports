<?php
// bookings/verify_payment.php - Updated with correct column names
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/send_email.php';

date_default_timezone_set(DEFAULT_TIMEZONE);

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function jsonResponse($data) {
    ob_end_clean();
    if (!is_array($data) || json_encode($data) === false) {
        error_log("Invalid JSON data: " . print_r($data, true));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Internal server error: Invalid response data']);
    } else {
        echo json_encode($data);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

if (!isset($_POST['reference']) || empty($_POST['reference'])) {
    jsonResponse(['success' => false, 'message' => 'Payment reference is required']);
}

$reference = trim($_POST['reference']);
error_log("Starting payment verification for reference: $reference");

if (!isset($_SESSION['payment_reference']) || $_SESSION['payment_reference'] !== $reference) {
    error_log("Payment reference mismatch or session expired: $reference");
    jsonResponse(['success' => false, 'message' => 'Payment reference mismatch or session expired']);
}

if (
    !isset($_SESSION['selected_trip']) ||
    !isset($_SESSION['selected_seats']) ||
    !isset($_SESSION['passenger_details']) ||
    !isset($_SESSION['booking_ids'])
) {
    error_log("Missing session data: " . json_encode([
        'selected_trip' => isset($_SESSION['selected_trip']) ? 'set' : 'not set',
        'selected_seats' => isset($_SESSION['selected_seats']) ? 'set' : 'not set',
        'passenger_details' => isset($_SESSION['passenger_details']) ? 'set' : 'not set',
        'booking_ids' => isset($_SESSION['booking_ids']) ? 'set' : 'not set'
    ]));
    jsonResponse(['success' => false, 'message' => 'Booking details not found in session']);
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$passenger_details = $_SESSION['passenger_details'];
$booking_ids = $_SESSION['booking_ids'];

// Get trip instance ID - look for existing trip instance or create one
$template_id = isset($trip['template_id']) ? (int)$trip['template_id'] : 0;
$trip_date = isset($trip['trip_date']) ? $trip['trip_date'] : '';

if ($template_id <= 0 || empty($trip_date)) {
    error_log("Invalid template ID or trip date: template_id=$template_id, trip_date=$trip_date");
    jsonResponse(['success' => false, 'message' => 'Invalid trip data']);
}

// Find or create trip instance
$stmt = $conn->prepare("
    SELECT id FROM trip_instances 
    WHERE template_id = ? AND trip_date = ? AND status = 'active'
");
if (!$stmt) {
    error_log("Prepare trip instance check failed: " . $conn->error);
    jsonResponse(['success' => false, 'message' => 'Database error']);
}
$stmt->bind_param("is", $template_id, $trip_date);
$stmt->execute();
$instance_result = $stmt->get_result();
$trip_instance = $instance_result->fetch_assoc();
$stmt->close();

if (!$trip_instance) {
    // Create new trip instance
    $stmt = $conn->prepare("
        INSERT INTO trip_instances (template_id, trip_date, booked_seats, status) 
        VALUES (?, ?, 0, 'active')
    ");
    if (!$stmt) {
        error_log("Prepare trip instance creation failed: " . $conn->error);
        jsonResponse(['success' => false, 'message' => 'Database error']);
    }
    $stmt->bind_param("is", $template_id, $trip_date);
    if (!$stmt->execute()) {
        error_log("Create trip instance failed: " . $stmt->error);
        jsonResponse(['success' => false, 'message' => 'Failed to create trip instance']);
    }
    $trip_id = $conn->insert_id;
    $stmt->close();
} else {
    $trip_id = $trip_instance['id'];
}

// Validate trip template and get vehicle capacity
$stmt = $conn->prepare("
    SELECT tt.*, vt.capacity
    FROM trip_templates tt
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    WHERE tt.id = ? AND tt.status = 'active'
");
if (!$stmt) {
    error_log("Prepare trip template check failed: " . $conn->error);
    jsonResponse(['success' => false, 'message' => 'Database error']);
}
$stmt->bind_param("i", $template_id);
$stmt->execute();
$template_result = $stmt->get_result();
$trip_template = $template_result->fetch_assoc();
$stmt->close();

if (!$trip_template) {
    error_log("Trip template not found or inactive: $template_id");
    jsonResponse(['success' => false, 'message' => 'Invalid or inactive trip template']);
}

// Check for conflicting paid bookings
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings 
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid' 
        AND status != 'cancelled'
");
if (!$stmt) {
    error_log("Prepare seat check failed: " . $conn->error);
    jsonResponse(['success' => false, 'message' => 'Database error']);
}
$stmt->bind_param("is", $template_id, $trip_date);
$stmt->execute();
$booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$booked_seat_numbers = array_column($booked_seats, 'seat_number');
$stmt->close();

$conflicting_seats = array_intersect($selected_seats, $booked_seat_numbers);
if (!empty($conflicting_seats)) {
    error_log("Conflicting seats for template $template_id on $trip_date: " . implode(', ', $conflicting_seats));
    jsonResponse(['success' => false, 'message' => 'Selected seats are no longer available: ' . implode(', ', $conflicting_seats)]);
}

if (count($booking_ids) !== count($selected_seats) || count($booking_ids) !== count($passenger_details)) {
    error_log("Mismatch in booking IDs, seats, or passenger details: " . json_encode([
        'booking_ids_count' => count($booking_ids),
        'selected_seats_count' => count($selected_seats),
        'passenger_details_count' => count($passenger_details)
    ]));
    jsonResponse(['success' => false, 'message' => 'Invalid booking data']);
}

$conn->begin_transaction();

try {
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
    $total_amount = count($selected_seats) * $trip['price'];
    if ($paid_amount < $total_amount) {
        throw new Exception("Amount mismatch: paid ₦{$paid_amount}, expected ₦{$total_amount}");
    }

    // Update bookings (correct column names)
    $pnrs = [];
    foreach ($booking_ids as $booking_id) {
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET payment_status = 'paid', status = 'confirmed', created_at = created_at
            WHERE id = ? AND payment_status = 'pending' AND status = 'pending'
        ");
        if (!$stmt) {
            throw new Exception('Prepare booking update failed: ' . $conn->error);
        }
        $stmt->bind_param("i", $booking_id);
        if (!$stmt->execute()) {
            throw new Exception('Update booking failed for ID ' . $booking_id . ': ' . $stmt->error);
        }
        if ($stmt->affected_rows === 0) {
            throw new Exception('Booking ID ' . $booking_id . ' not found or already processed');
        }
        $stmt->close();

        // Fetch PNR for the booking
        $stmt = $conn->prepare("SELECT pnr FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $pnr_result = $stmt->get_result()->fetch_assoc();
        $pnrs[] = $pnr_result['pnr'];
        $stmt->close();
    }

    // Insert payment records
    error_log("Inserting payment for booking IDs: " . implode(', ', $booking_ids));
    $stmt_payment = $conn->prepare("
        INSERT INTO payments 
        (booking_id, amount, transaction_reference, gateway_reference, payment_method, status, gateway_response, created_at) 
        VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
    ");
    if (!$stmt_payment) {
        throw new Exception('Prepare payment failed: ' . $conn->error);
    }

    $json_response = json_encode($payment_data);
    foreach ($booking_ids as $index => $booking_id) {
        $seat_amount = $trip['price']; // Amount per seat
        $stmt_payment->bind_param(
            "idssss",
            $booking_id,
            $seat_amount,
            $reference,
            $gateway_reference,
            $payment_method,
            $json_response
        );
        if (!$stmt_payment->execute()) {
            throw new Exception('Insert payment failed for booking ID ' . $booking_id . ': ' . $stmt_payment->error);
        }
    }
    $stmt_payment->close();

    // Update trip_instances booked seats
    error_log("Updating trip_instances ID: $trip_id with booked seats: " . count($selected_seats));
    $stmt_trip = $conn->prepare("
        UPDATE trip_instances 
        SET booked_seats = booked_seats + ? 
        WHERE id = ? AND status = 'active'
    ");
    if (!$stmt_trip) {
        throw new Exception('Prepare trip_instances update failed: ' . $conn->error);
    }
    $num_seats = count($selected_seats);
    $stmt_trip->bind_param("ii", $num_seats, $trip_id);
    if (!$stmt_trip->execute()) {
        throw new Exception('Update trip_instances failed: ' . $stmt_trip->error);
    }
    if ($stmt_trip->affected_rows === 0) {
        error_log("Warning: No rows affected when updating trip_instances ID: $trip_id");
        // Don't throw error here, as the booking might still be valid
    }
    $stmt_trip->close();

    $conn->commit();

    // Store booking confirmation data
    $_SESSION['booking_confirmation'] = [
        'pnrs' => $pnrs,
        'booking_ids' => $booking_ids,
        'passenger_details' => $passenger_details,
        'selected_seats' => $selected_seats,
        'total_amount' => $total_amount,
        'payment_method' => $payment_method,
        'payment_reference' => $reference,
        'trip' => $trip
    ];

    // Send booking confirmation emails
    foreach ($passenger_details as $index => $passenger) {
        $confirmation_data = [
            'pnr' => $pnrs[$index],
            'booking_id' => $booking_ids[$index],
            'passenger_name' => $passenger['name'],
            'email' => $passenger['email'],
            'phone' => $passenger['phone'],
            'emergency_contact' => isset($passenger['emergency_contact']) ? $passenger['emergency_contact'] : '',
            'special_requests' => isset($passenger['special_requests']) ? $passenger['special_requests'] : '',
            'seat_number' => $passenger['seat_number'],
            'total_amount' => $trip['price'], // Amount per seat
            'payment_method' => $payment_method,
            'payment_reference' => $reference,
            'trip' => $trip
        ];
        $email_sent = sendBookingConfirmationEmail($confirmation_data);
        if ($email_sent) {
            error_log("Booking confirmation email sent successfully to: " . $passenger['email']);
        } else {
            error_log("Failed to send booking confirmation email to: " . $passenger['email']);
        }
    }

    // Clear booking session data
    unset($_SESSION['selected_trip']);
    unset($_SESSION['selected_seats']);
    unset($_SESSION['passenger_details']);
    unset($_SESSION['payment_reference']);
    unset($_SESSION['booking_ids']);

    jsonResponse([
        'success' => true,
        'message' => 'Payment verified and bookings confirmed successfully',
        'pnrs' => $pnrs,
        'booking_ids' => $booking_ids,
        'redirect_url' => "booking_confirmation.php?pnr=" . urlencode($pnrs[0])
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
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_payment)) $stmt_payment->close();
    if (isset($stmt_trip)) $stmt_trip->close();
    $conn->close();
}
?>