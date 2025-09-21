<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Enforce staff-only access
requireRole('staff', '/login.php');

// Set content type to JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scanQR' && isset($_POST['qrCode'])) {
    $pnr = sanitizeInput($_POST['qrCode']);
    
    if (!empty($pnr)) {
        // Query to get booking details
        $stmt = $conn->prepare("
            SELECT
                b.*,
                vt.type as vehicle_type,
                v.vehicle_number,
                ts.departure_time,
                ts.arrival_time,
                pc.name as pickup_city,
                dc.name as dropoff_city,
                tt.price
            FROM bookings b
            JOIN trip_templates tt ON b.template_id = tt.id
            JOIN cities pc ON tt.pickup_city_id = pc.id
            JOIN cities dc ON tt.dropoff_city_id = dc.id
            JOIN time_slots ts ON tt.time_slot_id = ts.id
            JOIN vehicles v ON tt.vehicle_id = v.id
            JOIN vehicle_types vt ON tt.vehicle_type_id = vt.id
            WHERE b.pnr = ?
        ");
        $stmt->bind_param("s", $pnr);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($booking) {
            if ($booking['status'] === 'confirmed' || $booking['status'] === 'pending') {
                // Update status to 'boarded'
                $stmt = $conn->prepare("UPDATE bookings SET status = 'boarded' WHERE id = ? AND status IN ('confirmed', 'pending')");
                $stmt->bind_param("i", $booking['id']);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $response = [
                        'status' => 'success',
                        'passenger_name' => $booking['passenger_name'],
                        'pickup_city' => $booking['pickup_city'],
                        'dropoff_city' => $booking['dropoff_city'],
                        'vehicle_type' => $booking['vehicle_type'],
                        'vehicle_number' => $booking['vehicle_number'],
                        'seat_number' => $booking['seat_number'],
                        'boarding_time' => date('Y-m-d H:i:s')
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Failed to update boarding status'
                    ];
                }
                $stmt->close();
            } else if ($booking['status'] === 'boarded') {
                $response = [
                    'status' => 'warning',
                    'passenger_name' => $booking['passenger_name'],
                    'pickup_city' => $booking['pickup_city'],
                    'dropoff_city' => $booking['dropoff_city'],
                    'vehicle_type' => $booking['vehicle_type'],
                    'vehicle_number' => $booking['vehicle_number'],
                    'seat_number' => $booking['seat_number'],
                    'boarding_time' => $booking['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid booking status'];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'No booking found with the provided PNR'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Invalid PNR'];
    }
}

echo json_encode($response);
exit;
?>