<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa;">
    <div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;"><?php echo SITE_NAME; ?></h1>
        <p style="margin: 5px 0 0 0;">Welcome to Your Account</p>
    </div>
    <div style="padding: 30px; background: white;">
        <h2 style="color: #dc2626; margin-bottom: 20px;">Your Account Details</h2>
        <p>Welcome to <?php echo SITE_NAME; ?>!</p>
        <p>Your account has been created with the following details:</p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
        <p><strong>Password:</strong> <?php echo htmlspecialchars($user_data['password']); ?></p>
        <p>Please log in at <a href="<?php echo SITE_URL; ?>/login.php" style="color: #dc2626; text-decoration: underline;">this link</a> using the provided password and change it immediately for security.</p>
        <p>If you have any questions, please contact our support team.</p>
        <p>Thank you for joining <?php echo SITE_NAME; ?>!</p>
    </div>
</div>