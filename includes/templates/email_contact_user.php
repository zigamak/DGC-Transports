<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #e30613 0%, #c70410 100%); color: white; padding: 20px; text-align: center;">
        <img src="https://dgctransports.com/assets/images/logo-3.png" alt="<?= SITE_NAME ?> Logo" style="height: 40px; margin-bottom: 10px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: bold;"><?= SITE_NAME ?></h1>
        <p style="margin: 5px 0 0 0; font-size: 16px;">Confirmation of Your Message</p>
    </div>
    <div style="padding: 30px; background: white;">
        <h2 style="color: #1f2937; font-size: 22px; margin-bottom: 15px;">We’ve Received Your Message!</h2>
        <p style="margin-bottom: 15px; color: #4b5563;">Dear <?= htmlspecialchars($template_data['name']) ?>,</p>
        <p style="margin-bottom: 25px; color: #4b5563;">Thank you for reaching out to <?= SITE_NAME ?>. We confirm that your message has been successfully received, and our team will get back to you shortly.</p>
        
        <p style="font-weight: bold; color: #1f2937; margin-bottom: 10px;">Your Message Details:</p>
        <div style="background-color: #eff6ff; padding: 15px; border-left: 4px solid #e30613; border-radius: 4px;">
            <p style="margin-bottom: 5px;"><strong>Subject:</strong> <?= htmlspecialchars($template_data['subject']) ?></p>
            <p style="margin-bottom: 0;"><strong>Message:</strong></p>
            <p style="white-space: pre-wrap; margin-top: 5px; font-style: italic; color: #374151;"><?= nl2br(htmlspecialchars($template_data['message'])) ?></p>
        </div>
        
        <p style="margin-top: 25px; color: #4b5563;">If you have any further questions while you wait, please feel free to contact us by replying to this email.</p>
        <p style="margin-top: 30px; color: #1f2937;">Best regards,<br>The <?= SITE_NAME ?> Team</p>
    </div>
    <div style="background-color: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280;">
        &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
    </div>
</div>