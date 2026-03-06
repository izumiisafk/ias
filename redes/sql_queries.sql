-- ========================================
-- CLASS SCHEDULING SYSTEM - SQL QUERIES
-- ========================================

-- ========================================
-- 1. DASHBOARD COUNTS QUERIES
-- ========================================

-- Active Sections Count
SELECT COUNT(*) as active_sections 
FROM sections 
WHERE status = 'Active';

-- Scheduled Classes Count
SELECT COUNT(*) as scheduled_classes 
FROM schedules 
WHERE status = 'Active';

-- Available Rooms Count
SELECT COUNT(*) as available_rooms 
FROM rooms 
WHERE status = 'Available';

-- Active Faculty Count
SELECT COUNT(*) as active_faculty 
FROM faculty 
WHERE status = 'Active';

-- Unresolved Conflicts Count
SELECT COUNT(*) as unresolved_conflicts 
FROM conflicts 
WHERE status = 'Unresolved';

-- Overloaded Faculty Count
SELECT COUNT(*) as overloaded_faculty
FROM (
    SELECT f.faculty_id,
           SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)) as total_hours
    FROM faculty f
    LEFT JOIN schedules s ON f.faculty_id = s.faculty_id
    WHERE s.status = 'Active'
    GROUP BY f.faculty_id
    HAVING total_hours > f.max_teaching_hours
) as overloaded;


-- ========================================
-- 2. FACULTY LOAD CALCULATION
-- ========================================

-- Get Faculty Teaching Hours Summary
SELECT 
    f.faculty_id,
    f.faculty_code,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    f.department,
    f.max_teaching_hours,
    COUNT(DISTINCT s.subject_id) as subject_count,
    COUNT(DISTINCT s.section_id) as section_count,
    COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as teaching_hours,
    ROUND((COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) / f.max_teaching_hours) * 100, 0) as load_percentage,
    CASE 
        WHEN COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) > f.max_teaching_hours THEN 'Overloaded'
        WHEN COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) >= (f.max_teaching_hours * 0.85) THEN 'Full Load'
        ELSE 'Underloaded'
    END as load_status
FROM faculty f
LEFT JOIN schedules s ON f.faculty_id = s.faculty_id AND s.status = 'Active'
WHERE f.status = 'Active'
GROUP BY f.faculty_id, f.faculty_code, f.first_name, f.last_name, f.department, f.max_teaching_hours
ORDER BY teaching_hours DESC;


-- ========================================
-- 3. UNDERLOADED / FULL LOAD / OVERLOADED DETECTION
-- ========================================

-- Underloaded Faculty (Less than 85% of max hours)
SELECT 
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    f.department,
    COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as teaching_hours,
    f.max_teaching_hours,
    ROUND((COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) / f.max_teaching_hours) * 100, 0) as load_percentage
FROM faculty f
LEFT JOIN schedules s ON f.faculty_id = s.faculty_id AND s.status = 'Active'
WHERE f.status = 'Active'
GROUP BY f.faculty_id, f.first_name, f.last_name, f.department, f.max_teaching_hours
HAVING load_percentage < 85;

-- Full Load Faculty (85% to 100%)
SELECT 
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    f.department,
    COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as teaching_hours,
    f.max_teaching_hours,
    ROUND((COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) / f.max_teaching_hours) * 100, 0) as load_percentage
FROM faculty f
LEFT JOIN schedules s ON f.faculty_id = s.faculty_id AND s.status = 'Active'
WHERE f.status = 'Active'
GROUP BY f.faculty_id, f.first_name, f.last_name, f.department, f.max_teaching_hours
HAVING load_percentage >= 85 AND load_percentage <= 100;

-- Overloaded Faculty (More than 100%)
SELECT 
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    f.department,
    COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as teaching_hours,
    f.max_teaching_hours,
    ROUND((COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) / f.max_teaching_hours) * 100, 0) as load_percentage
FROM faculty f
LEFT JOIN schedules s ON f.faculty_id = s.faculty_id AND s.status = 'Active'
WHERE f.status = 'Active'
GROUP BY f.faculty_id, f.first_name, f.last_name, f.department, f.max_teaching_hours
HAVING load_percentage > 100;


-- ========================================
-- 4. ROOM AVAILABILITY QUERY
-- ========================================

-- Get Room Utilization
SELECT 
    r.room_id,
    r.room_code,
    r.room_name,
    r.room_type,
    CONCAT(r.building, ' - ', r.floor) as location,
    r.capacity,
    r.facilities,
    COUNT(s.schedule_id) as scheduled_slots,
    ROUND((COUNT(s.schedule_id) / 30) * 100, 0) as utilization_percentage,
    CASE 
        WHEN COUNT(s.schedule_id) = 0 THEN 'Available'
        ELSE 'Occupied'
    END as current_status
FROM rooms r
LEFT JOIN schedules s ON r.room_id = s.room_id AND s.status = 'Active'
GROUP BY r.room_id, r.room_code, r.room_name, r.room_type, r.building, r.floor, r.capacity, r.facilities
ORDER BY utilization_percentage DESC;

-- Check Room Availability at Specific Time
SELECT r.*
FROM rooms r
WHERE r.room_id NOT IN (
    SELECT room_id 
    FROM schedules 
    WHERE day_of_week = 'Monday' 
    AND start_time < '12:00:00' 
    AND end_time > '10:00:00'
    AND status = 'Active'
)
AND r.status = 'Available';


-- ========================================
-- 5. FACULTY TIME CONFLICT DETECTION
-- ========================================

-- Detect Faculty Schedule Conflicts
SELECT 
    s1.schedule_id as schedule_1,
    s2.schedule_id as schedule_2,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    s1.day_of_week,
    s1.start_time,
    s1.end_time,
    sec1.section_name as section_1,
    sec2.section_name as section_2,
    CONCAT('Teaching 2 classes at same time: ', sec1.section_name, ' and ', sec2.section_name) as conflict_description
FROM schedules s1
JOIN schedules s2 ON s1.faculty_id = s2.faculty_id 
    AND s1.schedule_id < s2.schedule_id
    AND s1.day_of_week = s2.day_of_week
    AND s1.start_time < s2.end_time 
    AND s1.end_time > s2.start_time
JOIN faculty f ON s1.faculty_id = f.faculty_id
JOIN sections sec1 ON s1.section_id = sec1.section_id
JOIN sections sec2 ON s2.section_id = sec2.section_id
WHERE s1.status = 'Active' AND s2.status = 'Active';


-- ========================================
-- 6. ROOM DOUBLE BOOKING DETECTION
-- ========================================

-- Detect Room Booking Conflicts
SELECT 
    s1.schedule_id as schedule_1,
    s2.schedule_id as schedule_2,
    r.room_code,
    r.room_name,
    s1.day_of_week,
    s1.start_time,
    s1.end_time,
    CONCAT(f1.first_name, ' ', f1.last_name) as faculty_1,
    CONCAT(f2.first_name, ' ', f2.last_name) as faculty_2,
    sec1.section_name as section_1,
    sec2.section_name as section_2,
    CONCAT('Room double-booked: ', sec1.section_name, ' and ', sec2.section_name) as conflict_description
FROM schedules s1
JOIN schedules s2 ON s1.room_id = s2.room_id 
    AND s1.schedule_id < s2.schedule_id
    AND s1.day_of_week = s2.day_of_week
    AND s1.start_time < s2.end_time 
    AND s1.end_time > s2.start_time
JOIN rooms r ON s1.room_id = r.room_id
JOIN faculty f1 ON s1.faculty_id = f1.faculty_id
JOIN faculty f2 ON s2.faculty_id = f2.faculty_id
JOIN sections sec1 ON s1.section_id = sec1.section_id
JOIN sections sec2 ON s2.section_id = sec2.section_id
WHERE s1.status = 'Active' AND s2.status = 'Active';


-- ========================================
-- 7. CRUD QUERIES - FACULTY
-- ========================================

-- Create Faculty
INSERT INTO faculty (faculty_code, first_name, last_name, department, email, phone, max_teaching_hours)
VALUES (?, ?, ?, ?, ?, ?, ?);

-- Read Faculty
SELECT * FROM faculty WHERE faculty_id = ?;

-- Read All Faculty
SELECT 
    faculty_id,
    faculty_code,
    CONCAT(first_name, ' ', last_name) as full_name,
    department,
    email,
    phone,
    max_teaching_hours,
    status
FROM faculty 
ORDER BY last_name, first_name;

-- Update Faculty
UPDATE faculty 
SET first_name = ?, 
    last_name = ?, 
    department = ?, 
    email = ?, 
    phone = ?,
    max_teaching_hours = ?,
    status = ?
WHERE faculty_id = ?;

-- Delete Faculty
DELETE FROM faculty WHERE faculty_id = ?;


-- ========================================
-- 8. CRUD QUERIES - ROOMS
-- ========================================

-- Create Room
INSERT INTO rooms (room_code, room_name, room_type, building, floor, capacity, facilities)
VALUES (?, ?, ?, ?, ?, ?, ?);

-- Read Room
SELECT * FROM rooms WHERE room_id = ?;

-- Read All Rooms
SELECT 
    room_id,
    room_code,
    room_name,
    room_type,
    CONCAT(building, ' - ', floor) as location,
    capacity,
    facilities,
    status
FROM rooms 
ORDER BY room_code;

-- Update Room
UPDATE rooms 
SET room_code = ?,
    room_name = ?,
    room_type = ?,
    building = ?,
    floor = ?,
    capacity = ?,
    facilities = ?,
    status = ?
WHERE room_id = ?;

-- Delete Room
DELETE FROM rooms WHERE room_id = ?;


-- ========================================
-- 9. CRUD QUERIES - SECTIONS
-- ========================================

-- Create Section
INSERT INTO sections (section_name, program, year_level, semester, academic_year, total_students, adviser_id)
VALUES (?, ?, ?, ?, ?, ?, ?);

-- Read Section
SELECT * FROM sections WHERE section_id = ?;

-- Read All Sections with Adviser
SELECT 
    s.section_id,
    s.section_name,
    s.program,
    s.year_level,
    s.semester,
    s.academic_year,
    s.total_students,
    CONCAT(f.first_name, ' ', f.last_name) as adviser_name,
    s.status
FROM sections s
LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
ORDER BY s.section_name;

-- Update Section
UPDATE sections 
SET section_name = ?,
    program = ?,
    year_level = ?,
    semester = ?,
    academic_year = ?,
    total_students = ?,
    adviser_id = ?,
    status = ?
WHERE section_id = ?;

-- Delete Section
DELETE FROM sections WHERE section_id = ?;


-- ========================================
-- 10. CRUD QUERIES - SUBJECTS
-- ========================================

-- Create Subject
INSERT INTO subjects (subject_code, subject_name, description, units, hours_per_week, department, year_level, semester)
VALUES (?, ?, ?, ?, ?, ?, ?, ?);

-- Read Subject
SELECT * FROM subjects WHERE subject_id = ?;

-- Read All Subjects
SELECT 
    subject_id,
    subject_code,
    subject_name,
    units,
    hours_per_week,
    department,
    year_level,
    semester,
    status
FROM subjects 
ORDER BY subject_code;

-- Update Subject
UPDATE subjects 
SET subject_code = ?,
    subject_name = ?,
    description = ?,
    units = ?,
    hours_per_week = ?,
    department = ?,
    year_level = ?,
    semester = ?,
    status = ?
WHERE subject_id = ?;

-- Delete Subject
DELETE FROM subjects WHERE subject_id = ?;


-- ========================================
-- 11. CRUD QUERIES - SCHEDULES
-- ========================================

-- Create Schedule
INSERT INTO schedules (section_id, subject_id, faculty_id, room_id, day_of_week, start_time, end_time, academic_year, semester)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);

-- Read Schedule
SELECT * FROM schedules WHERE schedule_id = ?;

-- Read All Schedules with Details
SELECT 
    sch.schedule_id,
    sec.section_name,
    sub.subject_code,
    sub.subject_name,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    r.room_code,
    r.room_name,
    sch.day_of_week,
    TIME_FORMAT(sch.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(sch.end_time, '%h:%i %p') as end_time,
    sch.academic_year,
    sch.semester,
    sch.status
FROM schedules sch
JOIN sections sec ON sch.section_id = sec.section_id
JOIN subjects sub ON sch.subject_id = sub.subject_id
JOIN faculty f ON sch.faculty_id = f.faculty_id
JOIN rooms r ON sch.room_id = r.room_id
ORDER BY sch.day_of_week, sch.start_time;

-- Update Schedule
UPDATE schedules 
SET section_id = ?,
    subject_id = ?,
    faculty_id = ?,
    room_id = ?,
    day_of_week = ?,
    start_time = ?,
    end_time = ?,
    academic_year = ?,
    semester = ?,
    status = ?
WHERE schedule_id = ?;

-- Delete Schedule
DELETE FROM schedules WHERE schedule_id = ?;


-- ========================================
-- 12. ADDITIONAL USEFUL QUERIES
-- ========================================

-- Get Faculty Schedule Details
SELECT 
    sec.section_name,
    sub.subject_name,
    r.room_name,
    sch.day_of_week,
    TIME_FORMAT(sch.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(sch.end_time, '%h:%i %p') as end_time
FROM schedules sch
JOIN sections sec ON sch.section_id = sec.section_id
JOIN subjects sub ON sch.subject_id = sub.subject_id
JOIN rooms r ON sch.room_id = r.room_id
WHERE sch.faculty_id = ? AND sch.status = 'Active'
ORDER BY 
    FIELD(sch.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    sch.start_time;

-- Get Room Schedule
SELECT 
    sec.section_name,
    sub.subject_name,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    sch.day_of_week,
    TIME_FORMAT(sch.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(sch.end_time, '%h:%i %p') as end_time
FROM schedules sch
JOIN sections sec ON sch.section_id = sec.section_id
JOIN subjects sub ON sch.subject_id = sub.subject_id
JOIN faculty f ON sch.faculty_id = f.faculty_id
WHERE sch.room_id = ? AND sch.status = 'Active'
ORDER BY 
    FIELD(sch.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    sch.start_time;

-- Get Section Schedule
SELECT 
    sub.subject_name,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    r.room_name,
    sch.day_of_week,
    TIME_FORMAT(sch.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(sch.end_time, '%h:%i %p') as end_time
FROM schedules sch
JOIN subjects sub ON sch.subject_id = sub.subject_id
JOIN faculty f ON sch.faculty_id = f.faculty_id
JOIN rooms r ON sch.room_id = r.room_id
WHERE sch.section_id = ? AND sch.status = 'Active'
ORDER BY 
    FIELD(sch.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    sch.start_time;
