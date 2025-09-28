<?php
// contact_success.php
session_start();


// Check for the success message in the session
$success_message = $_SESSION['success_message'] ?? null;

// Include config for SITE_NAME and header/footer includes
try {
    require_once __DIR__ . '/includes/config.php';
} catch (Exception $e) {
    // Handle config error if necessary
    define('SITE_NAME', 'DGC Transports'); 
}

// If no success message, redirect them back to the contact page
if (!$success_message) {
    header('Location: contact.php');
    exit;
}

// Clear the success message so it doesn't show on subsequent page loads
unset($_SESSION['success_message']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --white: #ffffff;
            --gray-bg: #f5f5f5;
        }
        body {
            background: linear-gradient(135deg, var(--white), var(--gray-bg));
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .success-icon {
            color: #10b981; /* Tailwind 'emerald-500' for a strong success color */
        }
    </style>
</head>
<body>
    <?php
    // Include header.php
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php: " . $e->getMessage());
    }
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container w-full max-w-lg">
            <div class="card p-12 text-center">
                <i class="fas fa-check-circle success-icon text-7xl mb-6 animate-pulse"></i>
                <h1 class="text-3xl font-bold text-gray-800 mb-4">Message Sent Successfully!</h1>
                <p class="text-lg text-gray-600 mb-8"><?= htmlspecialchars($success_message) ?></p>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <p class="text-sm text-green-700 font-medium flex items-center justify-center">
                        <i class="fas fa-info-circle mr-2"></i>A confirmation email has been sent to your inbox.
                    </p>
                </div>
                <a href="index.php" class="inline-block mt-8 text-primary-red hover:text-dark-red font-semibold transition duration-150 ease-in-out">
                    <i class="fas fa-home mr-2"></i>Go to Homepage
                </a>
            </div>
        </div>
    </div>
    <?php
    // Include footer.php
    try {
        require_once __DIR__ . '/templates/footer.php';
    } catch (Exception $e) {
        error_log("Failed to include footer.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>
</body>
</html>