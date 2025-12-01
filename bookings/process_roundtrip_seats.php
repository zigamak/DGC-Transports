<?php
// bookings/process_roundtrip_seats.php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_SESSION['roundtrip'])) {
    echo json_encode(['success' => false, 'message' => 'No round trip booking found in session']);
    exit();
}

$outbound_seats = json_decode($_POST['outbound_seats'], true);
$return_seats = json_decode($_POST['return_seats'], true);

if (!$outbound_seats || !$return_seats) {
    echo json_encode(['success' => false, 'message' => 'Invalid seat selection']);
    exit();
}

$roundtrip = $_SESSION['roundtrip'];
$num_seats = $roundtrip['num_seats'];

// Validate seat counts
if (count($outbound_seats) !== $num_seats || count($return_seats) !== $num_seats) {
    echo json_encode(['success' => false, 'message' => 'Seat count mismatch']);
    exit();
}

// Validate outbound seats availability
$outbound_template_id = $roundtrip['outbound']['templateId'];
$outbound_trip_date = $roundtrip['outbound']['tripDate'];

$placeholders = str_repeat('?,', count($outbound_seats) - 1) . '?';
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status = 'confirmed'
        AND seat_number IN ($placeholders)
");

$params = array_merge([$outbound_template_id, $outbound_trip_date], $outbound_seats);
$types = 'is' . str_repeat('s', count($outbound_seats));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($result)) {
    $conflicting_seats = array_column($result, 'seat_number');
    echo json_encode([
        'success' => false, 
        'message' => 'Outbound seats ' . implode(', ', $conflicting_seats) . ' are no longer available'
    ]);
    exit();
}

// Validate return seats availability
$return_template_id = $roundtrip['return']['templateId'];
$return_trip_date = $roundtrip['return']['tripDate'];

$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status = 'confirmed'
        AND seat_number IN ($placeholders)
");

$params = array_merge([$return_template_id, $return_trip_date], $return_seats);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($result)) {
    $conflicting_seats = array_column($result, 'seat_number');
    echo json_encode([
        'success' => false, 
        'message' => 'Return seats ' . implode(', ', $conflicting_seats) . ' are no longer available'
    ]);
    exit();
}

// **FIX: Store seats in the CORRECT session structure**
// This is what passenger_details.php expects!
$_SESSION['roundtrip_seats'] = [
    'outbound' => $outbound_seats,
    'return' => $return_seats
];

// Log for debugging
error_log("Round trip seats stored successfully:");
error_log("Outbound seats: " . implode(', ', $outbound_seats));
error_log("Return seats: " . implode(', ', $return_seats));

echo json_encode(['success' => true]);
?>