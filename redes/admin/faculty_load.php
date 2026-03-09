<?php
require_once 'includes/auth.php'; 
require_once '../config/db.php';
$page_title = 'Faculty Load - Class Scheduling System';

// ================================================================
// FETCH FACULTY WITH THEIR SCHEDULE LOAD
// ================================================================
$faculty_loads = $conn->query("
    SELECT 
        f.faculty_id,
        f.faculty_code,
        f.first_name,
        f.last_name,
        f.department,
        f.email,
        f.phone,
        f.max_teaching_hours,
        f.status,
        COUNT(s.schedule_id) AS total_schedules,
        COALESCE(SUM(
            TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) / 60
        ), 0) AS total_hours_assigned
    FROM faculty f
    LEFT JOIN schedules s ON f.faculty_id = s.faculty_id 
        AND s.status = 'Active'
        AND s.term_id = (SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1)
    GROUP BY f.faculty_id
    ORDER BY f.department, f.last_name, f.first_name
");

// ================================================================
// SUMMARY STATS
// ================================================================
$stats = $conn->query("
    SELECT
        COUNT(DISTINCT f.faculty_id) AS total_faculty,
        COUNT(DISTINCT CASE WHEN f.status = 'Active' THEN f.faculty_id END) AS active_faculty,
        COUNT(DISTINCT CASE 
            WHEN COALESCE(weekly_hours.total_hours, 0) > f.max_teaching_hours 
            THEN f.faculty_id 
        END) AS overloaded_faculty,
        COUNT(DISTINCT CASE 
            WHEN COALESCE(weekly_hours.total_hours, 0) = 0 
            AND f.status = 'Active'
            THEN f.faculty_id 
        END) AS unassigned_faculty
    FROM faculty f
    LEFT JOIN (
        SELECT faculty_id, 
               SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60) AS total_hours
        FROM schedules 
        WHERE status = 'Active'
          AND term_id = (SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1)
        GROUP BY faculty_id
    ) weekly_hours ON f.faculty_id = weekly_hours.faculty_id
")->fetch_assoc();

// ================================================================
// GET ACTIVE TERM NAME
// ================================================================
$activeTerm = $conn->query("SELECT academic_year, semester FROM academic_terms WHERE is_active=1 LIMIT 1")->fetch_assoc();
$term_label = $activeTerm ? $activeTerm['semester'] . ' (' . $activeTerm['academic_year'] . ')' : 'No Active Term';

// ================================================================
// FILTER BY DEPARTMENT
// ================================================================
$filter_dept = $_GET['department'] ?? '';
$dept_where  = $filter_dept ? "HAVING f.department = '" . $conn->real_escape_string($filter_dept) . "'" : "";

// Re-fetch with filter if needed
if ($filter_dept) {
    $faculty_loads = $conn->query("
        SELECT 
            f.faculty_id,
            f.faculty_code,
            f.first_name,
            f.last_name,
            f.department,
            f.email,
            f.phone,
            f.max_teaching_hours,
            f.status,
            COUNT(s.schedule_id) AS total_schedules,
            COALESCE(SUM(
                TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) / 60
            ), 0) AS total_hours_assigned
        FROM faculty f
        LEFT JOIN schedules s ON f.faculty_id = s.faculty_id 
            AND s.status = 'Active'
            AND s.term_id = (SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1)
        WHERE f.department = '" . $conn->real_escape_string($filter_dept) . "'
        GROUP BY f.faculty_id
        ORDER BY f.last_name, f.first_name
    ");
}

// Collect rows into array so we can use it multiple times
$faculty_rows = [];
if ($faculty_loads) {
    while ($row = $faculty_loads->fetch_assoc()) {
        $faculty_rows[] = $row;
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Teacher Loading Management</h1>
            <p>Monitor faculty teaching loads • Active Term: <strong><?= htmlspecialchars($term_label) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="staff_management.php?tab=teachers" class="btn-secondary-custom">
                <i class="bi bi-person-plus me-1"></i> Manage Teachers
            </a>
        </div>
    </div>

    <!-- SUMMARY STATS -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card blue">
                <h3>Total Faculty</h3>
                <div class="number"><?= $stats['total_faculty'] ?? 0 ?></div>
                <i class="bi bi-people-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Active Faculty</h3>
                <div class="number"><?= $stats['active_faculty'] ?? 0 ?></div>
                <i class="bi bi-person-check-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card red">
                <h3>Overloaded</h3>
                <div class="number"><?= $stats['overloaded_faculty'] ?? 0 ?></div>
                <i class="bi bi-exclamation-triangle-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card purple">
                <h3>No Load Yet</h3>
                <div class="number"><?= $stats['unassigned_faculty'] ?? 0 ?></div>
                <i class="bi bi-person-dash-fill icon"></i>
            </div>
        </div>
    </div>

    <!-- FILTER BY DEPARTMENT -->
    <div class="content-card mb-4">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="form-label mb-0" style="white-space:nowrap; font-weight:600;">Filter by Department:</label>
            <select name="department" class="form-select" style="max-width:320px;" onchange="this.form.submit()">
                <option value="">— All Departments —</option>
                <option value="College of Computer Studies (CCS)"          <?= $filter_dept === 'College of Computer Studies (CCS)' ? 'selected' : '' ?>>College of Computer Studies (CCS)</option>
                <option value="College of Hospitality and Tourism Management (CHTM)" <?= $filter_dept === 'College of Hospitality and Tourism Management (CHTM)' ? 'selected' : '' ?>>College of Hospitality and Tourism Management (CHTM)</option>
                <option value="College of Business Administration (CBA)"   <?= $filter_dept === 'College of Business Administration (CBA)' ? 'selected' : '' ?>>College of Business Administration (CBA)</option>
                <option value="College of Criminal Justice Education (CCJE)" <?= $filter_dept === 'College of Criminal Justice Education (CCJE)' ? 'selected' : '' ?>>College of Criminal Justice Education (CCJE)</option>
                <option value="College of Engineering (COE)"               <?= $filter_dept === 'College of Engineering (COE)' ? 'selected' : '' ?>>College of Engineering (COE)</option>
            </select>
            <?php if ($filter_dept): ?>
                <a href="faculty_load.php" class="btn-secondary-custom">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LOAD TABLE -->
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">
                <i class="bi bi-bar-chart-fill me-2" style="color:var(--accent);"></i>
                Faculty Teaching Load
            </h5>
            <div class="load-legend d-flex gap-3">
                <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span> Normal</span>
                <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> Near Limit</span>
                <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Overloaded</span>
                <span class="legend-item"><span class="legend-dot" style="background:#94a3b8;"></span> No Load</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Department</th>
                        <th>Schedules</th>
                        <th>Hours Assigned</th>
                        <th>Max Hours</th>
                        <th>Load Progress</th>
                        <th>Load Status</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faculty_rows)): ?>
                        <tr><td colspan="9" class="text-center py-4">No faculty found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($faculty_rows as $row):
                        $assigned = round($row['total_hours_assigned'], 1);
                        $max      = $row['max_teaching_hours'];
                        $pct      = $max > 0 ? min(($assigned / $max) * 100, 100) : 0;

                        // Determine load status
                        if ($assigned == 0) {
                            $load_status = 'No Load';
                            $load_class  = 'load-none';
                            $bar_color   = '#94a3b8';
                            $badge_class = 'badge-secondary';
                        } elseif ($assigned > $max) {
                            $load_status = 'Overloaded';
                            $load_class  = 'load-over';
                            $bar_color   = '#ef4444';
                            $badge_class = 'badge-danger';
                        } elseif ($pct >= 80) {
                            $load_status = 'Near Limit';
                            $load_class  = 'load-near';
                            $bar_color   = '#f59e0b';
                            $badge_class = 'badge-warning';
                        } else {
                            $load_status = 'Normal';
                            $load_class  = 'load-ok';
                            $bar_color   = '#22c55e';
                            $badge_class = 'badge-success';
                        }
                    ?>
                    <tr class="<?= $load_class ?>-row">
                        <td>
                            <div style="font-weight:700;"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></div>
                            <div style="font-size:11px; color:var(--text-secondary);"><?= htmlspecialchars($row['faculty_code']) ?></div>
                        </td>
                        <td style="font-size:12px;"><?= htmlspecialchars($row['department']) ?></td>
                        <td style="text-align:center; font-weight:700;"><?= $row['total_schedules'] ?></td>
                        <td style="text-align:center; font-weight:700; color:<?= $bar_color ?>;">
                            <?= $assigned ?> hrs
                        </td>
                        <td style="text-align:center;"><?= $max ?> hrs</td>
                        <td style="min-width:150px;">
                            <div class="load-bar-wrap">
                                <div class="load-bar-track">
                                    <div class="load-bar-fill" style="width:<?= $pct ?>%; background:<?= $bar_color ?>;"></div>
                                </div>
                                <span class="load-bar-pct"><?= round($pct) ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="<?= $badge_class ?>"><?= $load_status ?></span>
                            <?php if ($assigned > $max): ?>
                                <div style="font-size:10px; color:#ef4444; margin-top:2px;">
                                    +<?= round($assigned - $max, 1) ?> hrs over limit
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Active'): ?>
                                <span class="badge-success">Active</span>
                            <?php elseif ($row['status'] === 'On Leave'): ?>
                                <span class="badge-warning">On Leave</span>
                            <?php else: ?>
                                <span class="badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon" title="View Schedules"
                                onclick="viewFacultySchedule(<?= $row['faculty_id'] ?>, '<?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>')">
                                <i class="bi bi-calendar3"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- VIEW SCHEDULE MODAL -->
<div class="modal fade" id="facultyScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="facultyScheduleTitle">
                    <i class="bi bi-calendar3 me-2"></i>Schedule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="facultyScheduleBody">
                <div class="text-center py-3">Loading...</div>
            </div>
        </div>
    </div>
</div>

<!-- FACULTY SCHEDULE DATA (JSON for modal) -->
<script>
const facultyScheduleData = <?php
    $sched_map = [];
    $active_term_id = $activeTerm ? $conn->query("SELECT term_id FROM academic_terms WHERE is_active=1 LIMIT 1")->fetch_assoc()['term_id'] : 0;
    $all_scheds = $conn->query("
        SELECT s.faculty_id, s.day_of_week, s.start_time, s.end_time,
               sub.subject_name, sub.subject_code,
               sec.section_name
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.subject_id
        JOIN sections sec ON s.section_id = sec.section_id
        WHERE s.status = 'Active' AND s.term_id = $active_term_id
        ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), s.start_time
    ");
    if ($all_scheds) {
        while ($sr = $all_scheds->fetch_assoc()) {
            $sched_map[$sr['faculty_id']][] = $sr;
        }
    }
    echo json_encode($sched_map);
?>;

function viewFacultySchedule(facultyId, name) {
    document.getElementById('facultyScheduleTitle').innerHTML = 
        '<i class="bi bi-calendar3 me-2"></i>' + name + ' — Schedule';

    const scheds = facultyScheduleData[facultyId] ?? [];
    let html = '';

    if (scheds.length === 0) {
        html = '<div class="text-center py-4 text-muted">No active schedules for this faculty this term.</div>';
    } else {
        html = '<div class="table-responsive"><table class="custom-table"><thead><tr>' +
               '<th>Day</th><th>Subject</th><th>Section</th><th>Start</th><th>End</th><th>Duration</th>' +
               '</tr></thead><tbody>';

        scheds.forEach(s => {
            const start = s.start_time.substring(0, 5);
            const end   = s.end_time.substring(0, 5);
            const startMin = parseInt(start.split(':')[0]) * 60 + parseInt(start.split(':')[1]);
            const endMin   = parseInt(end.split(':')[0]) * 60 + parseInt(end.split(':')[1]);
            const duration = ((endMin - startMin) / 60).toFixed(1) + ' hrs';

            // Format to 12hr
            const fmt = t => {
                let [h, m] = t.split(':').map(Number);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return h + ':' + String(m).padStart(2,'0') + ' ' + ampm;
            };

            html += '<tr>' +
                '<td><strong>' + s.day_of_week + '</strong></td>' +
                '<td>' + s.subject_code + ' - ' + s.subject_name + '</td>' +
                '<td>' + s.section_name + '</td>' +
                '<td>' + fmt(start) + '</td>' +
                '<td>' + fmt(end) + '</td>' +
                '<td>' + duration + '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
    }

    document.getElementById('facultyScheduleBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('facultyScheduleModal')).show();
}
</script>

<style>
/* Load progress bar */
.load-bar-wrap { display:flex; align-items:center; gap:8px; }
.load-bar-track { flex:1; height:8px; background:var(--color-surface2); border-radius:99px; overflow:hidden; }
.load-bar-fill  { height:100%; border-radius:99px; transition:width 0.4s ease; }
.load-bar-pct   { font-size:11px; font-weight:700; color:var(--text-secondary); white-space:nowrap; min-width:32px; }

/* Row highlights for overloaded */
.load-over-row  { background: rgba(239,68,68,0.04) !important; }
.load-near-row  { background: rgba(245,158,11,0.04) !important; }

/* Legend */
.legend-item { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-secondary); }
.legend-dot  { width:10px; height:10px; border-radius:50%; display:inline-block; }

/* Badge secondary for no load */
.badge-secondary {
    background: rgba(148,163,184,0.15);
    color: #94a3b8;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

/* Stats card red variant */
.stats-card.red {
    background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(239,68,68,0.05));
    border-color: rgba(239,68,68,0.2);
}
.stats-card.red .number { color: #ef4444; }
</style>

<?php include '../includes/footer.php'; ?>