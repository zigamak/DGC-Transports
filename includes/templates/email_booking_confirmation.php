<?php
// includes/templates/email_booking_confirmation.php
require_once __DIR__ . '/../../includes/config.php';

// Ensure booking_data is set and provide defaults
$booking_data = isset($booking_data) ? $booking_data : [];
$passenger_name = isset($booking_data['passenger_name']) ? htmlspecialchars($booking_data['passenger_name'], ENT_QUOTES, 'UTF-8') : 'Passenger';
$pnr = isset($booking_data['pnr']) ? htmlspecialchars($booking_data['pnr'], ENT_QUOTES, 'UTF-8') : 'N/A';
$seat_number = isset($booking_data['seat_number']) ? htmlspecialchars($booking_data['seat_number'], ENT_QUOTES, 'UTF-8') : 'N/A';
$total_amount = isset($booking_data['total_amount']) ? number_format($booking_data['total_amount'], 0) : '0';
$payment_method = isset($booking_data['payment_method']) && $booking_data['payment_method'] ? htmlspecialchars(ucfirst($booking_data['payment_method']), ENT_QUOTES, 'UTF-8') : 'Unknown';
$payment_reference = isset($booking_data['payment_reference']) ? htmlspecialchars($booking_data['payment_reference'], ENT_QUOTES, 'UTF-8') : 'N/A';
$trip = isset($booking_data['trip']) ? $booking_data['trip'] : [];
$pickup_city = isset($trip['pickup_city']) ? htmlspecialchars($trip['pickup_city'], ENT_QUOTES, 'UTF-8') : 'N/A';
$dropoff_city = isset($trip['dropoff_city']) ? htmlspecialchars($trip['dropoff_city'], ENT_QUOTES, 'UTF-8') : 'N/A';
$trip_date = isset($trip['trip_date']) ? date('D, M j, Y', strtotime($trip['trip_date'])) : 'N/A';
$departure_time = isset($trip['departure_time']) && $trip['departure_time'] ? date('H:i', strtotime($trip['departure_time'])) : 'N/A';
$vehicle_type = isset($trip['vehicle_type']) ? htmlspecialchars($trip['vehicle_type'], ENT_QUOTES, 'UTF-8') : 'N/A';
$vehicle_number = isset($trip['vehicle_number']) ? htmlspecialchars($trip['vehicle_number'], ENT_QUOTES, 'UTF-8') : 'N/A';
$driver_name = isset($trip['driver_name']) && $trip['driver_name'] ? htmlspecialchars($trip['driver_name'], ENT_QUOTES, 'UTF-8') : 'N/A';
$email = isset($booking_data['email']) ? htmlspecialchars($booking_data['email'], ENT_QUOTES, 'UTF-8') : 'N/A';
$phone = isset($booking_data['phone']) ? htmlspecialchars($booking_data['phone'], ENT_QUOTES, 'UTF-8') : 'N/A';
$emergency_contact = isset($booking_data['emergency_contact']) ? htmlspecialchars($booking_data['emergency_contact'], ENT_QUOTES, 'UTF-8') : '';
$special_requests = isset($booking_data['special_requests']) ? htmlspecialchars($booking_data['special_requests'], ENT_QUOTES, 'UTF-8') : '';

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - <?= htmlspecialchars(SITE_NAME) ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .success-badge {
            background: #059669;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            margin: 15px 0;
            font-weight: bold;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .pnr-section {
            background: #f3f4f6;
            border-left: 4px solid #dc2626;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .pnr-section h3 {
            margin: 0 0 10px 0;
            color: #dc2626;
            font-size: 18px;
        }
        .pnr-code {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            letter-spacing: 2px;
        }
        .trip-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
        }
        .trip-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .trip-route {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            font-size: 20px;
            font-weight: bold;
        }
        .route-city {
            color: #dc2626;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .route-arrow {
            margin: 0 15px;
            color: #6b7280;
            font-size: 24px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-value {
            color: #1f2937;
            font-weight: bold;
        }
        .seats-section {
            margin: 25px 0;
        }
        .seats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .seat-badge {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }
        .passenger-info {
            background: #ecfdf5;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .payment-summary {
            background: #fffbeb;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        .amount-paid {
            font-size: 32px;
            font-weight: bold;
            color: #059669;
            margin: 10px 0;
        }
        .important-info {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .important-info h3 {
            color: #92400e;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
        }
        .important-info ul {
            margin: 0;
            padding-left: 20px;
            color: #92400e;
        }
        .important-info li {
            margin: 8px 0;
        }
        .footer {
            background: #f3f4f6;
            padding: 30px;
            text-align: center;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .footer h3 {
            color: #1f2937;
            margin: 0 0 15px 0;
        }
        .contact-info {
            margin: 15px 0;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            color: #dc2626;
            text-decoration: none;
            margin: 0 10px;
        }
        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            .trip-route {
                flex-direction: column;
                gap: 10px;
            }
            .route-arrow {
                transform: rotate(90deg);
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
            <p>Your Trusted Travel Partner</p>
            <div class="success-badge">✓ BOOKING CONFIRMED</div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Greeting -->
            <h2 style="color: #1f2937; margin: 0 0 10px 0;">Hello <?= $passenger_name ?>!</h2>
            <p style="color: #6b7280; margin: 0 0 20px 0;">Thank you for choosing <?= htmlspecialchars(SITE_NAME) ?>. Your booking has been successfully confirmed.</p>

            <!-- PNR Section -->
            <div class="pnr-section">
                <h3>Your Booking Reference (PNR)</h3>
                <div class="pnr-code"><?= $pnr ?></div>
                <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 14px;">Keep this reference number for your records</p>
            </div>

            <!-- Trip Details -->
            <div class="trip-details">
                <div class="trip-header">
                    🚌 Trip Information
                </div>
                
                <div class="trip-route">
                    <div class="route-city"><?= $pickup_city ?></div>
                    <div class="route-arrow">→</div>
                    <div class="route-city"><?= $dropoff_city ?></div>
                </div>

                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">📅 Travel Date</span>
                        <span class="detail-value"><?= $trip_date ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🕐 Departure</span>
                        <span class="detail-value"><?= $departure_time ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🚐 Vehicle Type</span>
                        <span class="detail-value"><?= $vehicle_type ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🔢 Vehicle Number</span>
                        <span class="detail-value"><?= $vehicle_number ?></span>
                    </div>
                    <?php if ($driver_name !== 'N/A'): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <span class="detail-label">👨‍✈️ Driver</span>
                        <span class="detail-value"><?= $driver_name ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Seats -->
                <div class="seats-section">
                    <h4 style="margin: 0 0 10px 0; color: #1f2937;">🪑 Your Seat</h4>
                    <div class="seats-container">
                        <span class="seat-badge">Seat <?= $seat_number ?></span>
                    </div>
                </div>
            </div>

            <!-- Passenger Information -->
            <div class="passenger-info">
                <h3 style="color: #1f2937; margin: 0 0 15px 0;">👤 Passenger Information</h3>
                <div class="details-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item">
                        <span class="detail-label">Name</span>
                        <span class="detail-value"><?= $passenger_name ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?= $email ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?= $phone ?></span>
                    </div>
                    <?php if ($emergency_contact): ?>
                    <div class="detail-item">
                        <span class="detail-label">Emergency Contact</span>
                        <span class="detail-value"><?= $emergency_contact ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($special_requests): ?>
                    <div class="detail-item">
                        <span class="detail-label">Special Requests</span>
                        <span class="detail-value"><?= $special_requests ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="payment-summary">
                <h3 style="color: #1f2937; margin: 0 0 10px 0;">💳 Payment Summary</h3>
                <p style="margin: 5px 0; color: #6b7280;">
                    Total: ₦<?= $total_amount ?>
                </p>
                <div class="amount-paid">₦<?= $total_amount ?></div>
                <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 14px;">
                    Payment Method: <?= $payment_method ?><br>
                    Reference: <?= $payment_reference ?>
                </p>
            </div>

            <!-- Important Information -->
            <div class="important-info">
                <h3>⚠️ Important Travel Information</h3>
                <ul>
                    <li><strong>Arrive Early:</strong> Please be at the terminal at least 30 minutes before departure time</li>
                    <li><strong>Valid ID Required:</strong> Bring a government-issued photo ID for verification</li>
                    <li><strong>Keep Your PNR:</strong> Save this email and your PNR (<?= $pnr ?>) for reference</li>
                    <li><strong>Luggage Policy:</strong> One carry-on bag and one checked bag are included</li>
                    <li><strong>Contact Support:</strong> Call us immediately if you need to make changes to your booking</li>
                </ul>
            </div>

            <!-- Additional Notes -->
            <div style="background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; color: #0c4a6e;"><strong>Note:</strong> Changes or cancellations must be made at least 2 hours before departure. Additional charges may apply.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h3>Need Help?</h3>
            <div class="contact-info">
                <p><strong>Customer Support</strong></p>
                <p>📧 Email: <?= htmlspecialchars(SITE_EMAIL) ?></p>
                <p>📞 Phone: <?= htmlspecialchars(SITE_PHONE) ?></p>
                <p>🕐 Available 24/7</p>
            </div>
            
            <div style="border-top: 1px solid #d1d5db; margin: 20px 0; padding-top: 20px;">
                <p style="margin: 0; font-size: 14px;">
                    This is an automated message. Please do not reply to this email.<br>
                    For support, please contact us using the information above.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #9ca3af;">
                    © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.<br>
                    Safe travels and thank you for choosing us!
                </p>
            </div>
        </div>
    </div>
</body>
</html>

<?php
$email_content = ob_get_clean();
echo $email_content;
?>