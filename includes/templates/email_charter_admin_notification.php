<?php 
// Assumes $template_data is available with all charter fields
$name = htmlspecialchars($template_data['name'] ?? 'N/A');
$email = htmlspecialchars($template_data['email'] ?? 'N/A');
$phone = htmlspecialchars($template_data['phone'] ?? 'N/A');
$pickup = htmlspecialchars($template_data['pickup_location'] ?? 'N/A');
$dropoff = htmlspecialchars($template_data['dropoff_location'] ?? 'N/A');
$departure = date('M j, Y', strtotime($template_data['departure_date'] ?? ''));
$return = !empty($template_data['return_date']) ? date('M j, Y', strtotime($template_data['return_date'])) : 'One Way (N/A)';
$passengers = htmlspecialchars($template_data['passengers'] ?? 'N/A');
$luggage = nl2br(htmlspecialchars($template_data['luggage_details'] ?? 'Not specified'));
$vehicle = htmlspecialchars($template_data['preferred_vehicle'] ?? 'Any');
$notes = nl2br(htmlspecialchars($template_data['additional_notes'] ?? 'None'));
?>
<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; background-color: #fff; border: 1px solid #e30613; border-radius: 8px; overflow: hidden;">
    <div style="background-color: #e30613; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 26px; font-weight: bold;">NEW CHARTER REQUEST 🚨</h1>
        <p style="margin: 5px 0 0 0; font-size: 18px;"><?= SITE_NAME ?> Notification</p>
    </div>
    <div style="padding: 30px; background: white;">
        <p style="margin-bottom: 20px; font-size: 16px; color: #1f2937;">A new charter request has been submitted. Please review the details below.</p>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tbody style="font-size: 14px;">
                <tr><td colspan="2" style="background-color: #f3f4f6; padding: 10px; font-weight: bold; border-top: 1px solid #ccc;">CLIENT CONTACT INFO</td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; width: 30%; font-weight: bold;">Name:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $name ?></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Email:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><a href="mailto:<?= $email ?>" style="color: #e30613;"><?= $email ?></a></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Phone:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $phone ?></td></tr>

                <tr><td colspan="2" style="background-color: #f3f4f6; padding: 10px; font-weight: bold; border-top: 1px solid #ccc; margin-top: 15px;">TRIP DETAILS</td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Route:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $pickup ?> &rarr; <?= $dropoff ?></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Departure Date:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $departure ?></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Return Date:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $return ?></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Passengers:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $passengers ?></td></tr>
                <tr><td style="padding: 8px 10px; border-bottom: 1px dashed #eee; font-weight: bold;">Preferred Vehicle:</td><td style="padding: 8px 10px; border-bottom: 1px dashed #eee;"><?= $vehicle ?></td></tr>
                <tr><td style="padding: 8px 10px; font-weight: bold;">Luggage Details:</td><td style="padding: 8px 10px;"><?= $luggage ?></td></tr>
            </tbody>
        </table>
        
        <div style="background-color: #ffe5e7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <p style="margin: 0; font-weight: bold; color: #e30613;">Additional Notes from Client:</p>
            <p style="margin-top: 5px; color: #1f2937; white-space: pre-wrap;"><?= $notes ?></p>
        </div>

        <p style="margin-top: 30px; color: #1f2937;">System Notification:<br>This email was sent to admin@dgctransports.com and CC'd to staff@dgctransports.com.</p>
    </div>
    <div style="background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #eee;">
        This is an automated notification from <?= SITE_NAME ?>.
    </div>
</div>