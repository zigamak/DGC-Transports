<?php
// includes/paystack.php

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/booking_functions.php';

/**
 * Verify Paystack payment
 */
function verify_paystack_payment($reference) {
    if (empty($reference)) {
        throw new Exception("Invalid payment reference");
    }
    
    $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Paystack cURL error for reference $reference: [Errno $errno] $error (HTTP $http_code)");
        throw new Exception("Failed to verify Paystack payment: $error (HTTP $http_code)");
    }
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($result['status'])) {
            if ($result['status']) {
                return $result;
            }
            throw new Exception("Paystack verification failed: " . ($result['message'] ?? 'Invalid response'));
        }
        throw new Exception("Invalid JSON response from Paystack: " . json_last_error_msg());
    }
    
    throw new Exception("Failed to verify Paystack payment: HTTP $http_code");
}

/**
 * Update payment status and details
 */
function update_payment_status($booking_id, $reference, $status, $amount) {
    $db = get_db_connection();
    
    try {
        $query = "SELECT payment_id, payment_reference FROM payments WHERE booking_id = :booking_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':booking_id' => $booking_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("No payment record found for booking ID: $booking_id");
        }
        
        if ($payment['payment_reference'] && $payment['payment_reference'] !== $reference) {
            throw new Exception("Payment reference mismatch for booking ID: $booking_id. Expected: {$payment['payment_reference']}, Received: $reference");
        }
        
        $update_query = "UPDATE payments SET 
                        payment_status = :status,
                        amount = :amount,
                        payment_reference = :reference,
                        updated_at = NOW()
                      WHERE booking_id = :booking_id";
        
        $stmt = $db->prepare($update_query);
        $stmt->execute([
            ':status' => $status,
            ':amount' => $amount,
            ':reference' => $reference,
            ':booking_id' => $booking_id
        ]);
        
        if ($status === 'completed') {
            update_booking_status($booking_id, 'confirmed');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to update payment status for booking_id=$booking_id, reference=$reference: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get booking for payment
 */
function get_booking_for_payment($booking_id) {
    $db = get_db_connection();
    
    $query = "SELECT b.*, 
                     CASE 
                         WHEN b.is_guest = 1 THEN b.guest_email 
                         ELSE u.email 
                     END as customer_email,
                     CASE 
                         WHEN b.is_guest = 1 THEN b.guest_name 
                         ELSE CONCAT(u.first_name, ' ', u.last_name) 
                     END as customer_name,
                     s.title as property_title,
                     s.location as property_location
              FROM bookings b 
              LEFT JOIN users u ON b.user_id = u.user_id 
              LEFT JOIN shortlets s ON b.shortlet_id = s.shortlet_id
              WHERE b.booking_id = :booking_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':booking_id' => $booking_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Initialize Paystack payment with subaccount split
 */
function initialize_paystack_payment($booking_id, $amount, $email, $callback_url, $payment_reference) {
    $url = "https://api.paystack.co/transaction/initialize";
    
    // Convert amount to kobo
    $amount_in_kobo = $amount * 100;
    
    // Load subaccount configuration
    $subaccount_code = defined('PAYSTACK_SUBACCOUNT_CODE') ? PAYSTACK_SUBACCOUNT_CODE : null;
    $split_percentage = defined('PAYSTACK_SPLIT_PERCENTAGE') ? (int)PAYSTACK_SPLIT_PERCENTAGE : 100;
    
    // Validate split percentage (must be between 0 and 100)
    if ($split_percentage < 0 || $split_percentage > 100) {
        error_log("Invalid PAYSTACK_SPLIT_PERCENTAGE: $split_percentage. Using default 100.");
        $split_percentage = 100;
    }
    
    // Build payment fields
    $fields = [
        'email' => $email,
        'amount' => $amount_in_kobo,
        'reference' => $payment_reference,
        'callback_url' => $callback_url,
        'metadata' => [
            'booking_id' => $booking_id,
            'payment_reference' => $payment_reference,
            'custom_fields' => [
                [
                    'display_name' => 'Booking ID',
                    'variable_name' => 'booking_id',
                    'value' => $booking_id
                ]
            ]
        ]
    ];
    
    // Add subaccount split if configured
    if (!empty($subaccount_code) && $split_percentage > 0 && $split_percentage < 100) {
        // Calculate transaction charge (amount to go to subaccount in kobo)
        $transaction_charge = intval(($split_percentage / 100) * $amount_in_kobo);
        
        $fields['subaccount'] = $subaccount_code;
        $fields['transaction_charge'] = $transaction_charge;
        $fields['bearer'] = 'account'; // Main account bears transaction fees
        
        error_log("Paystack split configured: Subaccount=$subaccount_code, Split=$split_percentage%, Charge=" . ($transaction_charge/100) . " NGN");
    } elseif (!empty($subaccount_code) && $split_percentage === 100) {
        // Send all funds to subaccount
        $fields['subaccount'] = $subaccount_code;
        $fields['bearer'] = 'subaccount'; // Subaccount bears transaction fees
        
        error_log("Paystack full transfer: Subaccount=$subaccount_code receives 100%");
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && !$error) {
        $result = json_decode($response, true);
        if ($result['status'] && isset($result['data']['authorization_url'])) {
            error_log("Paystack payment initialized successfully: Reference=$payment_reference");
            return $result['data']['authorization_url'];
        }
        throw new Exception("Paystack response error: " . ($result['message'] ?? 'Invalid response'));
    }
    
    throw new Exception("Failed to initialize Paystack payment: " . ($error ?: 'HTTP ' . $http_code));
}
?>