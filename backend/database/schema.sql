-- Internship Management Platform Database Schema
-- Day 2: Create DB (aligned with IMP specification document)

CREATE DATABASE IF NOT EXISTS internship_management
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE internship_management;

-- ─────────────────────────────────────────
-- USERS (base for all roles)
-- Roles: student, hr, coordinator, mentor, hod
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'hr', 'coordinator', 'mentor', 'hod') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- STUDENT PROFILES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    university VARCHAR(255),
    course VARCHAR(255),
    year_of_study INT,
    resume_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- APPLICATIONS (Section 5.1)
-- Statuses: applied, test_completed, hr_round, selected, rejected
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    status ENUM('applied', 'test_completed', 'hr_round', 'selected', 'rejected') DEFAULT 'applied',
    cover_letter TEXT,
    resume_path VARCHAR(500),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- ASSESSMENTS / ONLINE TESTS (Section 5.1)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    score DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- PROJECTS (Section 5.4)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    technology_stack VARCHAR(500),
    difficulty_level ENUM('beginner', 'intermediate', 'advanced'),
    duration_months TINYINT COMMENT '1, 2, or 3 months',
    project_type ENUM('web_development', 'mobile_app', 'backend', 'ui_ux', 'graphic_design', 'product_design', 'seo', 'social_media', 'content_marketing'),
    mentor_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- PROJECT ASSIGNMENTS (Day 19)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS project_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    student_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- PHASES P1-P6 (Section 6)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS phases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    phase_number TINYINT NOT NULL COMMENT '1=Learning, 2=Docs, 3=Design, 4=Dev, 5=Testing, 6=Deployment',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- PROGRESS TRACKING (Day 21 / Section 9)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    phase_id INT NOT NULL,
    completion_percent TINYINT DEFAULT 0,
    validated_by INT COMMENT 'mentor/guide who validated',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (phase_id) REFERENCES phases(id) ON DELETE CASCADE,
    FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- DAILY LOGS (Section 5.3)
-- Fields: tasks completed, time spent, issues faced, next day plan
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    log_date DATE NOT NULL,
    tasks_completed TEXT NOT NULL,
    time_spent DECIMAL(4,2) COMMENT 'hours',
    issues_faced TEXT,
    next_day_plan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_log_date (log_date),
    INDEX idx_student_date (student_id, log_date)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- FEEDBACK (Section 9 / Day 23)
-- From mentor/guide to student
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_student_id INT NOT NULL,
    phase_id INT,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (phase_id) REFERENCES phases(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- EVALUATIONS (Section 11 / Day 25)
-- Criteria: task completion, quality, consistency, communication, delivery
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    task_completion DECIMAL(4,2),
    output_quality DECIMAL(4,2),
    consistency DECIMAL(4,2),
    communication DECIMAL(4,2),
    final_delivery DECIMAL(4,2),
    total_score DECIMAL(5,2),
    comments TEXT,
    evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- NOTIFICATIONS (Section 10 / Day 24)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deadline', 'phase_complete', 'missed_update', 'reminder', 'general') DEFAULT 'general',
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_read (is_read),
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- CERTIFICATES (Day 31-34)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    issued_by INT NOT NULL,
    evaluation_id INT,
    certificate_path VARCHAR(500),
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

