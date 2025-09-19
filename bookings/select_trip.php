<?php
// bookings/select_trip.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../templates/header.php'; 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $virtual_trip_id = $_POST['virtual_trip_id'];
    $num_seats = (int)$_POST['num_seats'];
    $price = (float)$_POST['price'];

    // Parse virtual_trip_id (format: vehicle_id-time_slot_id-trip_date)
    list($vehicle_id, $time_slot_id, $trip_date) = explode('-', $virtual_trip_id);

    // Fetch trip details
    $stmt = $conn->prepare("
        SELECT 
            v.id as vehicle_id,
            v.vehicle_number,
            v.driver_name,
            vt.id as vehicle_type_id,
            vt.type as vehicle_type,
            vt.capacity,
            vt.price,
            ts.id as time_slot_id,
            ts.departure_time,
            ts.arrival_time,
            c1.name as pickup_city,
            c2.name as dropoff_city
        FROM vehicles v
        JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
        JOIN time_slots ts ON ts.id = ?
        JOIN cities c1 ON c1.id = ?
        JOIN cities c2 ON c2.id = ?
        WHERE v.id = ?
    ");
    $stmt->bind_param("iiii", $time_slot_id, $_SESSION['search']['pickup_city_id'], $_SESSION['search']['dropoff_city_id'], $vehicle_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();

    if (!$trip) {
        // Handle error: trip not found
        header("Location: search_trips.php?error=trip_not_found");
        exit();
    }

    // Create a virtual trip ID for use in seat_selection.php
    $trip['id'] = $virtual_trip_id; // Use virtual_trip_id as a unique identifier
    $trip['trip_date'] = $trip_date;
    $trip['price'] = $price;

    // Store selected trip and number of seats in session
    $_SESSION['selected_trip'] = $trip;
    $_SESSION['selected_num_seats'] = $num_seats;

    // Redirect to seat selection
    header("Location: seat_selection.php");
    exit();
}
?>