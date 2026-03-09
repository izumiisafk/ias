CREATE DATABASE `class_scheduling`;
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
('LAB-101', 'Computer Lab 1', 'Laboratory', 'Tech Building', '1st', 40, 'Available'),
('LAB-102', 'Computer Lab 2', 'Laboratory', 'Tech Building', '1st', 40, 'Available');
-- Lecture Rooms
('RM-103', 'Room 103', 'Lecture', 'Main Building', '1st', 40, 'Available'),
('RM-104', 'Room 104', 'Lecture', 'Main Building', '2nd', 40, 'Available'),
('RM-105', 'Room 105', 'Lecture', 'Science Building', '2nd', 40, 'Available'),
('RM-106', 'Room 106', 'Lecture', 'Science Building', '3rd', 40, 'Available'),
('RM-107', 'Room 107', 'Lecture', 'Admin Building', '1st', 40, 'Available'),

-- Labs for BS Tourism Management (BSTM)
('LAB-201', 'Tourism Lab 1', 'Laboratory', 'Tourism Building', '1st', 40, 'Available'),
('LAB-202', 'Tourism Lab 2', 'Laboratory', 'Tourism Building', '2nd', 40, 'Available'),

-- Labs for BS Business Administration (BSBA)
('LAB-301', 'Business Lab 1', 'Laboratory', 'Admin Building', '1st', 40, 'Available'),
('LAB-302', 'Business Lab 2', 'Laboratory', 'Admin Building', '2nd', 40, 'Available'),

-- Labs for BS Criminology (BSCRIM)
('LAB-401', 'Criminology Lab 1', 'Laboratory', 'Crim Building', '1st', 40, 'Available'),
('LAB-402', 'Criminology Lab 2', 'Laboratory', 'Crim Building', '2nd', 40, 'Available'),

-- Labs for BS Civil Engineering (BSCE)
('LAB-501', 'Civil Eng Lab 1', 'Laboratory', 'Engineering Building', '1st', 40, 'Available'),
('LAB-502', 'Civil Eng Lab 2', 'Laboratory', 'Engineering Building', '2nd', 40, 'Available');

-- SUBJECTS
-- ======================================
-- BS Information Technology (BSIT)
-- ======================================
-- 1st Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSIT101', 'Introduction to Computing', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active'),
('BSIT102', 'Computer Programming 1', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active'),
('BSIT103', 'Computer Fundamentals', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active'),
('BSIT111', 'Discrete Mathematics', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active'),
('BSIT112', 'Introduction to Networking', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active'),
('BSIT113', 'IT Ethics', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active');

-- 2nd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSIT201', 'Data Structures and Algorithms', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active'),
('BSIT202', 'Object Oriented Programming', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active'),
('BSIT203', 'Database Systems', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active'),
('BSIT211', 'Web Development', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active'),
('BSIT212', 'Computer Architecture', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active'),
('BSIT213', 'Network Security', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active');

-- 3rd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSIT301', 'Systems Analysis and Design', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active'),
('BSIT302', 'Software Engineering', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active'),
('BSIT303', 'Mobile App Development', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active'),
('BSIT311', 'Cloud Computing', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active'),
('BSIT312', 'Network Administration', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active'),
('BSIT313', 'Data Analytics', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active');

-- 4th Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSIT401', 'Capstone Project', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active'),
('BSIT402', 'Artificial Intelligence', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active'),
('BSIT403', 'Enterprise Systems', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active'),
('BSIT411', 'Information Systems Audit', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active'),
('BSIT412', 'Project Management', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active'),
('BSIT413', 'Cloud Security', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active');

-- ======================================
-- BS Tourism Management (BSTM)
-- ======================================
-- 1st Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSTM101', 'Introduction to Tourism', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active'),
('BSTM102', 'Tourism Geography', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active'),
('BSTM103', 'Hospitality Fundamentals', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active'),
('BSTM111', 'Tourism Laws and Ethics', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active'),
('BSTM112', 'Cultural Tourism', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active'),
('BSTM113', 'Tourism Marketing Basics', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active');

-- 2nd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSTM201', 'Travel Agency Operations', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active'),
('BSTM202', 'Tourism Marketing', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active'),
('BSTM203', 'Event Management', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active'),
('BSTM211', 'Hospitality Management', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active'),
('BSTM212', 'Tourism Policy and Planning', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active'),
('BSTM213', 'Ecotourism', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active');

-- 3rd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSTM301', 'Travel and Transport Management', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active'),
('BSTM302', 'Tour Operations Management', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active'),
('BSTM303', 'Tourism Research Methods', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active'),
('BSTM311', 'Sustainable Tourism', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active'),
('BSTM312', 'Tourism Entrepreneurship', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active'),
('BSTM313', 'International Tourism', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active');

-- 4th Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSTM401', 'Capstone Project in Tourism', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active'),
('BSTM402', 'Tourism Marketing Strategy', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active'),
('BSTM403', 'Tourism Policy and Development', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active'),
('BSTM411', 'Ecotourism Strategies', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active'),
('BSTM412', 'Travel Agency Management', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active'),
('BSTM413', 'Tourism Research Project', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active');

-- ======================================
-- BS Business Administration (BSBA)
-- ======================================
-- 1st Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSBA101', 'Principles of Management', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active'),
('BSBA102', 'Business Mathematics', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active'),
('BSBA103', 'Introduction to Economics', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active'),
('BSBA111', 'Business Communication', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active'),
('BSBA112', 'Financial Accounting', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active'),
('BSBA113', 'Business Ethics', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active');

-- 2nd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSBA201', 'Marketing Management', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active'),
('BSBA202', 'Human Resource Management', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active'),
('BSBA203', 'Managerial Accounting', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active'),
('BSBA211', 'Operations Management', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active'),
('BSBA212', 'Business Law', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active'),
('BSBA213', 'Entrepreneurship', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active');

-- 3rd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSBA301', 'Strategic Management', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active'),
('BSBA302', 'International Business', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active'),
('BSBA303', 'Financial Management', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active'),
('BSBA311', 'Business Analytics', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active'),
('BSBA312', 'Leadership and Management', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active'),
('BSBA313', 'Investment Management', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active');

-- 4th Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSBA401', 'Capstone Project in Business', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active'),
('BSBA402', 'Marketing Strategy', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active'),
('BSBA403', 'International Finance', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active'),
('BSBA411', 'Project Management', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active'),
('BSBA412', 'Business Research Methods', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active'),
('BSBA413', 'Corporate Governance', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active');

-- ======================================
-- BS Criminology (BSCRIM)
-- ======================================
-- 1st Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCR101', 'Introduction to Criminology', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active'),
('BSCR102', 'Criminal Law', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active'),
('BSCR103', 'Sociology of Crime', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active'),
('BSCR111', 'Psychology of Crime', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active'),
('BSCR112', 'Criminal Investigation 1', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active'),
('BSCR113', 'Ethics and Law Enforcement', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active');

-- 2nd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCR201', 'Police Administration', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active'),
('BSCR202', 'Forensic Science', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active'),
('BSCR203', 'Criminalistics', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active'),
('BSCR211', 'Correctional Administration', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active'),
('BSCR212', 'Criminal Investigation 2', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active'),
('BSCR213', 'Cybercrime Investigation', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active');

-- 3rd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCR301', 'Criminology Research', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active'),
('BSCR302', 'Criminal Profiling', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active'),
('BSCR303', 'Law Enforcement Practices', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active'),
('BSCR311', 'Victimology', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active'),
('BSCR312', 'Advanced Forensic Science', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active'),
('BSCR313', 'Criminal Justice Administration', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active');

-- 4th Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCR401', 'Capstone Project in Criminology', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active'),
('BSCR402', 'Criminal Law Review', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active'),
('BSCR403', 'Advanced Criminal Investigation', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active'),
('BSCR411', 'Criminal Justice Policies', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active'),
('BSCR412', 'Special Topics in Criminology', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active'),
('BSCR413', 'Forensic Psychology', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active');

-- ======================================
-- BS Civil Engineering (BSCE)
-- ======================================
-- 1st Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCE101', 'Engineering Mathematics 1', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active'),
('BSCE102', 'Engineering Drawing', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active'),
('BSCE103', 'Statics', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active'),
('BSCE111', 'Mechanics of Materials', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active'),
('BSCE112', 'Introduction to Civil Engineering', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active'),
('BSCE113', 'Engineering Ethics', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active');

-- 2nd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCE201', 'Structural Analysis', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active'),
('BSCE202', 'Construction Materials', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active'),
('BSCE203', 'Surveying 1', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active'),
('BSCE211', 'Fluid Mechanics', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active'),
('BSCE212', 'Geotechnical Engineering 1', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active'),
('BSCE213', 'Construction Project Management', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active');

-- 3rd Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCE301', 'Structural Design', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active'),
('BSCE302', 'Transportation Engineering', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active'),
('BSCE303', 'Hydraulics', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active'),
('BSCE311', 'Surveying 2', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active'),
('BSCE312', 'Structural Engineering 2', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active'),
('BSCE313', 'Environmental Engineering', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active');

-- 4th Year
INSERT INTO subjects (subject_code, subject_name, units, hours_per_week, department, year_level, status) VALUES
('BSCE401', 'Capstone Project in Civil Engineering', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active'),
('BSCE402', 'Construction Law', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active'),
('BSCE403', 'Advanced Geotechnical Engineering', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active'),
('BSCE411', 'Project Management in Civil Engineering', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active'),
('BSCE412', 'Bridge Design', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active'),
('BSCE413', 'Construction Safety', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active');

-- ACADEMIC TERM (required para gumana ang schedules)
INSERT INTO academic_terms (academic_year, semester, is_active) VALUES
('2025-2026', '1st Semester', 0);
INSERT INTO academic_terms (academic_year, semester, is_active) 
VALUES ('2025-2026', '2nd Semester', 1);

CREATE TABLE room_assignments (
assignment_id INT AUTO_INCREMENT PRIMARY KEY,
section_id INT,
room_id INT,
assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE subjects
MODIFY year_level VARCHAR(50);

ALTER TABLE rooms ADD allowed_program VARCHAR(50) NULL;
UPDATE rooms SET allowed_program='BSIT' WHERE room_code='LAB-101';
UPDATE rooms SET allowed_program='BSIT' WHERE room_code='LAB-102';
UPDATE rooms SET allowed_program='BSTM' WHERE room_code='LAB-201';
UPDATE rooms SET allowed_program='BSTM' WHERE room_code='LAB-202';
UPDATE rooms SET allowed_program='BSBA' WHERE room_code='LAB-301';
UPDATE rooms SET allowed_program='BSBA' WHERE room_code='LAB-302';
UPDATE rooms SET allowed_program='BSCRIM' WHERE room_code='LAB-401';
UPDATE rooms SET allowed_program='BSCRIM' WHERE room_code='LAB-402';
UPDATE rooms SET allowed_program='BSCE' WHERE room_code='LAB-501';
UPDATE rooms SET allowed_program='BSCE' WHERE room_code='LAB-502';

ALTER TABLE sections 
ADD COLUMN term_id INT NULL AFTER adviser_id,
ADD FOREIGN KEY (term_id) REFERENCES academic_terms(term_id) ON DELETE SET NULL;

ALTER TABLE conflicts 
  ADD COLUMN resolved_at DATETIME NULL AFTER status,
  ADD COLUMN resolved_note VARCHAR(255) NULL AFTER resolved_at;

UPDATE rooms
SET capacity = 40;