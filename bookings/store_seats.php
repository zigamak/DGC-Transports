<?php
// bookings/store_seats.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['seats'])) {
        throw new Exception('No seats data provided');
    }

    // Check if trip is selected
    if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_num_seats'])) {
        throw new Exception('No trip selected');
    }

    // Decode seats data
    $selected_seats = json_decode($_POST['seats'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid seats data format');
    }

    // Validate seat count
    $required_seats = $_SESSION['selected_num_seats'];
    if (count($selected_seats) !== $required_seats) {
        throw new Exception('Invalid number of seats selected');
    }

    // Validate seat numbers are integers
    foreach ($selected_seats as $seat) {
        if (!is_numeric($seat) || $seat < 1) {
            throw new Exception('Invalid seat number: ' . $seat);
        }
    }

    $trip = $_SESSION['selected_trip'];

    // Check if seats are still available (double-check to prevent race conditions)
    $placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT seat_number 
        FROM seat_bookings sb
        JOIN bookings b ON sb.booking_id = b.id
        WHERE b.trip_id = ? AND b.payment_status = 'paid' AND seat_number IN ($placeholders)
    ");
    
    $params = array_merge([$trip['id']], $selected_seats);
    $types = str_repeat('i', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($booked_seats)) {
        $booked_numbers = array_column($booked_seats, 'seat_number');
        throw new Exception('Seats ' . implode(', ', $booked_numbers) . ' are no longer available');
    }

    // Validate seats don't exceed trip capacity
    if (max($selected_seats) > $trip['capacity']) {
        throw new Exception('Selected seat number exceeds vehicle capacity');
    }

    // Store selected seats in session
    $_SESSION['selected_seats'] = $selected_seats;

    echo json_encode([
        'success' => true,
        'message' => 'Seats stored successfully',
        'seats' => $selected_seats
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>