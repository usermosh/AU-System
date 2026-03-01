-- ============================================================
-- Arellano University Digital Clearance & Document Request
-- Database Schema v1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS au_clearance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE au_clearance_db;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','department','registrar','admin') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- Table: students
-- ============================================================
CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    student_number VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60),
    last_name VARCHAR(60) NOT NULL,
    course VARCHAR(100) NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    section VARCHAR(20),
    contact_number VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_number (student_number)
) ENGINE=InnoDB;

-- ============================================================
-- Table: departments
-- ============================================================
CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (department_code)
) ENGINE=InnoDB;

-- Insert default departments
INSERT INTO departments (department_name, department_code, description) VALUES
('Registrar Office', 'REG', 'Official records and enrollment'),
('Library', 'LIB', 'Library clearance and book returns'),
('Finance / Cashier', 'FIN', 'Financial obligations and fees'),
('Student Affairs', 'SAO', 'Student discipline and co-curricular'),
('Laboratory', 'LAB', 'Laboratory equipment and fees'),
('Guidance Center', 'GC', 'Guidance counseling clearance'),
('College Dean', 'DEAN', 'Academic dean clearance'),
('Property Custodian', 'PROP', 'University property accountability');

-- ============================================================
-- Table: clearances
-- ============================================================
CREATE TABLE clearances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester ENUM('1st','2nd','Summer') NOT NULL,
    clearance_type ENUM('graduation','regular') NOT NULL DEFAULT 'regular',
    overall_status ENUM('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_status (overall_status),
    UNIQUE KEY unique_clearance (student_id, school_year, semester, clearance_type)
) ENGINE=InnoDB;

-- ============================================================
-- Table: clearance_status (per department)
-- ============================================================
CREATE TABLE clearance_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clearance_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    status ENUM('pending','cleared','deficiency') NOT NULL DEFAULT 'pending',
    remarks TEXT,
    reviewed_by INT UNSIGNED,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (clearance_id) REFERENCES clearances(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_dept_clearance (clearance_id, department_id),
    INDEX idx_clearance (clearance_id),
    INDEX idx_dept (department_id)
) ENGINE=InnoDB;

-- ============================================================
-- Table: document_requests
-- ============================================================
CREATE TABLE document_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    document_type ENUM('TOR','Diploma','Certificate of Enrollment','Good Moral','Honorable Dismissal','Transfer Credentials','Authentication') NOT NULL,
    copies INT UNSIGNED NOT NULL DEFAULT 1,
    purpose TEXT,
    status ENUM('pending','payment_verification','approved','rejected','ready_for_pickup','released') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_by INT UNSIGNED,
    processed_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- Table: payments
-- ============================================================
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_request_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','gcash','maya') NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100),
    proof_notes TEXT,
    status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT UNSIGNED,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (document_request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (document_request_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- Table: logs
-- ============================================================
CREATE TABLE logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action_performed VARCHAR(255) NOT NULL,
    affected_table VARCHAR(60),
    affected_record_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_datetime (date_time)
) ENGINE=InnoDB;

-- ============================================================
-- Default Admin Account (password: Admin@AU2024)
-- ============================================================
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@arellano.edu.ph', '$2y$12$eImiTXuWVxfM37uY4JANjQ==', 'admin');
-- NOTE: Update password hash in config/setup.php during deployment
