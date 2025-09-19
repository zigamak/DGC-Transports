<?php
// bookings/process_booking.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

// Check if trip and seats are selected
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['selected_num_seats'])) {
    header("Location: search_trips.php");
    exit();
}

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: passenger_details.php");
    exit();
}

// Validate required fields
$required_fields = ['passenger_name', 'email', 'phone', 'terms'];
$errors = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        if ($field === 'terms') {
            $errors[] = "You must accept the terms and conditions";
        } else {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }
}

// Validate email format
if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address";
}

// Validate phone number (basic validation)
if (!empty($_POST['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $_POST['phone'])) {
    $errors[] = "Please enter a valid phone number";
}

// If there are validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: passenger_details.php");
    exit();
}

// Store passenger details in session
$passenger_details = [
    'passenger_name' => trim($_POST['passenger_name']),
    'email' => trim($_POST['email']),
    'phone' => trim($_POST['phone']),
    'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
    'special_requests' => trim($_POST['special_requests'] ?? '')
];

$_SESSION['passenger_details'] = $passenger_details;

// Clear any previous form errors
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

// Redirect to payment page
header("Location: paystack.php");
exit();
?>