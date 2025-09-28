<?php
// about-us.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session (Keep this for session-based header/footer/error messages if needed)
session_start();

// Define SITE_URL and SITE_NAME based on config.php or fallbacks
try {
    require_once __DIR__ . '/includes/config.php';
} catch (Exception $e) {
    // Fallback definitions if config.php fails
    define('SITE_URL', 'https://booking.dgctransports.com');
    define('SITE_NAME', 'DGC Transports');
    error_log("Failed to include config.php in about-us.php: " . $e->getMessage());
}

// NOTE: We don't need to include db.php or fetch cities/vehicle_types for this static page.

// Re-defining custom CSS variables for consistency with index.php
// In a real application, these should be in a separate, common CSS file (e.g., /assets/css/style.css)
// or defined in a common PHP file if you prefer
$custom_styles = '
/*
 * REFACTORED CSS - Included for standalone styling
 */
:root {
    --primary-red: #e30613;
    --dark-red: #c70410;
    --black: #1a1a1a;
    --white: #ffffff;
    --gray-bg: #f5f5f5;
    --light-gray-border: #e5e7eb;
}
body {
    background: linear-gradient(135deg, var(--white), var(--gray-bg));
    font-family: \'Inter\', sans-serif;
}
.container {
    max-width: 1280px;
}
.card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.3s ease;
}
.btn-primary {
    background: linear-gradient(to right, var(--primary-red), var(--dark-red));
    color: var(--white);
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s ease, transform 0.2s ease;
}
.btn-primary:hover {
    background: linear-gradient(to right, var(--dark-red), var(--primary-red));
    transform: translateY(-1px);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.5s ease-out;
}
.text-primary-red {
    color: var(--primary-red);
}
';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        <?php echo $custom_styles; ?>
        /* Specific styles for the about page content if needed */
        .feature-icon {
            color: var(--primary-red);
        }
    </style>
</head>
<body>
    <?php
    // Include the header template
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php in about-us.php: " . $e->getMessage());
        echo '<div class="bg-red-100 border-l-4 border-primary-red text-red-700 p-4" role="alert">
                <p class="font-bold">Error</p>
                <p>Failed to load header. Please contact support.</p>
              </div>';
    }
    ?>
    
    <main class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            <div class="card p-8 lg:p-16 fade-in">
                <div class="max-w-4xl mx-auto">
                    
                    <header class="text-center mb-12">
                        <h1 class="text-5xl font-extrabold text-gray-900 mb-4">
                            Our <span class="text-primary-red">Mission</span>
                        </h1>
                        <p class="text-xl text-gray-600">
                            Redefining travel experiences across Nigeria
                        </p>
                    </header>

                    <section class="space-y-8 text-lg text-gray-700">
                        <div class="flex items-start space-x-4">
                            <i class="fas fa-route text-3xl feature-icon mt-1"></i>
                            <div>
                                <h2 class="text-3xl font-semibold text-gray-800 mb-2">Introducing DGC Transport Services</h2>
                                <p>
                                    <strong>DGC Transport Services</strong> is a new and ambitious road transportation company based in Jos, Plateau State. We are set to redefine travel experiences across Nigeria by providing a superior class of service in road transport.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4">
                            <i class="fas fa-handshake text-3xl feature-icon mt-1"></i>
                            <div>
                                <h2 class="text-3xl font-semibold text-gray-800 mb-2">Our Services and Commitment</h2>
                                <p>
                                    Specializing in both executive charter and commercial services, DGC Transport aims to provide a safe, comfortable, and reliable means of transport from Jos to various destinations nationwide. We understand the growing need for quality road travel, and our commitment is to meet and exceed those expectations.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4">
                            <i class="fas fa-bus-alt text-3xl feature-icon mt-1"></i>
                            <div>
                                <h2 class="text-3xl font-semibold text-gray-800 mb-2">Investment in Quality</h2>
                                <p>
                                    We are investing heavily in a modern fleet, recruiting and training professional drivers, and implementing customer-centric operations. Our goal is to deliver a premium service that prioritizes passenger satisfaction, ensuring every journey with DGC Transport is a pleasant and stress-free experience.
                                </p>
                            </div>
                        </div>
                    </section>
                    
                    <div class="mt-12 text-center">
                         <a href="index.php" class="btn-primary inline-flex items-center">
                            <i class="fas fa-ticket-alt mr-2"></i>Book Your Premium Journey Today!
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php
    // Include the footer template
    try {
        require_once __DIR__ . '/templates/footer.php';
    } catch (Exception $e) {
        error_log("Failed to include footer.php in about-us.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>

</body>
</html>