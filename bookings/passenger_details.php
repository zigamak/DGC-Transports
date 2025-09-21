<?php
// bookings/passenger_details.php
session_start();

try {
    require_once BASE_DIR . '/includes/db.php';
} catch (Exception $e) {
    error_log("Failed to include db.php: " . $e->getMessage());
    die("Error: Database connection failed. Please check server logs.");
}

try {
    require_once BASE_DIR . '/includes/config.php';
} catch (Exception $e) {
    error_log("Failed to include config.php: " . $e->getMessage());
    define('SITE_URL', 'https://booking.dgctransports.com');
    define('SITE_NAME', 'DGC Transports');
}

try {
    require_once BASE_DIR . '/templates/header.php';
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
?>

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
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Passenger Forms -->
            <div class="lg:col-span-2">
                <form id="passengerForm" action="<?= SITE_URL ?>/bookings/process_booking.php" method="POST" class="space-y-6">
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
                                           placeholder="Enter full name as it appears on ID">
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
                                               placeholder="your@email.com">
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
                                               placeholder="+234 xxx xxx xxxx">
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

                                <?php if ($i === 0): // Only show special requests for first passenger ?>
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
                            Proceed to Payment - ₦<?= number_format($total_amount, 0) ?>
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
                                <span class="font-semibold">₦<?= number_format($trip['price'], 0) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Number of seats:</span>
                                <span class="font-semibold"><?= $num_seats ?></span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-lg font-bold border-t pt-3">
                            <span>Total Amount:</span>
                            <span class="text-primary text-xl">₦<?= number_format($total_amount, 0) ?></span>
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
                            <span>Your booking is protected and secure. Payment is processed through encrypted channels.</span>
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

                // Phone validation (relaxed to allow various formats)
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
                // Re-enable submit button in case of validation failure
                const submitBtn = e.target.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-credit-card mr-2"></i>Proceed to Payment - ₦<?= number_format($total_amount, 0) ?>';
                submitBtn.disabled = false;
                return false;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing Booking...';
            submitBtn.disabled = true;
        });

        // Auto-fill email and phone for subsequent passengers if first passenger is filled
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