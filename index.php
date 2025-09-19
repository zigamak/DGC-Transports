<?php
// index.php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';

// Fetch cities and vehicle types for the form
$cities_result = $conn->query("SELECT * FROM cities ORDER BY name");
$vehicles_result = $conn->query("SELECT * FROM vehicle_types ORDER BY capacity");

require_once 'templates/header.php'; // Correct path relative to index.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Journey - Premium Travel Service</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#dc2626',
                        secondary: '#991b1b',
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl w-full">
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
                <div class="grid lg:grid-cols-2 gap-0">
                    <!-- Left side - Booking Form -->
                    <div class="p-8 lg:p-12">
                        <div class="max-w-md mx-auto">
                            <!-- Header -->
                            <div class="text-center mb-8">
                                <h1 class="text-4xl font-bold text-black mb-2">
                                    Book Your <span class="text-primary">Journey</span>
                                </h1>
                                <p class="text-gray-600 text-lg">Experience premium travel with comfort and style</p>
                            </div>

                            <!-- Booking Form -->
                            <form id="bookingForm" action="bookings/search_trips.php" method="POST" class="space-y-6">
                                <!-- Cities Row -->
                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Pickup City -->
                                    <div class="relative">
                                        <label for="pickup_city" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt text-primary mr-2"></i>From
                                        </label>
                                        <select class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white" 
                                                id="pickup_city" name="pickup_city_id" required onchange="updateDropoffOptions()">
                                            <option value="">Select pickup city</option>
                                            <?php while ($city = $cities_result->fetch_assoc()): ?>
                                                <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <!-- Dropoff City -->
                                    <div class="relative">
                                        <label for="dropoff_city" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-map-marker-alt text-primary mr-2"></i>To
                                        </label>
                                        <select class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white" 
                                                id="dropoff_city" name="dropoff_city_id" required>
                                            <option value="">Select destination</option>
                                            <?php 
                                            $cities_result->data_seek(0);
                                            while ($city = $cities_result->fetch_assoc()): ?>
                                                <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Vehicle Type, Date, and Number of Seats Row -->
                                <div class="grid grid-cols-3 gap-4">
                                    <!-- Vehicle Type -->
                                    <div>
                                        <label for="vehicle_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-car text-primary mr-2"></i>Vehicle
                                        </label>
                                        <select class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white" 
                                                id="vehicle_type" name="vehicle_type_id" required>
                                            <option value="">Choose vehicle</option>
                                            <?php while ($vehicle = $vehicles_result->fetch_assoc()): ?>
                                                <option value="<?= $vehicle['id'] ?>">
                                                    <?= htmlspecialchars($vehicle['type']) ?> (₦<?= number_format($vehicle['price'], 0) ?>/seat)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <!-- Departure Date -->
                                    <div>
                                        <label for="departure_date" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-calendar text-primary mr-2"></i>Date
                                        </label>
                                        <input type="date" 
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                               id="departure_date" name="departure_date" required
                                               min="<?= date('Y-m-d') ?>">
                                    </div>

                                    <!-- Number of Seats -->
                                    <div>
                                        <label for="num_seats" class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-users text-primary mr-2"></i>Seats
                                        </label>
                                        <input type="number" 
                                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200" 
                                               id="num_seats" name="num_seats" required
                                               min="1" max="12" placeholder="No. of seats">
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" 
                                        class="w-full bg-gradient-to-r from-primary to-secondary text-white font-bold py-4 px-6 rounded-xl hover:from-secondary hover:to-primary transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-search mr-2"></i>
                                    Search Available Trips
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right side - Hero Image -->
                    <div class="relative bg-gradient-to-br from-gray-900 to-black lg:flex items-center justify-center">
                        <div class="absolute inset-0 bg-black opacity-50"></div>
                        <div class="relative z-10 p-8 text-center">
                            <div class="mb-8">
                                <i class="fas fa-car-side text-primary text-8xl mb-6"></i>
                                <h2 class="text-4xl font-bold text-white mb-4">Premium Travel Experience</h2>
                                <p class="text-xl text-gray-200 mb-6">Safe, comfortable, and reliable transportation between cities</p>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 max-w-sm mx-auto">
                                <div class="flex items-center text-white">
                                    <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-shield-alt text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">Safe & Secure</h3>
                                        <p class="text-gray-300 text-sm">Professional drivers</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center text-white">
                                    <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center mr-4">
                                        <i class="fas fa-clock text-xl"></i>
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold">On-Time Service</h3>
                                        <p class="text-gray-300 text-sm">Punctual departures</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center text-white">
                                    <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center mr-4">
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
        // Store original city options
        const originalCities = Array.from(document.getElementById('dropoff_city').options).map(option => ({
            value: option.value,
            text: option.text
        }));

        function updateDropoffOptions() {
            const pickupSelect = document.getElementById('pickup_city');
            const dropoffSelect = document.getElementById('dropoff_city');
            const selectedPickupValue = pickupSelect.value;

            dropoffSelect.innerHTML = '<option value="">Select destination</option>';
            originalCities.forEach(city => {
                if (city.value && city.value !== selectedPickupValue) {
                    const option = new Option(city.text, city.value);
                    dropoffSelect.add(option);
                }
            });
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const pickupCity = document.getElementById('pickup_city').value;
            const dropoffCity = document.getElementById('dropoff_city').value;
            const vehicleType = document.getElementById('vehicle_type').value;
            const departureDate = document.getElementById('departure_date').value;
            const numSeats = document.getElementById('num_seats').value;

            if (!pickupCity || !dropoffCity || !vehicleType || !departureDate || !numSeats) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            if (pickupCity === dropoffCity) {
                e.preventDefault();
                alert('Pickup and dropoff cities cannot be the same.');
                return false;
            }

            if (numSeats < 1) {
                e.preventDefault();
                alert('Please select at least one seat.');
                return false;
            }
        });

        // Set minimum date to today
        document.getElementById('departure_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>