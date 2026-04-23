-- Internship Management Platform Database Schema
-- Day 1: Initial setup

CREATE DATABASE IF NOT EXISTS internship_management 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE internship_management;

-- Users table (base for all roles)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'hr', 'coordinator', 'mentor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;
