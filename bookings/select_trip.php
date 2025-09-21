<?php
// bookings/select_trip.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $virtual_trip_id = $_POST['virtual_trip_id'];
    $num_seats = (int)$_POST['num_seats'];
    $price = (float)$_POST['price'];

    // Parse virtual_trip_id (format: trip_id-date)
    list($trip_id, $date) = explode('-', $virtual_trip_id);

    // Fetch trip details
    $stmt = $conn->prepare("
        SELECT 
            t.id as trip_id,
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
            t.trip_date,
            c1.name as pickup_city,
            c2.name as dropoff_city
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.id
        JOIN vehicle_types vt ON v.vehicle_type_id = vt.id
        JOIN time_slots ts ON t.time_slot_id = ts.id
        JOIN cities c1 ON t.pickup_city_id = c1.id
        JOIN cities c2 ON t.dropoff_city_id = c2.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();

    if ($trip) {
        $_SESSION['selected_trip'] = $trip;
        $_SESSION['selected_num_seats'] = $num_seats;
        $_SESSION['selected_price'] = $price * $num_seats;
        header("Location: seat_selection.php");
        exit();
    } else {
        // Handle invalid trip
        header("Location: search_trips.php");
        exit();
    }
}
?>