<?php
require_once 'includes/auth.php'; 
require_once '../config/db.php';
$page_title = 'Class Timetable - Class Scheduling System';

/* =================================================
   HANDLE INSERT
================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $termStmt = $conn->prepare("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=TRUE LIMIT 1");
    $termStmt->execute();
    $termData = $termStmt->fetch();

    if (!$termData) {
        die("No active term found. Please set an active term in the academic_terms table first.");
    }

    $term_id    = $termData['term_id'];
    $section_id = $_POST['section_id'];
    $room_id    = null;

    $roomQuery = $conn->prepare("
        SELECT r.room_id
        FROM room_assignments ra
        LEFT JOIN rooms r ON ra.room_id = r.room_id
        WHERE ra.section_id = ?
        LIMIT 1
    ");
    $roomQuery->execute([$section_id]);
    $roomData = $roomQuery->fetch();
    if ($roomData) {
        $room_id = $roomData['room_id'];
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO schedules 
            (section_id, subject_id, faculty_id, room_id, term_id, day_of_week, start_time, end_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        if ($stmt->execute([
            $_POST['section_id'],
            $_POST['subject_id'],
            $_POST['faculty_id'],
            $room_id,
            $term_id,
            $_POST['day_of_week'],
            $_POST['start_time'],
            $_POST['end_time']
        ])) {
            echo "<script>alert('Schedule added successfully!'); window.location.href = window.location.pathname;</script>";
            exit();
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

/* =================================================
   HANDLE EDIT
================================================= */
if (isset($_POST['edit_schedule'])) {
    try {
        $stmt = $conn->prepare("
            UPDATE schedules SET 
                faculty_id=?,
                day_of_week=?,
                start_time=?,
                end_time=?,
                status=?
            WHERE schedule_id=?
        ");
        $status = $_POST['status'];
        if (!in_array($status, ['Active','Cancelled'])) {
            $status = 'Active';
        }
        if ($stmt->execute([
            $_POST['faculty_id'],
            $_POST['day_of_week'],
            $_POST['start_time'],
            $_POST['end_time'],
            $status,
            $_POST['schedule_id']
        ])) {
            echo "<script>alert('Schedule updated successfully!'); window.location.href=window.location.pathname;</script>";
            exit();
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

/* =================================================
   HANDLE DELETE
================================================= */
if (isset($_POST['delete_schedule'])) {
    $schedule_id = intval($_POST['schedule_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM schedules WHERE schedule_id=?");
        if ($stmt->execute([$schedule_id])) {
            echo "<script>alert('Schedule deleted successfully!'); window.location.href=window.location.pathname;</script>";
            exit();
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

/* =================================================
   GET ACTIVE TERM
================================================= */
$activeTermRow = $conn->query("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=TRUE LIMIT 1")->fetch();
if (!$activeTermRow) {
    $active_term_id   = 0;
    $active_term_name = 'No Active Term';
} else {
    $active_term_id   = $activeTermRow['term_id'];
    $active_term_name = $activeTermRow['academic_year'] . ' - ' . $activeTermRow['semester'];
}

/* =================================================
   DEPARTMENT & YEAR FILTER
================================================= */
$departmentToPrograms = [
    'CCS'  => ['BSIT'],
    'CHTM' => ['BSTM'],
    'CBA'  => ['BSBA'],
    'CCJE' => ['BSCRIM'],
    'COE'  => ['BSCE'],
];
$departmentLabels = [
    'CCS'  => 'College of Computer Studies (CCS)',
    'CHTM' => 'College of Hospitality and Tourism Management (CHTM)',
    'CBA'  => 'College of Business Administration (CBA)',
    'CCJE' => 'College of Criminal Justice Education (CCJE)',
    'COE'  => 'College of Engineering (COE)',
];

$filter_department = $_GET['department'] ?? '';
$filter_year       = $_GET['year'] ?? '';

$where_parts = ["s.term_id = $active_term_id"];

if ($filter_department && isset($departmentToPrograms[$filter_department])) {
    $programs = $departmentToPrograms[$filter_department];
    $escaped  = [];
    foreach ($programs as $p) {
        $escaped[] = $conn->quote($p);
    }
    $where_parts[] = "sec.program IN (" . implode(',', $escaped) . ")";
}

if ($filter_year && in_array($filter_year, ['1','2','3','4'])) {
    $where_parts[] = "sec.section_name LIKE '% {$filter_year}%'";
}

$where_sql = implode(' AND ', $where_parts);

/* =================================================
   FETCH SCHEDULES
================================================= */
$schedules_result = $conn->query("
    SELECT s.*, 
           sec.section_name, sec.program,
           sub.subject_name, sub.subject_code,
           f.first_name, f.last_name,
           r.room_name
    FROM schedules s
    JOIN sections sec ON s.section_id = sec.section_id
    JOIN subjects sub ON s.subject_id = sub.subject_id
    JOIN faculty f ON s.faculty_id = f.faculty_id
    LEFT JOIN rooms r ON s.room_id = r.room_id
    WHERE $where_sql
    ORDER BY 
        CASE s.day_of_week 
            WHEN 'Monday'    THEN 1 
            WHEN 'Tuesday'   THEN 2 
            WHEN 'Wednesday' THEN 3 
            WHEN 'Thursday'  THEN 4 
            WHEN 'Friday'    THEN 5 
            WHEN 'Saturday'  THEN 6 
            ELSE 7 
        END,
        s.start_time
")->fetchAll();

$schedule_data = [];
$all_schedules = [];
if (!empty($schedules_result)) {
    foreach ($schedules_result as $row) {
        if (empty($row['room_name'])) {
            $roomQuery = $conn->prepare("
                SELECT r.room_name 
                FROM room_assignments ra 
                JOIN rooms r ON ra.room_id = r.room_id 
                WHERE ra.section_id = ? 
                LIMIT 1
            ");
            $roomQuery->execute([$row['section_id']]);
            $roomData = $roomQuery->fetch();
            $row['room_name'] = $roomData ? $roomData['room_name'] : '(No Assigned Room Yet)';
        }
        $day  = $row['day_of_week'];
        $time = date("H:i", strtotime($row['start_time']));
        $schedule_data[$day][$time][] = $row;
        $all_schedules[] = $row;
    }
}

function generate_time_options($start = 6, $end = 21, $interval = 30) {
    $times = [];
    for ($h = $start; $h <= $end; $h++) {
        for ($m = 0; $m < 60; $m += $interval) {
            if ($h === $end && $m > 0) continue;
            $time24 = sprintf("%02d:%02d", $h, $m);
            $time12 = date("h:i A", strtotime($time24));
            $times[$time24] = $time12;
        }
    }
    return $times;
}

$time_options = generate_time_options(6, 21, 30);

$time_slots = [];
for ($h = 6; $h <= 21; $h++) {
    $time_slots[] = sprintf("%02d:00", $h);
    $time_slots[] = sprintf("%02d:30", $h);
}
array_pop($time_slots);

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

/* =================================================
   DROPDOWNS
================================================= */
$faculty_list  = $conn->query("SELECT * FROM faculty WHERE status='Active' ORDER BY last_name")->fetchAll();
$sections_list = $conn->query("
    SELECT sec.section_id, sec.section_name, sec.program, r.room_code
    FROM sections sec
    LEFT JOIN room_assignments ra ON sec.section_id = ra.section_id
    LEFT JOIN rooms r ON ra.room_id = r.room_id
    WHERE sec.status='Active'
    ORDER BY sec.section_name
")->fetchAll();
$all_subs = $conn->query("SELECT * FROM subjects WHERE status='Active'")->fetchAll();

$active_tab = $_GET['tab'] ?? 'grid';

function build_filter_qs($extra = []) {
    global $filter_department, $filter_year;
    $params = [];
    if ($filter_department) $params['department'] = $filter_department;
    if ($filter_year)       $params['year']       = $filter_year;
    foreach ($extra as $k => $v) $params[$k] = $v;
    return $params ? '?' . http_build_query($params) : '';
}
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

    <!-- FILTER BY DEPARTMENT & YEAR -->
    <div class="content-card mb-4">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <label class="form-label mb-0" style="white-space:nowrap;">
                <i class="bi bi-building me-1"></i>Department:
            </label>
            <select name="department" class="form-select" style="max-width:280px;" onchange="this.form.submit()">
                <option value="">— All Departments —</option>
                <?php foreach ($departmentLabels as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $filter_department === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="form-label mb-0" style="white-space:nowrap;">
                <i class="bi bi-mortarboard me-1"></i>Year Level:
            </label>
            <select name="year" class="form-select" style="max-width:160px;" onchange="this.form.submit()">
                <option value="">— All Years —</option>
                <option value="1" <?= $filter_year === '1' ? 'selected' : '' ?>>1st Year</option>
                <option value="2" <?= $filter_year === '2' ? 'selected' : '' ?>>2nd Year</option>
                <option value="3" <?= $filter_year === '3' ? 'selected' : '' ?>>3rd Year</option>
                <option value="4" <?= $filter_year === '4' ? 'selected' : '' ?>>4th Year</option>
            </select>
            <?php if ($filter_department || $filter_year): ?>
                <a href="schedules.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn-secondary-custom">
                    <i class="bi bi-x-lg me-1"></i>Clear Filters
                </a>
                <span class="filter-active-badge">
                    <?php
                    $badge_parts = [];
                    if ($filter_department) $badge_parts[] = $departmentLabels[$filter_department] ?? $filter_department;
                    if ($filter_year)       $badge_parts[] = $filter_year . ($filter_year == 1 ? 'st' : ($filter_year == 2 ? 'nd' : ($filter_year == 3 ? 'rd' : 'th'))) . ' Year';
                    echo htmlspecialchars(implode(' · ', $badge_parts));
                    ?>
                </span>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABS -->
    <div class="content-card mb-4">
        <div class="schedule-tabs mb-0">
            <a href="schedules.php<?= build_filter_qs(['tab' => 'grid']) ?>" 
               class="schedule-tab <?= $active_tab === 'grid' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Timetable Grid
            </a>
            <a href="schedules.php<?= build_filter_qs(['tab' => 'list']) ?>" 
               class="schedule-tab <?= $active_tab === 'list' ? 'active' : '' ?>">
                <i class="bi bi-list-ul me-1"></i> Schedule List
            </a>
        </div>
    </div>

<?php if ($active_tab === 'grid'): ?>
<div class="content-card mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 style="color:var(--text-primary); font-weight:700; font-family:var(--font-display); margin:0;">
            <i class="bi bi-grid-3x3-gap-fill me-2" style="color:var(--accent);"></i>
            Timetable Grid
            <?php if ($filter_department || $filter_year): ?>
                <span style="color:var(--accent); font-size:13px; font-weight:500;">
                    — <?php
                    $parts = [];
                    if ($filter_department) $parts[] = $departmentLabels[$filter_department] ?? $filter_department;
                    if ($filter_year)       $parts[] = $filter_year . ($filter_year==1?'st':($filter_year==2?'nd':($filter_year==3?'rd':'th'))) . ' Year';
                    echo htmlspecialchars(implode(', ', $parts));
                    ?>
                </span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="table-responsive">
        <table class="timetable-grid">
            <thead>
                <tr>
                    <th class="time-col">Time</th>
                    <?php foreach ($days as $day): ?><th><?= $day ?></th><?php endforeach; ?>
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
                            <div class="timetable-block <?= $sc['status'] === 'Cancelled' ? 'cancelled-block' : '' ?>">
                                <div class="tb-subject"><?= htmlspecialchars($sc['subject_code'] ?? $sc['subject_name']) ?></div>
                                <div class="tb-section"><?= htmlspecialchars($sc['section_name']) ?></div>
                                <div class="tb-info"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($sc['first_name'].' '.$sc['last_name']) ?></div>
                                <div class="tb-info"><i class="bi bi-door-open-fill"></i> <?= htmlspecialchars($sc['room_name'] ?? '(No Assigned Room Yet)') ?></div>
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

<?php elseif ($active_tab === 'list'): ?>
<div class="content-card">
    <h5 class="mb-3" style="color:var(--text-primary); font-weight:700; font-family:var(--font-display);">
        <i class="bi bi-list-ul me-2" style="color:var(--accent);"></i>Schedule List
        <?php if ($filter_department || $filter_year): ?>
            <span style="color:var(--accent); font-size:13px; font-weight:500;">
                — <?php
                $parts = [];
                if ($filter_department) $parts[] = $departmentLabels[$filter_department] ?? $filter_department;
                if ($filter_year)       $parts[] = $filter_year . ($filter_year==1?'st':($filter_year==2?'nd':($filter_year==3?'rd':'th'))) . ' Year';
                echo htmlspecialchars(implode(', ', $parts));
                ?>
            </span>
        <?php endif; ?>
    </h5>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Section</th><th>Subject</th><th>Faculty</th><th>Room</th>
                    <th>Day</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_schedules)): ?>
                    <?php foreach ($all_schedules as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['room_name'] ?? '(No Assigned Room Yet)') ?></td>
                        <td><?= htmlspecialchars($row['day_of_week']) ?></td>
                        <td><?= date("h:i A", strtotime($row['start_time'])) ?></td>
                        <td><?= date("h:i A", strtotime($row['end_time'])) ?></td>
                        <td>
                            <?php if ($row['status'] === 'Active'): ?>
                                <span class="badge-success"><?= htmlspecialchars($row['status']) ?></span>
                            <?php else: ?>
                                <span class="badge-cancelled"><?= htmlspecialchars($row['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon btn-primary" title="Edit" 
                                onclick='openEditSchedule(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon btn-danger" title="Delete" 
                                onclick="confirmDeleteSchedule(<?= $row['schedule_id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center py-3">No schedules found for this term.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div><!-- end main-content -->


<!-- ================================================================
     ADD MODAL
================================================================ -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_schedule" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Add New Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section / Room</label>
                            <select name="section_id" id="add_section_id" class="form-select" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections_list as $sr): ?>
                                    <option value="<?= $sr['section_id'] ?>"
                                        data-program="<?= htmlspecialchars($sr['program']) ?>">
                                        <?= htmlspecialchars($sr['section_name'] . ' / ' . ($sr['room_code'] ?? 'No Room')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" id="add_subject_id" class="form-select" required>
                                <option value="">Select Section first</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Faculty</label>
                            <select name="faculty_id" id="add_faculty_id" class="form-select" required>
                                <option value="">Select Section first</option>
                            </select>
                            <small class="text-muted" id="add_faculty_hint"></small>
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
                            <select name="start_time" id="add_start_time" class="form-select" required>
                                <option value="">Select Start Time</option>
                                <?php foreach ($time_options as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <select name="end_time" id="add_end_time" class="form-select" required>
                                <option value="">— Select Start Time first —</option>
                            </select>
                            <small class="text-muted" id="add_end_hint"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg me-1"></i>Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ================================================================
     EDIT MODAL
================================================================ -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editScheduleForm">
                <input type="hidden" name="edit_schedule" value="1">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Section / Room</label>
                            <input type="text" id="edit_section_display" class="form-control" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" id="edit_subject_display" class="form-control" readonly>
                            <input type="hidden" id="edit_subject_units" value="3">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Faculty</label>
                            <select name="faculty_id" id="edit_faculty_id" class="form-select" required>
                                <option value="">Loading...</option>
                            </select>
                            <small class="text-muted" id="edit_faculty_hint"></small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Day</label>
                            <select name="day_of_week" id="edit_day_of_week" class="form-select" required>
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
                            <select name="start_time" id="edit_start_time" class="form-select" required>
                                <option value="">Select Start Time</option>
                                <?php foreach ($time_options as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <select name="end_time" id="edit_end_time" class="form-select" required>
                                <option value="">Auto-set by start time</option>
                            </select>
                            <small class="text-muted" id="edit_end_hint"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- DELETE FORM (hidden) -->
<form method="POST" id="deleteScheduleForm" style="display:none;">
    <input type="hidden" name="delete_schedule" value="1">
    <input type="hidden" name="schedule_id" id="delete_schedule_id">
</form>


<script>
const allSubjects = <?= json_encode($all_subs) ?>;
const allFaculty  = <?= json_encode($faculty_list) ?>;

const programToSubjectPrefix = {
    'BSIT':   'BSIT',
    'BSTM':   'BSTM',
    'BSBA':   'BSBA',
    'BSCRIM': 'BSCR',
    'BSCE':   'BSCE'
};
const programToDepartment = {
    'BSIT':   'College of Computer Studies (CCS)',
    'BSTM':   'College of Hospitality and Tourism Management (CHTM)',
    'BSBA':   'College of Business Administration (CBA)',
    'BSCRIM': 'College of Criminal Justice Education (CCJE)',
    'BSCE':   'College of Engineering (COE)'
};
const programToShortDept = {
    'BSIT':   'CCS Department',
    'BSTM':   'CHTM Department',
    'BSBA':   'CBA Department',
    'BSCRIM': 'CCJE Department',
    'BSCE':   'COE Department'
};

// ── Tracks selected subject units for each modal ──
let addSelectedUnits = 3;  // default
let editSelectedUnits = 3; // default

// ── Format minutes into HH:MM ──
function minsToVal(total) {
    const h = Math.floor(total / 60);
    const m = total % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

// ── Format HH:MM to 12hr display ──
function fmt12(val) {
    const [h24, m] = val.split(':').map(Number);
    const ampm = h24 >= 12 ? 'PM' : 'AM';
    const h12  = h24 % 12 || 12;
    return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

/**
 * Set the end time dropdown based on start time + subject units.
 * Auto-selects the calculated end time. No units text shown.
 * @param {string} startVal  - HH:MM value
 * @param {string} endId     - end time select element ID
 * @param {string} hintId    - small hint element ID
 * @param {number} units     - subject units (2 or 3)
 * @param {string} currentEndVal - pre-select this value if set (edit mode)
 */
function setEndTime(startVal, endId, hintId, units, currentEndVal) {
    const endSel  = document.getElementById(endId);
    const hintEl  = document.getElementById(hintId);

    endSel.innerHTML = '';

    if (!startVal) {
        endSel.innerHTML = '<option value="">— Select Start Time first —</option>';
        if (hintEl) hintEl.textContent = '';
        return;
    }

    const [startH, startM] = startVal.split(':').map(Number);
    const startTotal = startH * 60 + startM;

    // End time = start + (units × 60 minutes)
    // Each unit = 1 hour, so 3 units = 3 hours
    const endTotal = startTotal + (units * 60);
    const endH     = Math.floor(endTotal / 60);
    const endM     = endTotal % 60;

    // Check operating hours limit (max 21:00)
    if (endH > 21 || (endH === 21 && endM > 0)) {
        endSel.innerHTML = '<option value="">Start time too late for ' + units + '-unit subject</option>';
        if (hintEl) {
            hintEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1" style="color:#f59e0b;"></i>Choose an earlier start time.';
        }
        return;
    }

    const endVal = minsToVal(endTotal);

    const opt       = document.createElement('option');
    opt.value       = endVal;
    opt.textContent = fmt12(endVal);
    opt.selected    = true;
    endSel.appendChild(opt);

    if (hintEl) {
        hintEl.innerHTML = '<i class="bi bi-clock-fill me-1" style="color:var(--accent);"></i>' +
            'Auto-set: <strong>' + fmt12(startVal) + '</strong> → <strong>' + fmt12(endVal) + '</strong> (' + units + ' units)';
    }
}

// ── FACULTY DROPDOWN ──
function populateFacultyDropdown(selectEl, hintEl, program, selectedFacultyId) {
    selectEl.innerHTML = '';
    hintEl.textContent = '';
    const department = programToDepartment[program] ?? '';
    const shortDept  = programToShortDept[program]  ?? '';
    const emptyOpt   = document.createElement('option');
    emptyOpt.value   = '';
    emptyOpt.textContent = '— Select Faculty —';
    selectEl.appendChild(emptyOpt);
    let count = 0;
    allFaculty.forEach(fac => {
        if (fac.department.trim() === department.trim()) {
            const opt       = document.createElement('option');
            opt.value       = fac.faculty_id;
            opt.textContent = fac.last_name + ', ' + fac.first_name;
            if (String(fac.faculty_id) === String(selectedFacultyId)) opt.selected = true;
            selectEl.appendChild(opt);
            count++;
        }
    });
    if (count > 0) {
        hintEl.innerHTML = '<i class="bi bi-funnel-fill me-1"></i>Showing <strong>' + count +
            '</strong> faculty from <strong>' + shortDept + '</strong>';
    } else {
        hintEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1" style="color:#f59e0b;"></i>No active faculty found in ' + shortDept;
    }
}

// ── SUBJECT DROPDOWN ──
function populateSubjectDropdown(selectEl, program, sectionCode) {
    selectEl.innerHTML = '<option value="">Select Subject</option>';
    const digits   = sectionCode.replace(/[^0-9]/g, '');
    const yearNum  = digits[0] ?? '';
    const semNum   = digits[1] ?? '';
    const yearMap  = { '1': '1st Year', '2': '2nd Year', '3': '3rd Year', '4': '4th Year' };
    const semMap   = { '1': '1st Sem',  '2': '2nd Sem' };
    const yearText = yearMap[yearNum] ?? '';
    const semText  = semMap[semNum]  ?? '';
    const prefix   = programToSubjectPrefix[program] ?? program;
    let count = 0;
    allSubjects.forEach(sub => {
        if (sub.subject_code.startsWith(prefix) &&
            sub.year_level.includes(yearText) &&
            sub.year_level.includes(semText)) {
            const opt       = document.createElement('option');
            opt.value       = sub.subject_id;
            opt.textContent = sub.subject_name;
            opt.dataset.units = sub.units ?? 3;
            selectEl.appendChild(opt);
            count++;
        }
    });
    if (count === 0) {
        selectEl.innerHTML = '<option value="">No subjects found for this section</option>';
    }
}

// ── ADD MODAL: section change → populate subjects + faculty ──
const addSectionSel  = document.getElementById('add_section_id');
const addSubjectSel  = document.getElementById('add_subject_id');
const addFacultySel  = document.getElementById('add_faculty_id');
const addFacultyHint = document.getElementById('add_faculty_hint');
const addStartSel    = document.getElementById('add_start_time');
const addEndSel      = document.getElementById('add_end_time');

addSectionSel.addEventListener('change', function () {
    const opt      = this.options[this.selectedIndex];
    const program  = opt.dataset.program ?? '';
    const secLabel = opt.textContent.trim().split(' / ')[0].trim();

    addSubjectSel.innerHTML = '<option value="">Select Subject</option>';
    addFacultySel.innerHTML = '<option value="">Select Faculty</option>';
    addFacultyHint.textContent = '';
    addEndSel.innerHTML = '<option value="">— Select Start Time first —</option>';
    document.getElementById('add_end_hint').textContent = '';
    addSelectedUnits = 3;

    if (!program) return;
    populateSubjectDropdown(addSubjectSel, program, secLabel);
    populateFacultyDropdown(addFacultySel, addFacultyHint, program, '');
});

// ADD MODAL: subject change → update units + recalculate end time
addSubjectSel.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    addSelectedUnits = parseInt(opt.dataset.units) || 3;
    // Recalculate end time if start time already selected
    if (addStartSel.value) {
        setEndTime(addStartSel.value, 'add_end_time', 'add_end_hint', addSelectedUnits, '');
    }
});

// ADD MODAL: start time change → auto-set end time based on subject units
addStartSel.addEventListener('change', function () {
    setEndTime(this.value, 'add_end_time', 'add_end_hint', addSelectedUnits, '');
});

// EDIT MODAL: start time change → recalculate end time based on stored subject units
document.getElementById('edit_start_time').addEventListener('change', function () {
    setEndTime(this.value, 'edit_end_time', 'edit_end_hint', editSelectedUnits, '');
});

// ── OPEN EDIT MODAL ──
function openEditSchedule(data) {
    document.getElementById('edit_schedule_id').value     = data.schedule_id;
    document.getElementById('edit_section_display').value = data.section_name + ' / ' + (data.room_name ?? '(No Assigned Room Yet)');
    document.getElementById('edit_subject_display').value = data.subject_name;
    document.getElementById('edit_status').value          = data.status;
    document.getElementById('edit_day_of_week').value     = data.day_of_week;

    // Get subject units from allSubjects array
    const subjectMatch = allSubjects.find(s => String(s.subject_id) === String(data.subject_id));
    editSelectedUnits  = subjectMatch ? (parseInt(subjectMatch.units) || 3) : 3;
    document.getElementById('edit_subject_units').value = editSelectedUnits;

    // Set start time first, then auto-calculate end time
    const startVal = data.start_time ? data.start_time.substring(0, 5) : '';
    document.getElementById('edit_start_time').value = startVal;

    // Auto-set end time based on subject units (pre-select the stored end time)
    const storedEnd = data.end_time ? data.end_time.substring(0, 5) : '';
    setEndTime(startVal, 'edit_end_time', 'edit_end_hint', editSelectedUnits, storedEnd);

    // Populate faculty
    populateFacultyDropdown(
        document.getElementById('edit_faculty_id'),
        document.getElementById('edit_faculty_hint'),
        data.program ?? '',
        data.faculty_id
    );

    new bootstrap.Modal(document.getElementById('editScheduleModal')).show();
}

// ── DELETE ──
function confirmDeleteSchedule(id) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        document.getElementById('delete_schedule_id').value = id;
        document.getElementById('deleteScheduleForm').submit();
    }
}
</script>

<style>
.filter-active-badge {
    background: rgba(79,163,255,0.12);
    border: 1px solid rgba(79,163,255,0.3);
    color: var(--accent);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.timetable-grid { width:100%; border-collapse:collapse; font-size:12px; min-width:900px; }
.timetable-grid thead th { background:var(--color-surface2); color:var(--text-secondary); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.07em; padding:10px 8px; text-align:center; border:1px solid var(--color-border); }
.timetable-grid thead th.time-col { width:80px; }
.timetable-grid tbody td { border:1px solid var(--color-border); vertical-align:top; padding:4px; min-height:48px; }
.time-label { background:var(--color-surface2); color:var(--text-secondary); font-size:11px; font-weight:600; text-align:center; white-space:nowrap; padding:8px 6px !important; width:80px; }
.timetable-cell { background:var(--color-surface); min-width:130px; min-height:48px; }
.timetable-cell:hover { background:rgba(255,255,255,0.02); }
.timetable-block { background:linear-gradient(135deg,rgba(79,163,255,0.15),rgba(79,163,255,0.08)); border:1px solid rgba(79,163,255,0.25); border-left:3px solid var(--accent); border-radius:6px; padding:6px 8px; margin-bottom:3px; }
.tb-subject { font-weight:700; color:var(--accent); font-size:12px; font-family:var(--font-display); margin-bottom:2px; }
.tb-section { font-weight:600; color:var(--text-primary); font-size:11px; margin-bottom:3px; }
.tb-info { color:var(--text-secondary); font-size:10px; display:flex; align-items:center; gap:3px; margin-bottom:2px; }
.tb-time { font-size:10px; font-weight:600; color:var(--text-secondary); }
.cancelled-block { background:#f8d7da; border-color:#dc3545; color:#721c24; }
.schedule-tabs { display:flex; gap:10px; margin-bottom:1rem; }
.schedule-tab { padding:6px 12px; border-radius:6px; text-decoration:none; color:var(--text-secondary); font-weight:600; background:var(--color-surface2); }
.schedule-tab.active { background:var(--accent); color:#fff; }
.custom-table td .btn-icon { margin:0 2px; }
.custom-table td:last-child { text-align:center; }
.badge-cancelled { background:rgba(239,68,68,0.12); color:#ef4444; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
</style>

<?php include '../includes/footer.php'; ?>