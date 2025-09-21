<?php
// bookings/select_trip.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

header('Content-Type: application/json');

function sendResponse($success, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

$virtual_trip_id = isset($_POST['virtual_trip_id']) ? $_POST['virtual_trip_id'] : '';
$num_seats = isset($_POST['num_seats']) ? (int)$_POST['num_seats'] : 0;
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
$template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$trip_date = isset($_POST['trip_date']) ? $_POST['trip_date'] : '';

// Validate inputs
if (!$virtual_trip_id || $num_seats <= 0 || $price <= 0 || !$template_id || !$trip_date) {
    sendResponse(false, 'Invalid input data. Please try again.');
}

// Validate trip date
if (strtotime($trip_date) < strtotime(date('Y-m-d'))) {
    sendResponse(false, 'Trip date cannot be in the past.');
}

// Verify template exists, is active, and matches recurrence rules
$stmt = $conn->prepare("
    SELECT 
        tt.id,
        tt.pickup_city_id,
        tt.dropoff_city_id,
        tt.price,
        pc.name as pickup_city,
        dc.name as dropoff_city,
        vt.capacity,
        vt.type as vehicle_type,
        v.vehicle_number,
        v.driver_name,
        ts.departure_time,
        ts.arrival_time,
        COALESCE(ti.booked_seats, 0) AS booked_seats
    FROM trip_templates tt
    JOIN cities pc ON tt.pickup_city_id = pc.id
    JOIN cities dc ON tt.dropoff_city_id = dc.id
    JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
    JOIN vehicles v ON tt.vehicle_id = v.id
    JOIN time_slots ts ON tt.time_slot_id = ts.id
    LEFT JOIN trip_instances ti ON tt.id = ti.template_id AND ti.trip_date = ?
    WHERE tt.id = ?
        AND tt.status = 'active'
        AND ? BETWEEN tt.start_date AND tt.end_date
        AND (
            (tt.recurrence_type = 'day' AND ? = tt.start_date)
            OR (tt.recurrence_type = 'week' AND FIND_IN_SET(DAYNAME(?), tt.recurrence_days))
            OR (tt.recurrence_type = 'month')
            OR (tt.recurrence_type = 'year' AND DATE_FORMAT(?, '%m-%d') = DATE_FORMAT(tt.start_date, '%m-%d'))
        )
");
$stmt->bind_param("sissss", $trip_date, $template_id, $trip_date, $trip_date, $trip_date, $trip_date);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    sendResponse(false, 'Invalid or unavailable trip template.');
}

// Verify seat availability
$available_seats = $result['capacity'] - $result['booked_seats'];
if ($available_seats < $num_seats) {
    sendResponse(false, 'Insufficient seats available for this trip.');
}

// Store selection in session with all necessary information
$_SESSION['selected_trip'] = [
    'virtual_trip_id' => $virtual_trip_id,
    'template_id' => $template_id,
    'trip_date' => $trip_date,
    'num_seats' => $num_seats,
    'price' => $price,
    'pickup_city_id' => $result['pickup_city_id'],
    'dropoff_city_id' => $result['dropoff_city_id'],
    'pickup_city' => $result['pickup_city'],
    'dropoff_city' => $result['dropoff_city'],
    'capacity' => $result['capacity'],
    'vehicle_type' => $result['vehicle_type'],
    'vehicle_number' => $result['vehicle_number'],
    'driver_name' => $result['driver_name'],
    'departure_time' => $result['departure_time'],
    'arrival_time' => $result['arrival_time']
];

// Remove any previously selected seats
unset($_SESSION['selected_seats']);

sendResponse(true, 'Trip selected successfully.');
?>