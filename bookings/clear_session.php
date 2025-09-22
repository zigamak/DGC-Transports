<?php
// bookings/clear_session.php
// Clear all session data
session_start();
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
echo json_encode(['status' => 'cleared']);
?>