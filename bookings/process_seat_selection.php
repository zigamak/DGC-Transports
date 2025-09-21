<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Check if trip and seats are provided
if (!isset($_SESSION['selected_trip']) || !isset($_POST['selected_seats'])) {
    header("Location: search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = json_decode($_POST['selected_seats'], true);

// Validate selected seats
if (!is_array($selected_seats) || count($selected_seats) !== $trip['num_seats']) {
    header("Location: seat_selection.php");
    exit();
}

// Validate seats are not already booked
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status != 'cancelled'
");
$stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
$stmt->execute();
$booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$booked_seat_numbers = array_column($booked_seats, 'seat_number');
$stmt->close();

foreach ($selected_seats as $seat) {
    if (in_array($seat, $booked_seat_numbers)) {
        header("Location: seat_selection.php?error=seat_already_booked");
        exit();
    }
}

// Save selected seats to session
$_SESSION['selected_seats'] = $selected_seats;

// Redirect to passenger details
header("Location: passenger_details.php");
exit();
?>