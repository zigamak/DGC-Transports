<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;">
    <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;"><?php echo SITE_NAME; ?></h1>
        <p style="margin: 5px 0 0 0;">Set Up Your Account Password</p>
    </div>
    <div style="padding: 30px; background: white;">
        <h2 style="color: #dc2626; margin-bottom: 20px;">Welcome to <?php echo SITE_NAME; ?>!</h2>
        <p>Your account has been created with the email: <strong><?php echo htmlspecialchars($reset_data['email']); ?></strong>.</p>
        <p>Please click the button below to set up your password:</p>
        <div style="text-align: center; margin: 20px 0;">
            <a href="<?php echo SITE_URL; ?>/set_password.php?token=<?php echo htmlspecialchars($reset_data['token']); ?>" style="background: #dc2626; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block;">Set Password</a>
        </div>
        <p>This link will expire at <?php echo htmlspecialchars($reset_data['expires_at']); ?>.</p>
        <p>If you did not request this account, please ignore this email or contact our support team.</p>
        <p>Thank you, <?php echo SITE_NAME; ?> Team</p>
    </div>
</div>