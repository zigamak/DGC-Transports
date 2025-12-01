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

// Check if payment reference is provided
if (!isset($_POST['reference']) || empty(trim($_POST['reference']))) {
    error_log("Missing or empty payment reference");
    echo json_encode(['success' => false, 'message' => 'Invalid payment reference']);
    exit();
}

$reference = trim($_POST['reference']);

// DETECT ROUND TRIP vs ONE-WAY (updated to match process_booking.php)
$is_roundtrip = isset($_SESSION['is_roundtrip']) && $_SESSION['is_roundtrip'] === true;

if ($is_roundtrip) {
    // Round trip validation (using standard session keys now)
    if (!isset($_SESSION['booking_ids']) || !isset($_SESSION['total_amount']) || !isset($_SESSION['roundtrip'])) {
        error_log("Missing round trip session data");
        echo json_encode(['success' => false, 'message' => 'Booking session expired. Please start a new booking.']);
        exit();
    }
    
    $booking_ids = $_SESSION['booking_ids'];
    $total_amount = $_SESSION['total_amount'];
    $pnrs = $_SESSION['pnrs'];
    $roundtrip = $_SESSION['roundtrip'];
    
} else {
    // One-way validation
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
}

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

    // Update ALL booking records (works for both one-way and round trip)
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
        error_log("Updated booking ID $booking_id to paid and confirmed (ref: $reference)");
    }
    $stmt->close();

    // Insert payment details into payments table
    $payment_method = $tranx['data']['channel'] ?? 'unknown';
    $stmt_payment = $conn->prepare("
        INSERT INTO payments (booking_id, amount, transaction_reference, gateway_reference, payment_method, status, gateway_response, created_at)
        VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
    ");
    if (!$stmt_payment) {
        throw new Exception('Failed to prepare payment insert query: ' . $conn->error);
    }

    $gateway_response = json_encode($tranx['data']); // Store full Paystack response
    foreach ($booking_ids as $booking_id) {
        $stmt_payment->bind_param("idssss", $booking_id, $total_amount, $reference, $reference, $payment_method, $gateway_response);
        if (!$stmt_payment->execute()) {
            throw new Exception('Failed to insert payment for booking ID ' . $booking_id . ': ' . $stmt_payment->error);
        }
        error_log("Inserted payment for booking ID $booking_id: method=$payment_method, ref=$reference, amount=$total_amount");
    }
    $stmt_payment->close();

    // Handle referral credits if applicable (only for one-way, not round trips)
    if (!$is_roundtrip && isset($_SESSION['referral_code']) && isset($_SESSION['referrer_id'])) {
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

    // Fetch PNRs and prepare confirmation data
    $confirmed_pnrs = [];
    
    if ($is_roundtrip) {
        // ROUND TRIP: Fetch PNRs from database and prepare confirmation data
        foreach ($booking_ids as $booking_id) {
            $stmt_pnr = $conn->prepare("SELECT pnr FROM bookings WHERE id = ?");
            $stmt_pnr->bind_param("i", $booking_id);
            $stmt_pnr->execute();
            $result = $stmt_pnr->get_result();
            $booking = $result->fetch_assoc();
            $stmt_pnr->close();
            
            if ($booking && isset($booking['pnr'])) {
                $confirmed_pnrs[] = $booking['pnr'];
            }
        }
        
        // Fetch trip details from database for confirmation
        $stmt = $conn->prepare("
            SELECT 
                tt.*,
                pc.name as pickup_city,
                dc.name as dropoff_city,
                vt.type as vehicle_type,
                v.vehicle_number,
                v.driver_name,
                ts.departure_time
            FROM trip_templates tt
            JOIN cities pc ON tt.pickup_city_id = pc.id
            JOIN cities dc ON tt.dropoff_city_id = dc.id
            JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
            JOIN vehicles v ON tt.vehicle_id = v.id
            JOIN time_slots ts ON tt.time_slot_id = ts.id
            WHERE tt.id = ?
        ");
        
        $stmt->bind_param("i", $roundtrip['outbound']['templateId']);
        $stmt->execute();
        $outbound_trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $conn->prepare("
            SELECT 
                tt.*,
                pc.name as pickup_city,
                dc.name as dropoff_city,
                vt.type as vehicle_type,
                v.vehicle_number,
                v.driver_name,
                ts.departure_time
            FROM trip_templates tt
            JOIN cities pc ON tt.pickup_city_id = pc.id
            JOIN cities dc ON tt.dropoff_city_id = dc.id
            JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
            JOIN vehicles v ON tt.vehicle_id = v.id
            JOIN time_slots ts ON tt.time_slot_id = ts.id
            WHERE tt.id = ?
        ");
        
        $stmt->bind_param("i", $roundtrip['return']['templateId']);
        $stmt->execute();
        $return_trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Store in session for confirmation page
        $_SESSION['confirmed_pnrs'] = $confirmed_pnrs;
        $_SESSION['confirmed_outbound_trip'] = array_merge($outbound_trip, ['trip_date' => $roundtrip['outbound']['tripDate']]);
        $_SESSION['confirmed_return_trip'] = array_merge($return_trip, ['trip_date' => $roundtrip['return']['tripDate']]);
        $_SESSION['confirmed_total_amount'] = $total_amount;
        $_SESSION['is_roundtrip_confirmation'] = true;
        
        // TODO: Send round trip confirmation emails (implement later if needed)
        error_log("Round trip booking confirmed - PNRs: " . implode(', ', $confirmed_pnrs));
        
    } else {
        // ONE-WAY: Fetch PNRs and send emails
        $booking_data_array = [];
        
        foreach ($passenger_details as $index => $passenger) {
            // Validate passenger data
            if (!isset($passenger['passenger_name']) && !isset($passenger['name'])) {
                error_log("Invalid passenger data at index $index: " . json_encode($passenger));
                throw new Exception("Invalid passenger data for booking ID {$booking_ids[$index]}");
            }
            
            // Handle both 'passenger_name' and 'name' keys for backward compatibility
            $passenger_name = $passenger['passenger_name'] ?? $passenger['name'];
            $seat_number = $passenger['seat_number'] ?? '';

            // Fetch PNR for each booking
            $stmt_pnr = $conn->prepare("SELECT pnr FROM bookings WHERE id = ?");
            if (!$stmt_pnr) {
                throw new Exception('Failed to prepare PNR query: ' . $conn->error);
            }
            $stmt_pnr->bind_param("i", $booking_ids[$index]);
            $stmt_pnr->execute();
            $result = $stmt_pnr->get_result();
            $booking = $result->fetch_assoc();
            $stmt_pnr->close();

            if (!$booking || !isset($booking['pnr'])) {
                throw new Exception('Failed to fetch PNR for booking ID ' . $booking_ids[$index]);
            }

            $confirmed_pnrs[] = $booking['pnr'];

            $booking_data = [
                'email' => $passenger['email'] ?? '',
                'passenger_name' => $passenger_name,
                'pnr' => $booking['pnr'],
                'seat_number' => $seat_number,
                'total_amount' => $total_amount,
                'payment_method' => $payment_method,
                'payment_reference' => $reference,
                'phone' => $passenger['phone'] ?? '',
                'emergency_contact' => $passenger['emergency_contact'] ?? '',
                'special_requests' => $passenger['special_requests'] ?? '',
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
            $booking_data_array[] = $booking_data;

            error_log("Preparing to send passenger email with data: " . json_encode($booking_data));
            if (sendBookingConfirmationEmail($booking_data)) {
                error_log("Booking confirmation email sent successfully to: {$booking_data['email']} for PNR {$booking_data['pnr']}");
            } else {
                error_log("Failed to send confirmation email to {$booking_data['email']} for PNR {$booking_data['pnr']}");
            }
        }

        // Send admin notification email
        if (!empty($booking_data_array)) {
            if (sendAdminBookingNotificationEmail($booking_data_array, $total_amount, $payment_method, $reference)) {
                error_log("Admin booking notification email sent successfully for PNR {$booking_data_array[0]['pnr']}");
            } else {
                error_log("Failed to send admin booking notification email for PNR {$booking_data_array[0]['pnr']}");
            }
        }

        // Store PNRs in session for confirmation page
        $_SESSION['confirmed_pnrs'] = $confirmed_pnrs;
        $_SESSION['confirmed_trip'] = $_SESSION['selected_trip'];
        $_SESSION['confirmed_total_amount'] = $total_amount;
        $_SESSION['first_pnr'] = $confirmed_pnrs[0] ?? '';
    }

    // Clear session data
    if ($is_roundtrip) {
        unset($_SESSION['roundtrip']);
        unset($_SESSION['roundtrip_seats']);
        unset($_SESSION['is_roundtrip']);
    } else {
        unset($_SESSION['selected_seats']);
        unset($_SESSION['selected_trip']);
        unset($_SESSION['passenger_details']);
    }
    
    // Clear common session data
    unset($_SESSION['booking_ids']);
    unset($_SESSION['pnrs']);
    unset($_SESSION['total_amount']);
    unset($_SESSION['payment_reference']);

    // Redirect to confirmation with first PNR
    $redirect_url = SITE_URL . '/bookings/confirmation.php?pnr=' . urlencode($confirmed_pnrs[0] ?? '');

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully!',
        'redirect_url' => $redirect_url
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Payment verification failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment verification failed: ' . $e->getMessage()]);
    exit();
}

ob_end_flush();
?>