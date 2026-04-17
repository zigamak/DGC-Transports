<?php
// bookings/paystack.php - Redirect-based Paystack payment (no popup)
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Detect if this is a round trip booking
$is_roundtrip = isset($_SESSION['is_roundtrip']) && $_SESSION['is_roundtrip'] === true;

if ($is_roundtrip) {
    // Round trip validation
    if (!isset($_SESSION['roundtrip']) || !isset($_SESSION['booking_ids']) || !isset($_SESSION['total_amount'])) {
        error_log("Missing round trip session data");
        $_SESSION['error'] = 'Session expired. Please start a new booking.';
        header("Location: ../index.php");
        exit();
    }

    $roundtrip    = $_SESSION['roundtrip'];
    $booking_ids  = $_SESSION['booking_ids'];
    $total_amount = $_SESSION['total_amount'];
    $pnrs         = $_SESSION['pnrs'];

    $outbound_seats = $_SESSION['roundtrip_seats']['outbound'] ?? [];
    $return_seats   = $_SESSION['roundtrip_seats']['return'] ?? [];

    // Get passenger details from first booking
    $first_booking_id = $booking_ids[0];
    $stmt = $conn->prepare("SELECT passenger_name, email, phone FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $first_booking_id);
    $stmt->execute();
    $passenger_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $email          = $passenger_data['email'];
    $passenger_name = $passenger_data['passenger_name'];

    $trip_type  = "Round Trip";
    $trip_route = ($roundtrip['outbound']['pickup_city'] ?? 'Round Trip');
    $seats_meta = 'Out: ' . implode(',', $outbound_seats) . ' | Ret: ' . implode(',', $return_seats);

} else {
    // One-way trip validation
    if (!isset($_SESSION['booking_ids']) || !isset($_SESSION['total_amount'])) {
        error_log("Missing one-way session data");
        $_SESSION['error'] = 'Session expired. Please start a new booking.';
        header("Location: ../index.php");
        exit();
    }

    if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['passenger_details'])) {
        error_log("Missing trip details in session");
        $_SESSION['error'] = 'Session expired. Please start a new booking.';
        header("Location: ../index.php");
        exit();
    }

    $trip              = $_SESSION['selected_trip'];
    $selected_seats    = $_SESSION['selected_seats'];
    $passenger_details = $_SESSION['passenger_details'];

    $booking_ids  = $_SESSION['booking_ids'];
    $pnrs         = $_SESSION['pnrs'];
    $total_amount = $_SESSION['total_amount'];

    $primary_passenger = reset($passenger_details);
    $email          = $primary_passenger['email'];
    $passenger_name = $primary_passenger['passenger_name'] ?? $primary_passenger['name'];

    $trip_type  = "One Way";
    $trip_route = ($trip['pickup_city'] ?? '') . ' to ' . ($trip['dropoff_city'] ?? '');
    $seats_meta = implode(', ', $selected_seats);
}

// Generate a unique reference for this transaction
$reference = 'DGC_' . uniqid() . '_' . time();
$_SESSION['payment_reference'] = $reference;

// Convert amount to kobo (Paystack uses kobo)
$amount_in_kobo = (int)($total_amount * 100);

// Subaccount and split config
$subaccount_code  = defined('PAYSTACK_SUBACCOUNT_CODE') ? PAYSTACK_SUBACCOUNT_CODE : 'ACCT_3k06e4jqesf2v3u';
$split_percentage = defined('PAYSTACK_SPLIT_PERCENTAGE') ? (int)PAYSTACK_SPLIT_PERCENTAGE : 100;

if ($split_percentage < 0 || $split_percentage > 100) {
    error_log("Invalid PAYSTACK_SPLIT_PERCENTAGE: $split_percentage. Using default 100.");
    $split_percentage = 100;
}

$transaction_charge = (int)(($split_percentage / 100) * $amount_in_kobo);

// Callback URL: Paystack redirects here after payment
$callback_url = SITE_URL . '/bookings/verify_payment.php?reference=' . urlencode($reference);

// Build metadata
$metadata = [
    'custom_fields' => [
        ['display_name' => 'Primary Passenger', 'variable_name' => 'passenger_name', 'value' => $passenger_name],
        ['display_name' => 'Trip Type',          'variable_name' => 'trip_type',      'value' => $trip_type],
        ['display_name' => 'Route',              'variable_name' => 'trip_route',     'value' => $trip_route],
        ['display_name' => 'Seats',              'variable_name' => 'seats',          'value' => $seats_meta],
        ['display_name' => 'Booking IDs',        'variable_name' => 'booking_ids',    'value' => implode(',', $booking_ids)],
        ['display_name' => 'PNRs',               'variable_name' => 'pnrs',           'value' => implode(',', $pnrs)],
    ]
];

// Initialize transaction via Paystack API
$payload = json_encode([
    'email'              => $email,
    'amount'             => $amount_in_kobo,
    'currency'           => 'NGN',
    'reference'          => $reference,
    'callback_url'       => $callback_url,
    'subaccount'         => $subaccount_code,
    'transaction_charge' => $transaction_charge,
    'metadata'           => $metadata,
]);

$curl = curl_init();

// Detect if running on localhost/development to skip SSL cert issues
$is_local = (
    defined('APP_ENV') && APP_ENV === 'local'
) || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) 
  || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');

$curl_options = [
    CURLOPT_URL            => "https://api.paystack.co/transaction/initialize",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ],
    CURLOPT_TIMEOUT        => 30,
];

if ($is_local) {
    // On localhost (XAMPP/WAMP etc.), skip SSL verification to avoid cacert.pem errors
    $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
    $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
    error_log("Paystack: running in LOCAL mode — SSL verification disabled.");
} else {
    // On production, enforce SSL properly
    $curl_options[CURLOPT_SSL_VERIFYPEER] = true;
    $curl_options[CURLOPT_SSL_VERIFYHOST] = 2;
}

curl_setopt_array($curl, $curl_options);

$response = curl_exec($curl);
$err      = curl_error($curl);
curl_close($curl);

if ($err) {
    error_log("Paystack initialize API error: $err");
    $_SESSION['error'] = 'Payment could not be initialized. Please try again.';
    header("Location: passenger_details.php");
    exit();
}

$result = json_decode($response, true);

if (!isset($result['status']) || $result['status'] !== true || empty($result['data']['authorization_url'])) {
    error_log("Paystack initialize failed: " . json_encode($result));
    $_SESSION['error'] = 'Payment could not be started. Please try again.';
    header("Location: passenger_details.php");
    exit();
}

// Send user directly to Paystack's hosted payment page
$authorization_url = $result['data']['authorization_url'];

ob_end_clean();
header("Location: " . $authorization_url);
exit();
?>