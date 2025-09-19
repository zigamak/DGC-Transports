<?php
// includes/functions.php

/**
 * Checks if the current user has the required role.
 * Redirects to the login page if not.
 * @param string $role The required role (e.g., 'admin', 'staff', 'customer').
 */
function check_role($role) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header("Location: /transport_booking/login.php");
        exit();
    }
}

/**
 * Generate a unique PNR (Passenger Name Record) for booking
 * @return string Unique PNR
 */
function generatePNR() {
    return 'DGC' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Create a new booking in the database
 * @param mysqli $conn Database connection
 * @param array $booking_data Booking information
 * @return int|false Booking ID on success, false on failure
 */
function createBooking($conn, $booking_data) {
    try {
        // Start transaction
        $conn->autocommit(FALSE);
        
        // Generate PNR
        $pnr = generatePNR();
        
        // Prepare booking insert statement
        $stmt = $conn->prepare("
            INSERT INTO bookings (
                user_id, pnr, passenger_name, email, phone, 
                emergency_contact, emergency_phone, special_requests,
                pickup_city_id, dropoff_city_id, trip_id, num_seats,
                total_amount, payment_status, booking_status, 
                payment_method, booking_source, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Extract emergency phone from emergency contact if provided
        $emergency_phone = '';
        if (!empty($booking_data['emergency_contact'])) {
            // Try to extract phone number from emergency contact string
            preg_match('/[\+]?[0-9\s\-\(\)]+/', $booking_data['emergency_contact'], $matches);
            $emergency_phone = isset($matches[0]) ? trim($matches[0]) : '';
        }
        
        // Bind parameters
        $stmt->bind_param(
            "isssssssiiidssss",
            $booking_data['user_id'],
            $pnr,
            $booking_data['passenger_name'],
            $booking_data['email'],
            $booking_data['phone'],
            $booking_data['emergency_contact'],
            $emergency_phone,
            $booking_data['special_requests'],
            $booking_data['pickup_city_id'],
            $booking_data['dropoff_city_id'],
            $booking_data['trip_id'],
            $booking_data['num_seats'],
            $booking_data['total_amount'],
            $booking_data['payment_status'],
            $booking_data['booking_status'],
            $booking_data['payment_method'],
            $booking_data['booking_source']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $booking_id = $conn->insert_id;
        
        // If booking has selected seats, save them to a seats table
        if (!empty($booking_data['selected_seats'])) {
            $seat_stmt = $conn->prepare("
                INSERT INTO booking_seats (booking_id, seat_number, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            foreach ($booking_data['selected_seats'] as $seat) {
                $seat_stmt->bind_param("is", $booking_id, $seat);
                if (!$seat_stmt->execute()) {
                    throw new Exception("Failed to save seat: " . $seat_stmt->error);
                }
            }
            $seat_stmt->close();
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(TRUE);
        
        return $booking_id;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->autocommit(TRUE);
        error_log("Booking creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a payment record
 * @param mysqli $conn Database connection
 * @param array $payment_data Payment information
 * @return int|false Payment ID on success, false on failure
 */
function createPayment($conn, $payment_data) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO payments (
                booking_id, amount, transaction_reference, payment_method, 
                payment_type, gateway_reference, status, gateway_response,
                processed_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "idsssssss",
            $payment_data['booking_id'],
            $payment_data['amount'],
            $payment_data['transaction_reference'],
            $payment_data['payment_method'],
            $payment_data['payment_type'],
            $payment_data['gateway_reference'],
            $payment_data['status'],
            $payment_data['gateway_response'],
            $payment_data['processed_at']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $payment_id = $conn->insert_id;
        $stmt->close();
        
        return $payment_id;
        
    } catch (Exception $e) {
        error_log("Payment creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Update booking status
 * @param mysqli $conn Database connection
 * @param int $booking_id Booking ID
 * @param string $payment_status Payment status
 * @param string $booking_status Booking status
 * @return bool Success status
 */
function updateBookingStatus($conn, $booking_id, $payment_status, $booking_status) {
    try {
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET payment_status = ?, booking_status = ?, updated_at = NOW(),
                confirmed_at = CASE WHEN ? = 'confirmed' THEN NOW() ELSE confirmed_at END
            WHERE id = ?
        ");
        
        $stmt->bind_param("sssi", $payment_status, $booking_status, $booking_status, $booking_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        
        $stmt->close();
        return true;
        
    } catch (Exception $e) {
        error_log("Booking status update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get booking by ID with trip details
 * @param mysqli $conn Database connection
 * @param int $booking_id Booking ID
 * @return array|false Booking data or false if not found
 */
function getBookingById($conn, $booking_id) {
    try {
        $stmt = $conn->prepare("
            SELECT b.*, t.pickup_city, t.dropoff_city, t.trip_date, t.departure_time,
                   t.vehicle_type, t.vehicle_number, t.price as trip_price
            FROM bookings b
            LEFT JOIN trips t ON b.trip_id = t.id
            WHERE b.id = ?
        ");
        
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            
            // Get selected seats
            $seat_stmt = $conn->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
            $seat_stmt->bind_param("i", $booking_id);
            $seat_stmt->execute();
            $seat_result = $seat_stmt->get_result();
            
            $seats = [];
            while ($seat_row = $seat_result->fetch_assoc()) {
                $seats[] = $seat_row['seat_number'];
            }
            $booking['selected_seats'] = $seats;
            
            $seat_stmt->close();
            $stmt->close();
            
            return $booking;
        }
        
        $stmt->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Get booking failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify Paystack payment
 * @param string $reference Payment reference
 * @return array|false Payment verification result
 */
function verifyPaystackPayment($reference) {
    $paystack_secret_key = PAYSTACK_SECRET_KEY; // Make sure this is defined in config.php
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $paystack_secret_key,
            "Cache-Control: no-cache",
        ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error: " . $err);
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['status']) || !$result['status']) {
        error_log("Paystack verification failed: " . $response);
        return false;
    }
    
    return $result['data'];
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Send email notification (basic function - you can enhance with proper email library)
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message) {
    // Basic PHP mail function - consider using PHPMailer or similar for production
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <noreply@' . $_SERVER['HTTP_HOST'] . '>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>