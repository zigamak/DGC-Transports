<?php
// bookings/process_booking.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';


// Add debugging
error_log("process_booking.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data received: " . print_r($_POST, true));

// Simple test to verify the file is being accessed
file_put_contents(__DIR__ . '/../logs/debug.log', date('Y-m-d H:i:s') . " - process_booking.php accessed\n", FILE_APPEND);

// Check if trip and seats are selected
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats'])) {
    header("Location: search_trips.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: passenger_details.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$num_seats = count($selected_seats);

// Validate required fields
if (!isset($_POST['passengers']) || !is_array($_POST['passengers']) || count($_POST['passengers']) !== $num_seats) {
    $_SESSION['error'] = 'Invalid passenger data provided.';
    header("Location: passenger_details.php");
    exit();
}

if (!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
    $_SESSION['error'] = 'You must accept the terms and conditions.';
    header("Location: passenger_details.php");
    exit();
}

$passenger_details = [];
$errors = [];

// Validate each passenger's details
foreach ($_POST['passengers'] as $index => $passenger) {
    $passenger_data = [];
    
    // Validate name
    if (empty(trim($passenger['name']))) {
        $errors[] = "Name is required for Passenger " . ($index + 1);
    } else {
        $passenger_data['name'] = trim($passenger['name']);
    }
    
    // Validate email
    if (empty(trim($passenger['email']))) {
        $errors[] = "Email is required for Passenger " . ($index + 1);
    } elseif (!filter_var($passenger['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format for Passenger " . ($index + 1);
    } else {
        $passenger_data['email'] = trim($passenger['email']);
    }
    
    // Validate phone
    if (empty(trim($passenger['phone']))) {
        $errors[] = "Phone number is required for Passenger " . ($index + 1);
    } else {
        $phone = preg_replace('/[^0-9+]/', '', $passenger['phone']);
        if (strlen($phone) < 10) {
            $errors[] = "Invalid phone number for Passenger " . ($index + 1);
        } else {
            $passenger_data['phone'] = $phone;
        }
    }
    
    // Validate seat number
    if (empty($passenger['seat_number']) || !in_array($passenger['seat_number'], $selected_seats)) {
        $errors[] = "Invalid seat assignment for Passenger " . ($index + 1);
    } else {
        $passenger_data['seat_number'] = (int)$passenger['seat_number'];
    }
    
    // Optional fields
    $passenger_data['emergency_contact'] = !empty($passenger['emergency_contact']) ? trim($passenger['emergency_contact']) : '';
    
    if ($index === 0) { // Only first passenger can have special requests
        $passenger_data['special_requests'] = !empty($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
    } else {
        $passenger_data['special_requests'] = '';
    }
    
    if (empty($errors)) {
        $passenger_details[] = $passenger_data;
    }
}

// If there are validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST; // Preserve form data
    header("Location: passenger_details.php");
    exit();
}

// Check seat availability one more time before proceeding
$placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
$stmt = $conn->prepare("
    SELECT seat_number 
    FROM bookings
    WHERE template_id = ? 
        AND trip_date = ? 
        AND payment_status = 'paid'
        AND status != 'cancelled'
        AND seat_number IN ($placeholders)
");

$params = [$trip['template_id'], $trip['trip_date']];
$params = array_merge($params, $selected_seats);
$types = 'is' . str_repeat('i', count($selected_seats));

$stmt->bind_param($types, ...$params);
$stmt->execute();
$booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($booked_seats)) {
    $conflicting_seats = array_column($booked_seats, 'seat_number');
    $_SESSION['error'] = 'Sorry, some of your selected seats (' . implode(', ', $conflicting_seats) . ') have been booked by another passenger. Please select different seats.';
    header("Location: seat_selection.php");
    exit();
}

// Start database transaction to create booking records
$conn->begin_transaction();

try {
    $booking_ids = [];
    
    // Create booking records for each passenger
    foreach ($passenger_details as $index => $passenger) {
        // Generate unique PNR (Passenger Name Record)
        $pnr = generatePNR();

        
        // Insert booking record
        $stmt = $conn->prepare("
            INSERT INTO bookings 
            (pnr, template_id, trip_date, passenger_name, email, phone, 
             emergency_contact, special_requests, seat_number, total_amount, payment_status, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
        ");
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param(
            "sissssssid",
            $pnr,
            $trip['template_id'],
            $trip['trip_date'],
            $passenger['name'],
            $passenger['email'],
            $passenger['phone'],
            $passenger['emergency_contact'],
            $passenger['special_requests'],
            $passenger['seat_number'],
            $trip['price']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create booking for ' . $passenger['name'] . ': ' . $stmt->error);
        }
        
        $booking_id = $conn->insert_id;
        $booking_ids[] = $booking_id;
        
        // Update passenger details with PNR and booking ID
        $passenger_details[$index]['pnr'] = $pnr;
        $passenger_details[$index]['booking_id'] = $booking_id;
        
        $stmt->close();
        
        error_log("Created booking ID: $booking_id with PNR: $pnr for passenger: " . $passenger['name']);
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Store booking data in session
    $_SESSION['passenger_details'] = $passenger_details;
    $_SESSION['booking_ids'] = $booking_ids;
    
    // Clear any previous errors
    unset($_SESSION['error']);
    unset($_SESSION['errors']);
    unset($_SESSION['form_data']);
    
    error_log("Successfully created " . count($booking_ids) . " bookings: " . implode(', ', $booking_ids));
    
    // Redirect to payment page
    header("Location: paystack.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Booking creation failed: " . $e->getMessage());
    
    $_SESSION['error'] = 'Failed to create booking. Please try again. Error: ' . $e->getMessage();
    header("Location: passenger_details.php");
    exit();
    
} finally {
    // Close any open statements
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>