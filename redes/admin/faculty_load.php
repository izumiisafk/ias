<?php
require_once 'includes/auth.php'; 
require_once '../config/db.php';
$page_title = 'Faculty Load - Class Scheduling System';

// ================================================================
// GET ACTIVE TERM
// ================================================================
$activeTerm     = $conn->query("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=TRUE LIMIT 1")->fetch();
$active_term_id = $activeTerm ? $activeTerm['term_id'] : 0;
$term_label     = $activeTerm ? $activeTerm['semester'] . ' (' . $activeTerm['academic_year'] . ')' : 'No Active Term';

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
        f.job_type,
        f.total_units,
        f.status,
        COUNT(s.schedule_id) AS total_schedules,
        COALESCE(SUM(sub.units), 0) AS total_units_assigned
    FROM faculty f
    LEFT JOIN schedules s ON f.faculty_id = s.faculty_id 
        AND s.status = 'Active'
        AND s.term_id = $active_term_id
    LEFT JOIN subjects sub ON s.subject_id = sub.subject_id
    GROUP BY f.faculty_id, f.faculty_code, f.first_name, f.last_name,
             f.department, f.email, f.phone, f.job_type, f.total_units, f.status
    ORDER BY f.department, f.last_name, f.first_name
");

// ================================================================
// SUMMARY STATS — compares assigned units against each faculty's total_units
// ================================================================
$stats = $conn->query("
    SELECT
        COUNT(DISTINCT f.faculty_id) AS total_faculty,
        COUNT(DISTINCT CASE WHEN f.status = 'Active' THEN f.faculty_id END) AS active_faculty,
        COUNT(DISTINCT CASE 
            WHEN COALESCE(assigned.total_units_assigned, 0) > f.total_units 
            THEN f.faculty_id 
        END) AS overloaded_faculty,
        COUNT(DISTINCT CASE 
            WHEN COALESCE(assigned.total_units_assigned, 0) = 0 
            AND f.status = 'Active'
            THEN f.faculty_id 
        END) AS unassigned_faculty
    FROM faculty f
    LEFT JOIN (
        SELECT s.faculty_id, 
               SUM(sub.units) AS total_units_assigned
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.subject_id
        WHERE s.status = 'Active'
          AND s.term_id = $active_term_id
        GROUP BY s.faculty_id
    ) assigned ON f.faculty_id = assigned.faculty_id
")->fetch();

// ================================================================
// FILTER BY DEPARTMENT
// ================================================================
$filter_dept = $_GET['department'] ?? '';

if ($filter_dept) {
    $stmt = $conn->prepare("
        SELECT 
            f.faculty_id,
            f.faculty_code,
            f.first_name,
            f.last_name,
            f.department,
            f.email,
            f.phone,
            f.job_type,
            f.total_units,
            f.status,
            COUNT(s.schedule_id) AS total_schedules,
            COALESCE(SUM(sub.units), 0) AS total_units_assigned
        FROM faculty f
        LEFT JOIN schedules s ON f.faculty_id = s.faculty_id 
            AND s.status = 'Active'
            AND s.term_id = ?
        LEFT JOIN subjects sub ON s.subject_id = sub.subject_id
        WHERE f.department = ?
        GROUP BY f.faculty_id, f.faculty_code, f.first_name, f.last_name,
                 f.department, f.email, f.phone, f.job_type, f.total_units, f.status
        ORDER BY f.last_name, f.first_name
    ");
    $stmt->execute([$active_term_id, $filter_dept]);
    $faculty_loads = $stmt;
}

// Collect rows into array
$faculty_rows = [];
if ($faculty_loads) {
    while ($row = $faculty_loads->fetch()) {
        $faculty_rows[] = $row;
    }
}

// ================================================================
// FETCH ALL SCHEDULES FOR MODAL VIEWER (includes subject units)
// ================================================================
$sched_map  = [];
$all_scheds = $conn->query("
    SELECT s.faculty_id, s.day_of_week, s.start_time, s.end_time,
           sub.subject_name, sub.subject_code, sub.units,
           sec.section_name
    FROM schedules s
    JOIN subjects sub ON s.subject_id = sub.subject_id
    JOIN sections sec ON s.section_id = sec.section_id
    WHERE s.status = 'Active' AND s.term_id = $active_term_id
    ORDER BY 
        CASE s.day_of_week 
            WHEN 'Monday'    THEN 1 
            WHEN 'Tuesday'   THEN 2 
            WHEN 'Wednesday' THEN 3 
            WHEN 'Thursday'  THEN 4 
            WHEN 'Friday'    THEN 5 
            WHEN 'Saturday'  THEN 6 
            ELSE 7 
        END, s.start_time
");
if ($all_scheds) {
    while ($sr = $all_scheds->fetch()) {
        $sched_map[$sr['faculty_id']][] = $sr;
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
                <option value="College of Computer Studies (CCS)"                    <?= $filter_dept === 'College of Computer Studies (CCS)' ? 'selected' : '' ?>>College of Computer Studies (CCS)</option>
                <option value="College of Hospitality and Tourism Management (CHTM)" <?= $filter_dept === 'College of Hospitality and Tourism Management (CHTM)' ? 'selected' : '' ?>>College of Hospitality and Tourism Management (CHTM)</option>
                <option value="College of Business Administration (CBA)"             <?= $filter_dept === 'College of Business Administration (CBA)' ? 'selected' : '' ?>>College of Business Administration (CBA)</option>
                <option value="College of Criminal Justice Education (CCJE)"         <?= $filter_dept === 'College of Criminal Justice Education (CCJE)' ? 'selected' : '' ?>>College of Criminal Justice Education (CCJE)</option>
                <option value="College of Engineering (COE)"                         <?= $filter_dept === 'College of Engineering (COE)' ? 'selected' : '' ?>>College of Engineering (COE)</option>
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
                        <th>Job Type</th>
                        <th>Schedules</th>
                        <th>Units Assigned</th>
                        <th>Total Units</th>
                        <th>Load Progress</th>
                        <th>Load Status</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faculty_rows)): ?>
                        <tr><td colspan="10" class="text-center py-4">No faculty found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($faculty_rows as $row):

                        $job_type = $row['job_type'] ?? 'Full-time';

                        // Use actual stored total_units; fallback to job type default only if 0/missing
                        $max_units = intval($row['total_units']) > 0
                            ? intval($row['total_units'])
                            : ($job_type === 'Part-time' ? 18 : 39);

                        // Units assigned = SUM of subject units (integer, no decimals)
                        $assigned = intval($row['total_units_assigned']);
                        $pct      = $max_units > 0 ? min(($assigned / $max_units) * 100, 100) : 0;

                        // Determine load status
                        if ($assigned === 0) {
                            $load_status = 'No Load';
                            $load_class  = 'load-none';
                            $bar_color   = '#94a3b8';
                            $badge_class = 'badge-secondary';
                        } elseif ($assigned > $max_units) {
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
                        <td>
                            <?php if ($job_type === 'Full-time'): ?>
                                <span class="badge-fulltime">Full-time</span>
                            <?php else: ?>
                                <span class="badge-parttime">Part-time</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-weight:700;"><?= intval($row['total_schedules']) ?></td>
                        <td style="text-align:center; font-weight:700; color:<?= $bar_color ?>;">
                            <?= $assigned ?> units
                        </td>
                        <td style="text-align:center;">
                            <span class="units-max-badge"><?= $max_units ?> units</span>
                        </td>
                        <td style="min-width:160px;">
                            <div class="load-bar-wrap">
                                <div class="load-bar-track">
                                    <div class="load-bar-fill" style="width:<?= $pct ?>%; background:<?= $bar_color ?>;"></div>
                                </div>
                                <span class="load-bar-pct"><?= round($pct) ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="<?= $badge_class ?>"><?= $load_status ?></span>
                            <?php if ($assigned > $max_units): ?>
                                <div style="font-size:10px; color:#ef4444; margin-top:2px;">
                                    +<?= ($assigned - $max_units) ?> units over limit
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
                                onclick="viewFacultySchedule(<?= $row['faculty_id'] ?>, '<?= htmlspecialchars($row['first_name'].' '.$row['last_name'], ENT_QUOTES) ?>')">
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
            <div class="modal-footer" id="facultyScheduleFooter" style="display:none;">
                <div class="total-units-summary">
                    <i class="bi bi-calculator me-1"></i>
                    Total Units Assigned: <strong id="modalTotalUnits">0</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const facultyScheduleData = <?= json_encode($sched_map) ?>;

function viewFacultySchedule(facultyId, name) {
    document.getElementById('facultyScheduleTitle').innerHTML =
        '<i class="bi bi-calendar3 me-2"></i>' + name + ' — Schedule';

    const scheds    = facultyScheduleData[facultyId] ?? [];
    const footer    = document.getElementById('facultyScheduleFooter');
    let html        = '';
    let totalUnits  = 0;

    if (scheds.length === 0) {
        html = '<div class="text-center py-4 text-muted">' +
               '<i class="bi bi-calendar-x" style="font-size:32px; opacity:0.3;"></i>' +
               '<p class="mt-2">No active schedules for this faculty this term.</p>' +
               '</div>';
        footer.style.display = 'none';
    } else {
        html = '<div class="table-responsive">' +
               '<table class="custom-table"><thead><tr>' +
               '<th>Day</th><th>Subject</th><th>Section</th>' +
               '<th>Start</th><th>End</th><th>Units</th>' +
               '</tr></thead><tbody>';

        scheds.forEach(s => {
            const start = s.start_time.substring(0, 5);
            const end   = s.end_time.substring(0, 5);
            const units = parseInt(s.units) || 3;
            totalUnits += units;

            const fmt = t => {
                let [h, m] = t.split(':').map(Number);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
            };

            html += '<tr>' +
                '<td><strong>' + s.day_of_week + '</strong></td>' +
                '<td>' + s.subject_code + ' — ' + s.subject_name + '</td>' +
                '<td>' + s.section_name + '</td>' +
                '<td>' + fmt(start) + '</td>' +
                '<td>' + fmt(end)   + '</td>' +
                '<td><span class="units-max-badge">' + units + ' units</span></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';

        document.getElementById('modalTotalUnits').textContent = totalUnits + ' units';
        footer.style.display = 'flex';
        footer.style.justifyContent = 'flex-start';
    }

    document.getElementById('facultyScheduleBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('facultyScheduleModal')).show();
}
</script>

<style>
/* Load progress bar */
.load-bar-wrap  { display:flex; align-items:center; gap:8px; }
.load-bar-track { flex:1; height:8px; background:var(--color-surface2); border-radius:99px; overflow:hidden; }
.load-bar-fill  { height:100%; border-radius:99px; transition:width 0.4s ease; }
.load-bar-pct   { font-size:11px; font-weight:700; color:var(--text-secondary); white-space:nowrap; min-width:32px; }

/* Row highlights */
.load-over-row { background: rgba(239,68,68,0.04) !important; }
.load-near-row { background: rgba(245,158,11,0.04) !important; }

/* Legend */
.legend-item { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-secondary); }
.legend-dot  { width:10px; height:10px; border-radius:50%; display:inline-block; }

/* No-load badge */
.badge-secondary {
    background: rgba(148,163,184,0.15); color: #94a3b8;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 600;
}

/* Job type badges */
.badge-fulltime {
    background: rgba(34,197,94,0.12); color: #22c55e;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
.badge-parttime {
    background: rgba(245,158,11,0.12); color: #f59e0b;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}

/* Units badge */
.units-max-badge {
    background: rgba(79,163,255,0.10); color: var(--accent);
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}

/* Modal footer total units */
.total-units-summary {
    background: rgba(79,163,255,0.08);
    border: 1px solid rgba(79,163,255,0.2);
    color: var(--accent);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 13px;
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