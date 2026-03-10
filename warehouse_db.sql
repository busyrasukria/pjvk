-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 31, 2025 at 03:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `warehouse_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `manpower`
--

CREATE TABLE `manpower` (
  `id` int(11) NOT NULL,
  `emp_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `img_path` varchar(255) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

--
-- Table structure for table `master`
--

CREATE TABLE `master` (
  `id` int(11) NOT NULL,
  `part_no_B` varchar(50) DEFAULT NULL,
  `erp_code_B` varchar(50) DEFAULT NULL,
  `part_no_FG` varchar(50) DEFAULT NULL,
  `erp_code_FG` varchar(50) DEFAULT NULL,
  `part_description` varchar(255) DEFAULT NULL,
  `stock_type` varchar(20) DEFAULT NULL,
  `model` varchar(20) DEFAULT NULL,
  `line` varchar(20) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `std_packing` int(11) DEFAULT NULL,
  `img_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



--
-- Table structure for table `transfer_tickets`
--

CREATE TABLE `transfer_tickets` (
  `ticket_id` int(11) NOT NULL,
  `unique_no` varchar(8) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `shift` enum('Day','Night') GENERATED ALWAYS AS (case when hour(`created_at`) between 8 and 19 then 'Day' else 'Night' end) STORED,
  `erp_code_FG` varchar(50) NOT NULL,
  `part_no_FG` varchar(50) DEFAULT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `prod_area` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `released_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Triggers `transfer_tickets`
--
DELIMITER $$
CREATE TRIGGER `before_insert_transfer_tickets` BEFORE INSERT ON `transfer_tickets` FOR EACH ROW BEGIN
    -- Only generate a new ID if the application did not provide one (i.e., it's NULL)
    IF NEW.unique_no IS NULL THEN
        -- Generate zero-padded unique number (8 digits)
        SET NEW.unique_no = LPAD(
            (SELECT IFNULL(MAX(ticket_id), 0) + 1 FROM transfer_tickets),
            8, '0'
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_insert_transfer_tickets_conditional` BEFORE INSERT ON `transfer_tickets` FOR EACH ROW BEGIN
    -- Only generate unique_no if PHP didn't provide one
    IF NEW.unique_no IS NULL OR NEW.unique_no = '' THEN
        -- Generate based on the next auto-increment ID
        -- Note: This assumes ticket_id is AUTO_INCREMENT
        SET @next_auto_increment = (
            SELECT AUTO_INCREMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfer_tickets'
        );
        SET NEW.unique_no = LPAD(@next_auto_increment, 8, '0');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_in`
--

CREATE TABLE `warehouse_in` (
  `log_id` int(11) NOT NULL,
  `scan_time` datetime NOT NULL DEFAULT current_timestamp(),
  `transfer_ticket_id` int(11) NOT NULL,
  `unique_no` varchar(8) NOT NULL,
  `prod_date` datetime DEFAULT NULL,
  `released_by` varchar(255) DEFAULT NULL,
  `erp_code_FG` varchar(50) NOT NULL,
  `part_no_FG` varchar(50) DEFAULT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `prod_area` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-
--
-- Table structure for table `warehouse_out_pne`
--

CREATE TABLE `warehouse_out_pne` (
  `log_id` int(11) NOT NULL,
  `scan_time` datetime NOT NULL DEFAULT current_timestamp(),
  `transfer_ticket_id` int(11) NOT NULL,
  `unique_no` varchar(8) NOT NULL,
  `prod_date` datetime DEFAULT NULL,
  `released_by` varchar(255) DEFAULT NULL,
  `erp_code_FG` varchar(50) NOT NULL,
  `part_no_FG` varchar(50) DEFAULT NULL,
  `part_name` varchar(255) DEFAULT NULL,
  `prod_area` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_out_pne`
--
-- Indexes for dumped tables
--

--
-- Indexes for table `manpower`
--
ALTER TABLE `manpower`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_id` (`emp_id`);

--
-- Indexes for table `master`
--
ALTER TABLE `master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transfer_tickets`
--
ALTER TABLE `transfer_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `erp_code` (`erp_code_FG`);

--
-- Indexes for table `warehouse_in`
--
ALTER TABLE `warehouse_in`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `unique_ticket_item_scan` (`transfer_ticket_id`,`erp_code_FG`),
  ADD KEY `idx_unique_no` (`unique_no`),
  ADD KEY `idx_erp_code_FG` (`erp_code_FG`);

--
-- Indexes for table `warehouse_out_pne`
--
ALTER TABLE `warehouse_out_pne`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `unique_ticket_scan_pne` (`transfer_ticket_id`),
  ADD KEY `idx_unique_no_pne` (`unique_no`),
  ADD KEY `idx_erp_code_FG_pne` (`erp_code_FG`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `manpower`
--
ALTER TABLE `manpower`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `master`
--
ALTER TABLE `master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `transfer_tickets`
--
ALTER TABLE `transfer_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=513;

--
-- AUTO_INCREMENT for table `warehouse_in`
--
ALTER TABLE `warehouse_in`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `warehouse_out_pne`
--
ALTER TABLE `warehouse_out_pne`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
