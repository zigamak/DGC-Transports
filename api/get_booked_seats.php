<?php
// api/get_booked_seats.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if needed for auth
session_start();

// Include database connection
require_once '../includes/db.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST request.'
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON format: ' . json_last_error_msg()
    ]);
    exit;
}

// Validate required parameters
if (!isset($data['template_id']) || !isset($data['trip_date'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters. Required: template_id, trip_date'
    ]);
    exit;
}

$template_id = (int)$data['template_id'];
$trip_date = trim($data['trip_date']);

// Validate template_id
if ($template_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid template_id. Must be a positive integer.'
    ]);
    exit;
}

// Validate trip_date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trip_date)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid trip_date format. Use YYYY-MM-DD format.'
    ]);
    exit;
}

// Validate that the date is valid
$date_parts = explode('-', $trip_date);
if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid date provided.'
    ]);
    exit;
}

try {
    // Check if template exists and is active
    $template_check = $conn->prepare("
        SELECT id, pickup_city_id, dropoff_city_id, vehicle_type_id, price 
        FROM trip_templates 
        WHERE id = ? AND status = 'active'
    ");
    
    if (!$template_check) {
        throw new Exception('Failed to prepare template check query: ' . $conn->error);
    }
    
    $template_check->bind_param("i", $template_id);
    $template_check->execute();
    $template_result = $template_check->get_result();
    
    if ($template_result->num_rows === 0) {
        $template_check->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Trip template not found or inactive.'
        ]);
        exit;
    }
    
    $template_info = $template_result->fetch_assoc();
    $template_check->close();
    
    // Query to fetch booked seats for this specific trip
    $query = "
        SELECT 
            b.seat_number,
            b.passenger_name,
            b.pnr,
            b.payment_status,
            b.status
        FROM bookings b
        WHERE b.template_id = ? 
            AND b.trip_date = ? 
            AND b.status != 'cancelled' 
            AND b.payment_status != 'cancelled'
        ORDER BY b.seat_number ASC
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare booked seats query: ' . $conn->error);
    }
    
    $stmt->bind_param("is", $template_id, $trip_date);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute booked seats query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $booked_seats = [];
    $seat_details = [];
    
    while ($row = $result->fetch_assoc()) {
        $booked_seats[] = (int)$row['seat_number'];
        $seat_details[] = [
            'seat_number' => (int)$row['seat_number'],
            'passenger_name' => $row['passenger_name'],
            'pnr' => $row['pnr'],
            'payment_status' => $row['payment_status'],
            'booking_status' => $row['status']
        ];
    }
    
    $stmt->close();
    
    // Get vehicle capacity for this trip
    $capacity_query = "
        SELECT vt.capacity 
        FROM trip_templates tt
        JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
        WHERE tt.id = ?
    ";
    
    $capacity_stmt = $conn->prepare($capacity_query);
    $capacity_stmt->bind_param("i", $template_id);
    $capacity_stmt->execute();
    $capacity_result = $capacity_stmt->get_result();
    $capacity_data = $capacity_result->fetch_assoc();
    $total_capacity = $capacity_data ? (int)$capacity_data['capacity'] : 0;
    $capacity_stmt->close();
    
    // Calculate available seats
    $available_seats = $total_capacity - count($booked_seats);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'booked_seats' => $booked_seats,
        'seat_details' => $seat_details,
        'total_booked' => count($booked_seats),
        'total_capacity' => $total_capacity,
        'available_seats' => $available_seats,
        'template_id' => $template_id,
        'trip_date' => $trip_date,
        'template_info' => [
            'pickup_city_id' => $template_info['pickup_city_id'],
            'dropoff_city_id' => $template_info['dropoff_city_id'],
            'vehicle_type_id' => $template_info['vehicle_type_id'],
            'price' => number_format($template_info['price'], 2)
        ]
    ]);
    
} catch (Exception $e) {
    // Log error (you can customize this to log to a file)
    error_log('API Error (get_booked_seats.php): ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An internal server error occurred.',
        'message' => $e->getMessage() // Remove in production
    ]);
}
?>