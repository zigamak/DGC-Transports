<?php
// bookings/process_booking.php - Updated for Round Trip Support

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

error_log("process_booking.php accessed - Method: " . $_SERVER['REQUEST_METHOD']);

$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
file_put_contents("$log_dir/debug.log", date('Y-m-d H:i:s') . " - process_booking.php accessed\n", FILE_APPEND);

// Check if it's a round trip booking - ONLY trust explicit POST data, not stale session
$is_roundtrip = (isset($_POST['is_roundtrip']) && $_POST['is_roundtrip'] == '1');

// Update session to match current request
$_SESSION['is_roundtrip'] = $is_roundtrip;

// Store in session for consistency
$_SESSION['is_roundtrip'] = $is_roundtrip;

if ($is_roundtrip) {
    // Round trip validation
    if (!isset($_SESSION['roundtrip']) || !isset($_SESSION['roundtrip_seats'])) {
        error_log("Missing roundtrip data in session");
        error_log("roundtrip exists: " . (isset($_SESSION['roundtrip']) ? 'yes' : 'no'));
        error_log("roundtrip_seats exists: " . (isset($_SESSION['roundtrip_seats']) ? 'yes' : 'no'));
        $_SESSION['error'] = 'Please select a round trip before proceeding.';
        header("Location: " . SITE_URL . "/index.php");
        exit();
    }
} else {
    // One-way validation
    if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats'])) {
        error_log("Missing selected_trip or selected_seats in session");
        error_log("selected_trip exists: " . (isset($_SESSION['selected_trip']) ? 'yes' : 'no'));
        error_log("selected_seats exists: " . (isset($_SESSION['selected_seats']) ? 'yes' : 'no'));
        $_SESSION['error'] = 'Please select a trip and seats before proceeding.';
        header("Location: " . SITE_URL . "/bookings/search_trips.php");
        exit();
    }
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error'] = 'Invalid form submission.';
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

if ($is_roundtrip) {
    // Round trip booking
    $roundtrip = $_SESSION['roundtrip'];
    $roundtrip_seats = $_SESSION['roundtrip_seats'];
    
    $outbound = $roundtrip['outbound'];
    $return = $roundtrip['return'];
    $outbound_seats = $roundtrip_seats['outbound'];
    $return_seats = $roundtrip_seats['return'];
    $num_seats = $roundtrip['num_seats'];
    
    // Calculate total amount for both trips
    $total_amount = ($outbound['price'] + $return['price']) * $num_seats;
    
} else {
    // One-way booking
    $trip = $_SESSION['selected_trip'];
    $selected_seats = $_SESSION['selected_seats'];
    $num_seats = count($selected_seats);
    $total_amount = $num_seats * $trip['price'];
}

$credits_to_use = 0;
$user_id = isLoggedIn() ? $_SESSION['user']['id'] : null;

// Handle credits for logged-in users
if ($user_id && isset($_POST['use_credits']) && $_POST['use_credits'] === 'on') {
    $stmt = $conn->prepare("SELECT credits FROM users WHERE id = ?");
    if ($stmt) {
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
        }
    }
}

// Handle referral code
$referral_code = null;
if (isset($_POST['referral_code']) && !empty(trim($_POST['referral_code']))) {
    $referral_code = strtoupper(trim($_POST['referral_code']));
    error_log("Processing referral code from form submission: $referral_code");
    
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
            $stmt = $conn->prepare("SELECT credits FROM referral_settings WHERE id = 1");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                $referral_settings = $result->fetch_assoc();
                $referral_credits = $referral_settings ? ($referral_settings['credits'] ?? 2000) : 2000;
                $stmt->close();
                
                $_SESSION['referral_code'] = $referral_code;
                $_SESSION['referrer_id'] = $referrer['referrer_id'];
                $referrer_name = $referrer['first_name'] . ' ' . $referrer['last_name'];
                $_SESSION['referral_message'] = "Referral code valid! Referred by $referrer_name.";
                
                error_log("Referral code $referral_code stored: Credits for referrer: $referral_credits, Referrer ID: " . $referrer['referrer_id']);
            }
        } else {
            error_log("Invalid referral code from form: $referral_code");
            $_SESSION['referral_error'] = 'Invalid referral code';
            $referral_code = null;
        }
    }
}

// Validate required fields
if (!isset($_POST['passengers']) || !is_array($_POST['passengers']) || count($_POST['passengers']) !== $num_seats) {
    error_log("Invalid passenger data");
    error_log("POST passengers exists: " . (isset($_POST['passengers']) ? 'yes' : 'no'));
    error_log("POST passengers is array: " . (is_array($_POST['passengers']) ? 'yes' : 'no'));
    error_log("POST passengers count: " . (isset($_POST['passengers']) ? count($_POST['passengers']) : 'N/A'));
    error_log("Expected num_seats: " . $num_seats);
    $_SESSION['error'] = 'Invalid passenger data provided. Please ensure all passenger details are complete.';
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

// Debug: Log the structure of passenger data
error_log("Passenger POST data structure:");
foreach ($_POST['passengers'] as $idx => $pass) {
    error_log("Passenger $idx keys: " . implode(', ', array_keys($pass)));
    if ($is_roundtrip) {
        error_log("  - outbound_seat: " . ($pass['outbound_seat'] ?? 'MISSING'));
        error_log("  - return_seat: " . ($pass['return_seat'] ?? 'MISSING'));
    } else {
        error_log("  - seat_number: " . ($pass['seat_number'] ?? 'MISSING'));
    }
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
    
    if (empty(trim($passenger['name']))) {
        $errors[] = "Name is required for Passenger " . ($index + 1);
    } else {
        $passenger_data['passenger_name'] = trim($passenger['name']);
    }
    
    if (empty(trim($passenger['email']))) {
        $errors[] = "Email is required for Passenger " . ($index + 1);
    } elseif (!filter_var($passenger['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format for Passenger " . ($index + 1);
    } else {
        $passenger_data['email'] = trim($passenger['email']);
    }
    
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
    
    if ($is_roundtrip) {
        // For round trip, validate both seat assignments
        error_log("Round trip validation - Passenger $index: outbound_seat=" . ($passenger['outbound_seat'] ?? 'NOT SET') . ", return_seat=" . ($passenger['return_seat'] ?? 'NOT SET'));
        
        if (!isset($passenger['outbound_seat']) || !isset($passenger['return_seat'])) {
            $errors[] = "Invalid seat assignment for Passenger " . ($index + 1) . " (missing seat data)";
            error_log("Missing seat data for passenger $index");
        } elseif (empty($passenger['outbound_seat']) || empty($passenger['return_seat'])) {
            $errors[] = "Invalid seat assignment for Passenger " . ($index + 1) . " (empty seat data)";
            error_log("Empty seat data for passenger $index");
        } else {
            $passenger_data['outbound_seat'] = $passenger['outbound_seat'];
            $passenger_data['return_seat'] = $passenger['return_seat'];
            error_log("Seats validated for passenger $index: outbound={$passenger['outbound_seat']}, return={$passenger['return_seat']}");
        }
    } else {
        // For one-way, validate single seat
        if (empty($passenger['seat_number']) || !in_array($passenger['seat_number'], $selected_seats)) {
            $errors[] = "Invalid seat assignment for Passenger " . ($index + 1);
        } else {
            $passenger_data['seat_number'] = $passenger['seat_number'];
        }
    }
    
    $passenger_data['emergency_contact'] = !empty($passenger['emergency_contact']) ? trim($passenger['emergency_contact']) : '';
    
    if ($index === 0) {
        $passenger_data['special_requests'] = !empty($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
    } else {
        $passenger_data['special_requests'] = '';
    }
    
    $passenger_details[] = $passenger_data;
}

if (!empty($errors)) {
    error_log("Validation errors: " . implode('; ', $errors));
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    exit();
}

// Start database transaction
$conn->begin_transaction();

try {
    $booking_ids = [];
    $pnrs = [];
    
    if ($is_roundtrip) {
        // Process OUTBOUND bookings
        foreach ($passenger_details as $index => $passenger) {
            $pnr = generatePNR();
            $booking_amount = $outbound['price'];
            
            // Fetch or create trip_id for outbound
            $stmt = $conn->prepare("SELECT id FROM trip_instances WHERE template_id = ? AND trip_date = ?");
            $stmt->bind_param("is", $outbound['templateId'], $outbound['tripDate']);
            $stmt->execute();
            $result = $stmt->get_result();
            $trip_instance = $result->fetch_assoc();
            $stmt->close();
            
            if (!$trip_instance) {
                $stmt = $conn->prepare("INSERT INTO trip_instances (template_id, trip_date, booked_seats, status) VALUES (?, ?, 0, 'active')");
                $stmt->bind_param("is", $outbound['templateId'], $outbound['tripDate']);
                $stmt->execute();
                $outbound_trip_id = $conn->insert_id;
                $stmt->close();
            } else {
                $outbound_trip_id = $trip_instance['id'];
            }
            
            $user_id_bind = $user_id;
            $payment_reference_bind = null;
            $free_booking_used = 0;
            
            $stmt = $conn->prepare("
                INSERT INTO bookings 
                (user_id, pnr, passenger_name, email, phone, emergency_contact, special_requests, 
                 template_id, trip_date, seat_number, total_amount, payment_reference, 
                 payment_status, status, created_at, updated_at, trip_id, free_booking_used, is_roundtrip, roundtrip_leg) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NULL, ?, ?, 1, 'outbound')
            ");
            
            $stmt->bind_param(
                "issssssisdsiii",
                $user_id_bind, $pnr, $passenger['passenger_name'], $passenger['email'], $passenger['phone'],
                $passenger['emergency_contact'], $passenger['special_requests'],
                $outbound['templateId'], $outbound['tripDate'], $passenger['outbound_seat'],
                $booking_amount, $payment_reference_bind, $outbound_trip_id, $free_booking_used
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create outbound booking: ' . $stmt->error);
            }
            
            $booking_ids[] = $conn->insert_id;
            $pnrs[] = $pnr;
            $stmt->close();
        }
        
        // Process RETURN bookings
        foreach ($passenger_details as $index => $passenger) {
            $pnr = generatePNR();
            $booking_amount = $return['price'];
            
            // Fetch or create trip_id for return
            $stmt = $conn->prepare("SELECT id FROM trip_instances WHERE template_id = ? AND trip_date = ?");
            $stmt->bind_param("is", $return['templateId'], $return['tripDate']);
            $stmt->execute();
            $result = $stmt->get_result();
            $trip_instance = $result->fetch_assoc();
            $stmt->close();
            
            if (!$trip_instance) {
                $stmt = $conn->prepare("INSERT INTO trip_instances (template_id, trip_date, booked_seats, status) VALUES (?, ?, 0, 'active')");
                $stmt->bind_param("is", $return['templateId'], $return['tripDate']);
                $stmt->execute();
                $return_trip_id = $conn->insert_id;
                $stmt->close();
            } else {
                $return_trip_id = $trip_instance['id'];
            }
            
            $user_id_bind = $user_id;
            $payment_reference_bind = null;
            $free_booking_used = 0;
            
            $stmt = $conn->prepare("
                INSERT INTO bookings 
                (user_id, pnr, passenger_name, email, phone, emergency_contact, special_requests, 
                 template_id, trip_date, seat_number, total_amount, payment_reference, 
                 payment_status, status, created_at, updated_at, trip_id, free_booking_used, is_roundtrip, roundtrip_leg) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NULL, ?, ?, 1, 'return')
            ");
            
            $stmt->bind_param(
                "issssssisdsiii",
                $user_id_bind, $pnr, $passenger['passenger_name'], $passenger['email'], $passenger['phone'],
                $passenger['emergency_contact'], $passenger['special_requests'],
                $return['templateId'], $return['tripDate'], $passenger['return_seat'],
                $booking_amount, $payment_reference_bind, $return_trip_id, $free_booking_used
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create return booking: ' . $stmt->error);
            }
            
            $booking_ids[] = $conn->insert_id;
            $pnrs[] = $pnr;
            $stmt->close();
        }
        
    } else {
        // ONE-WAY BOOKING (existing logic)
        $capacity = $trip['capacity'] ?? 5;
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as confirmed_count 
            FROM bookings 
            WHERE template_id = ? AND trip_date = ? AND payment_status = 'paid' AND status = 'confirmed'
        ");
        $stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        $count_row = $result->fetch_assoc();
        $stmt->close();
        $confirmed_count = $count_row['confirmed_count'];
        
        if (($confirmed_count + $num_seats) > $capacity) {
            throw new Exception('Sorry, the vehicle capacity has been reached for this trip.');
        }
        
        // Fetch or create trip_id
        $stmt = $conn->prepare("SELECT id FROM trip_instances WHERE template_id = ? AND trip_date = ? FOR UPDATE");
        $stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
        $stmt->execute();
        $result = $stmt->get_result();
        $trip_instance = $result->fetch_assoc();
        $stmt->close();
        
        if (!$trip_instance) {
            $stmt = $conn->prepare("INSERT INTO trip_instances (template_id, trip_date, booked_seats, status) VALUES (?, ?, 0, 'active')");
            $stmt->bind_param("is", $trip['template_id'], $trip['trip_date']);
            $stmt->execute();
            $trip_id = $conn->insert_id;
            $stmt->close();
        } else {
            $trip_id = $trip_instance['id'];
        }
        
        // Check seat availability
        $placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
        $stmt = $conn->prepare("
            SELECT seat_number 
            FROM bookings
            WHERE template_id = ? AND trip_date = ? AND payment_status = 'paid' AND status = 'confirmed' AND seat_number IN ($placeholders)
            FOR UPDATE
        ");
        
        $params = [$trip['template_id'], $trip['trip_date']];
        $params = array_merge($params, $selected_seats);
        $types = 'is' . str_repeat('s', count($selected_seats));
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $booked_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (!empty($booked_seats)) {
            $conflicting_seats = array_column($booked_seats, 'seat_number');
            throw new Exception('Sorry, some of your selected seats (' . implode(', ', $conflicting_seats) . ') have been booked by another passenger.');
        }
        
        // Create booking records
        foreach ($passenger_details as $index => $passenger) {
            $pnr = generatePNR();
            $booking_amount = $trip['price'];
            $payment_reference = null;
            $free_booking_used = 0;
            
            $user_id_bind = $user_id;
            $payment_reference_bind = $payment_reference;
            
            $stmt = $conn->prepare("
                INSERT INTO bookings 
                (user_id, pnr, passenger_name, email, phone, emergency_contact, special_requests, 
                 template_id, trip_date, seat_number, total_amount, payment_reference, 
                 payment_status, status, created_at, updated_at, trip_id, free_booking_used) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NULL, ?, ?)
            ");
            
            $stmt->bind_param(
                "issssssisdsiii",
                $user_id_bind, $pnr, $passenger['passenger_name'], $passenger['email'], $passenger['phone'],
                $passenger['emergency_contact'], $passenger['special_requests'],
                $trip['template_id'], $trip['trip_date'], $passenger['seat_number'],
                $booking_amount, $payment_reference_bind, $trip_id, $free_booking_used
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create booking for ' . $passenger['passenger_name'] . ': ' . $stmt->error);
            }
            
            $booking_id = $conn->insert_id;
            $booking_ids[] = $booking_id;
            $pnrs[] = $pnr;
            
            $passenger_details[$index]['pnr'] = $pnr;
            $passenger_details[$index]['booking_id'] = $booking_id;
            
            $stmt->close();
        }
    }
    
    // Deduct credits if used
    if ($user_id && $credits_to_use > 0) {
        $stmt = $conn->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
        $stmt->bind_param("dii", $credits_to_use, $user_id, $credits_to_use);
        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new Exception('Insufficient credits or user not found during deduction.');
        }
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO credit_transactions (user_id, amount, type, booking_id, created_at) VALUES (?, ?, 'debit', ?, NOW())");
        $first_booking_id = $booking_ids[0];
        $stmt->bind_param("idi", $user_id, $credits_to_use, $first_booking_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Commit the transaction
    $conn->commit();
    // Store booking data in session
$_SESSION['passenger_details'] = $passenger_details;
$_SESSION['booking_ids'] = $booking_ids;
$_SESSION['pnrs'] = $pnrs;
$_SESSION['total_amount'] = $total_amount;
$_SESSION['credits_used'] = $credits_to_use;

// Clear error messages
unset($_SESSION['error']);
unset($_SESSION['errors']);
unset($_SESSION['form_data']);
unset($_SESSION['referral_error']);

// DON'T clear trip data yet - payment page might need it
// Only clear seat selection data to prevent re-booking
if ($is_roundtrip) {
    // Keep roundtrip data but clear seat selections
    unset($_SESSION['roundtrip_seats']);
} else {
    // Keep selected_trip but clear seat selections
    unset($_SESSION['selected_seats']);
}

error_log("Successfully created " . count($booking_ids) . " bookings");

// Redirect to payment page
header("Location: " . SITE_URL . "/bookings/paystack.php");
exit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Booking creation failed: " . $e->getMessage());
    
    if (strpos($e->getMessage(), 'booked by another passenger') !== false || 
        strpos($e->getMessage(), 'vehicle capacity') !== false ||
        strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: " . SITE_URL . "/bookings/seat_selection.php?error=duplicate_seat");
    } else {
        $_SESSION['error'] = 'Failed to create booking. Please try again. Error: ' . htmlspecialchars($e->getMessage());
        header("Location: " . SITE_URL . "/bookings/passenger_details.php");
    }
    exit();
}
?>