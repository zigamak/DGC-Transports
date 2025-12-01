<?php
// index.php - Updated with Round Trip support + Customer Reviews Section
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

session_start();

define('IMAGE_SLIDES', [
    '/assets/images/abuja.png',
    '/assets/images/jos.jpeg',
    '/assets/images/abuja.jpeg',
    '/assets/images/jos1.jpeg',
]);

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
    define('SITE_NAME', 'DGC Transports');
}

$cities_result = null;
$vehicle_types_result = null;
$vehicle_types = [];
$max_date = date('Y-m-d', strtotime('+1 year'));

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $table_check = $conn->query("SHOW TABLES LIKE 'cities'");
    if ($table_check && $table_check->num_rows > 0) {
        $cities_result = $conn->query("SELECT id, name FROM cities ORDER BY name");
        $cities_result_dropoff = $conn->query("SELECT id, name FROM cities ORDER BY name"); 
    } else {
        error_log("Table 'cities' does not exist in database.");
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'vehicle_types'");
    if ($table_check && $table_check->num_rows > 0) {
       $vehicle_types_result = $conn->query("SELECT id, type, capacity FROM vehicle_types WHERE status = 'active' ORDER BY type");
        if ($vehicle_types_result) {
            while ($vehicle = $vehicle_types_result->fetch_assoc()) {
                $vehicle_types[$vehicle['id']] = $vehicle;
            }
        }
    } else {
        error_log("Table 'vehicle_types' does not exist in database.");
    }

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

// Handle review submission
$review_success_message = '';
$review_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_name = trim($_POST['review_name'] ?? '');
    $review_email = trim($_POST['review_email'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $message = trim($_POST['review_message'] ?? '');

    if (empty($review_name)) {
        $review_error_message = 'Please enter your name.';
    } elseif ($rating < 1 || $rating > 5) {
        $review_error_message = 'Please select a rating between 1 and 5 stars.';
    } elseif (empty($message)) {
        $review_error_message = 'Please share your experience.';
    } elseif (strlen($message) < 10) {
        $review_error_message = 'Your review must be at least 10 characters long.';
    } else {
        // Validate email if provided
        if (!empty($review_email) && !filter_var($review_email, FILTER_VALIDATE_EMAIL)) {
            $review_error_message = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("INSERT INTO reviews (name, email, rating, message, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            if ($stmt) {
                $stmt->bind_param("ssis", $review_name, $review_email, $rating, $message);
                if ($stmt->execute()) {
                    $review_success_message = 'Thank you for your review! It will be published after approval.';
                    // Clear form data on success
                    $_POST = [];
                } else {
                    error_log("Error inserting review: " . $stmt->error);
                    $review_error_message = 'An error occurred while submitting your review. Please try again later.';
                }
                $stmt->close();
            } else {
                error_log("Failed to prepare review statement: " . $conn->error);
                $review_error_message = 'An error occurred. Please try again later.';
            }
        }
    }
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
    .container { max-width: 1280px; }
    .card {
        background: var(--white);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
    }
    .card:hover {
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
    .form-group { position: relative; }
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
    .error-message i { margin-right: 8px; }
    .success-message {
        color: #059669;
        font-size: 0.9rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        font-weight: 500;
        padding: 8px;
        border-left: 4px solid #059669;
        background-color: #d1fae5;
        border-radius: 4px;
    }
    .success-message i { margin-right: 8px; }
    .hero-image-slider {
        position: relative;
        background-color: var(--black);
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
    .slide.active { opacity: 1; }
    .slide-overlay {
        position: absolute;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.5);
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-in { animation: fadeIn 0.5s ease-out; }
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
    .seat-counter button:hover { background: #e5e7eb; }
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
        color: #6b7280;
        border-bottom: 3px solid transparent;
        transition: color 0.2s, border-color 0.2s;
    }
    .tab-item.active-tab {
        color: var(--primary-red);
        border-bottom-color: var(--primary-red);
    }
    .trip-type-content { display: none; }
    .trip-type-content.active { display: block; }
    
    /* Star Rating Styles */
    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 8px;
    }
    .star-rating input[type="radio"] {
        display: none;
    }
    .star-rating label {
        cursor: pointer;
        font-size: 2rem;
        color: #d1d5db;
        transition: color 0.2s ease, transform 0.2s ease;
    }
    .star-rating label:hover,
    .star-rating label:hover ~ label {
        color: #fbbf24;
        transform: scale(1.1);
    }
    .star-rating input[type="radio"]:checked ~ label {
        color: #fbbf24;
    }
    .review-stars {
        color: #fbbf24;
        font-size: 1.25rem;
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

    <!-- Main Booking Card -->
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="container w-full">
            <div class="card overflow-hidden fade-in">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
                    <div class="p-8 lg:p-12">
                        <div class="max-w-md mx-auto">
                            <div class="flex border-b mb-8">
                                <div class="tab-item active-tab" onclick="switchTab('one-way')">
                                    One Way
                                </div>
                                <div class="tab-item" onclick="switchTab('round-trip')">
                                    Round Trip
                                </div>
                                <a href="charter.php" class="tab-item">
                                    Charter
                                </a>
                            </div>

                            <div class="text-center mb-8">
                                <h1 class="text-4xl font-bold text-gray-800">
                                    Book Your <span style="color: var(--primary-red);">Journey</span>
                                </h1>
                                <p class="text-gray-600 text-lg">Experience premium travel with comfort and style</p>
                            </div>

                            <?php if (isset($_SESSION['error'])): ?>
                                <p class="error-message"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($_SESSION['error']) ?></p>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <!-- One Way Form -->
                            <div id="one-way-form" class="trip-type-content active">
                                <form id="bookingForm" action="bookings/search_trips.php" method="POST" class="space-y-6">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div class="form-group">
                                            <label for="pickup_city_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                From
                                            </label>
                                            <select class="input-field" id="pickup_city_id" name="pickup_city_id" required onchange="updateDropoffOptions()">
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
                                                To
                                            </label>
                                            <select class="input-field" id="dropoff_city_id" name="dropoff_city_id" required>
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
                                                Vehicle
                                            </label>
                                            <select class="input-field" id="vehicle_type_id" name="vehicle_type_id" required onchange="updateMaxSeats()">
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
                                                Seats
                                            </label>
                                            <div class="seat-counter">
                                                <button type="button" id="decrement-seats" class="btn-seat">-</button>
                                                <input type="number" class="input-field" id="num_seats" name="num_seats" value="1" min="1" required>
                                                <button type="button" id="increment-seats" class="btn-seat">+</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="departure_date" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                Date
                                            </label>
                                            <input type="date" class="input-field" id="departure_date" name="departure_date" required min="<?= date('Y-m-d') ?>" max="<?= $max_date ?>">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-primary w-full mt-4">
                                        Search Available Trips
                                    </button>
                                </form>
                            </div>

                            <!-- Round Trip Form -->
                            <div id="round-trip-form" class="trip-type-content">
                                <form id="roundTripForm" action="bookings/roundtrip_search.php" method="POST" class="space-y-6">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div class="form-group">
                                            <label for="rt_pickup_city_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                From
                                            </label>
                                            <select class="input-field" id="rt_pickup_city_id" name="pickup_city_id" required onchange="updateRTDropoffOptions()">
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
                                            <label for="rt_dropoff_city_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                To
                                            </label>
                                            <select class="input-field" id="rt_dropoff_city_id" name="dropoff_city_id" required>
                                                <option value="">Select destination</option>
                                                <?php
                                                if (isset($cities_result_dropoff) && $cities_result_dropoff) {
                                                    $cities_result_dropoff->data_seek(0);
                                                    while ($city = $cities_result_dropoff->fetch_assoc()): ?>
                                                        <option value="<?= $city['id'] ?>"><?= htmlspecialchars($city['name']) ?></option>
                                                    <?php endwhile;
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div class="form-group">
                                            <label for="rt_departure_date" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                Departure Date
                                            </label>
                                            <input type="date" class="input-field" id="rt_departure_date" name="departure_date" required min="<?= date('Y-m-d') ?>" max="<?= $max_date ?>" onchange="updateReturnDateMin()">
                                        </div>
                                        <div class="form-group">
                                            <label for="rt_return_date" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                Return Date
                                            </label>
                                            <input type="date" class="input-field" id="rt_return_date" name="return_date" required min="<?= date('Y-m-d') ?>" max="<?= $max_date ?>">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div class="form-group">
                                            <label for="rt_vehicle_type_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                Vehicle
                                            </label>
                                            <select class="input-field" id="rt_vehicle_type_id" name="vehicle_type_id" required onchange="updateRTMaxSeats()">
                                                <option value="">Choose vehicle</option>
                                                <?php foreach ($vehicle_types as $vehicle): ?>
                                                    <option value="<?= $vehicle['id'] ?>" data-capacity="<?= $vehicle['capacity'] ?>">
                                                        <?= htmlspecialchars($vehicle['type']) ?> (<?= $vehicle['capacity'] ?> seats)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="rt_num_seats" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                                Seats
                                            </label>
                                            <div class="seat-counter">
                                                <button type="button" id="rt-decrement-seats" class="btn-seat">-</button>
                                                <input type="number" class="input-field" id="rt_num_seats" name="num_seats" value="1" min="1" required>
                                                <button type="button" id="rt-increment-seats" class="btn-seat">+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-primary w-full mt-4">
                                        Search Round Trip
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div id="heroImageSlider" class="hero-image-slider relative flex items-center justify-center min-h-[300px] lg:min-h-[450px]">
                        <?php 
                        foreach (IMAGE_SLIDES as $index => $image_path) {
                            $full_url = defined('SITE_URL') ? SITE_URL . $image_path : 'https://booking.dgctransports.com' . $image_path;
                            $active_class = $index === 0 ? 'active' : '';
                            echo '<div class="slide ' . $active_class . '" style="background-image: url(\'' . htmlspecialchars($full_url) . '\');"></div>';
                        }
                        ?>
                        <div class="slide-overlay"></div>
                        <div class="relative z-10 p-8 text-center text-white">
                            <h2 class="text-3xl sm:text-4xl font-bold mb-4">Premium Travel Experience</h2>
                            <p class="text-md sm:text-xl text-gray-200 mb-6">Safe, comfortable, and reliable transportation between cities</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-lg mx-auto">
                                <div class="flex items-center justify-center sm:block sm:text-left">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-red-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                        <i class="fas fa-shield-alt text-white"></i>
                                    </div>
                                    <div class="mt-2 sm:mt-0">
                                        <h3 class="font-semibold text-sm">Safe & Secure</h3>
                                        <p class="text-gray-300 text-xs">Professional drivers</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center sm:block sm:text-left">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-red-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                        <i class="fas fa-clock text-white"></i>
                                    </div>
                                    <div class="mt-2 sm:mt-0">
                                        <h3 class="font-semibold text-sm">On-Time Service</h3>
                                        <p class="text-gray-300 text-xs">Punctual departures</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center sm:block sm:text-left">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-red-600 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                        <i class="fas fa-star text-white"></i>
                                    </div>
                                    <div class="mt-2 sm:mt-0">
                                        <h3 class="font-semibold text-sm">Premium Comfort</h3>
                                        <p class="text-gray-300 text-xs">Luxury vehicles</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Reviews Section -->
    <div class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">What Our Customers Say</h2>
            <p class="text-gray-600">Real experiences from real travelers</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <!-- Review Form -->
            <div class="lg:col-span-1">
                <div class="card p-6">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Leave a Review</h3>
                    
                    <?php if (!empty($review_success_message)): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($review_success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($review_error_message)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($review_error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <input 
                                type="text" 
                                name="review_name" 
                                placeholder="Your Name *" 
                                required 
                                class="input-field" 
                                maxlength="100"
                                value="<?= isset($_POST['review_name']) && empty($review_success_message) ? htmlspecialchars($_POST['review_name']) : '' ?>">
                        </div>
                        
                        <div>
                            <input 
                                type="email" 
                                name="review_email" 
                                placeholder="Email (optional)" 
                                class="input-field"
                                value="<?= isset($_POST['review_email']) && empty($review_success_message) ? htmlspecialchars($_POST['review_email']) : '' ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Rating <span class="text-red-500">*</span>
                            </label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input 
                                        type="radio" 
                                        name="rating" 
                                        value="<?= $i ?>" 
                                        id="star<?= $i ?>" 
                                        required
                                        <?= (isset($_POST['rating']) && $_POST['rating'] == $i && empty($review_success_message)) ? 'checked' : '' ?>>
                                    <label for="star<?= $i ?>">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div>
                            <textarea 
                                name="review_message" 
                                rows="4" 
                                placeholder="Share your experience... (minimum 10 characters)" 
                                required 
                                class="input-field resize-none"
                                minlength="10"
                                maxlength="1000"><?= isset($_POST['review_message']) && empty($review_success_message) ? htmlspecialchars($_POST['review_message']) : '' ?></textarea>
                        </div>
                        
                        <button type="submit" name="submit_review" class="btn-primary w-full">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Review
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Reviews -->
            <div class="lg:col-span-2">
                <div class="space-y-6">
                    <?php
                    $reviews_result = $conn->query("SELECT name, rating, message, created_at FROM reviews WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6");
                    if ($reviews_result && $reviews_result->num_rows > 0):
                        while ($rev = $reviews_result->fetch_assoc()):
                    ?>
                        <div class="card p-6 fade-in">
                            <div class="flex items-start mb-3">
                                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-bold text-xl flex-shrink-0">
                                    <?= htmlspecialchars(strtoupper(substr($rev['name'], 0, 1))) ?>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center justify-between flex-wrap">
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($rev['name']) ?></h4>
                                        <div class="review-stars">
                                            <?php 
                                            for ($i = 0; $i < 5; $i++) {
                                                echo $i < $rev['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500"><?= date('F j, Y', strtotime($rev['created_at'])) ?></p>
                                </div>
                            </div>
                            <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($rev['message'])) ?></p>
                        </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <div class="card p-12 text-center">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-comments text-6xl"></i>
                            </div>
                            <p class="text-gray-500 text-lg">No reviews yet. Be the first to share your experience!</p>
                        </div>
                    <?php endif; ?>
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
        const vehicleTypes = <?php echo json_encode($vehicle_types); ?>;
        const originalCities = Array.from(document.getElementById('dropoff_city_id')?.options || []).map(option => ({
            value: option.value,
            text: option.text
        })).filter(city => city.value !== '');

        function switchTab(tabName) {
            document.querySelectorAll('.tab-item').forEach(tab => tab.classList.remove('active-tab'));
            document.querySelectorAll('.trip-type-content').forEach(content => content.classList.remove('active'));
            event.target.classList.add('active-tab');
            document.getElementById(tabName + '-form').classList.add('active');
        }

        function updateDropoffOptions() {
            const pickupSelect = document.getElementById('pickup_city_id');
            const dropoffSelect = document.getElementById('dropoff_city_id');
            if (!pickupSelect || !dropoffSelect) return;
            const selectedPickupValue = pickupSelect.value;
            dropoffSelect.innerHTML = '<option value="">Select destination</option>';
            originalCities.forEach(city => {
                if (city.value && city.value !== selectedPickupValue) {
                    dropoffSelect.add(new Option(city.text, city.value));
                }
            });
        }

        function updateMaxSeats() {
            const vehicleSelect = document.getElementById('vehicle_type_id');
            const numSeatsInput = document.getElementById('num_seats');
            if (!vehicleSelect || !numSeatsInput) return;
            const selectedVehicleId = vehicleSelect.value;
            if (selectedVehicleId && vehicleTypes[selectedVehicleId]) {
                const maxSeats = vehicleTypes[selectedVehicleId].capacity;
                numSeatsInput.max = maxSeats;
                if (parseInt(numSeatsInput.value) > maxSeats) numSeatsInput.value = maxSeats;
            }
        }

        function updateRTDropoffOptions() {
            const pickupSelect = document.getElementById('rt_pickup_city_id');
            const dropoffSelect = document.getElementById('rt_dropoff_city_id');
            if (!pickupSelect || !dropoffSelect) return;
            const selectedPickupValue = pickupSelect.value;
            dropoffSelect.innerHTML = '<option value="">Select destination</option>';
            originalCities.forEach(city => {
                if (city.value && city.value !== selectedPickupValue) {
                    dropoffSelect.add(new Option(city.text, city.value));
                }
            });
        }

        function updateRTMaxSeats() {
            const vehicleSelect = document.getElementById('rt_vehicle_type_id');
            const numSeatsInput = document.getElementById('rt_num_seats');
            if (!vehicleSelect || !numSeatsInput) return;
            const selectedVehicleId = vehicleSelect.value;
            if (selectedVehicleId && vehicleTypes[selectedVehicleId]) {
                const maxSeats = vehicleTypes[selectedVehicleId].capacity;
                numSeatsInput.max = maxSeats;
                if (parseInt(numSeatsInput.value) > maxSeats) numSeatsInput.value = maxSeats;
            }
        }

        function updateReturnDateMin() {
            const departureDate = document.getElementById('rt_departure_date')?.value;
            const returnDateInput = document.getElementById('rt_return_date');
            if (departureDate && returnDateInput) {
                returnDateInput.min = departureDate;
                if (returnDateInput.value && returnDateInput.value < departureDate) {
                    returnDateInput.value = departureDate;
                }
            }
        }

        const slider = {
            init: function() {
                this.slides = document.querySelectorAll('#heroImageSlider .slide');
                if (this.slides.length === 0) return;
                this.currentSlide = 0;
                setInterval(() => {
                    this.slides[this.currentSlide].classList.remove('active');
                    this.currentSlide = (this.currentSlide + 1) % this.slides.length;
                    this.slides[this.currentSlide].classList.add('active');
                }, 5000);
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            slider.init();

            // Seat counters
            ['','rt-'].forEach(prefix => {
                const input = document.getElementById(prefix + 'num_seats');
                const dec = document.getElementById(prefix + 'decrement-seats');
                const inc = document.getElementById(prefix + 'increment-seats');
                if (!input || !dec || !inc) return;

                dec.addEventListener('click', () => { 
                    if (parseInt(input.value) > 1) input.value = parseInt(input.value) - 1; 
                });
                inc.addEventListener('click', () => {
                    const max = parseInt(input.max) || 100;
                    if (parseInt(input.value) < max) input.value = parseInt(input.value) + 1;
                });
            });

            updateDropoffOptions();
            updateMaxSeats();
            updateRTDropoffOptions();
            updateRTMaxSeats();
        });
    </script>
</body>
</html>