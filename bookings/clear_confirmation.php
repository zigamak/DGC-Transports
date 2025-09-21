<?php
// bookings/clear_confirmation.php
session_start();

header('Content-Type: application/json');

// Clear any remaining session data
unset($_SESSION['booking_confirmation']);
unset($_SESSION['form_data']);
unset($_SESSION['errors']);
unset($_SESSION['error']);

echo json_encode(['success' => true, 'message' => 'Session cleared successfully']);
?>