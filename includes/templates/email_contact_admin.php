<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #e30613 0%, #c70410 100%); color: white; padding: 20px; text-align: center;">
        <img src="https://dgctransports.com/assets/images/logo-3.png" alt="<?= SITE_NAME ?> Logo" style="height: 40px; margin-bottom: 10px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: bold;"><?= SITE_NAME ?></h1>
        <p style="margin: 5px 0 0 0; font-size: 16px;">New Contact Form Submission</p>
    </div>
    <div style="padding: 30px; background: white;">
        <h2 style="color: #1f2937; font-size: 22px; border-bottom: 2px solid #e30613; padding-bottom: 10px; margin-bottom: 20px;">New Inquiry Received</h2>
        <p style="margin-bottom: 15px; color: #4b5563;">A customer has submitted a message via the contact form. Please review and respond quickly.</p>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px;">
            <p style="margin-bottom: 10px; color: #1f2937;"><strong>Name:</strong> <span style="font-weight: normal;"><?= htmlspecialchars($template_data['name']) ?></span></p>
            <p style="margin-bottom: 10px; color: #1f2937;"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($template_data['email']) ?>" style="color: #e30613; text-decoration: none;"><?= htmlspecialchars($template_data['email']) ?></a></p>
            <p style="margin-bottom: 10px; color: #1f2937;"><strong>Subject:</strong> <span style="font-weight: normal;"><?= htmlspecialchars($template_data['subject']) ?></span></p>
            <p style="margin-bottom: 0; color: #1f2937;"><strong>Message:</strong></p>
            <p style="white-space: pre-wrap; margin-top: 5px; padding: 10px; background-color: #ffffff; border: 1px solid #d1d5db; border-radius: 4px;"><?= nl2br(htmlspecialchars($template_data['message'])) ?></p>
        </div>
        
        <p style="margin-top: 25px; color: #6b7280; font-size: 14px;">
            Received at: <?= date('Y-m-d H:i:s') ?>
        </p>
    </div>
    <div style="background-color: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280;">
        This is an automated notification. Do not reply to this email.
    </div>
</div>