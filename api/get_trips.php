<?php
// api/get_trips.php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pickup_city_id = isset($input['pickup_city_id']) ? (int)$input['pickup_city_id'] : 0;
    $dropoff_city_id = isset($input['dropoff_city_id']) ? (int)$input['dropoff_city_id'] : 0;
    $vehicle_type_id = isset($input['vehicle_type_id']) ? (int)$input['vehicle_type_id'] : 0;
    $trip_date = isset($input['trip_date']) ? $input['trip_date'] : '';

    // Validate inputs
    if (!$pickup_city_id || !$dropoff_city_id || !$vehicle_type_id || !$trip_date) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d', $trip_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $trip_date) {
        echo json_encode(['error' => 'Invalid date format. Expected YYYY-MM-DD']);
        exit;
    }

    // Check if cities are the same
    if ($pickup_city_id === $dropoff_city_id) {
        echo json_encode(['error' => 'Pickup and dropoff cities cannot be the same']);
        exit;
    }

    // Query to fetch trips - MATCHING search_trips.php structure
    $query = "
        SELECT 
            CONCAT(tt.id, '-', ?) AS virtual_trip_id,
            tt.id AS template_id,
            v.id AS vehicle_id,
            v.vehicle_number,
            v.driver_name,
            tt.vehicle_type_id,
            vt.type AS vehicle_type,
            vt.capacity,
            tt.price,
            ts.departure_time,
            ts.arrival_time,
            ? AS trip_date,
            COALESCE(COUNT(b.id), 0) AS booked_seats,
            (vt.capacity - COALESCE(COUNT(b.id), 0)) AS available_seats
        FROM trip_templates tt
        JOIN vehicles v ON tt.vehicle_id = v.id
        JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
        JOIN time_slots ts ON tt.time_slot_id = ts.id
        LEFT JOIN trip_instances ti ON tt.id = ti.template_id AND ti.trip_date = ? AND ti.status = 'active'
        LEFT JOIN bookings b ON ti.id = b.trip_id AND b.trip_date = ? AND b.payment_status = 'paid' AND b.status != 'cancelled'
        WHERE tt.pickup_city_id = ?
            AND tt.dropoff_city_id = ?
            AND tt.vehicle_type_id = ?
            AND tt.status = 'active'
            AND ? BETWEEN tt.start_date AND tt.end_date
            AND (
                (tt.recurrence_type = 'day' AND ? = tt.start_date)
                OR (tt.recurrence_type = 'week' AND FIND_IN_SET(DAYNAME(?), tt.recurrence_days))
                OR (tt.recurrence_type = 'month')
                OR (tt.recurrence_type = 'year' AND DATE_FORMAT(?, '%m-%d') = DATE_FORMAT(tt.start_date, '%m-%d'))
            )
        GROUP BY tt.id, v.id, vt.id, ts.id, ti.id
        ORDER BY ts.departure_time
    ";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Query prepare failed: " . $conn->error);
        echo json_encode([
            'error' => 'Database query preparation failed',
            'debug' => $conn->error
        ]);
        exit;
    }

    // Bind parameters - 11 total parameters matching search_trips.php
    // Parameter mapping:
    // 1. CONCAT(tt.id, '-', ?) - virtual_trip_id
    // 2. ? AS trip_date - SELECT clause
    // 3. ti.trip_date = ? - LEFT JOIN trip_instances
    // 4. b.trip_date = ? - LEFT JOIN bookings
    // 5. tt.pickup_city_id = ?
    // 6. tt.dropoff_city_id = ?
    // 7. tt.vehicle_type_id = ?
    // 8. ? BETWEEN tt.start_date AND tt.end_date
    // 9. ? = tt.start_date - recurrence_type = 'day'
    // 10. DAYNAME(?) - recurrence_type = 'week'
    // 11. DATE_FORMAT(?, '%m-%d') - recurrence_type = 'year'
    
    $stmt->bind_param(
        "ssssiiissss",
        $trip_date,          // 1. CONCAT virtual_trip_id
        $trip_date,          // 2. trip_date in SELECT
        $trip_date,          // 3. ti.trip_date
        $trip_date,          // 4. b.trip_date
        $pickup_city_id,     // 5. tt.pickup_city_id
        $dropoff_city_id,    // 6. tt.dropoff_city_id
        $vehicle_type_id,    // 7. tt.vehicle_type_id
        $trip_date,          // 8. BETWEEN start_date AND end_date
        $trip_date,          // 9. recurrence_type = 'day'
        $trip_date,          // 10. DAYNAME for week
        $trip_date           // 11. DATE_FORMAT for year
    );

    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        echo json_encode([
            'error' => 'Database query execution failed',
            'debug' => $stmt->error
        ]);
        exit;
    }

    $result = $stmt->get_result();
    $trips = [];
    
    while ($row = $result->fetch_assoc()) {
        $trips[] = [
            'virtual_trip_id' => $row['virtual_trip_id'],
            'template_id' => (int)$row['template_id'],
            'vehicle_id' => (int)$row['vehicle_id'],
            'vehicle_number' => $row['vehicle_number'],
            'driver_name' => $row['driver_name'],
            'vehicle_type_id' => (int)$row['vehicle_type_id'],
            'vehicle_type' => $row['vehicle_type'],
            'capacity' => (int)$row['capacity'],
            'price' => number_format($row['price'], 2, '.', ''),
            'departure_time' => $row['departure_time'],
            'arrival_time' => $row['arrival_time'],
            'trip_date' => $row['trip_date'],
            'booked_seats' => (int)$row['booked_seats'],
            'available_seats' => (int)$row['available_seats']
        ];
    }
    
    $stmt->close();

    // Debugging: Log inputs and results
    error_log("get_trips.php inputs: pickup_city_id=$pickup_city_id, dropoff_city_id=$dropoff_city_id, vehicle_type_id=$vehicle_type_id, trip_date=$trip_date");
    error_log("get_trips.php results count: " . count($trips));
    
    if (empty($trips)) {
        error_log("No trips found. Check if trip_templates exist for this route and if recurrence conditions match.");
    }

    echo json_encode([
        'success' => true,
        'trips' => $trips,
        'count' => count($trips)
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method. POST required.']);
}
?>