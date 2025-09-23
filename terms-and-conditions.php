<?php
// templates/terms-and-conditions.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - DGC Transports</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .content-container { max-width: 1200px; }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans">

<?php require_once __DIR__ . '/templates/header.php'; ?>

<main class="min-h-screen py-20 px-4 sm:px-6 lg:px-8">
    <div class="content-container mx-auto bg-white rounded-lg shadow-xl p-8 md:p-12">
        <h1 class="text-4xl md:text-5xl font-extrabold text-center text-gray-900 mb-6 border-b-2 border-red-600 pb-4">Terms and Conditions</h1>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">1. Acceptance of Terms</h2>
            <p class="text-gray-600 leading-relaxed">By accessing or using our services, you agree to be bound by these Terms and Conditions. If you do not agree with any part of the terms, you must not use our services.</p>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">2. User Responsibilities</h2>
            <ul class="list-disc list-inside space-y-2 text-gray-600">
                <li>You must provide accurate and complete information during the booking process.</li>
                <li>You are responsible for the safety of your personal belongings.</li>
                <li>You must adhere to all laws and regulations, as well as the rules set by our transport partners.</li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">3. Booking and Payment</h2>
            <p class="text-gray-600 leading-relaxed">All bookings are subject to availability. Payment must be made in full at the time of booking unless otherwise stated. We reserve the right to cancel a booking if payment is not received in a timely manner.</p>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">4. Cancellations and Refunds</h2>
            <p class="text-gray-600 leading-relaxed">Cancellation policies vary depending on the trip and partner. Please check the specific cancellation policy before completing your booking. We are not liable for any third-party fees associated with cancellations.</p>
        </section>
        
        <section>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">5. Limitation of Liability</h2>
            <p class="text-gray-600 leading-relaxed">DGC Transports is a booking agent and is not liable for any loss, damage, or injury incurred during a trip. Our liability is limited to the booking fee paid to us.</p>
        </section>
        
    </div>
</main>

<?php require_once __DIR__ . '/templates/footer.php'; ?>

</body>
</html>