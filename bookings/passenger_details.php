<?php
// bookings/passenger_details.php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../templates/header.php';
} catch (Exception $e) {
    error_log("Failed to include header.php: " . $e->getMessage());
    echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Failed to load header. Please contact support.</div>';
}

// Check if trip and seats are selected
if (!isset($_SESSION['selected_trip']) || !isset($_SESSION['selected_seats'])) {
    header("Location: " . SITE_URL . "/bookings/search_trips.php");
    exit();
}

$trip = $_SESSION['selected_trip'];
$selected_seats = $_SESSION['selected_seats'];
$num_seats = count($selected_seats);
$total_amount = $num_seats * $trip['price'];

// Validate selected seats count matches required seats
if ($num_seats !== $trip['num_seats']) {
    header("Location: " . SITE_URL . "/bookings/seat_selection.php");
    exit();
}

// Check if user is logged in
$is_logged_in = isLoggedIn();

// Check for free booking eligibility
$free_booking = false;
if ($is_logged_in) {
    $user_id = $_SESSION['user']['id'];
    $query = "SELECT COUNT(*) as booking_count, MAX(free_booking_used) as free_booking_used 
              FROM bookings 
              WHERE user_id = ? AND payment_status = 'paid'";
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Failed to prepare query: " . $conn->error);
            echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Database error. Please try again later.</div>';
            exit();
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['booking_count'] >= 5 && !$row['free_booking_used']) {
            $free_booking = true;
            $total_amount = 0;
        }
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Database error. Please try again later.</div>';
        exit();
    }
}

// Referral message (no discount applied to user)
$referral_message = '';
if (isset($_SESSION['referral_message'])) {
    $referral_message = $_SESSION['referral_message'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Details - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold text-black">Passenger Details</h1>
                    <button onclick="goBack()" class="text-primary hover:text-secondary font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Seat Selection
                    </button>
                </div>
                <div class="text-sm text-gray-600">
                    Please provide passenger information for <?= $num_seats ?> seat(s): <?= implode(', ', array_map(function($seat) { return "Seat $seat"; }, $selected_seats)) ?>
                </div>
                <?php if ($free_booking): ?>
                    <div class="mt-4 p-4 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-start text-sm text-green-700">
                            <i class="fas fa-gift text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                            <span>Congratulations! This booking is free as you have 5 paid bookings.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Login/Signup Toggle Section -->
                <?php if (!$is_logged_in): ?>
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                            <div class="flex justify-center space-x-4 mb-6">
                                <button id="showLogin" class="bg-primary text-white font-bold py-2 px-4 rounded-xl hover:bg-secondary transition-all duration-200">Login</button>
                                <button id="showSignup" class="bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-xl hover:bg-gray-300 transition-all duration-200">Sign Up</button>
                            </div>

                            <!-- Login Form (Hidden by Default) -->
                            <div id="loginForm" class="hidden">
                                <h3 class="text-xl font-bold mb-4">Login</h3>
                                <form action="<?= SITE_URL ?>/auth/login.php" method="POST" class="space-y-4">
                                    <div>
                                        <label for="login_email" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address
                                        </label>
                                        <input type="email" id="login_email" name="email" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="Enter your email">
                                    </div>
                                    <div class="relative">
                                        <label for="login_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-lock text-primary mr-2"></i>Password
                                        </label>
                                        <input type="password" id="login_password" name="password" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="••••••••">
                                        <span class="absolute inset-y-0 right-0 top-6 pr-4 flex items-center text-sm leading-5">
                                            <i class="fas fa-eye text-gray-400 cursor-pointer" id="toggleLoginPassword"></i>
                                        </span>
                                    </div>
                                    <button type="submit"
                                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-3 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200">
                                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                                    </button>
                                </form>
                            </div>

                            <!-- Signup Form (Hidden by Default) -->
                            <div id="signupForm" class="hidden">
                                <h3 class="text-xl font-bold mb-4">Sign Up</h3>
                                <form action="<?= SITE_URL ?>/auth/signup.php" method="POST" class="space-y-4" id="signupFormElement">
                                    <div>
                                        <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-user text-primary mr-2"></i>First Name
                                        </label>
                                        <input type="text" id="first_name" name="first_name" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="Enter your first name">
                                    </div>
                                    <div>
                                        <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-user text-primary mr-2"></i>Last Name
                                        </label>
                                        <input type="text" id="last_name" name="last_name" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="Enter your last name">
                                    </div>
                                    <div>
                                        <label for="signup_email" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-envelope text-primary mr-2"></i>Email Address
                                        </label>
                                        <input type="email" id="signup_email" name="email" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="Enter your email">
                                    </div>
                                    <div>
                                        <label for="signup_phone" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-phone text-primary mr-2"></i>Phone Number
                                        </label>
                                        <input type="tel" id="signup_phone" name="phone" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="+234 xxx xxx xxxx">
                                    </div>
                                    <div class="relative">
                                        <label for="signup_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-lock text-primary mr-2"></i>Password
                                        </label>
                                        <input type="password" id="signup_password" name="password" required
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                                               placeholder="••••••••">
                                        <span class="absolute inset-y-0 right-0 top-6 pr-4 flex items-center text-sm leading-5">
                                            <i class="fas fa-eye text-gray-400 cursor-pointer" id="toggleSignupPassword"></i>
                                        </span>
                                    </div>
                                    <button type="submit"
                                            class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-3 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200">
                                        <i class="fas fa-user-plus mr-2"></i>Sign Up
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Passenger Forms -->
                <div class="lg:col-span-2">
                    <form id="passengerForm" action="<?= $free_booking ? SITE_URL . '/bookings/free_booking_confirmation.php' : SITE_URL . '/bookings/process_booking.php' ?>" method="POST" class="space-y-6">
                        <input type="hidden" name="free_booking" value="<?= $free_booking ? '1' : '0' ?>">
                        <?php for ($i = 0; $i < $num_seats; $i++): ?>
                            <div class="bg-white rounded-xl shadow-lg p-6 passenger-card">
                                <h3 class="text-xl font-bold mb-6 flex items-center">
                                    <i class="fas fa-user-circle text-primary mr-3"></i>
                                    Passenger <?= $i + 1 ?> - Seat <?= $selected_seats[$i] ?>
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="passenger_name_<?= $i ?>" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-user text-primary mr-2"></i>Full Name *
                                        </label>
                                        <input type="text" 
                                               class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent" 
                                               id="passenger_name_<?= $i ?>" 
                                               name="passengers[<?= $i ?>][name]" 
                                               required
                                               placeholder="Enter full name as it appears on ID"
                                               <?php if ($i === 0 && $is_logged_in): ?>
                                                   value="<?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?>"
                                               <?php endif; ?>>
                                        <input type="hidden" name="passengers[<?= $i ?>][seat_number]" value="<?= $selected_seats[$i] ?>">
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="email_<?= $i ?>" class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-envelope text-primary mr-2"></i>Email Address *
                                            </label>
                                            <input type="email" 
                                                   class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent" 
                                                   id="email_<?= $i ?>" 
                                                   name="passengers[<?= $i ?>][email]" 
                                                   required
                                                   placeholder="your@email.com"
                                                   <?php if ($i === 0 && $is_logged_in): ?>
                                                       value="<?= htmlspecialchars($_SESSION['user']['email']) ?>"
                                                   <?php endif; ?>>
                                        </div>

                                        <div>
                                            <label for="phone_<?= $i ?>" class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-phone text-primary mr-2"></i>Phone Number *
                                            </label>
                                            <input type="tel" 
                                                   class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent" 
                                                   id="phone_<?= $i ?>" 
                                                   name="passengers[<?= $i ?>][phone]" 
                                                   required
                                                   placeholder="+234 xxx xxx xxxx"
                                                   <?php if ($i === 0 && $is_logged_in && !empty($_SESSION['user']['phone'])): ?>
                                                       value="<?= htmlspecialchars($_SESSION['user']['phone']) ?>"
                                                   <?php endif; ?>>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="emergency_contact_<?= $i ?>" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-user-friends text-primary mr-2"></i>Emergency Contact (Optional)
                                        </label>
                                        <input type="text" 
                                               class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent" 
                                               id="emergency_contact_<?= $i ?>" 
                                               name="passengers[<?= $i ?>][emergency_contact]"
                                               placeholder="Emergency contact name and phone">
                                    </div>

                                    <?php if ($i === 0): ?>
                                        <div>
                                            <label for="special_requests" class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-comment text-primary mr-2"></i>Special Requests (Optional)
                                            </label>
                                            <textarea 
                                                class="form-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent" 
                                                id="special_requests" 
                                                name="special_requests" 
                                                rows="3"
                                                placeholder="Any special requirements for the group..."></textarea>
                                        </div>
                                        <!-- WHO REFERRED YOU SECTION -->
                                        <div>
                                            <label for="referral_code" class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-ticket-alt text-primary mr-2"></i>Who Referred You (Enter Referral Code) (Optional)
                                            </label>
                                            <div class="flex gap-2">
                                                <input type="text" 
                                                       id="referral_code" 
                                                       name="referral_code"
                                                       class="form-input flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent"
                                                       placeholder="Enter referral code"
                                                       value="<?= isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : '' ?>">
                                                <button type="button" id="applyReferralBtn" 
                                                        class="bg-primary text-white px-4 py-3 rounded-xl hover:bg-secondary transition-all duration-200">
                                                    Apply
                                                </button>
                                            </div>
                                            <div id="referralMessage" class="mt-2">
                                                <?php if ($referral_message): ?>
                                                    <p class="text-green-500 text-sm"><?= htmlspecialchars($referral_message) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>

                        <!-- Terms and Conditions -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <div class="flex items-start">
                                <input type="checkbox" id="terms" name="terms" required class="mt-1 mr-3 text-primary focus:ring-primary">
                                <label for="terms" class="text-sm text-gray-600">
                                    I agree to the <a href="#" class="text-primary hover:underline font-semibold">Terms and Conditions</a> 
                                    and <a href="#" class="text-primary hover:underline font-semibold">Privacy Policy</a>. 
                                    I understand that this booking is subject to availability and payment confirmation.
                                </label>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <button type="submit" 
                                    class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                <i class="fas fa-credit-card mr-2"></i>
                                <?= $free_booking ? 'Confirm Free Booking' : 'Proceed to Payment - ₦' . number_format($total_amount, 0) ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Booking Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-xl font-bold mb-4 flex items-center">
                            <i class="fas fa-file-invoice text-primary mr-2"></i>
                            Booking Summary
                        </h3>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-route text-xs mr-2"></i>Route:
                                </span>
                                <span class="font-semibold text-right"><?= htmlspecialchars($trip['pickup_city']) ?> → <?= htmlspecialchars($trip['dropoff_city']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-calendar text-xs mr-2"></i>Date:
                                </span>
                                <span class="font-semibold"><?= date('M j, Y', strtotime($trip['trip_date'])) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-clock text-xs mr-2"></i>Time:
                                </span>
                                <span class="font-semibold"><?= date('h:i A', strtotime($trip['departure_time'])) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-bus text-xs mr-2"></i>Vehicle:
                                </span>
                                <span class="font-semibold text-right"><?= htmlspecialchars($trip['vehicle_type']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 flex items-center">
                                    <i class="fas fa-id-badge text-xs mr-2"></i>Vehicle No:
                                </span>
                                <span class="font-semibold"><?= htmlspecialchars($trip['vehicle_number']) ?></span>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <div class="mb-4">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    <i class="fas fa-chair text-primary mr-2"></i>Selected Seats:
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($selected_seats as $seat): ?>
                                        <span class="bg-primary text-white px-3 py-1 rounded-full text-sm font-medium">
                                            <?= $seat ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Price per seat:</span>
                                    <span class="font-semibold"><?= $free_booking ? 'Free' : '₦' . number_format($trip['price'], 0) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Number of seats:</span>
                                    <span class="font-semibold"><?= $num_seats ?></span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center text-lg font-bold border-t pt-3">
                                <span>Total Amount:</span>
                                <span class="text-primary text-xl"><?= $free_booking ? 'Free' : '₦' . number_format($total_amount, 0) ?></span>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex items-start text-sm text-blue-700">
                                <i class="fas fa-info-circle text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                <span>Your seats will be reserved for 15 minutes while you complete the booking process.</span>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-green-50 rounded-lg border border-green-200">
                            <div class="flex items-start text-sm text-green-700">
                                <i class="fas fa-shield-alt text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                <span>Your booking is protected and secure. <?= $free_booking ? 'This free booking will be confirmed immediately.' : 'Payment is processed through encrypted channels.' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            if (confirm('Are you sure you want to go back? Your seat selection will be cleared.')) {
                fetch('<?= SITE_URL ?>/bookings/clear_seats.php', { method: 'POST' })
                    .then(() => {
                        window.location.href = '<?= SITE_URL ?>/bookings/seat_selection.php';
                    })
                    .catch(() => {
                        window.location.href = '<?= SITE_URL ?>/bookings/seat_selection.php';
                    });
            }
        }

        // Toggle Login/Signup Forms
        const showLoginBtn = document.getElementById('showLogin');
        const showSignupBtn = document.getElementById('showSignup');
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');

        if (showLoginBtn && showSignupBtn) {
            showLoginBtn.addEventListener('click', () => {
                loginForm.classList.remove('hidden');
                signupForm.classList.add('hidden');
                showLoginBtn.classList.add('bg-primary', 'text-white');
                showLoginBtn.classList.remove('bg-gray-200', 'text-gray-700');
                showSignupBtn.classList.add('bg-gray-200', 'text-gray-700');
                showSignupBtn.classList.remove('bg-primary', 'text-white');
            });

            showSignupBtn.addEventListener('click', () => {
                signupForm.classList.remove('hidden');
                loginForm.classList.add('hidden');
                showSignupBtn.classList.add('bg-primary', 'text-white');
                showSignupBtn.classList.remove('bg-gray-200', 'text-gray-700');
                showLoginBtn.classList.add('bg-gray-200', 'text-gray-700');
                showLoginBtn.classList.remove('bg-primary', 'text-white');
            });
        }

        // Password Toggle for Login
        const toggleLoginPassword = document.getElementById('toggleLoginPassword');
        const loginPasswordInput = document.getElementById('login_password');
        if (toggleLoginPassword) {
            toggleLoginPassword.addEventListener('click', () => {
                const type = loginPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                loginPasswordInput.setAttribute('type', type);
                toggleLoginPassword.classList.toggle('fa-eye');
                toggleLoginPassword.classList.toggle('fa-eye-slash');
            });
        }

        // Password Toggle for Signup
        const toggleSignupPassword = document.getElementById('toggleSignupPassword');
        const signupPasswordInput = document.getElementById('signup_password');
        if (toggleSignupPassword) {
            toggleSignupPassword.addEventListener('click', () => {
                const type = signupPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                signupPasswordInput.setAttribute('type', type);
                toggleSignupPassword.classList.toggle('fa-eye');
                toggleSignupPassword.classList.toggle('fa-eye-slash');
            });
        }

        // Signup Form Validation
        document.getElementById('signupFormElement')?.addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('signup_email').value.trim();
            const phone = document.getElementById('signup_phone').value.trim();
            const password = document.getElementById('signup_password').value.trim();

            let isValid = true;
            let firstInvalidField = null;

            if (!firstName) {
                isValid = false;
                alert('Please enter your first name.');
                firstInvalidField = document.getElementById('first_name');
            } else if (!lastName) {
                isValid = false;
                alert('Please enter your last name.');
                firstInvalidField = document.getElementById('last_name');
            } else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                isValid = false;
                alert('Please enter a valid email address.');
                firstInvalidField = document.getElementById('signup_email');
            } else if (!phone || !/^\+?[0-9\s\-\(\)]{10,}$/.test(phone)) {
                isValid = false;
                alert('Please enter a valid phone number (at least 10 digits).');
                firstInvalidField = document.getElementById('signup_phone');
            } else if (!password || password.length < 6) {
                isValid = false;
                alert('Password must be at least 6 characters long.');
                firstInvalidField = document.getElementById('signup_password');
            }

            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                return false;
            }
        });

        // AJAX Referral Code Checker
        document.getElementById('applyReferralBtn')?.addEventListener('click', function() {
            const referralCode = document.getElementById('referral_code').value.trim();
            const messageDiv = document.getElementById('referralMessage');
            const applyBtn = this;
            
            if (!referralCode) {
                messageDiv.innerHTML = '<p class="text-red-500 text-sm">Please enter a referral code</p>';
                return;
            }
            
            // Show loading state
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            messageDiv.innerHTML = '<p class="text-blue-500 text-sm">Checking referral code...</p>';
            
            // Make AJAX request
            fetch('<?= SITE_URL ?>/bookings/check_referral.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'referral_code=' + encodeURIComponent(referralCode)
            })
            .then(response => response.json())
            .then(data => {
                applyBtn.disabled = false;
                applyBtn.innerHTML = 'Apply';
                
                if (data.success) {
                    messageDiv.innerHTML = `<p class="text-green-500 text-sm">${data.message}</p>`;
                } else {
                    messageDiv.innerHTML = `<p class="text-red-500 text-sm">${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                applyBtn.disabled = false;
                applyBtn.innerHTML = 'Apply';
                messageDiv.innerHTML = '<p class="text-red-500 text-sm">Error checking referral code. Please try again.</p>';
            });
        });

        // No need to update total amount since no discount is applied
        function updateTotalAmount() {
            // No action needed
        }

        // Passenger Form Validation
        document.getElementById('passengerForm').addEventListener('submit', function(e) {
            let isValid = true;
            const passengers = <?= $num_seats ?>;
            let firstInvalidField = null;

            // Validate each passenger
            for (let i = 0; i < passengers; i++) {
                const name = document.getElementById(`passenger_name_${i}`).value.trim();
                const email = document.getElementById(`email_${i}`).value.trim();
                const phone = document.getElementById(`phone_${i}`).value.trim();

                if (!name) {
                    isValid = false;
                    alert(`Please fill in the name for Passenger ${i + 1}.`);
                    firstInvalidField = document.getElementById(`passenger_name_${i}`);
                    break;
                }

                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email || !emailRegex.test(email)) {
                    isValid = false;
                    alert(`Please enter a valid email address for Passenger ${i + 1}.`);
                    firstInvalidField = document.getElementById(`email_${i}`);
                    break;
                }

                // Phone validation
                const phoneRegex = /^\+?[0-9\s\-\(\)]{10,}$/;
                if (!phone || !phoneRegex.test(phone)) {
                    isValid = false;
                    alert(`Please enter a valid phone number (at least 10 digits) for Passenger ${i + 1}.`);
                    firstInvalidField = document.getElementById(`phone_${i}`);
                    break;
                }
            }

            // Check terms and conditions
            const terms = document.getElementById('terms').checked;
            if (!terms) {
                isValid = false;
                alert('Please accept the Terms and Conditions to proceed.');
                firstInvalidField = document.getElementById('terms');
            }

            if (!isValid) {
                e.preventDefault();
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                // Re-enable submit button
                const submitBtn = e.target.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.name) {
                    submitBtn.innerHTML = '<i class="fas fa-credit-card mr-2"></i><?= $free_booking ? "Confirm Free Booking" : "Proceed to Payment - ₦" . number_format($total_amount, 0) ?>';
                    submitBtn.disabled = false;
                }
                return false;
            }

            // Show loading state for main submit
            const submitBtn = e.target.querySelector('button[type="submit"]:not([name="apply_referral"])');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing Booking...';
                submitBtn.disabled = true;
            }
        });

        // Auto-fill email and phone for subsequent passengers
        document.getElementById('email_0')?.addEventListener('blur', function() {
            const firstEmail = this.value.trim();
            const passengers = <?= $num_seats ?>;
            
            if (passengers > 1 && firstEmail) {
                for (let i = 1; i < passengers; i++) {
                    const emailField = document.getElementById(`email_${i}`);
                    if (!emailField.value) {
                        emailField.value = firstEmail;
                    }
                }
            }
        });

        document.getElementById('phone_0')?.addEventListener('blur', function() {
            const firstPhone = this.value.trim();
            const passengers = <?= $num_seats ?>;
            
            if (passengers > 1 && firstPhone) {
                for (let i = 1; i < passengers; i++) {
                    const phoneField = document.getElementById(`phone_${i}`);
                    if (!phoneField.value) {
                        phoneField.value = firstPhone;
                    }
                }
            }
        });
    </script>
</body>
</html>
