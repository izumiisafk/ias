-- ============================================================
-- RUN THIS IN phpMyAdmin BEFORE using Staff Management
-- Add this to your existing class_scheduling database
-- ============================================================

USE `class_scheduling`;

-- System accounts table (for Registrar login accounts)
-- Teachers do NOT have login accounts, they are in the faculty table
CREATE TABLE system_accounts (
    account_id   INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,
    full_name    VARCHAR(100) NOT NULL,
    email        VARCHAR(100),
    phone        VARCHAR(20),
    department   VARCHAR(100),
    role         ENUM('registrar') NOT NULL DEFAULT 'registrar',
    status       ENUM('Active','Inactive') DEFAULT 'Active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default registrar account (password: registrar123)
-- This mirrors the hardcoded login so both work
INSERT INTO system_accounts (username, password, full_name, email, role, status)
VALUES ('registrar', 'registrar123', 'Registrar Officer', 'registrar@school.edu', 'registrar', 'Active');
