<?php
// process_charter.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/send_email.php';

// Set response header for JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 1. Sanitize and Validate Input
$required_fields = ['full_name', 'email', 'phone', 'pickup_location', 'dropoff_location', 'departure_date', 'num_seats'];
$charter_data = [];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
    $charter_data[$field] = trim($_POST[$field]);
}

// Non-required fields
$charter_data['notes'] = trim($_POST['notes'] ?? '');
$charter_data['vehicle_type_id'] = trim($_POST['vehicle_type_id'] ?? '');

// Validate email
if (!filter_var($charter_data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}

// Validate departure date
if (strtotime($charter_data['departure_date']) === false) {
    $errors[] = 'Invalid departure date.';
}

// Validate pickup and dropoff locations (ensure they are different)
if ($charter_data['pickup_location'] === $charter_data['dropoff_location']) {
    $errors[] = 'Pickup and dropoff locations cannot be the same.';
}

// Validate number of seats
if ($charter_data['num_seats'] < 1) {
    $errors[] = 'Number of passengers must be at least 1.';
}

// Fetch vehicle type name (if provided)
$charter_data['preferred_vehicle'] = '';
if (!empty($charter_data['vehicle_type_id'])) {
    try {
        $stmt = $conn->prepare("SELECT type FROM vehicle_types WHERE id = ?");
        $stmt->bind_param("i", $charter_data['vehicle_type_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $charter_data['preferred_vehicle'] = $result->fetch_assoc()['type'];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching vehicle data: " . $e->getMessage());
        $errors[] = 'Error processing vehicle information.';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

// 2. Save to Database
try {
    $stmt = $conn->prepare("
        INSERT INTO charter_requests 
        (name, email, phone, pickup_location, dropoff_location, departure_date, passengers, preferred_vehicle, additional_notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssssiss",
        $charter_data['full_name'],
        $charter_data['email'],
        $charter_data['phone'],
        $charter_data['pickup_location'],
        $charter_data['dropoff_location'],
        $charter_data['departure_date'],
        $charter_data['num_seats'],
        $charter_data['preferred_vehicle'],
        $charter_data['notes']
    );

    if (!$stmt->execute()) {
        throw new Exception("Database execution failed: " . $stmt->error);
    }
    
    $stmt->close();
    
    // 3. Send Confirmation Emails
    // (Assuming sendCharterUserConfirmationEmail and sendCharterAdminNotificationEmail are defined elsewhere)
    $user_email_success = sendCharterUserConfirmationEmail($charter_data);
    $admin_email_success = sendCharterAdminNotificationEmail($charter_data);
    
    // 4. Final Response
    if ($user_email_success && $admin_email_success) {
        echo json_encode([
            'success' => true,
            'message' => 'Charter request submitted successfully! A confirmation has been sent to your email.'
        ]);
    } else {
        error_log("Charter request ID " . $conn->insert_id . " saved, but one or more emails failed.");
        echo json_encode([
            'success' => true,
            'message' => 'Charter request submitted. However, there was an issue sending the confirmation email. We will contact you shortly.'
        ]);
    }
} catch (Exception $e) {
    error_log("Charter Submission Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
?>