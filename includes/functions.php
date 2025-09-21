<?php
// includes/functions.php - Updated for DGC Transport System

/**
 * Generate a unique PNR (Passenger Name Record) for booking
 * @return string Unique PNR
 */
function generatePNR() {
    // Define the prefix
    $prefix = 'D';
    
    // Generate 5 random digits
    $numbers = '';
    for ($i = 0; $i < 5; $i++) {
        $numbers .= random_int(0, 9);
    }
    
    // Return the PNR
    return $prefix . $numbers;
}


/**
 * Verify Paystack payment
 * @param string $reference Payment reference
 * @return array Payment verification result with status and data
 */
function verifyPaystackPayment($reference) {
    // Check if Paystack secret key is defined
    if (!defined('PAYSTACK_SECRET_KEY') || empty(PAYSTACK_SECRET_KEY)) {
        error_log("Paystack secret key not configured");
        return ['status' => false, 'message' => 'Payment verification service not configured'];
    }

    $url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
        "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable for production
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Enable for production
    curl_setopt($ch, CURLOPT_USERAGENT, 'DGC-Transport/1.0');

    $result = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("Paystack verification cURL error for reference {$reference}: {$err}");
        return ['status' => false, 'message' => 'Payment verification connection error'];
    }

    if ($http_code !== 200) {
        error_log("Paystack API returned HTTP {$http_code} for reference {$reference}: {$result}");
        return ['status' => false, 'message' => "Payment verification failed (Error code: {$http_code})"];
    }

    $response = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON response from Paystack for reference {$reference}: " . json_last_error_msg());
        return ['status' => false, 'message' => 'Invalid response from payment service'];
    }

    if (!isset($response['status']) || !$response['status']) {
        $error_message = $response['message'] ?? 'Payment verification failed';
        error_log("Paystack verification failed for reference {$reference}: {$error_message}");
        return ['status' => false, 'message' => $error_message];
    }

    if (!isset($response['data']) || $response['data']['status'] !== 'success') {
        $status = $response['data']['status'] ?? 'unknown';
        error_log("Payment not successful for reference {$reference}. Status: {$status}");
        return ['status' => false, 'message' => 'Payment was not successful'];
    }
    
    return ['status' => true, 'data' => $response['data']];
}

/**
 * Get booking by PNR with all related details
 * @param mysqli $conn Database connection
 * @param string $pnr Booking PNR
 * @return array|false Booking data or false if not found
 */
function getBookingByPNR($conn, $pnr) {
    try {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   pc.name as pickup_city_name, 
                   dc.name as dropoff_city_name,
                   vt.type as vehicle_type_name,
                   vt.capacity as vehicle_capacity,
                   t.departure_time, t.vehicle_number, t.driver_name, t.price
            FROM bookings b
            LEFT JOIN cities pc ON b.pickup_city_id = pc.id
            LEFT JOIN cities dc ON b.dropoff_city_id = dc.id
            LEFT JOIN vehicle_types vt ON b.vehicle_type_id = vt.id
            LEFT JOIN trips t ON b.trip_id = t.id
            WHERE b.pnr = ? AND b.status != 'cancelled'
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $pnr);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            
            // Get selected seats
            $seat_stmt = $conn->prepare("SELECT seat_number FROM seat_bookings WHERE booking_id = ? ORDER BY seat_number");
            if (!$seat_stmt) {
                throw new Exception("Seat prepare failed: " . $conn->error);
            }
            
            $seat_stmt->bind_param("i", $booking['id']);
            if (!$seat_stmt->execute()) {
                throw new Exception("Seat execute failed: " . $seat_stmt->error);
            }
            
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
        error_log("Get booking by PNR failed for PNR {$pnr}: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if (isset($seat_stmt)) $seat_stmt->close();
        return false;
    }
}

/**
 * Get available seats for a trip
 * @param mysqli $conn Database connection
 * @param int $trip_id Trip ID
 * @return array Available seats information
 */
function getAvailableSeatsForTrip($conn, $trip_id) {
    try {
        // Get trip details including vehicle capacity
        $trip_stmt = $conn->prepare("
            SELECT t.*, vt.capacity 
            FROM trips t 
            JOIN vehicle_types vt ON t.vehicle_type_id = vt.id 
            WHERE t.id = ?
        ");
        
        if (!$trip_stmt) {
            throw new Exception("Trip prepare failed: " . $conn->error);
        }
        
        $trip_stmt->bind_param("i", $trip_id);
        if (!$trip_stmt->execute()) {
            throw new Exception("Trip execute failed: " . $trip_stmt->error);
        }
        
        $trip_result = $trip_stmt->get_result();
        $trip_data = $trip_result->fetch_assoc();
        $trip_stmt->close();
        
        if (!$trip_data) {
            return ['capacity' => 0, 'booked' => 0, 'available' => 0, 'booked_seats' => []];
        }
        
        $capacity = $trip_data['capacity'];
        
        // Get booked seats for this specific trip
        $booked_stmt = $conn->prepare("
            SELECT sb.seat_number
            FROM bookings b
            JOIN seat_bookings sb ON b.id = sb.booking_id
            WHERE b.trip_id = ? AND b.payment_status = 'paid' AND b.status != 'cancelled'
            ORDER BY sb.seat_number
        ");
        
        if (!$booked_stmt) {
            throw new Exception("Booked seats prepare failed: " . $conn->error);
        }
        
        $booked_stmt->bind_param("i", $trip_id);
        if (!$booked_stmt->execute()) {
            throw new Exception("Booked seats execute failed: " . $booked_stmt->error);
        }
        
        $booked_result = $booked_stmt->get_result();
        $booked_seats = [];
        
        while ($row = $booked_result->fetch_assoc()) {
            $booked_seats[] = (int)$row['seat_number'];
        }
        
        $booked_stmt->close();
        
        return [
            'capacity' => $capacity,
            'booked' => count($booked_seats),
            'available' => $capacity - count($booked_seats),
            'booked_seats' => $booked_seats
        ];
        
    } catch (Exception $e) {
        error_log("Get available seats failed for trip {$trip_id}: " . $e->getMessage());
        return ['capacity' => 0, 'booked' => 0, 'available' => 0, 'booked_seats' => []];
    }
}

/**
 * Check if specific seats are available for a trip
 * @param mysqli $conn Database connection
 * @param array $seat_numbers Array of seat numbers to check
 * @param int $trip_id Trip ID
 * @return array Array of conflicting seat numbers (empty if all available)
 */
function checkSeatAvailabilityForTrip($conn, $seat_numbers, $trip_id) {
    try {
        if (empty($seat_numbers)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($seat_numbers) - 1) . '?';
        $stmt = $conn->prepare("
            SELECT sb.seat_number 
            FROM seat_bookings sb
            JOIN bookings b ON sb.booking_id = b.id
            WHERE b.trip_id = ? 
            AND b.payment_status = 'paid' 
            AND b.status != 'cancelled'
            AND sb.seat_number IN ($placeholders)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $types = 'i' . str_repeat('i', count($seat_numbers));
        $params = array_merge([$trip_id], $seat_numbers);
        
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $conflicting_seats = [];
        while ($row = $result->fetch_assoc()) {
            $conflicting_seats[] = (int)$row['seat_number'];
        }
        
        $stmt->close();
        return $conflicting_seats;
        
    } catch (Exception $e) {
        error_log("Check seat availability failed for trip {$trip_id}: " . $e->getMessage());
        return $seat_numbers; // Return all seats as conflicting on error
    }
}

/**
 * Sanitize input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency for display
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function formatCurrency($amount) {
    return '₦' . number_format((float)$amount, 0);
}

/**
 * Generate booking reference for Paystack
 * @return string Unique payment reference
 */
function generatePaymentReference() {
    return 'DGC_' . uniqid() . '_' . time();
}

/**
 * Log activity for debugging
 * @param string $message Log message
 * @param array $context Additional context data
 */
function logActivity($message, $context = []) {
    $log_entry = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log_entry .= " | Context: " . json_encode($context);
    }
    error_log($log_entry);
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Nigerian format)
 * @param string $phone Phone number to validate
 * @return bool True if valid phone format
 */
function isValidPhone($phone) {
    // Remove all non-digit characters except + at the beginning
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    
    // Check if it's a valid format
    // Allow international format (+country code + number)
    // Or local format (starts with 0 for Nigeria)
    if (preg_match('/^\+\d{10,15}$/', $cleaned) || preg_match('/^0\d{10}$/', $cleaned)) {
        return true;
    }
    
    return false;
}

/**
 * Validate passenger name
 * @param string $name Name to validate
 * @return bool True if valid name
 */
function isValidName($name) {
    // Allow letters, spaces, hyphens, apostrophes
    return preg_match('/^[a-zA-Z\s\-\'\.]{2,50}$/', trim($name));
}

/**
 * Get trip details by ID
 * @param mysqli $conn Database connection
 * @param int $trip_id Trip ID
 * @return array|false Trip data or false if not found
 */
function getTripById($conn, $trip_id) {
    try {
        $stmt = $conn->prepare("
            SELECT t.*, 
                   pc.name as pickup_city, 
                   dc.name as dropoff_city,
                   vt.type as vehicle_type,
                   vt.capacity
            FROM trips t
            JOIN cities pc ON t.pickup_city_id = pc.id
            JOIN cities dc ON t.dropoff_city_id = dc.id
            JOIN vehicle_types vt ON t.vehicle_type_id = vt.id
            WHERE t.id = ? AND t.status = 'active'
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $trip_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $trip = $result->fetch_assoc();
        $stmt->close();
        
        return $trip ?: false;
        
    } catch (Exception $e) {
        error_log("Get trip by ID failed for trip {$trip_id}: " . $e->getMessage());
        return false;
    }
}


/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'j, M, Y') {
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format time for display
 * @param string $time Time string
 * @param string $format Output format
 * @return string Formatted time
 */
function formatTime($time, $format = 'g:i A') {
    try {
        return date($format, strtotime($time));
    } catch (Exception $e) {
        return $time;
    }
}