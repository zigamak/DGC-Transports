<?php
// privacy-policy.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - DGC Transports</title>
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
        <h1 class="text-4xl md:text-5xl font-extrabold text-center text-gray-900 mb-6 border-b-2 border-red-600 pb-4">Privacy Policy</h1>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">1. Information We Collect</h2>
            <p class="text-gray-600 leading-relaxed">We collect personal information that you voluntarily provide to us when you register on the site or when you book a trip. This information includes your name, email address, phone number, and payment details.</p>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">2. How We Use Your Information</h2>
            <p class="text-gray-600 leading-relaxed">The information we collect is used to:</p>
            <ul class="list-disc list-inside space-y-2 text-gray-600 mt-2">
                <li>Process your bookings and payments.</li>
                <li>Communicate with you regarding your trip details.</li>
                <li>Improve our website and services.</li>
                <li>Send you marketing communications, where you have opted in.</li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">3. Data Security</h2>
            <p class="text-gray-600 leading-relaxed">We implement a variety of security measures to maintain the safety of your personal information. Your data is stored on secure servers and protected by encryption protocols. We do not store sensitive payment information on our servers.</p>
        </section>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">4. Sharing Your Information</h2>
            <p class="text-gray-600 leading-relaxed">We may share your information with our trusted third-party partners (e.g., transport providers, payment processors) to facilitate the services you have requested. We do not sell or rent your personal data to third parties for their marketing purposes.</p>
        </section>
        
        <section>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">5. Your Rights</h2>
            <p class="text-gray-600 leading-relaxed">You have the right to access, update, or delete your personal information. You can do this by logging into your account or by contacting us directly.</p>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/templates/footer.php'; ?>

</body>
</html>