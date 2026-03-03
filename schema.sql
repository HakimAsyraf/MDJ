-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2026 at 08:37 AM
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
-- Database: `mdj_tracking_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Jabatan Perancangan Pembangunan dan Landskap', 1, '2026-01-27 04:20:35'),
(2, 'OSC', 1, '2026-01-27 04:20:35'),
(3, 'Kejuruteraan', 1, '2026-01-27 04:20:35'),
(4, 'Pentadbiran', 1, '2026-01-27 04:20:35'),
(5, 'Penguatkuasa', 1, '2026-01-27 04:20:35'),
(6, 'Kewangan', 1, '2026-01-27 04:20:35'),
(7, 'Penilaian', 1, '2026-01-27 04:20:35'),
(8, 'IT Department', 1, '2026-01-27 04:20:35');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `document_type` enum('word','pdf') NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL DEFAULT 'Jabatan Perancangan Pembangunan dan Landskap',
  `tahun` int(11) NOT NULL,
  `no_kotak_fail` varchar(10) DEFAULT NULL,
  `no_fail_permohonan` varchar(50) DEFAULT NULL,
  `tarikh_permohonan_masuk` date DEFAULT NULL,
  `lot_pt` text DEFAULT NULL,
  `mukim` varchar(50) DEFAULT NULL,
  `aras` int(11) DEFAULT NULL,
  `kabinet` varchar(10) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_messages`
--

CREATE TABLE `mail_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `sender_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mail_messages`
--

INSERT INTO `mail_messages` (`id`, `sender_id`, `subject`, `body`, `sender_deleted`, `created_at`) VALUES
(1, 1, 'Test', '123', 0, '2026-02-12 16:46:29'),
(2, 1, 'Cubaan', 'Test', 0, '2026-02-12 16:50:01');

-- --------------------------------------------------------

--
-- Table structure for table `mail_recipients`
--

CREATE TABLE `mail_recipients` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mail_recipients`
--

INSERT INTO `mail_recipients` (`id`, `message_id`, `recipient_id`, `is_read`, `read_at`, `is_deleted`, `deleted_at`) VALUES
(2, 2, 3, 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(30) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link_url`, `is_read`, `read_at`, `created_at`) VALUES
(26, 1, '', 'Kemas Kini Surat', 'Jabatan Penguatkuasa kemas kini status: Diproses', '/surat_view.php?id=2', 0, NULL, '2026-02-25 11:00:57'),
(27, 3, '', 'Kemas Kini Surat', 'Jabatan Penguatkuasa kemas kini status: Diproses', '/surat_view.php?id=2', 0, NULL, '2026-02-25 11:00:57');

-- --------------------------------------------------------

--
-- Table structure for table `surat_attachments`
--

CREATE TABLE `surat_attachments` (
  `id` int(11) NOT NULL,
  `surat_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_ext` varchar(20) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_letters`
--

CREATE TABLE `surat_letters` (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_by_name` varchar(150) DEFAULT NULL,
  `tarikh_terima` date DEFAULT NULL,
  `no_fail_kementerian` varchar(120) DEFAULT NULL,
  `tarikh_surat` date DEFAULT NULL,
  `daripada_siapa` varchar(255) DEFAULT NULL,
  `perkara` text DEFAULT NULL,
  `tindakan` enum('Segera','Simpanan') DEFAULT NULL,
  `tempoh_days` int(11) DEFAULT NULL,
  `status` enum('Draf','Dihantar') NOT NULL DEFAULT 'Dihantar',
  `tarikh_jawab` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_menyurat`
--

CREATE TABLE `surat_menyurat` (
  `id` int(11) NOT NULL,
  `tarikh_penerimaan` date DEFAULT NULL,
  `no_fail_kementerian` varchar(120) DEFAULT NULL,
  `tarikh_surat` date DEFAULT NULL,
  `daripada_siapa` varchar(255) DEFAULT NULL,
  `perkara` text DEFAULT NULL,
  `dikirim_kepada` varchar(255) DEFAULT NULL,
  `tindakan` text DEFAULT NULL,
  `tempoh_menjawab` varchar(60) DEFAULT NULL,
  `status` varchar(60) DEFAULT NULL,
  `tarikh_dijawab` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_recipients`
--

CREATE TABLE `surat_recipients` (
  `id` int(11) NOT NULL,
  `surat_id` int(11) NOT NULL,
  `recipient_department` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Diterima',
  `comment` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_recipients_bak`
--

CREATE TABLE `surat_recipients_bak` (
  `id` int(11) NOT NULL,
  `surat_id` int(11) NOT NULL,
  `recipient_department` varchar(150) NOT NULL,
  `status` enum('Diterima','Diproses','Selesai') NOT NULL DEFAULT 'Diterima',
  `comment` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `department` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `department`, `created_at`, `last_login`, `status`) VALUES
(1, 'admin', 'admin@mdj.gov.my', '$2y$10$Zrt5QWdBRelVkWheEFfa/.W3I2PxYwMQG/gc/ijMGeLV..98fKZ6a', 'System Administrator', 'admin', 'IT Department', '2026-01-27 03:35:03', '2026-03-03 07:35:10', 'active'),
(3, 'asyraf', 'Asyraf@mdj.gov.my', '$2y$10$3CZqm8ADWyXDP7OxT21.b.YEs15v..peON7nQLdjlQIu8YkC0MlwW', 'Asyraf', 'staff', 'Pentadbiran', '2026-02-09 07:27:45', '2026-02-25 02:59:04', 'active'),
(4, 'hakim', 'Hakim@mdj.gov.my', '$2y$10$vnCQW4nXlKQtnLd.Q8YXZ.hXxPu3ADe6rqdifkjMx0ExLjh0T5dmm', 'Hakim', 'staff', 'Penguatkuasa', '2026-02-16 04:02:19', '2026-02-25 03:00:50', 'active'),
(5, 'pakcu', 'Pakcu@mdj.gov.my', '$2y$10$jHQo8s72GRzj17UcPtvDTuc8tGomzGRedPEVVz7QsEedTp2NKDaCi', 'Pakcu', 'staff', 'OSC', '2026-02-25 01:24:45', '2026-02-25 01:24:52', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme` enum('light','dark') DEFAULT 'light',
  `notifications` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `theme`, `notifications`) VALUES
(1, 1, 'light', 1),
(351, 3, 'light', 1),
(1196, 4, 'light', 1),
(1730, 5, 'light', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_docs_file` (`file_id`),
  ADD KEY `fk_docs_user` (`uploaded_by`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_files_no_fail` (`no_fail_permohonan`),
  ADD KEY `fk_files_user` (`created_by`);

--
-- Indexes for table `mail_messages`
--
ALTER TABLE `mail_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_sender` (`sender_id`);

--
-- Indexes for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_recipient` (`recipient_id`,`is_read`,`is_deleted`),
  ADD KEY `idx_mail_msg` (`message_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `surat_attachments`
--
ALTER TABLE `surat_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_id` (`surat_id`);

--
-- Indexes for table `surat_letters`
--
ALTER TABLE `surat_letters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `tarikh_terima` (`tarikh_terima`),
  ADD KEY `tarikh_surat` (`tarikh_surat`),
  ADD KEY `no_fail_kementerian` (`no_fail_kementerian`);

--
-- Indexes for table `surat_menyurat`
--
ALTER TABLE `surat_menyurat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_surat_created` (`created_at`),
  ADD KEY `idx_surat_status` (`status`),
  ADD KEY `fk_surat_updated_by` (`updated_by`),
  ADD KEY `idx_surat_created_by` (`created_by`);

--
-- Indexes for table `surat_recipients`
--
ALTER TABLE `surat_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_surat_id` (`surat_id`),
  ADD KEY `idx_recipient_department` (`recipient_department`);

--
-- Indexes for table `surat_recipients_bak`
--
ALTER TABLE `surat_recipients_bak`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_id` (`surat_id`),
  ADD KEY `recipient_department` (`recipient_department`),
  ADD KEY `status` (`status`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `idx_surat_dept` (`surat_id`,`recipient_department`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `mail_messages`
--
ALTER TABLE `mail_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `surat_attachments`
--
ALTER TABLE `surat_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `surat_letters`
--
ALTER TABLE `surat_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `surat_menyurat`
--
ALTER TABLE `surat_menyurat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `surat_recipients`
--
ALTER TABLE `surat_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `surat_recipients_bak`
--
ALTER TABLE `surat_recipients_bak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1919;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_docs_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docs_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `fk_files_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mail_messages`
--
ALTER TABLE `mail_messages`
  ADD CONSTRAINT `fk_mail_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mail_recipients`
--
ALTER TABLE `mail_recipients`
  ADD CONSTRAINT `fk_mail_msg` FOREIGN KEY (`message_id`) REFERENCES `mail_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mail_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `surat_attachments`
--
ALTER TABLE `surat_attachments`
  ADD CONSTRAINT `surat_attachments_ibfk_1` FOREIGN KEY (`surat_id`) REFERENCES `surat_letters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `surat_menyurat`
--
ALTER TABLE `surat_menyurat`
  ADD CONSTRAINT `fk_surat_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_surat_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `surat_recipients`
--
ALTER TABLE `surat_recipients`
  ADD CONSTRAINT `fk_surat_recipients_surat_menyurat` FOREIGN KEY (`surat_id`) REFERENCES `surat_menyurat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `surat_recipients_bak`
--
ALTER TABLE `surat_recipients_bak`
  ADD CONSTRAINT `surat_recipients_bak_ibfk_1` FOREIGN KEY (`surat_id`) REFERENCES `surat_menyurat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
