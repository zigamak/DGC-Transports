<?php
// This script is designed to be run as a daily cron job.
// It reads from the trip_schedules table and creates daily trip instances
// in the trips table.

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be executed via the command line.');
}

// Include necessary files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set the timezone to ensure correct date comparisons
date_default_timezone_set('Africa/Lagos');

// Get today's date
$today = new DateTime();
$today_str = $today->format('Y-m-d');
$day_of_week = $today->format('l');

echo "--------------------------------------------------------\n";
echo "Starting daily trip generation for: " . $today_str . "\n";
echo "Day of the week: " . $day_of_week . "\n";
echo "--------------------------------------------------------\n";

try {
    // Start a transaction for data consistency
    $conn->begin_transaction();

    // 1. Find all active trip schedules that are valid for today
    $sql = "SELECT * FROM trip_schedules 
            WHERE status = 'active'
            AND start_date <= ?
            AND end_date >= ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("SQL prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $today_str, $today_str);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $trips_created = 0;

    foreach ($schedules as $schedule) {
        $should_create_trip = false;

        // Check recurrence rules
        switch ($schedule['recurrence_type']) {
            case 'day':
                $should_create_trip = true;
                break;
            case 'week':
                $recurrence_days_array = explode(',', $schedule['recurrence_days']);
                if (in_array($day_of_week, $recurrence_days_array)) {
                    $should_create_trip = true;
                }
                break;
            case 'month':
                $start_date = new DateTime($schedule['start_date']);
                if ($today->format('j') === $start_date->format('j')) {
                    $should_create_trip = true;
                }
                break;
            case 'year':
                $start_date = new DateTime($schedule['start_date']);
                if ($today->format('m-d') === $start_date->format('m-d')) {
                    $should_create_trip = true;
                }
                break;
        }

        if ($should_create_trip) {
            // 2. Check if a trip for this schedule and date already exists
            $check_sql = "SELECT id FROM trips WHERE trip_date = ? AND time_slot_id = ? AND vehicle_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sii", $today_str, $schedule['time_slot_id'], $schedule['vehicle_id']);
            $check_stmt->execute();
            $existing_trip = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($existing_trip) {
                echo "  -> Trip for schedule ID {$schedule['id']} already exists for {$today_str}. Skipping.\n";
                continue;
            }

            // 3. Fetch vehicle and time slot details to populate the trips table
            $vehicle_stmt = $conn->prepare("SELECT vehicle_number, driver_name FROM vehicles WHERE id = ?");
            $vehicle_stmt->bind_param("i", $schedule['vehicle_id']);
            $vehicle_stmt->execute();
            $vehicle = $vehicle_stmt->get_result()->fetch_assoc();
            $vehicle_stmt->close();

            $time_stmt = $conn->prepare("SELECT departure_time, arrival_time FROM time_slots WHERE id = ?");
            $time_stmt->bind_param("i", $schedule['time_slot_id']);
            $time_stmt->execute();
            $time_slot = $time_stmt->get_result()->fetch_assoc();
            $time_stmt->close();

            // 4. Insert the new trip into the trips table
            $insert_sql = "
                INSERT INTO trips (
                    pickup_city_id, dropoff_city_id, vehicle_type_id, vehicle_id, time_slot_id, trip_date, 
                    departure_time, arrival_time, vehicle_number, driver_name, price, booked_seats, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'active')
            ";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(
                "iiiissssssd",
                $schedule['pickup_city_id'],
                $schedule['dropoff_city_id'],
                $schedule['vehicle_type_id'],
                $schedule['vehicle_id'],
                $schedule['time_slot_id'],
                $today_str,
                $time_slot['departure_time'],
                $time_slot['arrival_time'],
                $vehicle['vehicle_number'],
                $vehicle['driver_name'],
                $schedule['price']
            );

            if ($insert_stmt->execute()) {
                $trips_created++;
                echo "  -> Created new trip for schedule ID {$schedule['id']}.\n";
            } else {
                echo "  -> Failed to create trip for schedule ID {$schedule['id']}: " . $insert_stmt->error . "\n";
            }
            $insert_stmt->close();
        }
    }
    
    // Commit the transaction if all queries succeeded
    $conn->commit();
    echo "--------------------------------------------------------\n";
    echo "Trip generation complete. Total trips created: {$trips_created}.\n";
    echo "--------------------------------------------------------\n";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    echo "ERROR: An error occurred during trip generation.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Rolling back database changes.\n";
} finally {
    $conn->close();
}
?>
