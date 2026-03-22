-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2025 at 11:59 AM
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
-- Database: `dhautocare`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(10) NOT NULL,
  `security_question1` varchar(255) DEFAULT NULL,
  `security_answer_hash1` varchar(255) DEFAULT NULL,
  `security_question2` varchar(255) DEFAULT NULL,
  `security_answer_hash2` varchar(255) DEFAULT NULL,
  `security_question3` varchar(255) DEFAULT NULL,
  `security_answer_hash3` varchar(255) DEFAULT NULL,
  `reset_token` char(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`user_id`, `username`, `password`, `role`, `security_question1`, `security_answer_hash1`, `security_question2`, `security_answer_hash2`, `security_question3`, `security_answer_hash3`, `reset_token`, `reset_token_expiry`, `email`) VALUES
(1, 'admin', '$2y$10$YovYE31kMe7.n/JUc3ZKCeJVLkRiFBcwvgFjp7WuvQIqMOBBlGPIe', 'admin', 'What city were you born?', '$2y$10$1m5POdNPUT6G5Ljf/zr59eAvZqJc2ZztRYEyTQv7/pF06NKookigO', 'What is your favorite color?', '$2y$10$RtCwdXKqOZdDOWHeDYau2O0gJ9T0/E.g5gbn.37R8dHKcQu07PhQ6', 'What is your favorite animal?', '$2y$10$2DdoX0/nbZidDm6RUkPax.ieJ6skFVbBv5fj6F/Mko2W8xMrlxCr.', 'f280d008a121d72b304385ccdde207fe51b95a0839f6f613846a38f7c43195f2', '2025-08-08 14:47:31', 'd.goddessofthemoon.09@gmail.com'),
(2, 'employee', '$2y$10$7M2wUoIGCjq.o.ws8YCjvu5mKCUQX8lDMYCg4LRw3PGom/.m/f.4W', 'employee', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD KEY `idx_tbl_users_reset_token` (`reset_token`),
  ADD KEY `idx_tbl_users_reset_token_expiry` (`reset_token_expiry`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
