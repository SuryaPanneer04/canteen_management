-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2026 at 01:07 PM
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
-- Table structure for table `canteen_food_serving`
--

CREATE TABLE `canteen_food_serving` (
  `id` int(10) UNSIGNED NOT NULL,
  `transfer_id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `serving_date` date NOT NULL,
  `received_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `served_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pax` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `canteen_wastage`
--

CREATE TABLE `canteen_wastage` (
  `id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `serving_id` int(10) UNSIGNED DEFAULT NULL,
  `wastage_date` date NOT NULL,
  `wastage_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(150) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_items`
--

CREATE TABLE `food_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `food_code` varchar(50) NOT NULL,
  `food_name` varchar(150) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'PLATE',
  `status` enum('Enable','Disabled') NOT NULL DEFAULT 'Enable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_items`
--

INSERT INTO `food_items` (`id`, `food_code`, `food_name`, `unit`, `status`, `created_at`) VALUES
(1, 'FOOD001', 'Rice Meals', 'PLATE', 'Enable', '2026-09-07 10:49:37'),
(2, 'FOOD002', 'Sambar Rice', 'PLATE', 'Enable', '2026-09-07 10:49:37'),
(3, 'FOOD003', 'Curd Rice', 'PLATE', 'Enable', '2026-09-07 10:49:37'),
(4, 'FOOD004', 'Chapati', 'PLATE', 'Enable', '2026-09-07 10:49:37');

-- --------------------------------------------------------

--
-- Table structure for table `food_preparations`
--

CREATE TABLE `food_preparations` (
  `id` int(10) UNSIGNED NOT NULL,
  `preparation_no` varchar(60) NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `preparation_date` date NOT NULL,
  `prepared_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Prepared','Sent to Canteen','Completed') NOT NULL DEFAULT 'Prepared',
  `prepared_by` int(10) UNSIGNED NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_transfers`
--

CREATE TABLE `food_transfers` (
  `id` int(10) UNSIGNED NOT NULL,
  `transfer_no` varchar(60) NOT NULL,
  `preparation_id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transfer_date` date NOT NULL,
  `sent_by` int(10) UNSIGNED NOT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Sent','Received') NOT NULL DEFAULT 'Sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `received_at` datetime DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_requests`
--

CREATE TABLE `kitchen_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_no` varchar(60) NOT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('Draft','Submitted','Chef Approved','Sent to Store','Partially Issued','Completed','Rejected') NOT NULL DEFAULT 'Submitted',
  `cook_remarks` text DEFAULT NULL,
  `chef_remarks` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `sent_to_store_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kitchen_requests`
--

INSERT INTO `kitchen_requests` (`id`, `request_no`, `requested_by`, `request_date`, `status`, `cook_remarks`, `chef_remarks`, `approved_by`, `approved_at`, `sent_to_store_at`, `created_at`, `updated_at`) VALUES
(1, 'KR-20260907-0001', 3, '2026-09-07', 'Submitted', 'testing', NULL, NULL, NULL, NULL, '2026-09-07 11:01:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_request_items`
--

CREATE TABLE `kitchen_request_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `requested_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `approved_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `issued_qty` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kitchen_request_items`
--

INSERT INTO `kitchen_request_items` (`id`, `request_id`, `material_id`, `requested_qty`, `approved_qty`, `issued_qty`, `remarks`) VALUES
(1, 1, 2, 20.00, 0.00, 0.00, NULL);

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
(1, 'MAT001', 'Rice', 'Grains', 'KG', 50.00, 10.00, 'Enable', '2026-09-03 13:56:45', '2026-09-08 12:28:21'),
(2, 'MAT002', 'Wheat', 'Grains', 'KG', 30.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(3, 'MAT003', 'Cooking Oil', 'Oil', 'LTR', 20.00, 1015.00, 'Enable', '2026-09-03 13:56:45', '2026-09-05 11:55:24'),
(4, 'MAT004', 'Salt', 'Grocery', 'KG', 10.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(5, 'MAT005', 'Vegetables', 'Vegetables', 'KG', 30.00, 0.00, 'Enable', '2026-09-03 13:56:45', NULL),
(6, 'MAT006', 'Sugar', 'Grocery', 'KG', 5.00, 9.00, 'Enable', '2026-09-03 15:01:48', '2026-09-03 15:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `material_categories`
--

CREATE TABLE `material_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `status` enum('Enable','Disabled') NOT NULL DEFAULT 'Enable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `material_categories`
--

INSERT INTO `material_categories` (`id`, `category_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Grains', 'Enable', '2026-09-08 15:06:40', NULL),
(2, 'Grocery', 'Enable', '2026-09-08 15:06:40', NULL),
(3, 'Oil', 'Enable', '2026-09-08 15:06:40', NULL),
(4, 'Vegetables', 'Enable', '2026-09-08 15:06:40', NULL),
(5, 'Dairy', 'Enable', '2026-09-08 15:06:40', NULL),
(6, 'Spices', 'Enable', '2026-09-08 15:06:40', NULL),
(7, 'Beverages', 'Enable', '2026-09-08 15:06:40', NULL),
(8, 'Cleaning', 'Enable', '2026-09-08 15:06:40', NULL),
(9, 'Other', 'Enable', '2026-09-08 15:06:40', NULL);

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
(6, 'PR-20260908121410-483', 5, '2026-09-08', 'Pending', 'Purchase request created from Low Stock in Material Master.', '2026-09-08 15:44:10');

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
(4, 6, 5, 30.00, 0.00),
(5, 6, 4, 10.00, 0.00);

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
(4, 3, 'PURCHASE', 1000.00, NULL, NULL, '', NULL, '2026-09-05 11:55:24'),
(5, 1, 'PURCHASE', 10.00, NULL, NULL, 'testing', NULL, '2026-09-08 12:28:21');

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
-- Indexes for table `canteen_food_serving`
--
ALTER TABLE `canteen_food_serving`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_serving_transfer` (`transfer_id`),
  ADD KEY `idx_cfs_food_date` (`food_id`,`serving_date`),
  ADD KEY `fk_cfs_user` (`recorded_by`);

--
-- Indexes for table `canteen_wastage`
--
ALTER TABLE `canteen_wastage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cw_food_date` (`food_id`,`wastage_date`),
  ADD KEY `fk_cw_serving` (`serving_id`),
  ADD KEY `fk_cw_user` (`recorded_by`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_food_code` (`food_code`);

--
-- Indexes for table `food_preparations`
--
ALTER TABLE `food_preparations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_preparation_no` (`preparation_no`),
  ADD KEY `idx_fp_food` (`food_id`),
  ADD KEY `fk_fp_user` (`prepared_by`);

--
-- Indexes for table `food_transfers`
--
ALTER TABLE `food_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_transfer_no` (`transfer_no`),
  ADD KEY `idx_ft_preparation` (`preparation_id`),
  ADD KEY `idx_ft_food` (`food_id`),
  ADD KEY `fk_ft_sent_by` (`sent_by`),
  ADD KEY `fk_ft_received_by` (`received_by`);

--
-- Indexes for table `kitchen_requests`
--
ALTER TABLE `kitchen_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kitchen_request_no` (`request_no`),
  ADD KEY `idx_kr_requested_by` (`requested_by`),
  ADD KEY `idx_kr_status` (`status`),
  ADD KEY `fk_kr_approved_by` (`approved_by`);

--
-- Indexes for table `kitchen_request_items`
--
ALTER TABLE `kitchen_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kri_request` (`request_id`),
  ADD KEY `idx_kri_material` (`material_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `material_code` (`material_code`);

--
-- Indexes for table `material_categories`
--
ALTER TABLE `material_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_material_category_name` (`category_name`);

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
-- AUTO_INCREMENT for table `canteen_food_serving`
--
ALTER TABLE `canteen_food_serving`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `canteen_wastage`
--
ALTER TABLE `canteen_wastage`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `food_preparations`
--
ALTER TABLE `food_preparations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_transfers`
--
ALTER TABLE `food_transfers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kitchen_requests`
--
ALTER TABLE `kitchen_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kitchen_request_items`
--
ALTER TABLE `kitchen_request_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `material_categories`
--
ALTER TABLE `material_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Constraints for table `canteen_food_serving`
--
ALTER TABLE `canteen_food_serving`
  ADD CONSTRAINT `fk_cfs_food` FOREIGN KEY (`food_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_cfs_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `food_transfers` (`id`),
  ADD CONSTRAINT `fk_cfs_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `canteen_wastage`
--
ALTER TABLE `canteen_wastage`
  ADD CONSTRAINT `fk_cw_food` FOREIGN KEY (`food_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_cw_serving` FOREIGN KEY (`serving_id`) REFERENCES `canteen_food_serving` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cw_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `food_preparations`
--
ALTER TABLE `food_preparations`
  ADD CONSTRAINT `fk_fp_food` FOREIGN KEY (`food_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_fp_user` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `food_transfers`
--
ALTER TABLE `food_transfers`
  ADD CONSTRAINT `fk_ft_food` FOREIGN KEY (`food_id`) REFERENCES `food_items` (`id`),
  ADD CONSTRAINT `fk_ft_preparation` FOREIGN KEY (`preparation_id`) REFERENCES `food_preparations` (`id`),
  ADD CONSTRAINT `fk_ft_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ft_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `kitchen_requests`
--
ALTER TABLE `kitchen_requests`
  ADD CONSTRAINT `fk_kr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kr_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `kitchen_request_items`
--
ALTER TABLE `kitchen_request_items`
  ADD CONSTRAINT `fk_kri_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `fk_kri_request` FOREIGN KEY (`request_id`) REFERENCES `kitchen_requests` (`id`) ON DELETE CASCADE;

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
