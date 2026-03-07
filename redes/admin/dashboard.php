<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Dashboard - Class Scheduling System';

// ==========================
// COUNTS (Dynamic)
// ==========================

// Active Sections
$sections_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM sections WHERE status='Active'");
if ($result && $row = $result->fetch_assoc()) {
    $sections_count = $row['total'];
}

// Scheduled Classes
$schedules_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM schedules WHERE status='Active'");
if ($result && $row = $result->fetch_assoc()) {
    $schedules_count = $row['total'];
}

// Available Rooms
$rooms_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status='Available'");
if ($result && $row = $result->fetch_assoc()) {
    $rooms_count = $row['total'];
}

// Active Faculty
$faculty_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM faculty WHERE status='Active'");
if ($result && $row = $result->fetch_assoc()) {
    $faculty_count = $row['total'];
}

// Unresolved Conflicts
$conflicts_count = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM conflicts WHERE status='Unresolved'");
if ($result && $row = $result->fetch_assoc()) {
    $conflicts_count = $row['total'];
}

// Overloaded Faculty
$overloaded_count = 0;
$result = $conn->query("
    SELECT COUNT(DISTINCT faculty_id) as total
    FROM schedules
    GROUP BY faculty_id
    HAVING SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) > 24
");

if ($result) {
    $overloaded_count = $result->num_rows;
}
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Class Scheduling System</h1>
            <p>Section Management & Conflict Detection</p>
        </div>
        <span class="header-badge">Academic Term: 2025-2026</span>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card blue">
                <h3>Active Sections</h3>
                <div class="number"><?php echo $sections_count; ?></div>
                <i class="bi bi-people-fill icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Scheduled Classes</h3>
                <div class="number"><?php echo $schedules_count; ?></div>
                <i class="bi bi-calendar-check-fill icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card orange">
                <h3>Available Rooms</h3>
                <div class="number"><?php echo $rooms_count; ?></div>
                <i class="bi bi-door-open-fill icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card purple">
                <h3>Active Faculty</h3>
                <div class="number"><?php echo $faculty_count; ?></div>
                <i class="bi bi-person-workspace icon"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="stats-card red">
                <h3>Unresolved Conflicts</h3>
                <div class="number"><?php echo $conflicts_count; ?></div>
                <i class="bi bi-exclamation-triangle-fill icon"></i>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="stats-card cyan">
                <h3>Overloaded Faculty</h3>
                <div class="number"><?php echo $overloaded_count; ?></div>
                <i class="bi bi-person-fill-exclamation icon"></i>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>