<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;">
    <!-- Header with Logo -->
    <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px 20px; text-align: center;">
        <a href="<?= SITE_URL ?>/index.php" style="display: inline-block; margin-bottom: 10px;">
            <img src="<?= SITE_URL ?>/assets/images/logo-3.png" alt="Logo" style="max-height: 60px;" onerror="this.src='https://via.placeholder.com/150x50?text=Logo';">
        </a>
        <h1 style="margin: 0; font-size: 26px; font-weight: bold;"><?php echo SITE_NAME; ?></h1>
        <p style="margin: 5px 0 0 0; font-size: 15px;">Password Reset Request</p>
    </div>

    <!-- Body -->
    <div style="padding: 30px; background: white; border-radius: 0 0 10px 10px;">
        <h2 style="color: #dc2626; margin-bottom: 20px; font-size: 20px;">Reset Your Password</h2>
        <p style="font-size: 15px; color: #333;">You have requested to reset your password for <strong><?php echo SITE_NAME; ?></strong>.</p>
        <p style="font-size: 15px; color: #333;">Please click the button below to reset your password:</p>

        <!-- Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?php echo SITE_URL; ?>/reset_password.php?token=<?php echo htmlspecialchars($reset_data['token']); ?>"
               style="background: #dc2626; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: bold; display: inline-block;">
               Reset Password
            </a>
        </div>

        <p style="font-size: 14px; color: #555;">This link will expire at <strong><?php echo htmlspecialchars($reset_data['expires_at']); ?></strong>.</p>
        <p style="font-size: 14px; color: #555;">If you did not request a password reset, please ignore this email or contact our support team.</p>
        <p style="margin-top: 20px; font-size: 15px; color: #333;">Thank you,<br><strong><?php echo SITE_NAME; ?> Team</strong></p>
    </div>
</div>
