-- Complete Updated Database Schema for DGC Transports
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
  `phone` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  `affiliate_id` VARCHAR(50) UNIQUE,
  `credits` DECIMAL(10,2) DEFAULT 0.00,
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
  `recurrence_days` VARCHAR(255),
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
  `trip_id` INT DEFAULT NULL,
  `trip_date` DATE NOT NULL,
  `seat_number` INT NOT NULL,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
  `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seat` (`template_id`, `trip_date`, `seat_number`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`template_id`) REFERENCES `trip_templates`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`trip_id`) REFERENCES `trip_instances`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
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

-- Create the new password_resets table
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- 11. Email logs table
CREATE TABLE `email_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `to_email` VARCHAR(255) NOT NULL,
  `email_type` VARCHAR(100) NOT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `message` TEXT,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

-- =======================================
-- Insert Sample Data
-- =======================================

-- Cities (only Abuja and Jos)
INSERT INTO `cities` (`name`) VALUES
('Abuja'),
('Jos');

-- Vehicle Types (keep 5-seater car for simplicity)
INSERT INTO `vehicle_types` (`type`, `capacity`) VALUES
('5 Seater Car', 5);

-- Time Slots (4-hour trips)
INSERT INTO `time_slots` (`departure_time`, `arrival_time`) VALUES
('07:00:00', '11:00:00'),
('09:00:00', '13:00:00'),
('12:00:00', '16:00:00'),
('13:30:00', '17:30:00');

-- Vehicles (assign to type_id = 1)
INSERT INTO `vehicles` (`vehicle_type_id`, `vehicle_number`, `driver_name`) VALUES
(1, 'CAR001', 'John Doe'),
(1, 'CAR002', 'Jane Smith');

-- Trip Templates (Abuja → Jos and Jos → Abuja for each time slot)
-- Trip Templates (Abuja → Jos and Jos → Abuja for each time slot, yearly recurrence)
INSERT INTO `trip_templates` 
(`pickup_city_id`, `dropoff_city_id`, `vehicle_type_id`, `vehicle_id`, `time_slot_id`, `price`, `recurrence_type`, `recurrence_days`, `start_date`, `end_date`, `status`) 
VALUES
-- Abuja to Jos
(1, 2, 1, 1, 1, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
(1, 2, 1, 1, 2, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
(1, 2, 1, 2, 3, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
(1, 2, 1, 2, 4, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
-- Jos to Abuja
(2, 1, 1, 1, 1, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
(2, 1, 1, 1, 2, 5000.00, 'year',  NULL, '2025-09-22', '2026-09-22', 'active'),
(2, 1, 1, 2, 3, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active'),
(2, 1, 1, 2, 4, 5000.00, 'year', NULL, '2025-09-22', '2026-09-22', 'active');


-- Trip Instances (create sample trips for today)
INSERT INTO `trip_instances` (`template_id`, `trip_date`, `booked_seats`, `status`) VALUES
(1, '2025-09-22', 1, 'active'),
(2, '2025-09-22', 2, 'active'),
(5, '2025-09-22', 0, 'active'),
(6, '2025-09-22', 3, 'active');


-- Adding columns to users table for affiliate program


-- Creating affiliates table for tracking referrals
CREATE TABLE affiliates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id),
    FOREIGN KEY (referred_user_id) REFERENCES users(id)
);


CREATE TABLE referral_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credits DECIMAL(10,2) DEFAULT 2000.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default referral credit value
INSERT INTO referral_settings (credits) VALUES (2000.00);

ALTER TABLE bookings ADD free_booking_used TINYINT DEFAULT 0;