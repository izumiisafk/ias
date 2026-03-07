<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Class Timetable - Class Scheduling System';

/* =================================================
   HANDLE INSERT
================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $termStmt = $conn->prepare("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=1 LIMIT 1");
    $termStmt->execute();
    $termResult = $termStmt->get_result();

    if ($termResult->num_rows === 0) {
        die("No active term found. Please set an active term in the academic_terms table first.");
    }

    $termData = $termResult->fetch_assoc();
    $term_id  = $termData['term_id'];

    $stmt = $conn->prepare("
        INSERT INTO schedules 
        (section_id, subject_id, faculty_id, room_id, term_id, day_of_week, start_time, end_time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
    ");

    $stmt->bind_param(
        "iiiiisss",
        $_POST['section_id'],
        $_POST['subject_id'],
        $_POST['faculty_id'],
        $_POST['room_id'],
        $term_id,
        $_POST['day_of_week'],
        $_POST['start_time'],
        $_POST['end_time']
    );

    if ($stmt->execute()) {
        echo "<script>alert('Schedule added successfully!'); window.location.href = window.location.pathname;</script>";
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

/* =================================================
   GET ACTIVE TERM
================================================= */
$activeTermRow = $conn->query("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=1 LIMIT 1")->fetch_assoc();

if (!$activeTermRow) {
    $active_term_id   = 0;
    $active_term_name = 'No Active Term';
} else {
    $active_term_id   = $activeTermRow['term_id'];
    $active_term_name = $activeTermRow['academic_year'] . ' - ' . $activeTermRow['semester'];
}

/* =================================================
   FILTER BY SECTION
================================================= */
$filter_section = $_GET['section_id'] ?? '';

/* =================================================
   FETCH SCHEDULES
================================================= */
$where_section = $filter_section ? "AND s.section_id = " . intval($filter_section) : "";

$schedules_result = $conn->query("
    SELECT s.*, 
           sec.section_name,
           sub.subject_name, sub.subject_code,
           f.first_name, f.last_name,
           r.room_name
    FROM schedules s
    JOIN sections sec ON s.section_id = sec.section_id
    JOIN subjects sub ON s.subject_id = sub.subject_id
    JOIN faculty f ON s.faculty_id = f.faculty_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.term_id = $active_term_id $where_section
    ORDER BY 
        FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
        s.start_time
");

// Build schedule data array for grid
$schedule_data = [];
$all_schedules = [];
if ($schedules_result && $schedules_result->num_rows > 0) {
    while ($row = $schedules_result->fetch_assoc()) {
        $day  = $row['day_of_week'];
        $time = date("H:i", strtotime($row['start_time']));
        $schedule_data[$day][$time][] = $row;
        $all_schedules[] = $row;
    }
}

// Time slots 7AM - 9PM
$time_slots = [];
for ($h = 7; $h <= 21; $h++) {
    $time_slots[] = sprintf("%02d:00", $h);
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

/* =================================================
   DROPDOWNS
================================================= */
$sections = $conn->query("SELECT * FROM sections WHERE status='Active' ORDER BY section_name");
$subjects = $conn->query("SELECT * FROM subjects WHERE status='Active' ORDER BY subject_name");
$faculty  = $conn->query("SELECT * FROM faculty WHERE status='Active' ORDER BY last_name");
$rooms    = $conn->query("SELECT * FROM rooms WHERE status='Available' ORDER BY room_name");
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Class Timetable</h1>
            <p>Active Term: <strong><?= htmlspecialchars($active_term_name) ?></strong></p>
        </div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="bi bi-plus-lg me-2"></i>Add Schedule
        </button>
    </div>

    <!-- FILTER BY SECTION -->
    <div class="content-card mb-4">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="form-label mb-0" style="white-space:nowrap;">Filter by Section:</label>
            <select name="section_id" class="form-select" style="max-width:220px;" onchange="this.form.submit()">
                <option value="">— All Sections —</option>
                <?php
                $sec_opt = $conn->query("SELECT * FROM sections WHERE status='Active' ORDER BY section_name");
                if ($sec_opt) while ($s = $sec_opt->fetch_assoc()): ?>
                    <option value="<?= $s['section_id'] ?>" <?= $filter_section == $s['section_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['section_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <?php if ($filter_section): ?>
                <a href="schedules.php" class="btn-secondary-custom">
                    <i class="bi bi-x-lg me-1"></i>Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TIMETABLE GRID VIEW -->
    <div class="content-card mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; font-family:var(--font-display); margin:0;">
                <i class="bi bi-grid-3x3-gap-fill me-2" style="color:var(--accent);"></i>
                Timetable Grid
                <?php if ($filter_section): ?>
                    <?php
                    $sec_name_r = $conn->query("SELECT section_name FROM sections WHERE section_id=" . intval($filter_section));
                    $sec_name   = $sec_name_r ? $sec_name_r->fetch_assoc()['section_name'] : '';
                    ?>
                    <span style="color:var(--accent); font-size:14px;"> — <?= htmlspecialchars($sec_name) ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="timetable-grid">
                <thead>
                    <tr>
                        <th class="time-col">Time</th>
                        <?php foreach ($days as $day): ?>
                            <th><?= $day ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $slot): ?>
                    <tr>
                        <td class="time-label"><?= date("h:i A", strtotime($slot)) ?></td>
                        <?php foreach ($days as $day): ?>
                        <td class="timetable-cell">
                            <?php if (isset($schedule_data[$day][$slot])): ?>
                                <?php foreach ($schedule_data[$day][$slot] as $sc): ?>
                                <div class="timetable-block">
                                    <div class="tb-subject"><?= htmlspecialchars($sc['subject_code'] ?? $sc['subject_name']) ?></div>
                                    <div class="tb-section"><?= htmlspecialchars($sc['section_name']) ?></div>
                                    <div class="tb-info"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($sc['first_name'].' '.$sc['last_name']) ?></div>
                                    <div class="tb-info"><i class="bi bi-door-open-fill"></i> <?= htmlspecialchars($sc['room_name']) ?></div>
                                    <div class="tb-time"><?= date("h:i A", strtotime($sc['start_time'])) ?> - <?= date("h:i A", strtotime($sc['end_time'])) ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LIST VIEW -->
    <div class="content-card">
        <h5 class="mb-3" style="color:var(--text-primary); font-weight:700; font-family:var(--font-display);">
            <i class="bi bi-list-ul me-2" style="color:var(--accent);"></i>Schedule List
        </h5>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_schedules)): ?>
                        <?php foreach ($all_schedules as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['subject_name']) ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['room_name']) ?></td>
                            <td><?= htmlspecialchars($row['day_of_week']) ?></td>
                            <td><?= date("h:i A", strtotime($row['start_time'])) ?></td>
                            <td><?= date("h:i A", strtotime($row['end_time'])) ?></td>
                            <td><span class="badge-success"><?= htmlspecialchars($row['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-3">No schedules found for this term.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ADD MODAL -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Add New Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section</label>
                            <select name="section_id" class="form-select" required>
                                <option value="">Select Section</option>
                                <?php if($sections) while($row = $sections->fetch_assoc()): ?>
                                    <option value="<?= $row['section_id'] ?>"><?= htmlspecialchars($row['section_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php if($subjects) while($row = $subjects->fetch_assoc()): ?>
                                    <option value="<?= $row['subject_id'] ?>"><?= htmlspecialchars($row['subject_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Faculty</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Select Faculty</option>
                                <?php if($faculty) while($row = $faculty->fetch_assoc()): ?>
                                    <option value="<?= $row['faculty_id'] ?>"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room</label>
                            <select name="room_id" class="form-select" required>
                                <option value="">Select Room</option>
                                <?php if($rooms) while($row = $rooms->fetch_assoc()): ?>
                                    <option value="<?= $row['room_id'] ?>"><?= htmlspecialchars($row['room_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Day</label>
                            <select name="day_of_week" class="form-select" required>
                                <option>Monday</option>
                                <option>Tuesday</option>
                                <option>Wednesday</option>
                                <option>Thursday</option>
                                <option>Friday</option>
                                <option>Saturday</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Add Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* ── TIMETABLE GRID ── */
.timetable-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 900px;
}

.timetable-grid thead th {
    background: var(--color-surface2);
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 10px 8px;
    text-align: center;
    border: 1px solid var(--color-border);
}

.timetable-grid thead th.time-col {
    width: 80px;
}

.timetable-grid tbody td {
    border: 1px solid var(--color-border);
    vertical-align: top;
    padding: 4px;
    min-height: 48px;
}

.time-label {
    background: var(--color-surface2);
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    padding: 8px 6px !important;
    width: 80px;
}

.timetable-cell {
    background: var(--color-surface);
    min-width: 130px;
    min-height: 48px;
}

.timetable-cell:hover {
    background: rgba(255,255,255,0.02);
}

.timetable-block {
    background: linear-gradient(135deg, rgba(79,163,255,0.15), rgba(79,163,255,0.08));
    border: 1px solid rgba(79,163,255,0.25);
    border-left: 3px solid var(--accent);
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 3px;
}

.tb-subject {
    font-weight: 700;
    color: var(--accent);
    font-size: 12px;
    font-family: var(--font-display);
    margin-bottom: 2px;
}

.tb-section {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 11px;
    margin-bottom: 3px;
}

.tb-info {
    color: var(--text-secondary);
    font-size: 10px;
    margin-bottom: 1px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.tb-time {
    color: var(--accent-green);
    font-size: 10px;
    font-weight: 600;
    margin-top: 3px;
}
</style>

<?php include '../includes/footer.php'; ?>