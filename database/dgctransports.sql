-- Updated and extended SQL schema based on the provided tables and PHP code.
-- Added missing tables: trips, vehicles, time_slots, seat_bookings, payments.
-- Updated bookings table to include trip_id (instead of vehicle_type_id for trip-specific bookings),
-- total_amount, payment_status.
-- Added emergency_contact and special_requests to bookings for form fields.
-- Ensured foreign keys align with the code (e.g., trip_id references trips.id).
-- Vehicle_types is kept but linked via vehicles.
-- Assumed some sample data for new tables where relevant.

-- Existing tables (unchanged except bookings)

CREATE TABLE `cities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `cities` (`name`) VALUES
('Abuja'),
('Jos');

CREATE TABLE `vehicle_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(255) NOT NULL,
  `capacity` INT(11) NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `vehicle_types` (`type`, `capacity`) VALUES
('12 Seater', 12),
('5 Seater', 5);

CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL,
  PRIMARY KEY (`id`)
);

-- Updated bookings table: Added trip_id, total_amount, payment_status, emergency_contact, special_requests.
-- Replaced vehicle_type_id with trip_id (as per code logic for trip-specific bookings).
-- Status kept for booking lifecycle, payment_status for payment tracking.
-- departure_date is now tied to trip_date via trips.

CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `pnr` VARCHAR(20) NOT NULL UNIQUE,
  `passenger_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `emergency_contact` VARCHAR(255) DEFAULT NULL,
  `special_requests` TEXT DEFAULT NULL,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `trip_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `status` ENUM('pending', 'confirmed', 'canceled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`) ON DELETE CASCADE
);

-- New table: trips (for scheduled trips with date, time, price, status)
CREATE TABLE `trips` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` INT(11) NOT NULL,
  `time_slot_id` INT(11) NOT NULL,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `trip_date` DATE NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`),
  FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`),
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`)
);

-- Sample data for trips (assuming some vehicles and time slots exist; adjust as needed)
-- INSERT INTO `trips` (`vehicle_id`, `time_slot_id`, `pickup_city_id`, `dropoff_city_id`, `trip_date`, `price`, `status`) VALUES
-- (1, 1, 1, 2, '2025-09-20', 5000.00, 'active');

-- New table: vehicles (linked to vehicle_types, with driver details)
CREATE TABLE `vehicles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vehicle_number` VARCHAR(50) NOT NULL UNIQUE,
  `driver_name` VARCHAR(255) NOT NULL,
  `vehicle_type_id` INT(11) NOT NULL,
  `status` ENUM('available', 'in_use', 'maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`)
);

-- Sample data for vehicles
INSERT INTO `vehicles` (`vehicle_number`, `driver_name`, `vehicle_type_id`, `status`) VALUES
('ABC-123', 'John Doe', 1, 'available'),
('DEF-456', 'Jane Smith', 2, 'available');

-- New table: time_slots (for departure/arrival times)
CREATE TABLE `time_slots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  PRIMARY KEY (`id`)
);

-- Sample data for time slots
INSERT INTO `time_slots` (`departure_time`, `arrival_time`) VALUES
('08:00:00', '12:00:00'),
('14:00:00', '18:00:00');

-- New table: seat_bookings (for tracking booked seats per booking)
CREATE TABLE `seat_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `seat_number` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_seat_per_booking` (`booking_id`, `seat_number`)
);

-- New table: payments (for tracking payments with amounts)
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
  `status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);

-- Indexes for performance (as implied in queries)
CREATE INDEX `idx_bookings_trip_id` ON `bookings` (`trip_id`);
CREATE INDEX `idx_bookings_payment_status` ON `bookings` (`payment_status`);
CREATE INDEX `idx_trips_date_cities` ON `trips` (`trip_date`, `pickup_city_id`, `dropoff_city_id`);
CREATE INDEX `idx_seat_bookings_booking_id` ON `seat_bookings` (`booking_id`);
```To update your SQL schema and PHP code to incorporate amounts and support the functionality described (selecting date, vehicle, and number of seats, then showing available times and vehicles), I'll help you modify the database schema and PHP files. The goal is to add pricing to the system, integrate it with the booking process, and ensure the index page allows users to select a number of seats and see available trips with times and vehicles.

### Step 1: Update the SQL Schema

We need to add a `price` column to the `vehicle_types` table to store the cost per seat for each vehicle type and modify the `bookings` table to include `total_amount` and `payment_status`. Additionally, we'll create a `trips` table to store available trips with specific times and associate them with vehicles and prices. We'll also create a `seat_bookings` table to track individual seat selections and a `payments` table for payment records.

Here’s the updated SQL schema:

```sql
-- Cities table (unchanged)
CREATE TABLE `cities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `cities` (`name`) VALUES
('Abuja'),
('Jos');

-- Vehicle_types table (added price column)
CREATE TABLE `vehicle_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(255) NOT NULL,
  `capacity` INT(11) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `vehicle_types` (`type`, `capacity`, `price`) VALUES
('12 Seater', 12, 5000.00),
('5 Seater', 5, 7000.00);

-- Users table (unchanged)
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL,
  PRIMARY KEY (`id`)
);

-- Vehicles table (to associate vehicles with vehicle types)
CREATE TABLE `vehicles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type_id` INT(11) NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `driver_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`)
);

INSERT INTO `vehicles` (`vehicle_type_id`, `vehicle_number`, `driver_name`) VALUES
(1, 'ABC123', 'John Doe'),
(2, 'XYZ789', 'Jane Smith');

-- Time_slots table (to store available departure and arrival times)
CREATE TABLE `time_slots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `time_slots` (`departure_time`, `arrival_time`) VALUES
('08:00:00', '12:00:00'),
('14:00:00', '18:00:00');

-- Trips table (to store available trips with specific dates, times, and vehicles)
CREATE TABLE `trips` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `vehicle_id` INT(11) NOT NULL,
  `time_slot_id` INT(11) NOT NULL,
  `trip_date` DATE NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`),
  FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`)
);

INSERT INTO `trips` (`pickup_city_id`, `dropoff_city_id`, `vehicle_id`, `time_slot_id`, `trip_date`) VALUES
(1, 2, 1, 1, '2025-09-20'),
(1, 2, 2, 2, '2025-09-20');

-- Bookings table (updated with total_amount and payment_status)
CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `pnr` VARCHAR(20) NOT NULL UNIQUE,
  `passenger_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `vehicle_type_id` INT(11) NOT NULL,
  `trip_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `departure_date` DATE NOT NULL,
  `status` ENUM('pending', 'confirmed', 'canceled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`),
  FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`)
);

-- Seat_bookings table (to track selected seats for each booking)
CREATE TABLE `seat_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `seat_number` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);

-- Payments table (to track payment details)
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_reference` VARCHAR(50) NOT NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);