<?php 
// Assumes $template_data is available with all charter fields
$name = htmlspecialchars($template_data['name'] ?? 'Valued Customer');
$pickup = htmlspecialchars($template_data['pickup_location'] ?? 'N/A');
$dropoff = htmlspecialchars($template_data['dropoff_location'] ?? 'N/A');
$departure = date('M j, Y', strtotime($template_data['departure_date'] ?? ''));
$return = !empty($template_data['return_date']) ? date('M j, Y', strtotime($template_data['return_date'])) : 'One Way';
$passengers = htmlspecialchars($template_data['passengers'] ?? 'N/A');
$vehicle = htmlspecialchars($template_data['preferred_vehicle'] ?? 'Any');
$notes = nl2br(htmlspecialchars($template_data['additional_notes'] ?? 'None'));
?>
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #e30613 0%, #c70410 100%); color: white; padding: 20px; text-align: center;">
        <img src="https://dgctransports.com/assets/images/logo-3.png" alt="<?= SITE_NAME ?> Logo" style="height: 40px; margin-bottom: 10px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: bold;"><?= SITE_NAME ?></h1>
        <p style="margin: 5px 0 0 0; font-size: 16px;">Charter Request Received</p>
    </div>
    <div style="padding: 30px; background: white;">
        <h2 style="color: #1f2937; font-size: 22px; margin-bottom: 15px;">Your Charter Request is Confirmed!</h2>
        <p style="margin-bottom: 15px; color: #4b5563;">Dear*<?= $name ?>,</p>
        <p style="margin-bottom: 25px; color: #4b5563;">Thank you for submitting a charter request to <?= SITE_NAME ?>. Our team has received your details and will be in touch shortly.</p>
        
        <p style="font-weight: bold; color: #1f2937; margin-bottom: 10px;">Your Request Details:</p>
        <div style="background-color: #eff6ff; padding: 15px; border-left: 4px solid #e30613; border-radius: 4px;">
            <p style="margin-bottom: 5px;"><strong>Route:</strong> <?= $pickup ?> &rarr; <?= $dropoff ?></p>
            <p style="margin-bottom: 5px;"><strong>Departure Date:</strong> <?= $departure ?></p>
            <p style="margin-bottom: 5px;"><strong>Return Date:</strong> <?= $return ?></p>
            <p style="margin-bottom: 5px;"><strong>Passengers:</strong> <?= $passengers ?></p>
            <p style="margin-bottom: 5px;"><strong>Preferred Vehicle:</strong> <?= $vehicle ?></p>
            <p style="margin-bottom: 0;"><strong>Additional Notes:</strong></p>
            <p style="white-space: pre-wrap; margin-top: 5px; font-style: italic; color: #374151;"><?= $notes ?></p>
        </div>
        
        <p style="margin-top: 25px; color: #4b5563;">We aim to respond to all charter requests within 24 hours. For urgent inquiries, please call us directly.</p>
        <p style="margin-top: 30px; color: #1f2937;">Best regards,<br>The <?= SITE_NAME ?> Team</p>
    </div>
    <div style="background-color: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280;">
        &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
    </div>
</div>