-- Create bookings table for BTBMS
-- Run this in phpMyAdmin SQL tab for database `btbms`

CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` INT NOT NULL AUTO_INCREMENT,
  `trip_id` VARCHAR(10) NOT NULL,
  `seat_label` VARCHAR(10) NOT NULL,
  `passenger_name` VARCHAR(100) DEFAULT NULL,
  `booked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `trip_id_idx` (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Example insert:
-- INSERT INTO `bookings` (trip_id, seat_label, passenger_name) VALUES ('TR1','A1','Test Passenger');
