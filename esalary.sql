-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.infinityfree.com
-- Generation Time: Feb 25, 2026 at 10:37 PM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hr_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `cert_requests`
--

CREATE TABLE `cert_requests` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `emp_type` enum('A','B') NOT NULL COMMENT 'A=ข้าราชการ, B=พนักงานจ้าง',
  `title` varchar(20) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `id_card` varchar(13) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `join_date` date NOT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `position_allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monthly_remuneration` decimal(10,2) NOT NULL DEFAULT 0.00,
  `living_allowance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_income` decimal(10,2) NOT NULL DEFAULT 0.00,
  `purpose` varchar(100) NOT NULL,
  `purpose_other` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `contract_no` varchar(50) DEFAULT NULL COMMENT 'เฉพาะพนักงานจ้าง',
  `contract_date` date DEFAULT NULL COMMENT 'เฉพาะพนักงานจ้าง',
  `contract_end_date` date DEFAULT NULL COMMENT 'เฉพาะพนักงานจ้าง',
  `user_signature` varchar(255) DEFAULT NULL COMMENT 'พาทไฟล์ลายเซ็นผู้ขอ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cert_requests`
--

INSERT INTO `cert_requests` (`id`, `request_id`, `emp_type`, `title`, `fullname`, `id_card`, `position`, `department`, `join_date`, `salary`, `position_allowance`, `monthly_remuneration`, `living_allowance`, `total_income`, `purpose`, `purpose_other`, `phone`, `contract_no`, `contract_date`, `contract_end_date`, `user_signature`) VALUES
(1, 1, 'A', 'นาย', 'อิมัสมิง มงคล', '1234567890123', 'นักจัดว่าเด็ด', 'หน่วยที่ 1', '2026-02-25', '50000.00', '10000.00', '1000.00', '1000.00', '62000.00', 'กู้แต่งเมีย', NULL, '045814683', '', '0000-00-00', '0000-00-00', 'signatures/XIKD8LZ1.png');

-- --------------------------------------------------------

--
-- Table structure for table `payslip_requests`
--

CREATE TABLE `payslip_requests` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `title` varchar(20) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `req_month` varchar(2) NOT NULL,
  `req_year` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payslip_requests`
--

INSERT INTO `payslip_requests` (`id`, `request_id`, `title`, `fullname`, `position`, `department`, `req_month`, `req_year`) VALUES
(1, 3, 'นาย', 'อิมัสมิง มงคล', 'นักจัดว่าเด็ด', 'หน่วยที่ 1', '01', '2026');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `tracking_code` varchar(8) NOT NULL COMMENT 'รหัสติดตาม 8 หลัก Random',
  `request_type` enum('cert','payslip','both') NOT NULL,
  `status` enum('pending','verified','approved','rejected') NOT NULL DEFAULT 'pending',
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reject_reason` text DEFAULT NULL,
  `current_approver_id` int(11) DEFAULT NULL COMMENT 'ส่งต่อให้ใครอนุมัติ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `tracking_code`, `request_type`, `status`, `email`, `created_at`, `reject_reason`, `current_approver_id`) VALUES
(1, 'XIKD8LZ1', 'cert', 'approved', 'klunksskpao@gmail.com', '2026-02-25 07:43:20', NULL, 4),
(3, 'X10N5AJO', 'payslip', 'pending', 'klunksskpao@gmail.com', '2026-02-25 08:35:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `salary_data`
--

CREATE TABLE `salary_data` (
  `id` int(11) NOT NULL,
  `id_card` varchar(13) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_1` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'รายจ่าย 1',
  `expense_2` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'รายจ่าย 2',
  `expense_3` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'รายจ่าย 3',
  `note` text DEFAULT NULL COMMENT 'หมายเหตุ',
  `data_month` varchar(2) NOT NULL COMMENT 'เดือนของข้อมูล (01-12)',
  `data_year` varchar(4) NOT NULL COMMENT 'ปีของข้อมูล (เช่น 2024)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'ควรเก็บเป็น Hash (เช่น password_hash ใน PHP)',
  `role` enum('admin','finance','approver') NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `signature_image` varchar(255) DEFAULT NULL COMMENT 'พาทเก็บไฟล์รูปภาพลายเซ็น'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`, `position`, `signature_image`) VALUES
(1, 'admin', '123456', 'admin', 'ผู้ดูแลระบบสูงสุด', NULL, NULL),
(2, 'finance1', '123456', 'finance', 'เจ้าหน้าที่การเงิน 1', 'นักวิชาการเงินและบัญชีชำนาญการ', ''),
(3, 'approver1', '123456', 'approver', 'ผู้อนุมัติ 1 (ผอ.กอง)', NULL, NULL),
(4, 'approver2', '123456', 'approver', 'ผู้อนุมัติ 2 (รอง ผอ.)', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cert_requests`
--
ALTER TABLE `cert_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cert_request` (`request_id`);

--
-- Indexes for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payslip_request` (`request_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_code` (`tracking_code`),
  ADD KEY `fk_current_approver` (`current_approver_id`);

--
-- Indexes for table `salary_data`
--
ALTER TABLE `salary_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_card_period` (`id_card`,`data_month`,`data_year`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cert_requests`
--
ALTER TABLE `cert_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `salary_data`
--
ALTER TABLE `salary_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cert_requests`
--
ALTER TABLE `cert_requests`
  ADD CONSTRAINT `fk_cert_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payslip_requests`
--
ALTER TABLE `payslip_requests`
  ADD CONSTRAINT `fk_payslip_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_current_approver` FOREIGN KEY (`current_approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
ALTER TABLE `payslip_requests` ADD `slip_id_card` VARCHAR(13) NULL AFTER `fullname`;

ALTER TABLE `requests` 
ADD `verified_by` INT NULL COMMENT 'ID การเงินที่ตรวจสอบ',
ADD `approved_by` INT NULL COMMENT 'ID ผู้อนุมัติ';