<?php
// includes/send_email.php
// Function to send booking confirmation emails using PHPMailer

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send booking confirmation email
 */
function sendBookingConfirmationEmail($booking_data) {
    global $conn;
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($booking_data['email'], $booking_data['passenger_name']);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME;
        
        // Load the email template
        $mail->Body = renderBookingConfirmationTemplate($booking_data);
        
        // Alternative plain text version
        $mail->AltBody = createPlainTextVersion($booking_data);

        // Send email
        $result = $mail->send();
        
        if ($result) {
            // Log successful email
            logEmail($booking_data['email'], 'booking_confirmation', 'sent', 'Booking confirmation sent for PNR: ' . $booking_data['pnr']);
            error_log("Booking confirmation email sent successfully to: " . $booking_data['email']);
            return true;
        } else {
            // Log failed email
            logEmail($booking_data['email'], 'booking_confirmation', 'failed', 'Failed to send booking confirmation');
            error_log("Failed to send booking confirmation email to: " . $booking_data['email']);
            return false;
        }
        
    } catch (Exception $e) {
        // Log error
        logEmail($booking_data['email'], 'booking_confirmation', 'error', 'PHPMailer Error: ' . $mail->ErrorInfo);
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send booking cancellation email
 */
function sendBookingCancellationEmail($booking_data) {
    global $conn;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($booking_data['email'], $booking_data['passenger_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Cancelled - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME;
        
        // Simple cancellation template (you can create a separate template file)
        $mail->Body = renderCancellationTemplate($booking_data);
        $mail->AltBody = 'Your booking with PNR ' . $booking_data['pnr'] . ' has been cancelled.';

        $result = $mail->send();
        
        if ($result) {
            logEmail($booking_data['email'], 'booking_cancellation', 'sent', 'Booking cancellation sent for PNR: ' . $booking_data['pnr']);
            return true;
        } else {
            logEmail($booking_data['email'], 'booking_cancellation', 'failed', 'Failed to send booking cancellation');
            return false;
        }
        
    } catch (Exception $e) {
        logEmail($booking_data['email'], 'booking_cancellation', 'error', 'PHPMailer Error: ' . $mail->ErrorInfo);
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Render booking confirmation email template
 */
function renderBookingConfirmationTemplate($booking) {
    ob_start();
    include __DIR__ . '/../templates/email_booking_confirmation.php';
    return ob_get_clean();
}

/**
 * Render booking cancellation email template
 */
function renderCancellationTemplate($booking) {
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;">
        <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">' . SITE_NAME . '</h1>
            <p style="margin: 5px 0 0 0;">Booking Cancellation</p>
        </div>
        <div style="padding: 30px; background: white;">
            <h2 style="color: #dc2626; margin-bottom: 20px;">Booking Cancelled</h2>
            <p>Dear ' . htmlspecialchars($booking['passenger_name']) . ',</p>
            <p>Your booking with PNR <strong>' . htmlspecialchars($booking['pnr']) . '</strong> has been cancelled.</p>
            <p>If you have any questions, please contact our support team.</p>
            <p>Thank you for choosing ' . SITE_NAME . '.</p>
        </div>
    </div>';
    
    return $html;
}

/**
 * Create plain text version of booking confirmation
 */
function createPlainTextVersion($booking) {
    $text = SITE_NAME . " - Booking Confirmation\n\n";
    $text .= "Dear " . $booking['passenger_name'] . ",\n\n";
    $text .= "Your booking has been confirmed!\n\n";
    $text .= "PNR: " . $booking['pnr'] . "\n";
    $text .= "Route: " . $booking['trip']['pickup_city'] . " to " . $booking['trip']['dropoff_city'] . "\n";
    $text .= "Date: " . date('M j, Y', strtotime($booking['trip']['trip_date'])) . "\n";
    $text .= "Departure: " . date('H:i', strtotime($booking['trip']['departure_time'])) . "\n";
    $text .= "Vehicle: " . $booking['trip']['vehicle_type'] . " - " . $booking['trip']['vehicle_number'] . "\n";
    $text .= "Driver: " . $booking['trip']['driver_name'] . "\n";
    $text .= "Seats: " . implode(', ', array_map(function($seat) { return "Seat $seat"; }, $booking['selected_seats'])) . "\n";
    $text .= "Total Amount: ₦" . number_format($booking['total_amount'], 0) . "\n\n";
    $text .= "Please arrive at the terminal 30 minutes before departure.\n";
    $text .= "Bring a valid ID for verification.\n\n";
    $text .= "Thank you for choosing " . SITE_NAME . "!";
    
    return $text;
}

/**
 * Log email activity to database
 */
function logEmail($to_email, $email_type, $status, $message = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO email_logs (to_email, email_type, status, message, sent_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssss", $to_email, $email_type, $status, $message);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}

/**
 * Send reminder email (for future use)
 */
function sendTripReminderEmail($booking_data) {
    // Implementation for trip reminder emails
    // Can be used to send reminders 24 hours before trip
    return true;
}

/**
 * Test email configuration
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
        
        // Test connection
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => true, 'message' => 'SMTP configuration is working'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()];
    }
}
?>