<?php
// index.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session
session_start();

// Define image array and default SITE_URL for the slider
define('IMAGE_SLIDES', [
    '/assets/images/abuja.png',
    '/assets/images/jos.jpeg',
    '/assets/images/abuja.jpeg',
    '/assets/images/jos1.jpeg',
]);


// Include required files with error handling
try {
    require_once __DIR__ . '/includes/db.php';
} catch (Exception $e) {
    error_log("Failed to include db.php: " . $e->getMessage());
    die("Error: Database connection failed. Please check server logs.");
}

try {
    require_once __DIR__ . '/includes/config.php';
} catch (Exception $e) {
    error_log("Failed to include config.php: " . $e->getMessage());
    define('SITE_URL', 'https://booking.dgctransports.com');
    define('SITE_NAME', 'DGC Transports'); // Fallback
}

// Fetch cities and vehicle types for the form
$cities_result = null;
$vehicle_types_result = null;
$vehicle_types = [];
$max_date = date('Y-m-d', strtotime('+1 year')); // Default fallback

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    // Check if tables exist
    $table_check = $conn->query("SHOW TABLES LIKE 'cities'");
    if ($table_check && $table_check->num_rows > 0) {
        $cities_result = $conn->query("SELECT id, name FROM cities ORDER BY name");
        // Reset pointer for second use in dropoff
        $cities_result_dropoff = $conn->query("SELECT id, name FROM cities ORDER BY name"); 
    } else {
        error_log("Table 'cities' does not exist in database.");
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'vehicle_types'");
    if ($table_check && $table_check->num_rows > 0) {
        $vehicle_types_result = $conn->query("SELECT id, type, capacity FROM vehicle_types ORDER BY type");
        if ($vehicle_types_result) {
            while ($vehicle = $vehicle_types_result->fetch_assoc()) {
                $vehicle_types[$vehicle['id']] = $vehicle;
            }
        }
    } else {
        error_log("Table 'vehicle_types' does not exist in database.");
    }

    // Fetch the furthest end_date from trip_templates if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'trip_templates'");
    if ($table_check && $table_check->num_rows > 0) {
        $max_date_result = $conn->query("SELECT MAX(end_date) AS max_date FROM trip_templates WHERE status = 'active'");
        $max_date = $max_date_result ? ($max_date_result->fetch_assoc()['max_date'] ?? date('Y-m-d', strtotime('+1 year'))) : $max_date;
    } else {
        error_log("Table 'trip_templates' does not exist in database.");
    }
} else {
    error_log("Database connection not established.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Journey - <?= defined('SITE_NAME') ? SITE_NAME : 'DGC Transports' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
    /*
     * REFACTORED CSS
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
        font-family: 'Inter', sans-serif;
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
    .input-field {
        border: 2px solid var(--light-gray-border);
        border-radius: 8px;
        padding: 10px 12px;
        width: 100%;
        color: var(--black);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        font-size: 1rem;
        line-height: 1.5;
        box-sizing: border-box;
    }
    .input-field:focus {
        outline: none;
        border-color: var(--primary-red);
        box-shadow: 0 0 8px rgba(227, 6, 19, 0.2);
    }
    .form-group {
        position: relative;
    }
    select.input-field {
        height: 44px;
        padding-top: 0;
        padding-bottom: 0;
        display: block;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%231a1a1a%22%20d%3D%22M287%20177.3c-2.3-2.3-5.3-3.5-8.5-3.5h-259c-3.2%200-6.2%201.2-8.5%203.5l-2.4%202.4c-2.3%202.3-3.5%205.3-3.5%208.5s1.2%206.2%203.5%208.5l140.4%20140.4c2.3%202.3%205.3%203.5%208.5%203.5s6.2-1.2%208.5-3.5l140.4-140.4c2.3-2.3%203.5-5.3%203.5-8.5s-1.2-6.2-3.5-8.5z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat;
        background-position: right 12px top 50%;
        background-size: 10px auto;
    }
    .error-message {
        color: var(--primary-red);
        font-size: 0.9rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        font-weight: 500;
        padding: 8px;
        border-left: 4px solid var(--primary-red);
        background-color: #ffeaea;
        border-radius: 4px;
    }
    .error-message i {
        margin-right: 8px;
    }
    /* SLIDER CSS - REPLACING .hero-image */
    .hero-image-slider {
        position: relative;
        background-color: var(--black); /* Fallback */
        overflow: hidden;
    }
    .slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        opacity: 0;
        transition: opacity 1s ease-in-out;
    }
    .slide.active {
        opacity: 1;
    }
    .slide-overlay {
        position: absolute;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.5);
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-in {
        animation: fadeIn 0.5s ease-out;
    }
    .tooltip {
        position: relative;
    }
    .tooltip .tooltip-text {
        visibility: hidden;
        width: 200px;
        background-color: var(--primary-red);
        color: var(--white);
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 10;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: opacity 0.3s;
    }
    .tooltip:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
    }
    .seat-counter {
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--light-gray-border);
        border-radius: 8px;
        padding: 0;
        width: 100%;
        overflow: hidden;
        height: 44px;
    }
    .seat-counter button {
        background: #f5f5f5;
        color: var(--black);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: none;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .seat-counter button:hover {
        background: #e5e7eb;
    }
    .seat-counter input {
        text-align: center;
        border: none;
        width: 100%;
        background: transparent;
        font-size: 1rem;
        -moz-appearance: textfield;
        padding: 10px 0;
    }
    .seat-counter input::-webkit-outer-spin-button,
    .seat-counter input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .tab-item {
        padding: 10px 15px;
        cursor: pointer;
        font-weight: 600;
        color: #6b7280; /* gray-500 */
        border-bottom: 3px solid transparent;
        transition: color 0.2s, border-color 0.2s;
    }
    .tab-item.active-tab {
        color: var(--primary-red);
        border-bottom-color: var(--primary-red);
    }
    </style>
</head>
<body>
    <?php
    try {
        require_once __DIR__ . '/templates/header.php';
    } catch (Exception $e) {
        error_log("Failed to include header.php: " . $e->getMessage());
        echo '<div class="error-message"><i class="fas fa-exclamation-circle"></i>Failed to load header. Please contact support.</div>';
    }
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container w-full">
            <div class="card overflow-hidden fade-in">
                <div class="grid lg:grid-cols-2 gap-0">
                    <div class="p-8 lg:p-12">
                        <div class="max-w-md mx-auto">
                            <div class="flex border-b mb-8">
                                <a href="index.php" class="tab-item active-tab">
                                    <i class="fas fa-route mr-2"></i>Book Trip
                                </a>
                                <a href="charter.php" class="tab-item">
                                    <i class="fas fa-car-side mr-2"></i>Charter Vehicle
                                </a>
                            </div>

                            <div class="text-center mb-8">
                                <h1 class="text-4xl font-bold text-gray-800">
                                    Book Your <span class="text-primary-red">Journey</span>
                                </h1>
                                <p class="text-gray-600 text-lg">Experience premium travel with comfort and style</p>
                            </div>
                            <?php if (isset($_SESSION['error'])): ?>
                                <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($_SESSION['error']) ?></p>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            <?php if (!$cities_result || !$vehicle_types_result): ?>
                                <p class="error-message"><i class="fas fa-exclamation-circle"></i>Unable to load booking options. Please try again later.</p>
                            <?php endif; ?>
                            <form id="bookingForm" action="bookings/search_trips.php" method="POST" class="space-y-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label for="pickup_city_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                            From
                                        </label>
                                        <select class="input-field" id="pickup_city_id" name="pickup_city_id" required aria-label="Pickup city" onchange="updateDropoffOptions()">
                                            <option value="">Select pickup city</option>
                                            <?php
                                            if ($cities_result) {
                                                $cities_result->data_seek(0);
                                                while ($city = $cities_result->fetch_assoc()): ?>
                                                    <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                                <?php endwhile;
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="dropoff_city_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-map-marker-alt text-primary-red mr-2"></i>
                                            To
                                        </label>
                                        <select class="input-field" id="dropoff_city_id" name="dropoff_city_id" required aria-label="Destination city">
                                            <option value="">Select destination</option>
                                            <?php
                                            if (isset($cities_result_dropoff) && $cities_result_dropoff) {
                                                while ($city = $cities_result_dropoff->fetch_assoc()): ?>
                                                    <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                                <?php endwhile;
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                    <div class="form-group">
                                        <label for="vehicle_type_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-bus text-primary-red mr-2"></i>
                                            Vehicle
                                        </label>
                                        <select class="input-field" id="vehicle_type_id" name="vehicle_type_id" required aria-label="Vehicle type" onchange="updateMaxSeats()">
                                            <option value="">Choose vehicle</option>
                                            <?php foreach ($vehicle_types as $vehicle): ?>
                                                <option value="<?= $vehicle['id'] ?>" data-capacity="<?= $vehicle['capacity'] ?>">
                                                    <?= htmlspecialchars($vehicle['type']) ?> (<?= $vehicle['capacity'] ?> seats)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="num_seats" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-users text-primary-red mr-2"></i>
                                            Seats
                                        </label>
                                        <div class="seat-counter">
                                            <button type="button" id="decrement-seats" class="btn-seat" aria-label="Decrement seats">-</button>
                                            <input type="number" class="input-field" id="num_seats" name="num_seats" value="1" min="1" required aria-label="Number of seats">
                                            <button type="button" id="increment-seats" class="btn-seat" aria-label="Increment seats">+</button>
                                        </div>
                                    </div>
                                    <div class="form-group tooltip">
                                        <label for="departure_date" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-calendar-alt text-primary-red mr-2"></i>
                                            Date
                                        </label>
                                        <input type="date" class="input-field" id="departure_date" name="departure_date" required min="<?= date('Y-m-d') ?>" max="<?= $max_date ?>" aria-label="Departure date">
                                        <span class="tooltip-text">Select a travel date up to <?= date('M j, Y', strtotime($max_date)) ?></span>
                                    </div>
                                </div>

                                <button type="submit" class="btn-primary w-full mt-4">
                                    <i class="fas fa-search mr-2"></i>Search Available Trips
                                </button>
                            </form>
                        </div>
                    </div>
                    <div id="heroImageSlider" class="hero-image-slider relative hidden lg:flex items-center justify-center min-h-[450px]">
                        <?php 
                        // PHP to output all slides
                        foreach (IMAGE_SLIDES as $index => $image_path) {
                            $full_url = defined('SITE_URL') ? SITE_URL . $image_path : 'https://booking.dgctransports.com' . $image_path;
                            $active_class = $index === 0 ? 'active' : '';
                            echo '<div class="slide ' . $active_class . '" style="background-image: url(\'' . htmlspecialchars($full_url) . '\');"></div>';
                        }
                        ?>
                        <div class="slide-overlay"></div>
                        <div class="relative z-10 p-8 text-center text-white">
                            <i class="fas fa-bus text-8xl text-primary-red mb-6"></i>
                            <h2 class="text-4xl font-bold mb-4">Premium Travel Experience</h2>
                            <p class="text-xl text-gray-200 mb-6">Safe, comfortable, and reliable transportation between cities</p>
                            <div class="grid grid-cols-1 gap-6 max-w-sm mx-auto">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-shield-alt text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">Safe & Secure</h3>
                                        <p class="text-gray-300 text-sm">Professional drivers</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-clock text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">On-Time Service</h3>
                                        <p class="text-gray-300 text-sm">Punctual departures</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-star text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">Premium Comfort</h3>
                                        <p class="text-gray-300 text-sm">Luxury vehicles</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
    try {
        require_once __DIR__ . '/templates/footer.php'; 
    } catch (Exception $e) {
        error_log("Failed to include footer.php: " . $e->getMessage());
        echo '<footer class="py-4 text-center text-gray-500 text-sm">© ' . date('Y') . ' DGC Transports. All rights reserved.</footer>';
    }
    ?>

    <script>
        // Store vehicle types data
        const vehicleTypes = <?php echo json_encode($vehicle_types); ?>;

        // Store original city options (using the PHP result that was reset)
        const originalCities = Array.from(document.getElementById('dropoff_city_id').options).map(option => ({
            value: option.value,
            text: option.text
        })).filter(city => city.value !== ''); // Exclude the "Select destination" option

        function updateDropoffOptions() {
            const pickupSelect = document.getElementById('pickup_city_id');
            const dropoffSelect = document.getElementById('dropoff_city_id');
            const selectedPickupValue = pickupSelect.value;
            const currentDropoffValue = dropoffSelect.value; // Keep track of current selection

            dropoffSelect.innerHTML = '<option value="">Select destination</option>';
            let newDropoffValue = '';

            originalCities.forEach(city => {
                if (city.value && city.value !== selectedPickupValue) {
                    const option = new Option(city.text, city.value);
                    dropoffSelect.add(option);
                    if (city.value === currentDropoffValue) {
                         newDropoffValue = currentDropoffValue;
                    }
                }
            });

            // Restore previous dropoff selection if it's still a valid option
            if (newDropoffValue) {
                dropoffSelect.value = newDropoffValue;
            }
        }

        function updateMaxSeats() {
            const vehicleSelect = document.getElementById('vehicle_type_id');
            const numSeatsInput = document.getElementById('num_seats');
            const selectedVehicleId = vehicleSelect.value;

            if (selectedVehicleId && vehicleTypes[selectedVehicleId]) {
                const maxSeats = vehicleTypes[selectedVehicleId].capacity;
                numSeatsInput.max = maxSeats;
                if (parseInt(numSeatsInput.value) > maxSeats) {
                    numSeatsInput.value = maxSeats;
                }
            } else {
                numSeatsInput.max = '';
            }
        }
        
        // ** NEW SLIDER JAVASCRIPT **
        const slider = {
            init: function() {
                this.slides = document.querySelectorAll('#heroImageSlider .slide');
                if (this.slides.length === 0) return;
                this.currentSlide = 0;
                this.start();
            },
            nextSlide: function() {
                this.slides[this.currentSlide].classList.remove('active');
                this.currentSlide = (this.currentSlide + 1) % this.slides.length;
                this.slides[this.currentSlide].classList.add('active');
            },
            start: function() {
                // Change slide every 5000 milliseconds (5 seconds)
                setInterval(() => this.nextSlide(), 5000);
            }
        };

        // Form validation and seat counter logic
        document.addEventListener('DOMContentLoaded', function() {
            slider.init(); // Initialize the slider
            
            const form = document.getElementById('bookingForm');
            const numSeatsInput = document.getElementById('num_seats');
            const decrementBtn = document.getElementById('decrement-seats');
            const incrementBtn = document.getElementById('increment-seats');
            const today = new Date().toISOString().split('T')[0];
            const maxDate = '<?php echo $max_date; ?>';

            // Seat counter functionality
            decrementBtn.addEventListener('click', function() {
                let currentSeats = parseInt(numSeatsInput.value);
                if (currentSeats > 1) {
                    numSeatsInput.value = currentSeats - 1;
                }
            });

            incrementBtn.addEventListener('click', function() {
                let currentSeats = parseInt(numSeatsInput.value);
                const maxSeats = parseInt(numSeatsInput.max);
                // Check if maxSeats is defined and if currentSeats is less than max
                if (isNaN(maxSeats) || currentSeats < maxSeats) {
                    numSeatsInput.value = currentSeats + 1;
                } else if (!isNaN(maxSeats) && currentSeats >= maxSeats) {
                    showError(`Cannot exceed max seats for this vehicle (${maxSeats}).`);
                }
            });

            // Ensure seat input is always at least 1 and respects max on manual input
            numSeatsInput.addEventListener('change', function() {
                let currentSeats = parseInt(numSeatsInput.value);
                const maxSeats = parseInt(numSeatsInput.max);
                
                if (currentSeats < 1 || isNaN(currentSeats)) {
                    numSeatsInput.value = 1;
                } else if (!isNaN(maxSeats) && currentSeats > maxSeats) {
                    numSeatsInput.value = maxSeats;
                }
            });

            if (form) {
                form.addEventListener('submit', function(e) {
                    const pickupCity = document.getElementById('pickup_city_id').value;
                    const dropoffCity = document.getElementById('dropoff_city_id').value;
                    const vehicleType = document.getElementById('vehicle_type_id').value;
                    const departureDate = document.getElementById('departure_date').value;
                    const numSeats = parseInt(numSeatsInput.value);

                    // Clear any previous error messages
                    document.querySelectorAll('.error-message.temp-error').forEach(el => el.remove());

                    if (!pickupCity || !dropoffCity || !vehicleType || !departureDate) {
                        e.preventDefault();
                        showError('Please fill in all required fields.');
                        return false;
                    }

                    if (pickupCity === dropoffCity) {
                        e.preventDefault();
                        showError('Pickup and dropoff cities cannot be the same.');
                        return false;
                    }

                    if (numSeats < 1) {
                        e.preventDefault();
                        showError('Please select at least one seat.');
                        return false;
                    }

                    if (vehicleType && vehicleTypes[vehicleType] && numSeats > vehicleTypes[vehicleType].capacity) {
                        e.preventDefault();
                        showError(`Number of seats cannot exceed vehicle capacity (${vehicleTypes[vehicleType].capacity}).`);
                        return false;
                    }

                    if (departureDate < today) {
                        e.preventDefault();
                        showError('Departure date cannot be in the past.');
                        return false;
                    }

                    if (departureDate > maxDate) {
                        e.preventDefault();
                        showError('Departure date cannot be after ' + maxDate);
                        return false;
                    }
                });
            }

            function showError(message) {
                // Ensure the error is inserted before the form fields (at the start of the form)
                const errorDiv = document.createElement('p');
                errorDiv.className = 'error-message temp-error'; // Add temp-error class to distinguish
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
                form.prepend(errorDiv);
                setTimeout(() => errorDiv.remove(), 5000);
            }

            // Initialize dropdowns and max seats on page load
            updateDropoffOptions(); // Ensure initial state is correct
            updateMaxSeats();
        });
    </script>
</body>
</html>