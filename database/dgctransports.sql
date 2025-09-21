-- Complete Fresh Database Schema for DGC Transports
-- Drop existing database and create new one
DROP DATABASE IF EXISTS `dgctransports`;
CREATE DATABASE `dgctransports`;
USE `dgctransports`;

-- 1. Cities table
CREATE TABLE `cities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);

-- 2. Vehicle_types table
CREATE TABLE `vehicle_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(255) NOT NULL,
  `capacity` INT(11) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`)
);

-- 3. Users table
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL,
  PRIMARY KEY (`id`)
);

-- 4. Time_slots table
CREATE TABLE `time_slots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  PRIMARY KEY (`id`)
);

-- 5. Vehicles table
CREATE TABLE `vehicles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type_id` INT(11) NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `driver_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`)
);

-- 6. Trips table
CREATE TABLE `trips` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `vehicle_type_id` INT(11) NOT NULL,
  `vehicle_id` INT(11) DEFAULT NULL,
  `time_slot_id` INT(11) NOT NULL,
  `trip_date` DATE NOT NULL,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME DEFAULT NULL,
  `vehicle_number` VARCHAR(50) DEFAULT NULL,
  `driver_name` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `booked_seats` INT(11) DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`),
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`)
);

-- 7. Bookings table
CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `pnr` VARCHAR(20) NOT NULL UNIQUE,
  `passenger_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `emergency_contact` VARCHAR(255) DEFAULT NULL,
  `special_requests` TEXT DEFAULT NULL,
  `pickup_city_id` INT(11) NOT NULL,
  `dropoff_city_id` INT(11) NOT NULL,
  `vehicle_type_id` INT(11) NOT NULL,
  `trip_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
  `departure_date` DATE NOT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`),
  FOREIGN KEY (`trip_id`) REFERENCES `trips`(`id`)
);

-- 8. Seat_bookings table
CREATE TABLE `seat_bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `seat_number` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);

-- 9. Payments table
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_reference` VARCHAR(50) NOT NULL,
  `gateway_reference` VARCHAR(100) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `gateway_response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO `cities` (`name`) VALUES
('Abuja'),
('Jos'),
('Lagos'),
('Kano'),
('Port Harcourt');

INSERT INTO `vehicle_types` (`type`, `capacity`, `price`) VALUES
('12 Seater', 12, 5000.00),
('5 Seater', 5, 7000.00),
('18 Seater', 18, 4000.00);

INSERT INTO `time_slots` (`departure_time`, `arrival_time`) VALUES
('06:00:00', '10:00:00'),
('08:00:00', '12:00:00'),
('10:00:00', '14:00:00'),
('14:00:00', '18:00:00'),
('16:00:00', '20:00:00');

INSERT INTO `vehicles` (`vehicle_type_id`, `vehicle_number`, `driver_name`) VALUES
(1, 'DGC001', 'John Doe'),
(1, 'DGC002', 'Jane Smith'),
(2, 'DGC003', 'Mike Johnson'),
(2, 'DGC004', 'Sarah Wilson'),
(3, 'DGC005', 'David Brown');

INSERT INTO `trips` (`pickup_city_id`, `dropoff_city_id`, `vehicle_type_id`, `vehicle_id`, `time_slot_id`, `trip_date`, `departure_time`, `vehicle_number`, `driver_name`, `price`) VALUES
-- Abuja to Jos routes
(1, 2, 1, 1, 1, '2025-09-22', '06:00:00', 'DGC001', 'John Doe', 5000.00),
(1, 2, 1, 2, 2, '2025-09-22', '08:00:00', 'DGC002', 'Jane Smith', 5000.00),
(1, 2, 2, 3, 3, '2025-09-22', '10:00:00', 'DGC003', 'Mike Johnson', 7000.00),
(1, 2, 2, 4, 4, '2025-09-22', '14:00:00', 'DGC004', 'Sarah Wilson', 7000.00),
(1, 2, 3, 5, 5, '2025-09-22', '16:00:00', 'DGC005', 'David Brown', 4000.00),

-- Jos to Abuja routes
(2, 1, 1, 1, 1, '2025-09-23', '06:00:00', 'DGC001', 'John Doe', 5000.00),
(2, 1, 1, 2, 2, '2025-09-23', '08:00:00', 'DGC002', 'Jane Smith', 5000.00),
(2, 1, 2, 3, 3, '2025-09-23', '10:00:00', 'DGC003', 'Mike Johnson', 7000.00),
(2, 1, 2, 4, 4, '2025-09-23', '14:00:00', 'DGC004', 'Sarah Wilson', 7000.00),
(2, 1, 3, 5, 5, '2025-09-23', '16:00:00', 'DGC005', 'David Brown', 4000.00),

-- Additional routes for coming days
(1, 2, 1, 1, 1, '2025-09-24', '06:00:00', 'DGC001', 'John Doe', 5000.00),
(1, 2, 2, 3, 4, '2025-09-24', '14:00:00', 'DGC003', 'Mike Johnson', 7000.00),
(2, 1, 1, 2, 2, '2025-09-24', '08:00:00', 'DGC002', 'Jane Smith', 5000.00),
(2, 1, 3, 5, 5, '2025-09-24', '16:00:00', 'DGC005', 'David Brown', 4000.00);

-- Create an admin user (password: admin123)
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');