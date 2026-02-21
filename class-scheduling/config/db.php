<?php
// ===============================
// DATABASE CONFIGURATION
// ===============================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'class_scheduling');

// ===============================
// CREATE CONNECTION
// ===============================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ===============================
// CONNECTION ERROR HANDLING
// ===============================
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ===============================
// SET CHARACTER SET
// ===============================
if (!$conn->set_charset("utf8mb4")) {
    die("Error loading character set utf8mb4: " . $conn->error);
}

// ===============================
// ENABLE STRICT MODE (Optional but Recommended)
// ===============================
$conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");

// ===============================
// TIMEZONE (Optional)
// ===============================
date_default_timezone_set('Asia/Manila');

?>
