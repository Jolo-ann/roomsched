-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
-- Host: 127.0.0.1
-- Generation Time: May 18, 2026 at 11:30 AM
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
-- Database: `room-scheduler`
--

-- --------------------------------------------------------
-- 1. LOOKUP TABLES
-- --------------------------------------------------------

CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admins` (`id`, `username`, `password`) VALUES (1, 'admin', '12345');

-- Buildings
CREATE TABLE `buildings` (
  `building_id` int(11) NOT NULL AUTO_INCREMENT,
  `building_name` varchar(50) NOT NULL,
  PRIMARY KEY (`building_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `buildings` (`building_id`, `building_name`) VALUES 
(1, 'BAN'), 
(2, 'MAC');

-- Sections
CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_name` varchar(50) NOT NULL,
  PRIMARY KEY (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sections` (`section_id`, `section_name`) VALUES
(1, 'BSCS-1A'),
(2, 'BSCS-2A'),
(3, 'BSIT-1B'),
(4, 'BSCE-2'),
(5, 'BSLMS-3');

-- Courses
CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `courses` (`id`, `course_name`) VALUES
(1, 'COMPUTER SCIENCE'),
(2, 'INFORMATION TECHNOLOGY'),
(3, 'COMPUTER ENGINEERING'),
(4, 'ELECTRICAL ENGINEERING'),
(5, 'CIVIL ENGINEERING');

-- Subjects
CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `subjects` (`subject_id`, `subject_name`) VALUES
(1, 'MULTIMEDIA'),
(2, 'DATA STRUCTURES');

-- Class Days
CREATE TABLE `class_days` (
  `day_id` int(11) NOT NULL AUTO_INCREMENT,
  `day_name` varchar(50) NOT NULL,
  PRIMARY KEY (`day_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `class_days` (`day_id`, `day_name`) VALUES
(1, 'MWF'),
(2, 'TTH'),
(3, 'MW'),
(4, 'SATURDAY');

-- Class Times (String Slots for Display)
CREATE TABLE `class_times` (
  `time_id` int(11) NOT NULL AUTO_INCREMENT,
  `time_slot` varchar(50) NOT NULL,
  PRIMARY KEY (`time_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `class_times` (`time_id`, `time_slot`) VALUES
(1, '10:00 AM - 11:30 AM'),
(2, '1:00 PM - 2:30 PM');

-- Instructors
CREATE TABLE `instructors` (
  `instructor_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`instructor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `instructors` (`instructor_id`, `name`) VALUES
(1, 'ROWELL CASIL'),
(2, 'RYAN BABA'),
(3, 'MARC KEVIN BITUEN'),
(4, 'DONALD DUCK');

-- Rooms
CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(50) NOT NULL,
  PRIMARY KEY (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rooms` (`room_id`, `room_name`) VALUES
(1, '204'),
(2, 'MEGA-1'),
(3, '202');

-- --------------------------------------------------------
-- 2. MAIN BOOKINGS TABLE (STORES IDs ONLY)
-- --------------------------------------------------------

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `day_id` int(11) DEFAULT NULL,
  `time_id` int(11) DEFAULT NULL,
  `t_start` time DEFAULT NULL,
  `t_end` time DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  
  -- Structural Foreign Key Links
  CONSTRAINT `fk_b_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`building_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_days` FOREIGN KEY (`day_id`) REFERENCES `class_days` (`day_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_time` FOREIGN KEY (`time_id`) REFERENCES `class_times` (`time_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`instructor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_b_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping Data into Bookings (Strictly relational IDs)
INSERT INTO `bookings` (`id`, `building_id`, `section_id`, `course_id`, `subject_id`, `day_id`, `time_id`, `t_start`, `t_end`, `instructor_id`, `room_id`) VALUES
(13, 1, 2, 3, 1, 1, 1, '10:00:00', '11:30:00', 3, 1),
(14, 2, 1, 1, 2, 2, 2, '13:00:00', '14:30:00', 1, 2),
(15, 1, 4, 5, 1, 3, 2, '13:00:00', '14:30:00', 2, 3);

-- --------------------------------------------------------
-- 3. USERS TABLE
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `student_id`, `password`) VALUES
(9, '25-001', '12345'),
(10, '25-002', '678910');

COMMIT;