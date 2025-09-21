<?php
// cron_create_weekly_trips.php

// --- Includes ---
// Load configuration settings
require_once __DIR__ . '/includes/config.php';

// Load database connection logic
require_once __DIR__ . '/includes/db.php';

// Load shared functions
require_once __DIR__ . '/includes/functions.php';

// --- Main Script ---
logActivity("Starting weekly trip creation cron job.");

// Define the number of days to schedule trips for (e.g., 7 days for the next week)
$days_to_schedule = 7;
$today = new DateTime('now', new DateTimeZone(DEFAULT_TIMEZONE));
$trips_created_count = 0;

try {
    // 1. Fetch all cities
    $cities_query = "SELECT `id` FROM `cities`";
    $cities_result = $conn->query($cities_query);
    if ($cities_result === false) {
        throw new Exception("Failed to fetch cities: " . $conn->error);
    }
    $city_ids = [];
    while ($row = $cities_result->fetch_assoc()) {
        $city_ids[] = (int)$row['id'];
    }

    // 2. Fetch all vehicle types
    $vehicle_types_query = "SELECT `id`, `price` FROM `vehicle_types`";
    $vehicle_types_result = $conn->query($vehicle_types_query);
    if ($vehicle_types_result === false) {
        throw new Exception("Failed to fetch vehicle types: " . $conn->error);
    }
    $vehicle_types = [];
    while ($row = $vehicle_types_result->fetch_assoc()) {
        $vehicle_types[$row['id']] = $row['price'];
    }

    // 3. Fetch all time slots
    $time_slots_query = "SELECT `id`, `departure_time`, `arrival_time` FROM `time_slots`";
    $time_slots_result = $conn->query($time_slots_query);
    if ($time_slots_result === false) {
        throw new Exception("Failed to fetch time slots: " . $conn->error);
    }
    $time_slots = [];
    while ($row = $time_slots_result->fetch_assoc()) {
        $time_slots[] = $row;
    }

    // 4. Fetch all vehicles and their types
    $vehicles_query = "SELECT `id`, `vehicle_type_id`, `vehicle_number`, `driver_name` FROM `vehicles`";
    $vehicles_result = $conn->query($vehicles_query);
    if ($vehicles_result === false) {
        throw new Exception("Failed to fetch vehicles: " . $conn->error);
    }
    $vehicles_by_type = [];
    while ($row = $vehicles_result->fetch_assoc()) {
        $vehicles_by_type[$row['vehicle_type_id']][] = $row;
    }

    // Prepare statement for inserting new trips
    $trip_stmt = $conn->prepare("
        INSERT INTO `trips` 
            (`pickup_city_id`, `dropoff_city_id`, `vehicle_type_id`, `vehicle_id`, `time_slot_id`, 
             `trip_date`, `departure_time`, `arrival_time`, `vehicle_number`, `driver_name`, `price`) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$trip_stmt) {
        throw new Exception("Failed to prepare statement for trip creation: " . $conn->error);
    }

    // Iterate through each day for the next week
    for ($i = 1; $i <= $days_to_schedule; $i++) {
        $schedule_date = (clone $today)->modify("+$i days");
        $trip_date_str = $schedule_date->format(DATE_FORMAT);

        // Iterate through all city combinations (excluding same pickup/dropoff)
        foreach ($city_ids as $pickup_city_id) {
            foreach ($city_ids as $dropoff_city_id) {
                if ($pickup_city_id === $dropoff_city_id) {
                    continue;
                }

                // Iterate through all time slots
                foreach ($time_slots as $time_slot) {
                    // Iterate through all vehicle types
                    foreach ($vehicle_types as $vehicle_type_id => $price) {
                        // Check if a vehicle of this type is available to assign
                        if (!isset($vehicles_by_type[$vehicle_type_id]) || empty($vehicles_by_type[$vehicle_type_id])) {
                            logActivity("No vehicle found for vehicle_type_id: {$vehicle_type_id}. Skipping trip creation.");
                            continue;
                        }

                        // Assign a specific vehicle for this trip by randomly picking one from the available pool
                        $assigned_vehicle = $vehicles_by_type[$vehicle_type_id][array_rand($vehicles_by_type[$vehicle_type_id])];
                        
                        // Check if a trip for this exact combination already exists
                        $check_stmt = $conn->prepare("
                            SELECT COUNT(*) FROM `trips` 
                            WHERE `pickup_city_id` = ? AND `dropoff_city_id` = ? AND `vehicle_type_id` = ? 
                            AND `trip_date` = ? AND `time_slot_id` = ?
                        ");
                        $check_stmt->bind_param("iiisi", $pickup_city_id, $dropoff_city_id, $vehicle_type_id, $trip_date_str, $time_slot['id']);
                        $check_stmt->execute();
                        $count_result = $check_stmt->get_result()->fetch_row()[0];
                        $check_stmt->close();

                        // If the trip doesn't exist, create it
                        if ($count_result == 0) {
                            $trip_stmt->bind_param(
                                "iiiissssssd",
                                $pickup_city_id,
                                $dropoff_city_id,
                                $vehicle_type_id,
                                $assigned_vehicle['id'],
                                $time_slot['id'],
                                $trip_date_str,
                                $time_slot['departure_time'],
                                $time_slot['arrival_time'],
                                $assigned_vehicle['vehicle_number'],
                                $assigned_vehicle['driver_name'],
                                $price
                            );
                            $trip_stmt->execute();
                            $trips_created_count++;
                        }
                    }
                }
            }
        }
    }

    $trip_stmt->close();
    // The db.php script handles the connection, so we don't need to close it here
    // as it might be used by other parts of a larger script.
    // However, for a standalone cron job, it's good practice to close it.
    $conn->close();

    logActivity("Successfully completed cron job. Created {$trips_created_count} new trips.");

} catch (Exception $e) {
    logActivity("CRON Job failed unexpectedly: " . $e->getMessage());
    // The db.php script handles connection errors, but this ensures a clean exit
    if ($conn) {
        $conn->close();
    }
}