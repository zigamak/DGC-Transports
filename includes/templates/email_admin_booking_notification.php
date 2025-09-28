<?php
// includes/templates/email_admin_booking_notification.php
require_once __DIR__ . '/../../includes/config.php';

// Ensure template_data is set and provide defaults
$template_data = isset($template_data) ? $template_data : [];
$bookings = isset($template_data['bookings']) ? $template_data['bookings'] : [];
$total_amount = isset($template_data['total_amount']) ? number_format($template_data['total_amount'], 0) : '0';
$payment_method = isset($template_data['payment_method']) && $template_data['payment_method'] ? htmlspecialchars(ucfirst($template_data['payment_method']), ENT_QUOTES, 'UTF-8') : 'Unknown';
$payment_reference = isset($template_data['payment_reference']) ? htmlspecialchars($template_data['payment_reference'], ENT_QUOTES, 'UTF-8') : 'N/A';

// Log template_data for debugging
error_log("email_admin_booking_notification.php received template_data: " . json_encode($template_data));

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Booking Notification - <?= htmlspecialchars(SITE_NAME) ?></title>
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
            background-color: #ffffff;
            border-bottom: 3px solid #dc2626;
            padding: 20px;
            text-align: center;
            color: #1f2937;
        }
        .header h1 {
            margin: 5px 0 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #dc2626;
        }
        .header p {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .content {
            padding: 30px;
        }
        .booking-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
        }
        .booking-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
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
        .passenger-section {
            margin: 25px 0;
        }
        .passenger-card {
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .passenger-card:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
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
        .footer {
            background: #f3f4f6;
            padding: 30px;
            text-align: center;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <a href="<?= SITE_URL ?>">
                <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="Logo" style="height: 40px; width: auto; max-width: 100%;">
            </a>
            <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
            <p>Your Trusted Travel Partner</p>
        </div>

        <div class="content">
            <h2 style="color: #1f2937; margin: 0 0 10px 0;">New Booking Notification</h2>
            <p style="color: #6b7280; margin: 0 0 20px 0;">A new booking has been confirmed. Please review the details below.</p>

            <?php foreach ($bookings as $index => $booking): ?>
                <?php
                $passenger_name = isset($booking['passenger_name']) ? htmlspecialchars($booking['passenger_name'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $pnr = isset($booking['pnr']) ? htmlspecialchars($booking['pnr'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $seat_number = isset($booking['seat_number']) ? htmlspecialchars($booking['seat_number'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $email = isset($booking['email']) && !empty($booking['email']) ? htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $phone = isset($booking['phone']) && !empty($booking['phone']) ? htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $trip = isset($booking['trip']) ? $booking['trip'] : [];
                $pickup_city = isset($trip['pickup_city']) ? htmlspecialchars($trip['pickup_city'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $dropoff_city = isset($trip['dropoff_city']) ? htmlspecialchars($trip['dropoff_city'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $trip_date = isset($trip['trip_date']) ? date('D, M j, Y', strtotime($trip['trip_date'])) : 'N/A';
                $departure_time = isset($trip['departure_time']) && $trip['departure_time'] ? date('H:i', strtotime($trip['departure_time'])) : 'N/A';
                $vehicle_type = isset($trip['vehicle_type']) ? htmlspecialchars($trip['vehicle_type'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $vehicle_number = isset($trip['vehicle_number']) ? htmlspecialchars($trip['vehicle_number'], ENT_QUOTES, 'UTF-8') : 'N/A';
                $driver_name = isset($trip['driver_name']) && $trip['driver_name'] ? htmlspecialchars($trip['driver_name'], ENT_QUOTES, 'UTF-8') : 'N/A';
                ?>
                <div class="booking-details">
                    <div class="booking-header">
                        🚌 Booking <?php echo $index + 1; ?> (PNR: <?= $pnr ?>)
                    </div>
                    <div class="passenger-section">
                        <h4 style="color: #1f2937; margin: 0 0 10px 0;">Passenger Information</h4>
                        <div class="passenger-card">
                            <div class="details-grid" style="grid-template-columns: 1fr;">
                                <div class="detail-item">
                                    <span class="detail-label">Name</span>
                                    <span class="detail-value"><?= $passenger_name ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?= $email ?></span>
                                </div>
                                <?php if ($phone !== 'N/A'): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?= $phone ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Seat</span>
                                    <span class="detail-value"><?= $seat_number ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Route</span>
                            <span class="detail-value"><?= $pickup_city ?> → <?= $dropoff_city ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Travel Date</span>
                            <span class="detail-value"><?= $trip_date ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Departure</span>
                            <span class="detail-value"><?= $departure_time ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Type</span>
                            <span class="detail-value"><?= $vehicle_type ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Number</span>
                            <span class="detail-value"><?= $vehicle_number ?></span>
                        </div>
                        <?php if ($driver_name !== 'N/A'): ?>
                        <div class="detail-item">
                            <span class="detail-label">Driver</span>
                            <span class="detail-value"><?= $driver_name ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

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
        </div>

        <div class="footer">
            <p style="margin: 0; font-size: 14px;">
                This is an automated notification. Please review the booking details in the admin panel.
            </p>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #9ca3af;">
                © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>

<?php
$email_content = ob_get_clean();
echo $email_content;
?>