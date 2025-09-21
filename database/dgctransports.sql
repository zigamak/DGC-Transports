-- Complete Updated Database Schema for DGC Transports
-- Drop existing database and create new one
DROP DATABASE IF EXISTS `dgctransports`;
CREATE DATABASE `dgctransports`;
USE `dgctransports`;

-- 1. Cities table
CREATE TABLE `cities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
);

-- 2. Vehicle_types table
CREATE TABLE `vehicle_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(255) NOT NULL,
  `capacity` INT NOT NULL,
  PRIMARY KEY (`id`)
);

-- 3. Users table


CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- 4. Time_slots table
CREATE TABLE `time_slots` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  PRIMARY KEY (`id`)
);

-- 5. Vehicles table
CREATE TABLE `vehicles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `vehicle_type_id` INT NOT NULL,
  `vehicle_number` VARCHAR(50) NOT NULL,
  `driver_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`) ON DELETE RESTRICT
);

-- 6. Trip_templates table
CREATE TABLE `trip_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `pickup_city_id` INT NOT NULL,
  `dropoff_city_id` INT NOT NULL,
  `vehicle_type_id` INT NOT NULL,
  `vehicle_id` INT NOT NULL,
  `time_slot_id` INT NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `recurrence_type` ENUM('day', 'week', 'month', 'year') NOT NULL,
  `recurrence_days` VARCHAR(255), -- Comma-separated days for weekly recurrence (e.g., "Monday,Wednesday,Friday")
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pickup_city_id`) REFERENCES `cities`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`dropoff_city_id`) REFERENCES `cities`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots`(`id`) ON DELETE RESTRICT
);

-- 7. Trip_instances table
CREATE TABLE `trip_instances` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `template_id` INT NOT NULL,
  `trip_date` DATE NOT NULL,
  `booked_seats` INT DEFAULT 0,
  `status` ENUM('active', 'cancelled') DEFAULT 'active',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`template_id`) REFERENCES `trip_templates`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_trip` (`template_id`, `trip_date`)
);

-- 8. Bookings table
CREATE TABLE `bookings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT DEFAULT NULL,
  `pnr` VARCHAR(20) NOT NULL UNIQUE,
  `passenger_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `emergency_contact` VARCHAR(255) DEFAULT NULL,
  `special_requests` TEXT DEFAULT NULL,
  `template_id` INT NOT NULL,
  `trip_date` DATE NOT NULL,
  `seat_number` INT NOT NULL,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`template_id`) REFERENCES `trip_templates`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_seat` (`template_id`, `trip_date`, `seat_number`)
);

-- 9. Payments table
CREATE TABLE `payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `transaction_reference` VARCHAR(50) NOT NULL,
  `gateway_reference` VARCHAR(100) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `gateway_response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
);


CREATE TABLE password_resets (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert sample data
INSERT INTO `cities` (`name`) VALUES
('Abuja'),
('Jos'),
('Lagos'),
('Kano'),
('Port Harcourt');

INSERT INTO `vehicle_types` (`type`, `capacity`) VALUES
('12 Seater Bus', 12),
('5 Seater Car', 5),
('18 Seater Bus', 18);


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

INSERT INTO `trip_templates` (`pickup_city_id`, `dropoff_city_id`, `vehicle_type_id`, `vehicle_id`, `time_slot_id`, `price`, `recurrence_type`, `recurrence_days`, `start_date`, `end_date`, `status`) VALUES
(1, 3, 1, 1, 1, 5000.00, 'week', 'Monday,Wednesday,Friday', '2025-09-01', '2025-12-31', 'active'),
(3, 1, 2, 3, 2, 7000.00, 'month', NULL, '2025-09-01', '2026-08-31', 'active'),
(2, 4, 3, 5, 3, 4000.00, 'day', NULL, '2025-09-21', '2025-09-21', 'active');

INSERT INTO `trip_instances` (`template_id`, `trip_date`, `booked_seats`, `status`) VALUES
(1, '2025-09-22', 2, 'active'),
(2, '2025-10-01', 1, 'active');

