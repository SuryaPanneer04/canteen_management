-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 05, 2026 at 09:00 AM
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
-- Database: `canteen_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_code` varchar(50) DEFAULT NULL,
  `material_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `minimum_stock` decimal(12,2) DEFAULT 0.00,
  `current_stock` decimal(12,2) DEFAULT 0.00,
  `status` enum('Enable','Disabled') DEFAULT 'Enable',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `material_code`, `material_name`, `category`, `unit`, `minimum_stock`, `current_stock`, `status`, `created_at`, `updated_at`) VALUES
(1, 'MAT001', 'Rice', 'Grains', 'KG', 50.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(2, 'MAT002', 'Wheat', 'Grains', 'KG', 30.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(3, 'MAT003', 'Cooking Oil', 'Oil', 'LTR', 20.00, 1015.00, 'Enable', '2026-09-03 13:56:45', '2026-09-05 11:55:24'),
(4, 'MAT004', 'Salt', 'Grocery', 'KG', 10.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(5, 'MAT005', 'Vegetables', 'Vegetables', 'KG', 30.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(6, 'MAT006', 'Sugar', 'Grocery', 'KG', 5.00, 9.00, 'Enable', '2026-09-03 15:01:48', '2026-09-03 15:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_no` varchar(50) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `po_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('Draft','Pending','Approved','Ordered','Received','Cancelled') NOT NULL DEFAULT 'Draft',
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_no`, `request_id`, `supplier_id`, `po_date`, `expected_date`, `status`, `remarks`, `created_by`, `created_at`) VALUES
(1, 'PO-202609-0001', 1, 1, '2026-09-05', '2026-09-07', 'Received', 'for testing', 1, '2026-09-05 10:02:23');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `ordered_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `material_id`, `ordered_qty`, `unit_rate`, `total_amount`) VALUES
(1, 1, 3, 15.00, 150.00, 2250.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_no` varchar(60) NOT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected','Purchased','Completed') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `request_no`, `requested_by`, `request_date`, `status`, `remarks`, `created_at`) VALUES
(1, 'PR-20260903114200-489', 5, '2026-09-03', 'Approved', 'testing purchase', '2026-09-03 15:12:00'),
(2, 'PR-20260905062846-863', 1, '2026-09-05', 'Approved', 'test cooking', '2026-09-05 09:58:46'),
(3, '', 1, '0000-00-00', 'Pending', 'for testing', '2026-09-05 10:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_request_items`
--

CREATE TABLE `purchase_request_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL,
  `approved_qty` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_request_items`
--

INSERT INTO `purchase_request_items` (`id`, `request_id`, `material_id`, `requested_qty`, `approved_qty`) VALUES
(1, 1, 6, 10.00, 0.00),
(2, 2, 3, 15.00, 0.00),
(3, 3, 4, 10.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `status` enum('Enable','Disabled') NOT NULL DEFAULT 'Enable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `status`, `created_at`) VALUES
(1, 'Super Admin', 'Enable', '2026-09-02 13:53:42'),
(2, 'Store', 'Enable', '2026-09-02 13:53:42'),
(3, 'Purchase', 'Enable', '2026-09-02 13:53:42'),
(4, 'Kitchen', 'Enable', '2026-09-02 13:53:42'),
(5, 'Canteen', 'Enable', '2026-09-02 13:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('PURCHASE','ISSUE_KITCHEN','RETURN','ADJUSTMENT') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`id`, `material_id`, `transaction_type`, `quantity`, `reference_no`, `reference_id`, `remarks`, `created_by`, `created_at`) VALUES
(1, 6, 'PURCHASE', 10.00, NULL, NULL, 'testing purchase', NULL, '2026-09-03 15:03:36'),
(2, 6, 'ISSUE_KITCHEN', 1.00, NULL, NULL, 'testing  Issue Material', NULL, '2026-09-03 15:05:18'),
(3, 3, 'PURCHASE', 15.00, 'PO-202609-0001', NULL, 'Purchase Order Receiving', 1, '2026-09-05 10:14:53'),
(4, 3, 'PURCHASE', 1000.00, NULL, NULL, '', NULL, '2026-09-05 11:55:24');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `status` enum('Enable','Disabled') NOT NULL DEFAULT 'Enable',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `gst_number`, `status`, `created_by`, `created_at`) VALUES
(1, 'SUP-001', 'Vathi', 'Vathi', '9876543210', 'vathi@gmail.com', 'No-123, chennai', '1234567890', 'Enable', 1, '2026-09-05 10:01:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `employee_code` varchar(50) DEFAULT NULL,
  `employee_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Enable','Disabled') NOT NULL DEFAULT 'Enable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `employee_code`, `employee_name`, `email`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'ADMIN001', 'System Administrator', 'admin@gmail.com', '$2y$12$bC9023/7vrnQSTlF5MghQu8LzEE1X3qg4Tm2HYnzAMHfxFFMU3jVu', 'Enable', '2026-09-02 13:53:42', '2026-09-02 13:57:45'),
(2, 5, 'EMP001', 'canteen', 'canteen@gmail.com', '$2y$10$I1DQ.dieI1jSYTTdk5jdPOlxkOxUigiSKqN152tZOwWO3Df3VCPrO', 'Enable', '2026-09-02 14:04:11', '2026-09-03 11:10:18'),
(3, 4, 'EMP002', 'kitchen', 'kitchen@gmail.com', '$2y$10$bsd2v3E9J4njDDHR0ASFo.6myubotAjslp7tEItZv.M.tYbUAn3y2', 'Enable', '2026-09-02 15:48:06', '2026-09-03 11:09:47'),
(4, 3, 'EMP003', 'purchase', 'purchase@gmail.com', '$2y$10$5J/5AVqxraMei/ulEBu8xe.Igym4.IOLBFOj1gKsYqEI0eqcvMnx6', 'Enable', '2026-09-03 10:25:51', '2026-09-03 11:09:03'),
(5, 2, 'EMP004', 'store', 'store@gmail.com', '$2y$10$ENPxC/6qQtSK4.wNMFsgAOnxsjrHyaXiUSbZgDuTH3DHZqMNWlNY2', 'Enable', '2026-09-03 10:28:01', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `material_code` (`material_code`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_no` (`request_no`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD CONSTRAINT `purchase_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_request_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `stock_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
