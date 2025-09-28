<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa;">
    <div style="max-width: 600px; margin: 20px auto; background-color: #f8f9fa;">
        <!-- Header with Logo -->
        <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px 20px; text-align: center;">
            <a href="<?php echo htmlspecialchars(SITE_URL); ?>/index.php" style="display: inline-block; margin-bottom: 10px;">
                <img src="<?php echo htmlspecialchars(SITE_URL); ?>/assets/images/logo-3.png" alt="Logo" style="max-height: 60px;" onerror="this.src='https://via.placeholder.com/150x50?text=Logo';">
            </a>
            <h1 style="margin: 0; font-size: 26px; font-weight: bold;"><?php echo htmlspecialchars(SITE_NAME); ?></h1>
            <p style="margin: 5px 0 0 0; font-size: 15px;">Set Up Your Account Password</p>
        </div>

        <!-- Body -->
        <div style="padding: 30px; background: white; border-radius: 0 0 10px 10px;">
            <h2 style="color: #dc2626; margin-bottom: 20px; font-size: 20px;">Welcome to <?php echo htmlspecialchars(SITE_NAME); ?>!</h2>
            <?php if (isset($template_data['email']) && !empty($template_data['email'])): ?>
                <p style="font-size: 15px; color: #333;">Your account has been created with the email: <strong><?php echo htmlspecialchars($template_data['email']); ?></strong>.</p>
            <?php else: ?>
                <p style="font-size: 15px; color: #333;">Your account has been created.</p>
            <?php endif; ?>
            <p style="font-size: 15px; color: #333;">Please click the button below to set up your password:</p>

            <!-- Button -->
            <div style="text-align: center; margin: 30px 0;">
                <?php if (isset($template_data['token']) && !empty($template_data['token'])): ?>
                    <a href="<?php echo htmlspecialchars(SITE_URL . '/set_password.php?token=' . urlencode($template_data['token'])); ?>"
                       style="background: #dc2626; color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: bold; display: inline-block;">
                       Set Password
                    </a>
                <?php else: ?>
                    <p style="font-size: 14px; color: #dc2626;">Error: Password setup link is unavailable. Please contact support.</p>
                <?php endif; ?>
            </div>

            <?php if (isset($template_data['expires_at']) && !empty($template_data['expires_at'])): ?>
                <p style="font-size: 14px; color: #555;">This link will expire at <strong><?php echo htmlspecialchars($template_data['expires_at']); ?></strong>.</p>
            <?php else: ?>
                <p style="font-size: 14px; color: #555;">This link has a limited validity period. Please set your password promptly.</p>
            <?php endif; ?>
            <p style="font-size: 14px; color: #555;">If you did not request this account, please ignore this email or contact our support team at <a href="mailto:<?php echo htmlspecialchars(FROM_EMAIL); ?>" style="color: #dc2626; text-decoration: none;"><?php echo htmlspecialchars(FROM_EMAIL); ?></a>.</p>
            <p style="margin-top: 20px; font-size: 15px; color: #333;">Thank you,<br><strong><?php echo htmlspecialchars(SITE_NAME); ?> Team</strong></p>
        </div>
    </div>
</body>
</html>