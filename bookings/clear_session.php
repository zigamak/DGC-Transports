<?php
// bookings/clear_session.php
// Simple file to clear booking confirmation from session
session_start();
unset($_SESSION['booking_confirmation']);
echo json_encode(['status' => 'cleared']);
?>