-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 02:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `business_registration_and_compliance_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `business_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `registration_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `cac_registration_number` varchar(100) DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `bank_account_status` varchar(30) NOT NULL DEFAULT 'Not Opened',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`business_id`, `user_id`, `business_name`, `business_type`, `registration_status`, `cac_registration_number`, `registration_date`, `tin`, `bank_account_status`, `bank_name`, `bank_account_number`) VALUES
(2, 2, 'GADAN Technologies Ltd', 'Sole Proprietorship', 'Registered', 'B25786FA', '2026-11-03', '12RFA245', 'Opened', 'GADAN', '0068251209');

-- --------------------------------------------------------

--
-- Table structure for table `business_documents`
--

CREATE TABLE `business_documents` (
  `document_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `document_name` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_reminders`
--

CREATE TABLE `compliance_reminders` (
  `reminder_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `requirement_name` varchar(150) NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_guidance`
--

CREATE TABLE `registration_guidance` (
  `guidance_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `official_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_guidance`
--

INSERT INTO `registration_guidance` (`guidance_id`, `title`, `category`, `content`, `official_url`, `created_at`, `updated_at`) VALUES
(1, 'Business Name Registration with CAC', 'Business Registration', 'A Business Name is a suitable registration option for individuals or small businesses operating as sole proprietorships or partnerships in Nigeria.\r\n\r\nTo register a Business Name, applicants should:\r\n1. Visit the Corporate Affairs Commission (CAC) registration portal.\r\n2. Create an account or log in.\r\n3. Conduct a name availability search.\r\n4. Reserve the proposed business name.\r\n5. Complete the registration application.\r\n6. Provide the required details and supporting documents.\r\n7. Pay the applicable registration fees.\r\n8. Submit the application and monitor its status.\r\nAfter successful registration, download and keep your CAC registration documents safely.', 'https://www.cac.gov.ng/', '2026-11-20 00:45:32', '2026-11-20 00:45:32');

-- --------------------------------------------------------

--
-- Table structure for table `support_resources`
--

CREATE TABLE `support_resources` (
  `resource_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `resource_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_resources`
--

INSERT INTO `support_resources` (`resource_id`, `title`, `description`, `resource_url`) VALUES
(2, 'Corprate Affairs Commision', 'Access information and services relating to business registration in Nigeria.', 'https://www.cac.gov.ng/');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `password_hash`, `role`, `must_change_password`) VALUES
(1, 'Ishaq Salihu Lugga', 'ishaqsalihulugga@gmail.com', '08140106586', '$2y$10$2Udvu5IAczzSkZAsApAVoOyRQqdB1.mcG2x4HWeiWKcshNaGHFqMq', 'admin', 0),
(2, 'Ahmad Jabaka', 'islugga05@gmail.com', '07026574918', '$2y$10$xzaxDf0.mSiO7.Uzlum5fOr1LhjKIhXYBgnncAGnK8Ve.wo2M2axG', 'user', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`business_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `business_documents`
--
ALTER TABLE `business_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `compliance_reminders`
--
ALTER TABLE `compliance_reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `registration_guidance`
--
ALTER TABLE `registration_guidance`
  ADD PRIMARY KEY (`guidance_id`);

--
-- Indexes for table `support_resources`
--
ALTER TABLE `support_resources`
  ADD PRIMARY KEY (`resource_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `business_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `business_documents`
--
ALTER TABLE `business_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `compliance_reminders`
--
ALTER TABLE `compliance_reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `registration_guidance`
--
ALTER TABLE `registration_guidance`
  MODIFY `guidance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_resources`
--
ALTER TABLE `support_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `businesses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `business_documents`
--
ALTER TABLE `business_documents`
  ADD CONSTRAINT `business_documents_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;

--
-- Constraints for table `compliance_reminders`
--
ALTER TABLE `compliance_reminders`
  ADD CONSTRAINT `compliance_reminders_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
