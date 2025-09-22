<?php
// bookings/verify_payment.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/send_email.php';

header('Content-Type: application/json');

error_log("verify_payment.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Check if payment reference is provided
if (!isset($_POST['reference']) || empty(trim($_POST['reference']))) {
    error_log("Missing or empty payment reference");
    echo json_encode(['success' => false, 'message' => 'Invalid payment reference']);
    exit();
}

$reference = trim($_POST['reference']);

// Verify session data
if (!isset($_SESSION['booking_ids']) || !isset($_SESSION['passenger_details']) || !isset($_SESSION['total_amount'])) {
    error_log("Missing required session data: " . json_encode([
        'booking_ids' => isset($_SESSION['booking_ids']) ? 'set' : 'not set',
        'passenger_details' => isset($_SESSION['passenger_details']) ? 'set' : 'not set',
        'total_amount' => isset($_SESSION['total_amount']) ? 'set' : 'not set'
    ]));
    echo json_encode(['success' => false, 'message' => 'Booking session expired. Please start a new booking.']);
    exit();
}

$booking_ids = $_SESSION['booking_ids'];
$passenger_details = $_SESSION['passenger_details'];
$total_amount = $_SESSION['total_amount'];
$user_id = isLoggedIn() ? $_SESSION['user']['id'] : null;

// Verify payment with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . urlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    error_log("Paystack API error: $err");
    echo json_encode(['success' => false, 'message' => 'Failed to verify payment. Please contact support.']);
    exit();
}

$tranx = json_decode($response, true);

if (!$tranx['status'] || $tranx['data']['status'] !== 'success') {
    error_log("Paystack verification failed: " . json_encode($tranx));
    echo json_encode(['success' => false, 'message' => 'Payment verification failed.']);
    exit();
}

// Verify the amount paid matches expected amount
$amount_paid = $tranx['data']['amount'] / 100; // Convert from kobo to naira
if (abs($amount_paid - $total_amount) > 0.01) {
    error_log("Amount mismatch: Paid $amount_paid, Expected $total_amount");
    echo json_encode(['success' => false, 'message' => 'Payment amount mismatch. Please contact support.']);
    exit();
}

try {
    $conn->begin_transaction();

    // Update booking records
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET payment_status = 'paid', 
            status = 'confirmed', 
            payment_reference = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare booking update query: ' . $conn->error);
    }

    foreach ($booking_ids as $booking_id) {
        $stmt->bind_param("si", $reference, $booking_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking ID ' . $booking_id . ': ' . $stmt->error);
        }
        error_log("Updated booking ID $booking_id to paid and confirmed");
    }
    $stmt->close();

    // Handle referral credits if applicable
    if (isset($_SESSION['referral_code']) && isset($_SESSION['referrer_id'])) {
        $referral_code = $_SESSION['referral_code'];
        $referrer_id = $_SESSION['referrer_id'];

        error_log("Processing referral for code: $referral_code, referrer ID: $referrer_id");

        // Get referral credit amount from settings
        $stmt_settings = $conn->prepare("SELECT credits FROM referral_settings WHERE id = 1");
        if (!$stmt_settings) {
            throw new Exception('Failed to prepare referral settings query: ' . $conn->error);
        }
        $stmt_settings->execute();
        $result = $stmt_settings->get_result();
        $referral_settings = $result->fetch_assoc();
        $referral_credits = $referral_settings ? $referral_settings['credits'] : 2000.00;
        $stmt_settings->close();

        error_log("Referral credits to award: $referral_credits");

        // Credit the referrer
        $stmt_credit = $conn->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
        if (!$stmt_credit) {
            throw new Exception('Failed to prepare referrer credit query: ' . $conn->error);
        }
        $stmt_credit->bind_param("di", $referral_credits, $referrer_id);
        if (!$stmt_credit->execute()) {
            throw new Exception('Failed to credit referrer: ' . $stmt_credit->error);
        }
        $affected_rows = $stmt_credit->affected_rows;
        $stmt_credit->close();

        if ($affected_rows > 0) {
            error_log("Successfully credited referrer ID {$referrer_id} with {$referral_credits} credits");

            // Log the referral in affiliates table
            $stmt_affiliate = $conn->prepare("
                INSERT INTO affiliates (referrer_id, referred_user_id, referral_code, status, created_at) 
                VALUES (?, ?, ?, 'completed', NOW())
            ");
            if (!$stmt_affiliate) {
                throw new Exception('Failed to prepare affiliate log query: ' . $conn->error);
            }
            $stmt_affiliate->bind_param("iis", $referrer_id, $user_id, $referral_code);
            if (!$stmt_affiliate->execute()) {
                throw new Exception('Failed to log affiliate referral: ' . $stmt_affiliate->error);
            }
            $stmt_affiliate->close();

            error_log("Successfully logged referral in affiliates table - Referrer: $referrer_id, Referred: " . ($user_id ?? 'guest') . ", Code: $referral_code");

            // Verify the credit was added
            $stmt_verify = $conn->prepare("SELECT credits FROM users WHERE id = ?");
            $stmt_verify->bind_param("i", $referrer_id);
            $stmt_verify->execute();
            $result = $stmt_verify->get_result();
            $user_credits = $result->fetch_assoc();
            $stmt_verify->close();

            if ($user_credits) {
                error_log("Referrer ID {$referrer_id} now has {$user_credits['credits']} total credits");
            }
        } else {
            error_log("Warning: No user found with ID {$referrer_id} to credit");
        }

        // Clear referral session data
        unset($_SESSION['referral_code']);
        unset($_SESSION['referrer_id']);
        unset($_SESSION['referral_message']);
    }

    // Commit the transaction
    $conn->commit();

    // Send confirmation email to all passengers
    foreach ($passenger_details as $passenger) {
        $booking_data = [
            'email' => $passenger['email'],
            'passenger_name' => $passenger['name'],
            'pnr' => $passenger['pnr'],
            'seat_number' => $passenger['seat_number'],
            'total_amount' => $total_amount,
            'trip' => [
                'pickup_city' => $_SESSION['selected_trip']['pickup_city'],
                'dropoff_city' => $_SESSION['selected_trip']['dropoff_city'],
                'trip_date' => $_SESSION['selected_trip']['trip_date'],
                'departure_time' => $_SESSION['selected_trip']['departure_time'] ?? '',
                'vehicle_type' => $_SESSION['selected_trip']['vehicle_type'] ?? '',
                'vehicle_number' => $_SESSION['selected_trip']['vehicle_number'] ?? '',
                'driver_name' => $_SESSION['selected_trip']['driver_name'] ?? ''
            ]
        ];
        error_log("Preparing to send email with data: " . json_encode($booking_data));
        if (sendBookingConfirmationEmail($booking_data)) {
            error_log("Sent confirmation email to {$booking_data['email']} for PNR {$booking_data['pnr']}");
        } else {
            error_log("Failed to send confirmation email to {$booking_data['email']} for PNR {$booking_data['pnr']}");
        }
    }

    // Clear session data
    unset($_SESSION['booking_ids']);
    unset($_SESSION['passenger_details']);
    unset($_SESSION['selected_seats']);
    unset($_SESSION['total_amount']);
    unset($_SESSION['payment_reference']);

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully!',
        'redirect_url' => SITE_URL . '/bookings/confirmation.php'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Payment verification failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment verification failed: ' . $e->getMessage()]);
    exit();
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt && $stmt->errno === 0) {
        $stmt->close();
    }
}

ob_end_flush();
?>