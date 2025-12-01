-- 1. Add investor_id to vehicles table (links vehicle to investor)
ALTER TABLE vehicles 
ADD COLUMN investor_id INT DEFAULT NULL AFTER driver_name,
ADD FOREIGN KEY (investor_id) REFERENCES users(id) ON DELETE SET NULL;

-- 2. Update users role enum to include 'investor'
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'staff', 'customer', 'investor') NOT NULL DEFAULT 'customer';

-- 3. Create vehicle_expenses table for tracking costs
CREATE TABLE vehicle_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    expense_type ENUM('fuel', 'maintenance', 'insurance', 'repairs', 'other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    receipt_path VARCHAR(255) DEFAULT NULL,
    expense_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_vehicle_date (vehicle_id, expense_date)
);

-- 4. Create investor_payouts table (optional - for tracking profit distributions)
CREATE TABLE investor_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investor_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    payout_date DATE NOT NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investor_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    message TEXT NOT NULL,
    status ENUM('pending', 'approved') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(status),
    INDEX(created_at)
);

-- Add round trip support columns to bookings table

ALTER TABLE `bookings` 
ADD COLUMN `is_roundtrip` TINYINT(1) DEFAULT 0 COMMENT 'Is this booking part of a round trip' AFTER `free_booking_used`,
ADD COLUMN `roundtrip_leg` ENUM('outbound', 'return') DEFAULT NULL COMMENT 'Which leg of the round trip (outbound or return)' AFTER `is_roundtrip`,
ADD INDEX `idx_roundtrip` (`is_roundtrip`, `roundtrip_leg`);

-- Optional: Add a column to link paired round trip bookings together
ALTER TABLE `bookings`
ADD COLUMN `roundtrip_group_id` VARCHAR(50) DEFAULT NULL COMMENT 'Groups related round trip bookings together' AFTER `roundtrip_leg`,
ADD INDEX `idx_roundtrip_group` (`roundtrip_group_id`);