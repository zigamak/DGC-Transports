<?php
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
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($booking_data['email'], $booking_data['passenger_name']);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmation - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME;
        
        $mail->Body = renderBookingConfirmationTemplate($booking_data);
        if (empty($mail->Body)) {
            throw new Exception('Email body is empty - template rendering failed');
        }
        
        $mail->AltBody = createPlainTextVersion($booking_data);

        $result = $mail->send();
        
        if ($result) {
            logEmail($booking_data['email'], 'booking_confirmation', 'sent', 'Booking confirmation sent for PNR: ' . $booking_data['pnr']);
            error_log("Booking confirmation email sent successfully to: " . $booking_data['email']);
            return true;
        } else {
            logEmail($booking_data['email'], 'booking_confirmation', 'failed', 'Failed to send booking confirmation');
            error_log("Failed to send booking confirmation email to: " . $booking_data['email']);
            return false;
        }
        
    } catch (Exception $e) {
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
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($booking_data['email'], $booking_data['passenger_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Booking Cancelled - PNR: ' . $booking_data['pnr'] . ' - ' . SITE_NAME;
        
        $mail->Body = renderCancellationTemplate($booking_data);
        if (empty($mail->Body)) {
            throw new Exception('Cancellation email body is empty - template rendering failed');
        }
        
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
 * Send set password email
 */
function sendPasswordResetEmail($reset_data) {
    global $conn;
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($reset_data['email']);

        $mail->isHTML(true);
        $mail->Subject = 'Set Up Your Password - ' . SITE_NAME;
        
        $mail->Body = renderPasswordResetTemplate($reset_data);
        if (empty($mail->Body)) {
            throw new Exception('Set password email body is empty - template rendering failed');
        }
        
        $mail->AltBody = "Your account has been created.\n\nClick the following link to set up your password: " . SITE_URL . "/set_password.php?token={$reset_data['token']}\n\nThis link expires at {$reset_data['expires_at']}.";

        $result = $mail->send();
        
        if ($result) {
            logEmail($reset_data['email'], 'set_password', 'sent', 'Set password link sent to: ' . $reset_data['email']);
            error_log("Set password email sent successfully to: " . $reset_data['email']);
            return true;
        } else {
            logEmail($reset_data['email'], 'set_password', 'failed', 'Failed to send set password link');
            error_log("Failed to send set password email to: " . $reset_data['email']);
            return false;
        }
        
    } catch (Exception $e) {
        logEmail($reset_data['email'], 'set_password', 'error', 'PHPMailer Error: ' . $mail->ErrorInfo);
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Render booking confirmation email template
 */
function renderBookingConfirmationTemplate($booking) {
    $template_path = __DIR__ . '/templates/email_booking_confirmation.php';
    if (!file_exists($template_path)) {
        error_log("Email template not found: $template_path");
        return '';
    }
    
    ob_start();
    try {
        include $template_path;
        $content = ob_get_clean();
        if (empty($content)) {
            error_log("Email template rendered empty content: $template_path");
        }
        return $content;
    } catch (Exception $e) {
        error_log("Error rendering email template ($template_path): " . $e->getMessage());
        ob_end_clean();
        return '';
    }
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
 * Render set password email template
 */
function renderPasswordResetTemplate($reset_data) {
    $template_path = __DIR__ . '/templates/email_set_password.php';
    if (!file_exists($template_path)) {
        error_log("Email template not found: $template_path");
        return '';
    }
    
    ob_start();
    try {
        include $template_path;
        $content = ob_get_clean();
        if (empty($content)) {
            error_log("Email template rendered empty content: $template_path");
        }
        return $content;
    } catch (Exception $e) {
        error_log("Error rendering email template ($template_path): " . $e->getMessage());
        ob_end_clean();
        return '';
    }
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
    
    if (isset($booking['trip']['driver_name']) && !empty($booking['trip']['driver_name'])) {
        $text .= "Driver: " . $booking['trip']['driver_name'] . "\n";
    }
    
    $text .= "Seat: " . $booking['seat_number'] . "\n";
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
 * Send reminder email (for future use)
 */
function sendTripReminderEmail($booking_data) {
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
        
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => true, 'message' => 'SMTP configuration is working'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMTP Error: ' . $e->getMessage()];
    }
}
?>