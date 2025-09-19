<?php
// bookings/verify_payment.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['reference']) || !isset($_SESSION['payment_reference'])) {
        throw new Exception('Invalid payment reference');
    }

    $reference = $_POST['reference'];
    $session_reference = $_SESSION['payment_reference'];

    // Verify the reference matches what we stored
    if ($reference !== $session_reference) {
        throw new Exception('Payment reference mismatch');
    }

    // Verify payment with Paystack
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $reference,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Cache-Control: no-cache",
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        throw new Exception('Payment verification failed: ' . $err);
    }

    $paystack_response = json_decode($response, true);

    if (!$paystack_response['status'] || $paystack_response['data']['status'] !== 'success') {
        throw new Exception('Payment was not successful');
    }

    // Get session data
    if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats']) || !isset($_SESSION['passenger_details'])) {
        throw new Exception('Session data missing');
    }

    $trip = $_SESSION['selected_trip'];
    $selected_seats = $_SESSION['selected_seats'];
    $passenger_details = $_SESSION['passenger_details'];
    $total_amount = count($selected_seats) * $trip['price'];

    // FIXED: Better amount verification
    // Convert both amounts to integers (kobo) for exact comparison
    $paid_amount_kobo = (int) $paystack_response['data']['amount'];
    $expected_amount_kobo = (int) ($total_amount * 100);
    
    // Log for debugging
    error_log("Payment verification - Expected: ₦$total_amount ($expected_amount_kobo kobo), Paid: " . ($paid_amount_kobo/100) . " (₦$paid_amount_kobo kobo)");
    
    if ($paid_amount_kobo !== $expected_amount_kobo) {
        throw new Exception("Payment amount mismatch. Expected: ₦$total_amount, Received: ₦" . ($paid_amount_kobo/100));
    }

    // Additional security checks
    if ($paystack_response['data']['currency'] !== 'NGN') {
        throw new Exception('Invalid currency');
    }

    // Check if this payment has already been processed
    $stmt = $conn->prepare("SELECT id FROM payments WHERE transaction_reference = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $existing_payment = $stmt->get_result()->fetch_assoc();
    
    if ($existing_payment) {
        throw new Exception('This payment has already been processed');
    }

    // Start database transaction
    $conn->begin_transaction();

    try {
        // Double-check if seats are still available
        $seat_placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
        $stmt = $conn->prepare("
            SELECT sb.seat_number 
            FROM seat_bookings sb
            JOIN bookings b ON sb.booking_id = b.id
            WHERE b.trip_id = ? AND b.payment_status = 'paid' AND sb.seat_number IN ($seat_placeholders)
        ");
        
        $params = array_merge([$trip['id']], $selected_seats);
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $conflicting_seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($conflicting_seats)) {
            $conflicting_numbers = array_column($conflicting_seats, 'seat_number');
            throw new Exception('Seats ' . implode(', ', $conflicting_numbers) . ' are no longer available');
        }

        // Generate unique booking ID and PNR
        $booking_id = 'BOOK_' . uniqid() . '_' . time();
        $pnr = 'DGC' . strtoupper(substr(uniqid(), -6));

        // Create booking record
        $stmt = $conn->prepare("
            INSERT INTO bookings (
                id, pnr, passenger_name, email, phone, emergency_contact, 
                special_requests, pickup_city_id, dropoff_city_id, 
                vehicle_type_id, trip_id, total_amount, payment_status, 
                departure_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 'confirmed', NOW())
        ");
        
        $stmt->bind_param(
            "ssssssiiiidds",
            $booking_id,
            $pnr,
            $passenger_details['passenger_name'],
            $passenger_details['email'],
            $passenger_details['phone'],
            $passenger_details['emergency_contact'] ?? '',
            $passenger_details['special_requests'] ?? '',
            $trip['pickup_city_id'],
            $trip['dropoff_city_id'],
            $trip['vehicle_type_id'],
            $trip['id'],
            $total_amount,
            $trip['trip_date']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create booking: ' . $stmt->error);
        }

        // Get the actual database booking ID
        $db_booking_id = $conn->insert_id;

        // Create seat booking records
        $stmt = $conn->prepare("INSERT INTO seat_bookings (booking_id, seat_number) VALUES (?, ?)");
        foreach ($selected_seats as $seat_number) {
            $stmt->bind_param("si", $booking_id, $seat_number);
            if (!$stmt->execute()) {
                throw new Exception('Failed to book seat ' . $seat_number);
            }
        }

        // Create payment record with additional Paystack data
        $stmt = $conn->prepare("
            INSERT INTO payments (
                booking_id, amount, transaction_reference, gateway_reference, 
                payment_method, status, gateway_response, created_at
            ) VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
        ");
        
        $gateway_reference = $paystack_response['data']['gateway_response'] ?? '';
        $payment_method = $paystack_response['data']['authorization']['channel'] ?? 'card';
        $gateway_response = json_encode($paystack_response['data']);
        
        $stmt->bind_param(
            "sdssss", 
            $booking_id, 
            $total_amount, 
            $reference, 
            $gateway_reference,
            $payment_method,
            $gateway_response
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to record payment');
        }

        // Update trip booked seats count
        $stmt = $conn->prepare("
            UPDATE trips 
            SET booked_seats = (
                SELECT COUNT(*) 
                FROM seat_bookings sb 
                JOIN bookings b ON sb.booking_id = b.id 
                WHERE b.trip_id = ? AND b.payment_status = 'paid'
            )
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $trip['id'], $trip['id']);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Store booking info in session for confirmation page
        $_SESSION['booking_confirmation'] = [
            'booking_id' => $booking_id,
            'pnr' => $pnr,
            'passenger_name' => $passenger_details['passenger_name'],
            'email' => $passenger_details['email'],
            'phone' => $passenger_details['phone'],
            'trip' => $trip,
            'selected_seats' => $selected_seats,
            'total_amount' => $total_amount,
            'payment_reference' => $reference,
            'payment_method' => $payment_method
        ];

        // Clear booking-related session data
        unset($_SESSION['selected_trip']);
        unset($_SESSION['selected_seats']);
        unset($_SESSION['selected_num_seats']);
        unset($_SESSION['passenger_details']);
        unset($_SESSION['payment_reference']);

        // Log successful booking
        error_log("Booking successful: ID = $booking_id, PNR = $pnr, Amount = ₦$total_amount, Reference = $reference");

        // Send confirmation email (implement this function if needed)
        // sendBookingConfirmationEmail($booking_id, $passenger_details['email']);

        echo json_encode([
            'success' => true,
            'booking_id' => $booking_id,
            'pnr' => $pnr,
            'message' => 'Payment successful and booking confirmed'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking transaction failed: " . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>