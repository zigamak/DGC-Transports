<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;">
    <!-- Header with Logo -->
    <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px 20px; text-align: center;">
        <a href="<?= htmlspecialchars(SITE_URL) ?>/index.php" style="display: inline-block; margin-bottom: 10px;">
            <img src="https://dgctransports.com/assets/images/logo-3.png" alt="DGC Transports Logo" style="max-height: 60px; width: auto;" onerror="this.src='https://via.placeholder.com/150x50?text=Logo';">
        </a>
        <h1 style="margin: 0; font-size: 26px; font-weight: bold;"><?= htmlspecialchars(SITE_NAME) ?></h1>
        <p style="margin: 5px 0 0 0; font-size: 15px;">Password Reset Request</p>
    </div>

    <!-- Body -->
    <div style="padding: 30px; background: white; border-radius: 0 0 10px 10px;">
        <h2 style="color: #dc2626; margin-bottom: 20px; font-size: 20px;">Reset Your Password</h2>
        <p style="font-size: 15px; color: #333; line-height: 1.6;">
            We received a request to reset the password for your account with the email: 
            <strong><?= htmlspecialchars($template_data['email'] ?? 'N/A') ?></strong>.
        </p>
        <p style="font-size: 15px; color: #333; line-height: 1.6;">
            Please click the button below to reset your password:
        </p>

        <!-- Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?= htmlspecialchars(SITE_URL . '/reset_password.php?token=' . ($template_data['token'] ?? '')) ?>"
               style="background: #dc2626; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: bold; display: inline-block; transition: background 0.3s ease;">
               Reset Password
            </a>
        </div>

        <p style="font-size: 14px; color: #555; line-height: 1.6;">
            This link will expire at <strong><?= htmlspecialchars($template_data['expires_at'] ?? 'N/A') ?></strong>.
        </p>
        <p style="font-size: 14px; color: #555; line-height: 1.6;">
            If you did not request a password reset, please ignore this email or contact our support team at 
            <a href="mailto:admin@dgctransports.com" style="color: #dc2626; text-decoration: underline;">admin@dgctransports.com</a>.
        </p>
        <p style="margin-top: 20px; font-size: 15px; color: #333; line-height: 1.6;">
            Thank you,<br><strong><?= htmlspecialchars(SITE_NAME) ?> Team</strong>
        </p>
    </div>

    <!-- Footer -->
    <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e5e7eb;">
        <p style="margin: 0;">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.</p>
        <p style="margin: 5px 0 0;">
            <a href="<?= htmlspecialchars(SITE_URL) ?>/privacy-policy.php" style="color: #dc2626; text-decoration: none;">Privacy Policy</a> | 
            <a href="<?= htmlspecialchars(SITE_URL) ?>/terms-of-service.php" style="color: #dc2626; text-decoration: none;">Terms of Service</a>
        </p>
    </div>
</div>