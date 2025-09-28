<?php
// charter.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session
session_start();

// Include required files with error handling
try {
    // This will provide $conn
    require_once __DIR__ . '/includes/db.php';
} catch (Exception $e) {
    error_log("Failed to include db.php in charter.php: " . $e->getMessage());
    die("Error: Database connection failed. Please check server logs.");
}

try {
    require_once __DIR__ . '/includes/config.php';
} catch (Exception $e) {
    error_log("Failed to include config.php in charter.php: " . $e->getMessage());
    define('SITE_URL', 'https://booking.dgctransports.com');
    define('SITE_NAME', 'DGC Transports'); // Fallback
}

// Fetch vehicle types for the form
$vehicle_types_result = null;
$vehicle_types = [];
$today = date('Y-m-d');

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    // Fetch Vehicle Types
    $table_check_vehicles = $conn->query("SHOW TABLES LIKE 'vehicle_types'");
    if ($table_check_vehicles && $table_check_vehicles->num_rows > 0) {
        $vehicle_types_result = $conn->query("SELECT id, type, capacity FROM vehicle_types ORDER BY type");
        if ($vehicle_types_result) {
             while ($vehicle = $vehicle_types_result->fetch_assoc()) {
                $vehicle_types[] = $vehicle;
             }
        }
    } else {
        error_log("Table 'vehicle_types' does not exist in database for charter.");
    }
} else {
    error_log("Database connection not established in charter.php.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charter a Vehicle - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
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
    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    .input-field {
        border: 2px solid var(--light-gray-border);
        border-radius: 8px;
        padding: 10px 12px;
        width: 100%;
        color: var(--black);
        font-size: 1rem;
        box-sizing: border-box;
    }
    .input-field:focus {
        outline: none;
        border-color: var(--primary-red);
        box-shadow: 0 0 8px rgba(227, 6, 19, 0.2);
    }
    .error-message, .success-message {
        font-size: 0.9rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        font-weight: 500;
        padding: 8px;
        border-radius: 4px;
    }
    .error-message {
        color: var(--primary-red);
        border-left: 4px solid var(--primary-red);
        background-color: #ffeaea;
    }
    .success-message {
        color: #155724;
        border-left: 4px solid #155724;
        background-color: #d4edda;
    }
    .error-message i, .success-message i {
        margin-right: 8px;
    }
    .tab-item {
        padding: 10px 15px;
        cursor: pointer;
        font-weight: 600;
        color: #6b7280;
        border-bottom: 3px solid transparent;
        transition: color 0.2s, border-color 0.2s;
    }
    .tab-item.active-tab {
        color: var(--primary-red);
        border-bottom-color: var(--primary-red);
    }
    .charter-hero {
        background: linear-gradient(135deg, rgba(227, 6, 19, 0.8), rgba(199, 4, 16, 0.8)), url("<?= defined('SITE_URL') ? SITE_URL : 'https://booking.dgctransports.com' ?>/assets/images/bus_charter.jpg");
        background-size: cover;
        background-position: center;
    }
    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 8px;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
</head>
<body>
    <?php
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php in charter.php: " . $e->getMessage());
        echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Failed to load header. Please contact support.</div>';
    }
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container max-w-2xl w-full">
            <div class="card overflow-hidden">
                <div class="p-8 sm:p-12">
                    <div class="flex border-b mb-8">
                        <a href="index.php" class="tab-item">
                            <i class="fas fa-route mr-2"></i>Book Trip
                        </a>
                        <a href="charter.php" class="tab-item active-tab">
                            <i class="fas fa-car-side mr-2"></i>Charter Vehicle
                        </a>
                    </div>

                    <div class="text-center mb-8">
                        <h1 class="text-4xl font-bold text-gray-800">
                            <span class="text-primary-red">Charter</span> a Vehicle
                        </h1>
                        <p class="text-gray-600 text-lg">Request a private vehicle for your custom journey.</p>
                    </div>

                    <?php if (isset($_SESSION['charter_success'])): ?>
                        <div class="success-message"><i class="fas fa-check-circle"></i><?= htmlspecialchars($_SESSION['charter_success']) ?></div>
                        <?php unset($_SESSION['charter_success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['charter_error'])): ?>
                        <div class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($_SESSION['charter_error']) ?></div>
                        <?php unset($_SESSION['charter_error']); ?>
                    <?php endif; ?>

                    <div id="success-message" class="hidden success-message text-center p-6">
                        <i class="fas fa-check-circle text-2xl mb-4"></i>
                        <h2 class="text-2xl font-semibold text-gray-700 mb-2">Charter Request Submitted Successfully!</h2>
                        <p class="text-gray-600">We will get back to you soon with further details.</p>
                    </div>

                    <form id="charterForm" action="bookings/process_charter.php" method="POST" class="space-y-6">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Route Details</h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="pickup_location" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                    Traveling From (Pickup Location)
                                </label>
                                <input type="text" class="input-field" id="pickup_location" name="pickup_location" required placeholder="Enter pickup location" aria-label="Pickup location">
                            </div>
                            <div class="form-group">
                                <label for="dropoff_location" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                    Traveling To (Destination Location)
                                </label>
                                <input type="text" class="input-field" id="dropoff_location" name="dropoff_location" required placeholder="Enter destination location" aria-label="Destination location">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div class="form-group">
                                <label for="vehicle_type_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-bus text-primary-red mr-2"></i>
                                    Preferred Vehicle Type
                                </label>
                                <select class="input-field" id="vehicle_type_id" name="vehicle_type_id" required aria-label="Vehicle type">
                                    <option value="">Choose vehicle type</option>
                                    <?php foreach ($vehicle_types as $vehicle): ?>
                                        <option value="<?= $vehicle['id'] ?>">
                                            <?= htmlspecialchars($vehicle['type']) ?> (Max <?= $vehicle['capacity'] ?> seats)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="num_seats" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-users text-primary-red mr-2"></i>
                                    Approximate Number of Passengers
                                </label>
                                <input type="number" class="input-field" id="num_seats" name="num_seats" value="1" min="1" required aria-label="Number of seats">
                            </div>
                            <div class="form-group">
                                <label for="departure_date" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                    Departure Date
                                </label>
                                <input type="date" class="input-field" id="departure_date" name="departure_date" required min="<?= $today ?>" aria-label="Departure date">
                            </div>
                        </div>

                        <h2 class="text-xl font-semibold text-gray-700 mb-4 pt-4 border-t">Contact Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                                <input type="text" class="input-field" id="full_name" name="full_name" required aria-label="Full Name">
                            </div>
                            <div class="form-group">
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" class="input-field" id="email" name="email" required aria-label="Email Address">
                            </div>
                            <div class="form-group">
                                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" class="input-field" id="phone" name="phone" required aria-label="Phone Number">
                            </div>
                            <div class="form-group">
                                <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes (Optional)</label>
                                <textarea class="input-field" id="notes" name="notes" rows="1" aria-label="Additional notes"></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" id="submitButton" class="btn-primary w-full mt-6 flex items-center justify-center">
                            <span id="buttonText"><i class="fas fa-paper-plane mr-2"></i>Submit Charter Request</span>
                            <span id="spinner" class="spinner hidden"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php 
    try {
        require_once __DIR__ . '/templates/footer.php'; 
    } catch (Exception $e) {
        error_log("Failed to include footer.php in charter.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('charterForm');
            const submitButton = document.getElementById('submitButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('spinner');
            const successMessage = document.getElementById('success-message');
            const today = '<?= $today ?>';

            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Clear previous errors
                document.querySelectorAll('.error-message.temp-error').forEach(el => el.remove());

                // Client-side validation
                const pickupLocation = document.getElementById('pickup_location').value.trim();
                const dropoffLocation = document.getElementById('dropoff_location').value.trim();
                const departureDate = document.getElementById('departure_date').value;
                const numSeats = parseInt(document.getElementById('num_seats').value);
                const full_name = document.getElementById('full_name').value.trim();
                const email = document.getElementById('email').value.trim();

                let isValid = true;
                let errorMessage = '';

                if (pickupLocation === dropoffLocation && pickupLocation !== '') {
                    isValid = false;
                    errorMessage = 'Pickup and dropoff locations cannot be the same.';
                } else if (pickupLocation === '' || dropoffLocation === '') {
                    isValid = false;
                    errorMessage = 'Please enter both pickup and dropoff locations.';
                } else if (numSeats < 1) {
                    isValid = false;
                    errorMessage = 'Number of passengers must be at least 1.';
                } else if (departureDate < today) {
                    isValid = false;
                    errorMessage = 'Departure date cannot be in the past.';
                } else if (!full_name || !email) {
                    isValid = false;
                    errorMessage = 'Please ensure all contact fields are filled.';
                }

                if (!isValid) {
                    showError(errorMessage);
                    return;
                }

                // Show spinner, disable button
                submitButton.disabled = true;
                buttonText.classList.add('hidden');
                spinner.classList.remove('hidden');

                // Collect form data
                const formData = new FormData(form);

                // Submit form via AJAX
                fetch('bookings/process_charter.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button state
                    submitButton.disabled = false;
                    buttonText.classList.remove('hidden');
                    spinner.classList.add('hidden');

                    if (data.success) {
                        // Hide form and show success message
                        form.classList.add('hidden');
                        successMessage.classList.remove('hidden');
                    } else {
                        // Show errors
                        const errorMsg = data.message + (data.errors ? ': ' + data.errors.join(', ') : '');
                        showError(errorMsg);
                    }
                })
                .catch(error => {
                    // Reset button state
                    submitButton.disabled = false;
                    buttonText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                    showError('An unexpected error occurred. Please try again later.');
                    console.error('Error:', error);
                });
            });

            function showError(message) {
                const errorDiv = document.createElement('p');
                errorDiv.className = 'error-message temp-error';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
                form.parentNode.prepend(errorDiv);
                setTimeout(() => errorDiv.remove(), 5000);
            }
        });
    </script>
</body>
</html>