<?php
// bookings/process_booking.php


session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Add debugging
error_log("process_booking.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data received: " . print_r($_POST, true));
error_log("Session data: " . print_r($_SESSION, true));

// Ensure logs directory exists
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
// Simple test to verify the file is being accessed
file_put_contents("$log_dir/debug.log", date('Y-m-d H:i:s') . " - process_booking.php accessed\n", FILE_APPEND);

// Check if trip and seats are selected
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats'])) {
    error_log("Missing selected_trip or selected_seats in session");
    $_SESSION['error'] = 'Please select a trip and seats before proceeding.';
    header("Location: " . SITE_URL . "/bookings/search_trips.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error'] = 'Invalid form submission.';
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$num_seats = count($selected_seats);

// Calculate total amount - NO DISCOUNTS
$total_amount = $num_seats * $trip['price'];
$credits_to_use = 0;

// Check if user is logged in
$user_id = isLoggedIn() ? $_SESSION['user']['id'] : null;

// Handle credits for logged-in users
if ($user_id && isset($_POST['use_credits']) && $_POST['use_credits'] === 'on') {
    $stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Failed to prepare query for user credits: " . $conn->error);
        $_SESSION['error'] = 'Error retrieving user credits. Please try again.';
        header("Location: " . SITE_URL . "/bookings/passenger_details.php");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $user_credits = $user['credits'] ?? 0;
    if ($user_credits > 0) {
        $credits_to_use = min($user_credits, $total_amount);
        $total_amount = $total_amount - $credits_to_use;
        error_log("Using credits: $credits_to_use for user ID: $user_id");
    } else {
        error_log("No credits available for user ID: $user_id");
    }
}

// Handle referral code - ONLY STORE FOR CREDITS, NO DISCOUNT TO CURRENT USER
if (isset($_POST['referral_code']) && !empty(trim($_POST['referral_code']))) {
    $referral_code = strtoupper(trim($_POST['referral_code']));
    error_log("Processing referral code from form submission: $referral_code");
    
    // Check if referral code exists and find the referrer
    $stmt = $conn->prepare("SELECT u.id as referrer_id, u.first_name, u.last_name 
                           FROM users u 
                           WHERE u.affiliate_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $referral_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $referrer = $result->fetch_assoc();
        $stmt->close();
        
        if ($referrer) {
            // Get referral credit amount from settings
            $stmt = $conn->prepare("SELECT credits FROM referral_settings WHERE id = 1");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                $referral_settings = $result->fetch_assoc();
                $referral_credits = $result->num_rows > 0 ? $referral_settings['credits'] : 2000;
                $stmt->close();
                
                // Store in session for later use in verify_payment.php
                $_SESSION['referral_code'] = $referral_code;
                $_SESSION['referrer_id'] = $referrer['referrer_id'];
                $referrer_name = $referrer['first_name'] . ' ' . $referrer['last_name'];
                $_SESSION['referral_message'] = "Referral code valid! Referred by $referrer_name.";
                
                error_log("Referral code $referral_code stored: Credits for referrer: $referral_credits, Referrer ID: " . $referrer['referrer_id']);
            }
        } else {
            error_log("Invalid referral code from form: $referral_code");
            $_SESSION['referral_error'] = 'Invalid referral code';
        }
    }
}

// Validate required fields
if (!isset($_POST['passengers']) || !is_array($_POST['passengers']) || count($_POST['passengers']) !== $num_seats) {
    error_log("Invalid passenger data: " . json_encode([
        'post_passengers' => $_POST['passengers'] ?? 'not set',
        'expected_seats' => $num_seats,
        'actual_count' => is_array($_POST['passengers']) ? count($_POST['passengers']) : 'not array'
    ]));
    $_SESSION['error'] = 'Invalid passenger data provided. Please ensure all passenger details are complete.';
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

if (!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
    error_log("Terms and conditions not accepted");
    $_SESSION['error'] = 'You must accept the terms and conditions.';
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
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
        $passenger_data['passenger_name'] = trim($passenger['name']);
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
        $passenger_data['seat_number'] = $passenger['seat_number'];
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
    error_log("Validation errors: " . implode('; ', $errors));
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST; // Preserve form data
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

// Start database transaction to check and reserve seats
$conn->begin_transaction();

try {
    // Log selected trip details
    error_log("Selected trip details: " . json_encode($trip));

    // Verify trip template exists
    $stmt = $conn->prepare("SELECT id FROM trip_templates WHERE id = ? AND status = 'active'");
    if (!$stmt) {
        throw new Exception('Failed to prepare trip_templates query: ' . $conn->error);
    }
    $stmt->bind_param("i", $trip['template_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $trip_template = $result->fetch_assoc();
    $stmt->close();

    if (!$trip_template) {
        error_log("No active trip template found for template_id {$trip['template_id']}");
        throw new Exception('Invalid trip template ID: ' . $trip['template_id']);
    }

    // Fetch or create trip_id from trip_instances
    $stmt = $conn->prepare("
        SELECT id 
        FROM trip_instances 
        WHERE template_id = ? AND trip_date = ?
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare trip_instances query: ' . $conn->error);
    }
    $stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
    $stmt->execute();
    $result = $stmt->get_result();
    $trip_instance = $result->fetch_assoc();
    $stmt->close();

    if (!$trip_instance) {
        // Create new trip instance
        $stmt = $conn->prepare("
            INSERT INTO trip_instances (template_id, trip_date, booked_seats, status)
            VALUES (?, ?, 0, 'active')
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare trip_instances insert query: ' . $conn->error);
        }
        $stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create trip instance: ' . $stmt->error);
        }
        $trip_id = $conn->insert_id;
        $stmt->close();
        error_log("Created new trip instance with ID: $trip_id for template_id {$trip['template_id']} and trip_date {$trip['trip_date']}");
    } else {
        $trip_id = $trip_instance['id'];
        error_log("Found existing trip instance with ID: $trip_id for template_id {$trip['template_id']} and trip_date {$trip['trip_date']}");
    }

    // Check seat availability with FOR UPDATE to lock rows
    $placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT seat_number 
        FROM bookings
        WHERE template_id = ? 
            AND trip_date = ? 
            AND status != 'cancelled'
            AND seat_number IN ($placeholders)
        FOR UPDATE
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare seat availability query: ' . $conn->error);
    }
    
    $params = [$trip['template_id'], $trip['trip_date']];
    $params = array_merge($params, $selected_seats);
    $types = 'is' . str_repeat('s', count($selected_seats));
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($booked_seats)) {
        $conflicting_seats = array_column($booked_seats, 'seat_number');
        error_log("Conflicting seats detected: " . implode(', ', $conflicting_seats));
        throw new Exception('Sorry, some of your selected seats (' . implode(', ', $conflicting_seats) . ') have been booked by another passenger.');
    }
    
    $booking_ids = [];
    $pnrs = [];
    
    // Create booking records for each passenger
    foreach ($passenger_details as $index => $passenger) {
        // Generate unique PNR
        $pnr = generatePNR();
        
        // Calculate individual booking amount
        $booking_amount = $trip['price'];
        $payment_reference = null; // Set in verify_payment.php
        $updated_at = null; // Set in verify_payment.php
        
        // Insert booking record
        $stmt = $conn->prepare("
            INSERT INTO bookings 
            (user_id, pnr, passenger_name, email, phone, emergency_contact, special_requests, 
             template_id, trip_date, seat_number, total_amount, payment_reference, 
             payment_status, status, created_at, updated_at, trip_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param(
            "issssssisidssi",
            $user_id,
            $pnr,
            $passenger['passenger_name'],
            $passenger['email'],
            $passenger['phone'],
            $passenger['emergency_contact'],
            $passenger['special_requests'],
            $trip['template_id'],
            $trip['trip_date'],
            $passenger['seat_number'],
            $booking_amount,
            $payment_reference,
            $updated_at,
            $trip_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create booking for ' . $passenger['passenger_name'] . ': ' . $stmt->error);
        }
        
        $booking_id = $conn->insert_id;
        $booking_ids[] = $booking_id;
        $pnrs[] = $pnr;
        
        // Update passenger details with PNR and booking ID
        $passenger_details[$index]['pnr'] = $pnr;
        $passenger_details[$index]['booking_id'] = $booking_id;
        
        $stmt->close();
        
        error_log("Created booking ID: $booking_id with PNR: $pnr for passenger: " . $passenger['passenger_name']);
    }
    
    // Deduct credits if used
    if ($user_id && $credits_to_use > 0) {
        $stmt = $conn->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
        if (!$stmt) {
            throw new Exception('Failed to prepare credit deduction query: ' . $conn->error);
        }
        $stmt->bind_param("dii", $credits_to_use, $user_id, $credits_to_use);
        if (!$stmt->execute()) {
            throw new Exception('Failed to deduct credits: ' . $stmt->error);
        }
        if ($stmt->affected_rows === 0) {
            throw new Exception('Insufficient credits or user not found.');
        }
        $stmt->close();
        
        // Log credit transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (user_id, amount, type, booking_id, created_at)
            VALUES (?, ?, 'debit', ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare credit transaction log query: ' . $conn->error);
        }
        $first_booking_id = $booking_ids[0];
        $stmt->bind_param("idi", $user_id, $credits_to_use, $first_booking_id);
        $stmt->execute();
        $stmt->close();
        
        error_log("Deducted $credits_to_use credits for user ID: $user_id");
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Store booking data in session
    $_SESSION['passenger_details'] = $passenger_details;
    $_SESSION['booking_ids'] = $booking_ids;
    $_SESSION['pnrs'] = $pnrs;
    $_SESSION['total_amount'] = $total_amount; // Amount to pay via Paystack
    $_SESSION['credits_used'] = $credits_to_use;
    
    // Clear any previous errors
    unset($_SESSION['error']);
    unset($_SESSION['errors']);
    unset($_SESSION['form_data']);
    unset($_SESSION['referral_error']);
    
    error_log("Successfully created " . count($booking_ids) . " bookings: " . implode(', ', $booking_ids));
    error_log("Total amount to pay (after credits): ₦$total_amount, Credits used: ₦$credits_to_use");
    
    // Redirect to payment page
    header("Location: " . SITE_URL . "/bookings/paystack.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Booking creation failed: " . $e->getMessage());
    
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $_SESSION['error'] = 'Some of your selected seats are no longer available. Please choose different seats.';
        header("Location: " . SITE_URL . "/bookings/seat_selection.php?error=duplicate_seat");
    } else {
        $_SESSION['error'] = 'Failed to create booking. Please try again. Error: ' . htmlspecialchars($e->getMessage());
        header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    }
    exit();
    
} finally {
    // Close any open statements
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>