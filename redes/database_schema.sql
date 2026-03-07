CREATE DATABASE ``;
USE `bading`;

-- =========================
-- ACADEMIC TERMS
-- =========================
CREATE TABLE academic_terms (
    term_id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester','2nd Semester','Summer') NOT NULL,
    is_active BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_term (academic_year, semester)
) ENGINE=InnoDB;


-- =========================
-- FACULTY
-- =========================
CREATE TABLE faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_code VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    department VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    max_teaching_hours INT DEFAULT 24,
    status ENUM('Active','Inactive','On Leave') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================
-- ROOMS
-- =========================
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(20) UNIQUE NOT NULL,
    room_name VARCHAR(100) NOT NULL,
    room_type ENUM('Lecture','Laboratory','Conference','Auditorium') NOT NULL,
    building VARCHAR(50) NOT NULL,
    floor VARCHAR(10),
    capacity INT NOT NULL,
    status ENUM('Available','Maintenance') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================
-- SUBJECTS
-- =========================
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(150) NOT NULL,
    units INT NOT NULL,
    hours_per_week INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    year_level ENUM('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================
-- SECTIONS
-- =========================
CREATE TABLE sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) UNIQUE NOT NULL,
    program VARCHAR(100) NOT NULL,
    year_level ENUM('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
    total_students INT DEFAULT 0,
    adviser_id INT,
    status ENUM('Active','Inactive','Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (adviser_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- =========================
-- SCHEDULES
-- =========================
CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    faculty_id INT NOT NULL,
    room_id INT NOT NULL,
    term_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('Active','Cancelled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES academic_terms(term_id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================
-- CONFLICTS
-- =========================
CREATE TABLE conflicts (
    conflict_id INT AUTO_INCREMENT PRIMARY KEY,
    conflict_type ENUM('Faculty','Room','Section') NOT NULL,
    schedule_id_1 INT NOT NULL,
    schedule_id_2 INT NOT NULL,
    description TEXT NOT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Unresolved','Resolved') DEFAULT 'Unresolved',

    FOREIGN KEY (schedule_id_1) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id_2) REFERENCES schedules(schedule_id) ON DELETE CASCADE
) ENGINE=InnoDB;

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


-- ROOMS
INSERT INTO rooms (room_code, room_name, room_type, building, floor, capacity, status) VALUES
('RM-101', 'Room 101', 'Lecture', 'Main Building', '1st', 40, 'Available'),
('RM-102', 'Room 102', 'Lecture', 'Main Building', '1st', 40, 'Available'),
('RM-201', 'Room 201', 'Lecture', 'Main Building', '2nd', 40, 'Available'),
('LAB-101', 'Computer Lab 1', 'Laboratory', 'Tech Building', '1st', 30, 'Available'),
('LAB-102', 'Computer Lab 2', 'Laboratory', 'Tech Building', '1st', 30, 'Available');

-- SUBJECTS
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('CS101', 'Introduction to Computing', 3, 3, 'College of IT', '1st Year', 'Active'),
('CS102', 'Computer Programming 1', 3, 3, 'College of IT', '1st Year', 'Active'),
('CS201', 'Data Structures and Algorithms', 3, 3, 'College of IT', '2nd Year', 'Active'),
('CS202', 'Object Oriented Programming', 3, 3, 'College of IT', '2nd Year', 'Active'),
('CS301', 'Systems Analysis and Design', 3, 3, 'College of IT', '3rd Year', 'Active'),
('CS302', 'Web Development', 3, 3, 'College of IT', '3rd Year', 'Active');

-- ACADEMIC TERM (required para gumana ang schedules)
INSERT INTO academic_terms (academic_year, semester, is_active) VALUES
('2025-2026', '1st Semester', 1);


