<?php
// staff/confirm_booking.php

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
// check_role('staff'); // Uncomment this to enforce role-based access

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // Update the booking status to 'confirmed'
    $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);

    if ($stmt->execute()) {
        $message = "Booking confirmed successfully!";
    } else {
        $message = "Error confirming booking: " . $stmt->error;
    }
    $stmt->close();

    // Redirect back to the PNR check page with a message
    header("Location: check_pnr.php?message=" . urlencode($message));
    exit();
} else {
    header("Location: check_pnr.php");
    exit();
}
?>
