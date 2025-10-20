<?php
// includes/send_email.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Helper: Get emails of all users with role 'staff' or 'admin'
 * @return array Array of email strings, or empty array on failure
 */
function getStaffAdminEmails() {
    global $conn;
    $emails = [];
    
    try {
        // Use prepared statement for security
        $stmt = $conn->prepare("SELECT email FROM users WHERE role IN (?, ?)");
        if (!$stmt) {
            error_log("Prepare failed for getStaffAdminEmails: " . $conn->error);
            return $emails;
        }
        $roleStaff = 'staff';
        $roleAdmin = 'admin';
        $stmt->bind_param("ss", $roleStaff, $roleAdmin);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email'])) {
                $emails[] = $row['email'];
            }
        }
        $stmt->close();
        
        error_log("Fetched " . count($emails) . " staff/admin emails for CC");
    } catch (Exception $e) {
        error_log("Exception in getStaffAdminEmails: " . $e->getMessage());
    }
    
    return $emails;
}

/**
 * Generic function to send emails
 * @param array $data Email data (to_email, to_name, subject, template_path, template_data, cc, bcc)
 * @param string $email_type Type of email for logging (e.g., 'contact_user', 'booking_confirmation')
 * @param string $log_message Custom log message
 * @return bool Success or failure
 */
function sendEmail($data, $email_type, $log_message) {
    global $conn;
    $mail = new PHPMailer(true);

    try {
        // Validate required fields
        if (empty($data['to_email']) || empty($data['subject']) || empty($data['template_path'])) {
            error_log("Missing required email data: " . json_encode([
                'to_email' => isset($data['to_email']) ? $data['to_email'] : 'not set',
                'subject' => isset($data['subject']) ? $data['subject'] : 'not set',
                'template_path' => isset($data['template_path']) ? $data['template_path'] : 'not set'
            ]));
            throw new Exception('Missing required email data');
        }

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($data['to_email'], $data['to_name'] ?? '');
        if (!empty($data['reply_to'])) {
            $mail->addReplyTo($data['reply_to'], $data['reply_to_name'] ?? FROM_NAME);
        }

        // Add CC addresses
        if (!empty($data['cc']) && is_array($data['cc'])) {
            foreach (array_unique($data['cc']) as $cc_email) {  // Dedupe
                $mail->addCC($cc_email);
            }
        }

        // Add BCC addresses
        if (!empty($data['bcc']) && is_array($data['bcc'])) {
            foreach ($data['bcc'] as $bcc_email) {
                $mail->addBCC($bcc_email);
            }
        }
        
        $mail->isHTML(true);
        $mail->Subject = $data['subject'];

        // Render email body from template
        if (!file_exists($data['template_path'])) {
            error_log("Email template not found: {$data['template_path']}");
            throw new Exception('Email template not found');
        }
        ob_start();
        $template_data = $data['template_data'] ?? [];
        error_log("sendEmail: Passing template_data to {$data['template_path']}: " . json_encode($template_data));
        include $data['template_path'];
        $mail->Body = ob_get_clean();

        if (empty($mail->Body)) {
            error_log("Email body is empty for {$data['template_path']} - template rendering failed");
            throw new Exception('Email body is empty - template rendering failed');
        }

        $mail->AltBody = $data['alt_body'] ?? 'This is a plain-text version of the email.';

        if ($mail->send()) {
            logEmail($data['to_email'], $email_type, 'sent', $log_message);
            error_log("$log_message to: {$data['to_email']}");
            return true;
        } else {
            logEmail($data['to_email'], $email_type, 'failed', "Failed to send $email_type email: {$mail->ErrorInfo}");
            error_log("Failed to send $email_type email to: {$data['to_email']} - Error: {$mail->ErrorInfo}");
            return false;
        }
    } catch (Exception $e) {
        logEmail($data['to_email'], $email_type, 'error', "PHPMailer Error: {$e->getMessage()}");
        error_log("PHPMailer Error ($email_type): {$e->getMessage()}");
        return false;
    }
}

/**
 * Send booking confirmation email to passenger (unchanged)
 */
function sendBookingConfirmationEmail($booking_data) {
    // Validate booking_data
    if (empty($booking_data['pnr']) || empty($booking_data['passenger_name'])) {
        error_log("Invalid booking_data for sendBookingConfirmationEmail: " . json_encode($booking_data));
        return false;
    }

    $data = [
        'to_email' => $booking_data['email'] ?? '',
        'to_name' => $booking_data['passenger_name'],
        'reply_to' => FROM_EMAIL,
        'reply_to_name' => FROM_NAME,
        'subject' => 'Booking Confirmation - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME,
        'template_path' => __DIR__ . '/templates/email_booking_confirmation.php',
        'template_data' => $booking_data,
        'alt_body' => createPlainTextVersion($booking_data)
    ];
    return sendEmail($data, 'booking_confirmation', "Booking confirmation sent for PNR: {$booking_data['pnr']}");
}

/**
 * Send booking confirmation notification to admin
 * Only sends to admin@dgctransports.com and CCs info.revamplabs@gmail.com
 */
function sendAdminBookingNotificationEmail($booking_data_array, $total_amount, $payment_method, $payment_reference) {
    // Validate booking_data_array
    if (empty($booking_data_array) || !is_array($booking_data_array) || empty($booking_data_array[0]['pnr'])) {
        error_log("Invalid booking_data_array for sendAdminBookingNotificationEmail: " . json_encode($booking_data_array));
        return false;
    }

    $data = [
        'to_email' => 'admin@dgctransports.com',
        'to_name' => 'DGC Transports Admin',
        'cc' => ['info.revamplabs@gmail.com'],  // Only CC to info.revamplabs@gmail.com
        'reply_to' => FROM_EMAIL,
        'reply_to_name' => FROM_NAME,
        'subject' => 'New Booking Confirmation - PNR: ' . $booking_data_array[0]['pnr'] . ' - ' . SITE_NAME,
        'template_path' => __DIR__ . '/templates/email_admin_booking_notification.php',
        'template_data' => [
            'bookings' => $booking_data_array,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'payment_reference' => $payment_reference
        ],
        'alt_body' => createPlainTextAdminBookingNotification($booking_data_array, $total_amount, $payment_method, $payment_reference)
    ];
    return sendEmail($data, 'admin_booking_notification', "Admin booking notification sent for PNR: {$booking_data_array[0]['pnr']}");
}

/**
 * Create plain text version of admin booking notification (unchanged)
 */
function createPlainTextAdminBookingNotification($booking_data_array, $total_amount, $payment_method, $payment_reference) {
    $text = SITE_NAME . " - New Booking Notification\n\n";
    $text .= "A new booking has been confirmed:\n\n";
    $text .= "--- Booking Details ---\n";
    foreach ($booking_data_array as $index => $booking) {
        $text .= "Passenger " . ($index + 1) . ":\n";
        $text .= "Name: " . ($booking['passenger_name'] ?? 'N/A') . "\n";
        $text .= "Email: " . ($booking['email'] ?? 'N/A') . "\n";
        $text .= "Phone: " . ($booking['phone'] ?? 'N/A') . "\n";
        $text .= "PNR: " . ($booking['pnr'] ?? 'N/A') . "\n";
        $text .= "Seat: " . ($booking['seat_number'] ?? 'N/A') . "\n";
        $text .= "Route: " . ($booking['trip']['pickup_city'] ?? 'N/A') . " to " . ($booking['trip']['dropoff_city'] ?? 'N/A') . "\n";
        $text .= "Date: " . (isset($booking['trip']['trip_date']) ? date('M j, Y', strtotime($booking['trip']['trip_date'])) : 'N/A') . "\n";
        $text .= "Departure: " . (isset($booking['trip']['departure_time']) ? date('h:i A', strtotime($booking['trip']['departure_time'])) : 'N/A') . "\n";
        $text .= "Vehicle: " . ($booking['trip']['vehicle_type'] ?? 'N/A') . " - " . ($booking['trip']['vehicle_number'] ?? 'N/A') . "\n";
        if (isset($booking['trip']['driver_name']) && !empty($booking['trip']['driver_name'])) {
            $text .= "Driver: " . $booking['trip']['driver_name'] . "\n";
        }
        $text .= "\n";
    }
    $text .= "Total Amount: ₦" . number_format($total_amount, 0) . "\n";
    $text .= "Payment Method: " . (ucfirst($payment_method) ?? 'N/A') . "\n";
    $text .= "Payment Reference: " . ($payment_reference ?? 'N/A') . "\n\n";
    $text .= "Please review the booking details in the admin panel.";
    
    return $text;
}

/**
 * Send booking cancellation email (unchanged)
 */
function sendBookingCancellationEmail($booking_data) {
    $data = [
        'to_email' => $booking_data['email'],
        'to_name' => $booking_data['passenger_name'],
        'subject' => 'Booking Cancelled - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME,
        'template_path' => __DIR__ . '/templates/email_booking_cancellation.php',
        'template_data' => $booking_data,
        'alt_body' => 'Your booking with PNR ' . $booking_data['pnr'] . ' has been cancelled.'
    ];
    return sendEmail($data, 'booking_cancellation', "Booking cancellation sent for PNR: {$booking_data['pnr']}");
}

/**
 * Send password email (set password or reset password) (unchanged)
 */
function sendPasswordEmail($reset_data, $type = 'set_password') {
    $script = $type === 'set_password' ? 'set_password.php' : 'reset_password.php';
    $action = $type === 'set_password' ? 'set up your password' : 'reset your password';
    $data = [
        'to_email' => $reset_data['email'],
        'subject' => $type === 'set_password' ? 'Set Up Your Password - ' . SITE_NAME : 'Password Reset Request - ' . SITE_NAME,
        'template_path' => __DIR__ . '/templates/email_' . $type . '.php',
        'template_data' => $reset_data,
        'alt_body' => ($type === 'set_password' ? 'Your account has been created' : 'You requested a password reset') . ".\n\nClick the following link to $action: " . SITE_URL . "/$script?token={$reset_data['token']}\n\nThis link expires at {$reset_data['expires_at']}.\n\nIf you did not request this, please ignore this email."
    ];
    return sendEmail($data, $type, ucfirst(str_replace('_', ' ', $type)) . " link sent to: {$reset_data['email']}");
}

/**
 * Create plain text version of booking confirmation (unchanged)
 */
function createPlainTextVersion($booking) {
    $text = SITE_NAME . " - Booking Confirmation\n\n";
    $text .= "Dear " . ($booking['passenger_name'] ?? 'N/A') . ",\n\n";
    $text .= "Your booking has been confirmed!\n\n";
    $text .= "PNR: " . ($booking['pnr'] ?? 'N/A') . "\n";
    $text .= "Route: " . ($booking['trip']['pickup_city'] ?? 'N/A') . " to " . ($booking['trip']['dropoff_city'] ?? 'N/A') . "\n";
    $text .= "Date: " . (isset($booking['trip']['trip_date']) ? date('M j, Y', strtotime($booking['trip']['trip_date'])) : 'N/A') . "\n";
    $text .= "Departure: " . (isset($booking['trip']['departure_time']) ? date('h:i A', strtotime($booking['trip']['departure_time'])) : 'N/A') . "\n";
    $text .= "Vehicle: " . ($booking['trip']['vehicle_type'] ?? 'N/A') . " - " . ($booking['trip']['vehicle_number'] ?? 'N/A') . "\n";
    
    if (isset($booking['trip']['driver_name']) && !empty($booking['trip']['driver_name'])) {
        $text .= "Driver: " . $booking['trip']['driver_name'] . "\n";
    }
    
    $text .= "Seat: " . ($booking['seat_number'] ?? 'N/A') . "\n";
    $text .= "Total Amount: ₦" . number_format($booking['total_amount'] ?? 0, 0) . "\n";
    $text .= "Payment Method: " . (isset($booking['payment_method']) ? ucfirst($booking['payment_method']) : 'N/A') . "\n";
    $text .= "Payment Reference: " . (isset($booking['payment_reference']) ? $booking['payment_reference'] : 'N/A') . "\n\n";
    $text .= "Please arrive at the terminal 30 minutes before departure.\n";
    $text .= "Bring a valid ID for verification.\n\n";
    $text .= "Thank you for choosing " . SITE_NAME . "!";
    
    return $text;
}

/**
 * Log email activity to database (unchanged)
 */
function logEmail($to_email, $email_type, $status, $message = '') {
    global $conn;
    
    try {
        $result = $conn->query("SHOW TABLES LIKE 'email_logs'");
        if ($result->num_rows === 0) {
            $create_table = "
                CREATE TABLE email_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(255) NOT NULL,
                    email_type VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    message TEXT,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $conn->query($create_table);
            error_log("Created email_logs table");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO email_logs (to_email, email_type, status, message, sent_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssss", $to_email, $email_type, $status, $message);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}

/**
 * Send reminder email (for future use) (unchanged)
 */
function sendTripReminderEmail($booking_data) {
    return true;
}

/**
 * Test email configuration (unchanged)
 */
function testEmailConfiguration() {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => true, 'message' => 'SMTP configuration is working'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()];
    }
}

/**
 * Send charter request submission email to the user (unchanged)
 */
function sendCharterUserConfirmationEmail($charter_data) {
    $data = [
        'to_email' => $charter_data['email'],
        'to_name' => $charter_data['full_name'],
        'reply_to' => FROM_EMAIL,
        'reply_to_name' => FROM_NAME,
        'subject' => 'Charter Request Received - ' . SITE_NAME,
        'template_path' => __DIR__ . '/templates/email_charter_user_confirmation.php',
        'template_data' => $charter_data,
        'alt_body' => createPlainTextCharterConfirmation($charter_data)
    ];
    return sendEmail($data, 'charter_user_confirmation', "Charter request confirmation sent to: {$charter_data['email']}");
}

/**
 * Send charter request notification email to the admin
 * Only sends to admin@dgctransports.com and CCs info.revamplabs@gmail.com
 */
function sendCharterAdminNotificationEmail($charter_data) {
    $data = [
        'to_email' => 'admin@dgctransports.com',
        'to_name' => 'DGC Transports Admin',
        'cc' => ['info.revamplabs@gmail.com'],  // Only CC to info.revamplabs@gmail.com
        'reply_to' => $charter_data['email'],
        'reply_to_name' => $charter_data['full_name'],
        'subject' => 'NEW Charter Request Submitted by: ' . $charter_data['full_name'],
        'template_path' => __DIR__ . '/templates/email_charter_admin_notification.php',
        'template_data' => $charter_data,
        'alt_body' => createPlainTextCharterAdminNotification($charter_data)
    ];
    return sendEmail($data, 'charter_admin_notification', "New charter request notification sent to admin.");
}

/**
 * Create plain text version of the user charter confirmation (unchanged)
 */
function createPlainTextCharterConfirmation($charter) {
    $text = SITE_NAME . " - Charter Request Received\n\n";
    $text .= "Dear " . ($charter['full_name'] ?? 'N/A') . ",\n\n";
    $text .= "Thank you for submitting your charter request. We will review the details and get back to you with a quote shortly.\n\n";
    $text .= "--- Your Request Details ---\n";
    $text .= "Name: " . ($charter['full_name'] ?? 'N/A') . "\n";
    $text .= "Email: " . ($charter['email'] ?? 'N/A') . "\n";
    $text .= "Phone: " . ($charter['phone'] ?? 'N/A') . "\n";
    $text .= "Route: " . ($charter['pickup_location'] ?? 'N/A') . " to " . ($charter['dropoff_location'] ?? 'N/A') . "\n";
    $text .= "Departure Date: " . (isset($charter['departure_date']) ? date('M j, Y', strtotime($charter['departure_date'])) : 'N/A') . "\n";
    $text .= "Passengers: " . ($charter['num_seats'] ?? 'N/A') . "\n";
    $text .= "Preferred Vehicle: " . ($charter['preferred_vehicle'] ?? 'Any') . "\n";
    $text .= "Additional Notes: " . ($charter['notes'] ?? 'None') . "\n\n";
    $text .= "Thank you for choosing " . SITE_NAME . "!";
    
    return $text;
}

/**
 * Create plain text version of the admin charter notification (unchanged)
 */
function createPlainTextCharterAdminNotification($charter) {
    $text = SITE_NAME . " - NEW Charter Request\n\n";
    $text .= "A new charter request has been submitted:\n\n";
    $text .= "--- Charter Request Details ---\n";
    $text .= "Name: " . ($charter['full_name'] ?? 'N/A') . "\n";
    $text .= "Email: " . ($charter['email'] ?? 'N/A') . "\n";
    $text .= "Phone: " . ($charter['phone'] ?? 'N/A') . "\n";
    $text .= "Route: " . ($charter['pickup_location'] ?? 'N/A') . " to " . ($charter['dropoff_location'] ?? 'N/A') . "\n";
    $text .= "Departure Date: " . (isset($charter['departure_date']) ? date('M j, Y', strtotime($charter['departure_date'])) : 'N/A') . "\n";
    $text .= "Passengers: " . ($charter['num_seats'] ?? 'N/A') . "\n";
    $text .= "Preferred Vehicle: " . ($charter['preferred_vehicle'] ?? 'Any') . "\n";
    $text .= "Additional Notes: " . ($charter['notes'] ?? 'None') . "\n";
    $text .= "Submitted: " . date('Y-m-d H:i:s') . "\n\n";
    $text .= "Please contact the client to provide a quote.";
    
    return $text;
}
?>