<?php
// index.php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';

// Fetch cities and vehicle types for the form
$cities_result = $conn->query("SELECT id, name FROM cities ORDER BY name");
$vehicle_types_result = $conn->query("SELECT id, type, capacity FROM vehicle_types ORDER BY type");
$vehicle_types = [];
while ($vehicle = $vehicle_types_result->fetch_assoc()) {
    $vehicle_types[$vehicle['id']] = $vehicle;
}

// Fetch the furthest end_date from trip_templates
$max_date_result = $conn->query("SELECT MAX(end_date) AS max_date FROM trip_templates WHERE status = 'active'");
$max_date = $max_date_result->fetch_assoc()['max_date'] ?? date('Y-m-d', strtotime('+1 year'));

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Journey - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e30613;
            --dark-red: #c70410;
            --black: #1a1a1a;
            --white: #ffffff;
            --gray: #f5f5f5;
            --accent-blue: #3b82f6;
        }
        body {
            background: linear-gradient(135deg, var(--white), var(--gray));
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
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
            transform: translateY(-2px);
        }
        .input-field {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            width: 100%;
            color: var(--black);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 8px rgba(227, 6, 19, 0.2);
        }
        .form-group {
            position: relative;
        }
        .form-group i {
            position: absolute;
            left: 12px;
            top: 40px;
            color: var(--primary-red);
        }
        .input-field-with-icon {
            padding-left: 36px;
        }
        .error-message {
            color: var(--primary-red);
            font-size: 0.9rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .error-message i {
            margin-right: 6px;
        }
        .hero-image {
            background-image: url('/assets/images/bus-hero.jpg'); /* Replace with local asset */
            background-size: cover;
            background-position: center;
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
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container w-full">
            <div class="card overflow-hidden fade-in">
                <div class="grid lg:grid-cols-2 gap-0">
                    <!-- Left side - Booking Form -->
                    <div class="p-8 lg:p-12">
                        <div class="max-w-md mx-auto">
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
                            <form id="bookingForm" action="bookings/search_trips.php" method="POST" class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label for="pickup_city_id" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt mr-2"></i>From
                                        </label>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <select class="input-field input-field-with-icon" id="pickup_city_id" name="pickup_city_id" required aria-label="Pickup city" onchange="updateDropoffOptions()">
                                            <option value="">Select pickup city</option>
                                            <?php 
                                            $cities_result->data_seek(0);
                                            while ($city = $cities_result->fetch_assoc()): ?>
                                                <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="dropoff_city_id" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt mr-2"></i>To
                                        </label>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <select class="input-field input-field-with-icon" id="dropoff_city_id" name="dropoff_city_id" required aria-label="Destination city">
                                            <option value="">Select destination</option>
                                            <?php 
                                            $cities_result->data_seek(0);
                                            while ($city = $cities_result->fetch_assoc()): ?>
                                                <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="form-group">
                                        <label for="vehicle_type_id" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-bus mr-2"></i>Vehicle
                                        </label>
                                        <i class="fas fa-bus"></i>
                                        <select class="input-field input-field-with-icon" id="vehicle_type_id" name="vehicle_type_id" required aria-label="Vehicle type" onchange="updateMaxSeats()">
                                            <option value="">Choose vehicle</option>
                                            <?php foreach ($vehicle_types as $vehicle): ?>
                                                <option value="<?= $vehicle['id'] ?>" data-capacity="<?= $vehicle['capacity'] ?>">
                                                    <?= htmlspecialchars($vehicle['type']) ?> (<?= $vehicle['capacity'] ?> seats)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group tooltip">
                                        <label for="departure_date" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-calendar mr-2"></i>Date
                                        </label>
                                        <i class="fas fa-calendar"></i>
                                        <input type="date" class="input-field input-field-with-icon" id="departure_date" name="departure_date" required min="<?= date('Y-m-d') ?>" max="<?= $max_date ?>" aria-label="Departure date">
                                        <span class="tooltip-text">Select a travel date up to <?= date('M j, Y', strtotime($max_date)) ?></span>
                                    </div>
                                    <div class="form-group">
                                        <label for="num_seats" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-users mr-2"></i>Seats
                                        </label>
                                        <i class="fas fa-users"></i>
                                        <input type="number" class="input-field input-field-with-icon" id="num_seats" name="num_seats" required min="1" placeholder="No. of seats" aria-label="Number of seats">
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary w-full">
                                    <i class="fas fa-search mr-2"></i>Search Available Trips
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Right side - Hero Image -->
                    <div class="hero-image relative lg:flex items-center justify-center">
                        <div class="absolute inset-0 bg-black opacity-50"></div>
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

    <script>
        // Store vehicle types data
        const vehicleTypes = <?php echo json_encode($vehicle_types); ?>;

        // Store original city options
        const originalCities = Array.from(document.getElementById('dropoff_city_id').options).map(option => ({
            value: option.value,
            text: option.text
        }));

        function updateDropoffOptions() {
            const pickupSelect = document.getElementById('pickup_city_id');
            const dropoffSelect = document.getElementById('dropoff_city_id');
            const selectedPickupValue = pickupSelect.value;

            dropoffSelect.innerHTML = '<option value="">Select destination</option>';
            originalCities.forEach(city => {
                if (city.value && city.value !== selectedPickupValue) {
                    const option = new Option(city.text, city.value);
                    dropoffSelect.add(option);
                }
            });
        }

        function updateMaxSeats() {
            const vehicleSelect = document.getElementById('vehicle_type_id');
            const numSeatsInput = document.getElementById('num_seats');
            const selectedVehicleId = vehicleSelect.value;

            if (selectedVehicleId && vehicleTypes[selectedVehicleId]) {
                const maxSeats = vehicleTypes[selectedVehicleId].capacity;
                numSeatsInput.max = maxSeats;
                numSeatsInput.placeholder = `1-${maxSeats} seats`;
                if (numSeatsInput.value > maxSeats) {
                    numSeatsInput.value = maxSeats;
                }
            } else {
                numSeatsInput.max = '';
                numSeatsInput.placeholder = 'No. of seats';
            }
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const pickupCity = document.getElementById('pickup_city_id').value;
            const dropoffCity = document.getElementById('dropoff_city_id').value;
            const vehicleType = document.getElementById('vehicle_type_id').value;
            const departureDate = document.getElementById('departure_date').value;
            const numSeats = document.getElementById('num_seats').value;
            const today = new Date().toISOString().split('T')[0];
            const maxDate = '<?php echo $max_date; ?>';

            if (!pickupCity || !dropoffCity || !vehicleType || !departureDate || !numSeats) {
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

        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
            const form = document.getElementById('bookingForm');
            form.prepend(errorDiv);
            setTimeout(() => errorDiv.remove(), 5000);
        }

        // Initialize max seats on page load
        updateMaxSeats();
    </script>
</body>
</html>