<?php
// bookings/clear_seats.php
session_start();

header('Content-Type: application/json');

// Clear selected seats and passenger details from session
unset($_SESSION['selected_seats']);
unset($_SESSION['passenger_details']);
unset($_SESSION['form_data']);
unset($_SESSION['errors']);
unset($_SESSION['error']);

echo json_encode(['success' => true, 'message' => 'Seats cleared successfully']);
?>