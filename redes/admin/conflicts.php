<?php
require_once 'includes/auth.php'; 
require_once '../config/db.php';
$page_title = 'Conflicts - Class Scheduling System';

// ================================================================
// GET ACTIVE TERM
// ================================================================
$activeTermRow = $conn->query("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=1 LIMIT 1")->fetch_assoc();
$active_term_id   = $activeTermRow['term_id'] ?? 0;
$active_term_name = $activeTermRow ? $activeTermRow['semester'] . ' (' . $activeTermRow['academic_year'] . ')' : 'No Active Term';

// ================================================================
// BACKFILL: Sync room_id into schedules from room_assignments
// Fixes any schedules where room_id is NULL because the schedule
// was added before the room was assigned (or save failed silently).
// Safe to run every time — only updates rows where room_id IS NULL.
// ================================================================
$conn->query("
    UPDATE schedules s
    JOIN room_assignments ra ON ra.section_id = s.section_id
    SET s.room_id = ra.room_id
    WHERE s.room_id IS NULL
      AND ra.room_id IS NOT NULL
");

// ================================================================
// STEP 1: DETECT ALL CURRENTLY ACTIVE CONFLICTS
//
// KEY FIX: We no longer filter by same section or same program.
// - Faculty conflict = same teacher, same day, overlapping time (ANY sections)
// - Room conflict    = same room, same day, overlapping time (ANY sections/programs)
// - Section conflict = same section, same day, overlapping time (TWO subjects for same class)
// ================================================================

$current_conflicts = []; // key = "sid1_sid2_type"

// ── FACULTY conflicts: same teacher, any sections, overlapping time ──
$faculty_detect = $conn->query("
    SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
           CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
           s1.day_of_week,
           s1.start_time AS s1_start, s1.end_time AS s1_end,
           s2.start_time AS s2_start, s2.end_time AS s2_end,
           sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name,
           sec1.section_name AS sec1_name, sec2.section_name AS sec2_name,
           sec1.program AS prog1, sec2.program AS prog2
    FROM schedules s1
    JOIN schedules s2
        ON  s1.faculty_id   = s2.faculty_id
        AND s1.day_of_week  = s2.day_of_week
        AND s1.schedule_id  < s2.schedule_id
        AND s1.start_time   < s2.end_time
        AND s1.end_time     > s2.start_time
    JOIN faculty  f    ON s1.faculty_id  = f.faculty_id
    JOIN subjects sub1 ON s1.subject_id  = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id  = sub2.subject_id
    JOIN sections sec1 ON s1.section_id  = sec1.section_id
    JOIN sections sec2 ON s2.section_id  = sec2.section_id
    WHERE s1.term_id = $active_term_id
      AND s2.term_id = $active_term_id
      AND s1.status  = 'Active'
      AND s2.status  = 'Active'
");
if ($faculty_detect) {
    while ($fc = $faculty_detect->fetch_assoc()) {
        $key  = $fc['sid1'] . '_' . $fc['sid2'] . '_Faculty';
        $desc = "Faculty conflict: " . $fc['faculty_name'] . " is double-booked on " . $fc['day_of_week'] .
                " — teaching '" . $fc['sub1_name'] . "' (" . $fc['sec1_name'] . ") " .
                date('h:i A', strtotime($fc['s1_start'])) . "–" . date('h:i A', strtotime($fc['s1_end'])) .
                " overlaps with '" . $fc['sub2_name'] . "' (" . $fc['sec2_name'] . ") " .
                date('h:i A', strtotime($fc['s2_start'])) . "–" . date('h:i A', strtotime($fc['s2_end']));
        $current_conflicts[$key] = ['sid1' => $fc['sid1'], 'sid2' => $fc['sid2'], 'type' => 'Faculty', 'desc' => $desc];
    }
}

// ── ROOM conflicts: same room, ANY two sections (different programs included), overlapping time ──
// KEY: Uses COALESCE(schedule.room_id, room_assignments.room_id) so conflicts are caught
// whether the room is stored directly in schedules OR only in room_assignments table.
$room_detect = $conn->query("
    SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
           r.room_name, r.room_code, s1.day_of_week,
           s1.start_time AS s1_start, s1.end_time AS s1_end,
           s2.start_time AS s2_start, s2.end_time AS s2_end,
           sec1.section_name AS sec1_name, sec2.section_name AS sec2_name,
           sec1.program AS prog1, sec2.program AS prog2,
           sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name
    FROM schedules s1
    LEFT JOIN room_assignments ra1 ON ra1.section_id = s1.section_id
    JOIN schedules s2
        ON  s1.day_of_week  = s2.day_of_week
        AND s1.schedule_id  < s2.schedule_id
        AND s1.start_time   < s2.end_time
        AND s1.end_time     > s2.start_time
    LEFT JOIN room_assignments ra2 ON ra2.section_id = s2.section_id
    JOIN rooms    r    ON r.room_id     = COALESCE(s1.room_id, ra1.room_id)
    JOIN sections sec1 ON s1.section_id = sec1.section_id
    JOIN sections sec2 ON s2.section_id = sec2.section_id
    JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
    WHERE s1.term_id = $active_term_id
      AND s2.term_id = $active_term_id
      AND s1.status  = 'Active'
      AND s2.status  = 'Active'
      AND COALESCE(s1.room_id, ra1.room_id) IS NOT NULL
      AND COALESCE(s2.room_id, ra2.room_id) IS NOT NULL
      AND COALESCE(s1.room_id, ra1.room_id) = COALESCE(s2.room_id, ra2.room_id)
");
if ($room_detect) {
    while ($rc = $room_detect->fetch_assoc()) {
        $key  = $rc['sid1'] . '_' . $rc['sid2'] . '_Room';
        // Show program labels when sections are from different programs
        $sec1_label = $rc['sec1_name'] . ($rc['prog1'] !== $rc['prog2'] ? ' (' . $rc['prog1'] . ')' : '');
        $sec2_label = $rc['sec2_name'] . ($rc['prog1'] !== $rc['prog2'] ? ' (' . $rc['prog2'] . ')' : '');
        $desc = "Room conflict: " . $rc['room_name'] . " (" . $rc['room_code'] . ") is double-booked on " . $rc['day_of_week'] .
                " — '" . $rc['sub1_name'] . "' for " . $sec1_label . " " .
                date('h:i A', strtotime($rc['s1_start'])) . "–" . date('h:i A', strtotime($rc['s1_end'])) .
                " overlaps with '" . $rc['sub2_name'] . "' for " . $sec2_label . " " .
                date('h:i A', strtotime($rc['s2_start'])) . "–" . date('h:i A', strtotime($rc['s2_end']));
        $current_conflicts[$key] = ['sid1' => $rc['sid1'], 'sid2' => $rc['sid2'], 'type' => 'Room', 'desc' => $desc];
    }
}

// ── SECTION conflicts: same section, two subjects overlapping (different teachers/rooms OK) ──
$section_detect = $conn->query("
    SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
           sec.section_name, s1.day_of_week,
           s1.start_time AS s1_start, s1.end_time AS s1_end,
           s2.start_time AS s2_start, s2.end_time AS s2_end,
           sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name
    FROM schedules s1
    JOIN schedules s2
        ON  s1.section_id  = s2.section_id
        AND s1.day_of_week = s2.day_of_week
        AND s1.schedule_id < s2.schedule_id
        AND s1.start_time  < s2.end_time
        AND s1.end_time    > s2.start_time
    JOIN sections sec  ON s1.section_id  = sec.section_id
    JOIN subjects sub1 ON s1.subject_id  = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id  = sub2.subject_id
    WHERE s1.term_id = $active_term_id
      AND s2.term_id = $active_term_id
      AND s1.status  = 'Active'
      AND s2.status  = 'Active'
");
if ($section_detect) {
    while ($sc = $section_detect->fetch_assoc()) {
        $key  = $sc['sid1'] . '_' . $sc['sid2'] . '_Section';
        $desc = "Section conflict: " . $sc['section_name'] . " has two overlapping classes on " . $sc['day_of_week'] .
                " — '" . $sc['sub1_name'] . "' " .
                date('h:i A', strtotime($sc['s1_start'])) . "–" . date('h:i A', strtotime($sc['s1_end'])) .
                " overlaps with '" . $sc['sub2_name'] . "' " .
                date('h:i A', strtotime($sc['s2_start'])) . "–" . date('h:i A', strtotime($sc['s2_end']));
        $current_conflicts[$key] = ['sid1' => $sc['sid1'], 'sid2' => $sc['sid2'], 'type' => 'Section', 'desc' => $desc];
    }
}

// ================================================================
// STEP 2: LOAD EXISTING UNRESOLVED CONFLICTS FROM DB
// Use LEAST/GREATEST to normalize key order — prevents duplicates
// caused by reversed sid1/sid2 in old DB records.
// ================================================================

// First: clean up any duplicate unresolved entries for the same pair+type
// (keeps only the most recent one per pair)
$conn->query("
    DELETE c1 FROM conflicts c1
    INNER JOIN conflicts c2
        ON LEAST(c1.schedule_id_1, c1.schedule_id_2) = LEAST(c2.schedule_id_1, c2.schedule_id_2)
        AND GREATEST(c1.schedule_id_1, c1.schedule_id_2) = GREATEST(c2.schedule_id_1, c2.schedule_id_2)
        AND c1.conflict_type = c2.conflict_type
        AND c1.conflict_id < c2.conflict_id
    WHERE c1.status = 'Unresolved' AND c2.status = 'Unresolved'
");
$existing_unresolved = []; // normalized_key => conflict_id
$ex_res = $conn->query("
    SELECT conflict_id,
           LEAST(schedule_id_1, schedule_id_2)    AS sid_lo,
           GREATEST(schedule_id_1, schedule_id_2) AS sid_hi,
           conflict_type
    FROM conflicts
    WHERE status = 'Unresolved'
");
if ($ex_res) {
    while ($ex = $ex_res->fetch_assoc()) {
        $key = $ex['sid_lo'] . '_' . $ex['sid_hi'] . '_' . $ex['conflict_type'];
        $existing_unresolved[$key] = $ex['conflict_id'];
    }
}

// Normalize current_conflicts keys the same way (sid1 < sid2 guaranteed by SQL,
// but we normalize anyway to be safe)
$normalized_conflicts = [];
foreach ($current_conflicts as $raw_key => $cf) {
    $lo  = min($cf['sid1'], $cf['sid2']);
    $hi  = max($cf['sid1'], $cf['sid2']);
    $key = $lo . '_' . $hi . '_' . $cf['type'];
    $normalized_conflicts[$key] = $cf;
}

// ================================================================
// STEP 3: RECONCILE
// - Currently conflicting but NOT in DB → INSERT as Unresolved
// - In DB as Unresolved but NOT currently conflicting → UPDATE to Resolved (auto)
// ================================================================
foreach ($existing_unresolved as $key => $conflict_id) {
    if (!isset($normalized_conflicts[$key])) {
        $upd = $conn->prepare("
            UPDATE conflicts
            SET status = 'Resolved',
                resolved_at = NOW(),
                resolved_note = 'Auto-resolved: schedule was fixed in the timetable'
            WHERE conflict_id = ?
        ");
        $upd->bind_param("i", $conflict_id);
        $upd->execute();
    }
}

foreach ($normalized_conflicts as $key => $cf) {
    if (!isset($existing_unresolved[$key])) {
        $lo = min($cf['sid1'], $cf['sid2']);
        $hi = max($cf['sid1'], $cf['sid2']);
        $ins = $conn->prepare("
            INSERT INTO conflicts (conflict_type, schedule_id_1, schedule_id_2, description, status)
            VALUES (?, ?, ?, ?, 'Unresolved')
        ");
        $ins->bind_param("siis", $cf['type'], $lo, $hi, $cf['desc']);
        $ins->execute();
    }
}

// ================================================================
// HANDLE MANUAL MARK AS RESOLVED
// ================================================================
if (isset($_POST['resolve_conflict'])) {
    $conflict_id = intval($_POST['conflict_id']);
    $upd = $conn->prepare("
        UPDATE conflicts
        SET status = 'Resolved',
            resolved_at = NOW(),
            resolved_note = 'Manually marked as resolved by admin'
        WHERE conflict_id = ?
    ");
    $upd->bind_param("i", $conflict_id);
    $upd->execute();
    header("Location: conflicts.php");
    exit();
}

// ================================================================
// FETCH STATS + ROWS
// ================================================================
$tab = $_GET['tab'] ?? 'unresolved';

$total_conflicts   = 0;
$faculty_conflicts = 0;
$room_conflicts    = 0;
$section_conflicts = 0;
$resolved_count    = 0;

$count_res = $conn->query("
    SELECT c.conflict_type, c.status, COUNT(*) AS cnt
    FROM conflicts c
    JOIN schedules s1 ON c.schedule_id_1 = s1.schedule_id
    JOIN schedules s2 ON c.schedule_id_2 = s2.schedule_id
    WHERE (s1.term_id = $active_term_id OR s2.term_id = $active_term_id)
    GROUP BY c.conflict_type, c.status
");
if ($count_res) {
    while ($cr = $count_res->fetch_assoc()) {
        if ($cr['status'] === 'Unresolved') {
            $total_conflicts += $cr['cnt'];
            if ($cr['conflict_type'] === 'Faculty')  $faculty_conflicts += $cr['cnt'];
            if ($cr['conflict_type'] === 'Room')     $room_conflicts    += $cr['cnt'];
            if ($cr['conflict_type'] === 'Section')  $section_conflicts += $cr['cnt'];
        } else {
            $resolved_count += $cr['cnt'];
        }
    }
}

$status_filter = ($tab === 'resolved') ? 'Resolved' : 'Unresolved';
$conflict_rows = [];
$result = $conn->query("
    SELECT c.*
    FROM conflicts c
    JOIN schedules s1 ON c.schedule_id_1 = s1.schedule_id
    JOIN schedules s2 ON c.schedule_id_2 = s2.schedule_id
    WHERE c.status = '$status_filter'
      AND (s1.term_id = $active_term_id OR s2.term_id = $active_term_id)
    ORDER BY c.detected_at DESC
");
if ($result) while ($row = $result->fetch_assoc()) $conflict_rows[] = $row;
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Schedule Conflict Detection</h1>
            <p>Active Term: <strong><?= htmlspecialchars($active_term_name) ?></strong>
               — Conflicts auto-detect on every page load. Fixed schedules move to Resolved automatically.</p>
        </div>
        <a href="conflicts.php" class="btn-primary-custom">
            <i class="bi bi-arrow-clockwise me-1"></i> Re-scan Now
        </a>
    </div>

    <!-- STATS -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card red">
                <h3>Total Unresolved</h3>
                <div class="number"><?= $total_conflicts ?></div>
                <i class="bi bi-exclamation-triangle-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card orange">
                <h3>Faculty Conflicts</h3>
                <div class="number"><?= $faculty_conflicts ?></div>
                <i class="bi bi-person-fill-exclamation icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card purple">
                <h3>Room Conflicts</h3>
                <div class="number"><?= $room_conflicts ?></div>
                <i class="bi bi-door-closed-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Resolved</h3>
                <div class="number"><?= $resolved_count ?></div>
                <i class="bi bi-check-circle-fill icon"></i>
            </div>
        </div>
    </div>

    <?php if ($total_conflicts === 0 && $tab === 'unresolved'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill" style="font-size:20px;"></i>
        <div>
            <strong>No conflicts detected!</strong> All schedules for this term are clean — no double-booked faculty, rooms, or sections.
        </div>
    </div>
    <?php endif; ?>

    <div class="content-card">

        <div class="conflict-tabs mb-4">
            <a href="?tab=unresolved" class="conflict-tab <?= $tab === 'unresolved' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-circle me-1"></i> Unresolved
                <?php if ($total_conflicts > 0): ?>
                    <span class="tab-badge"><?= $total_conflicts ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=resolved" class="conflict-tab <?= $tab === 'resolved' ? 'active' : '' ?>">
                <i class="bi bi-check-circle me-1"></i> Resolved
                <?php if ($resolved_count > 0): ?>
                    <span class="tab-badge resolved-badge"><?= $resolved_count ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Detected At</th>
                        <?php if ($tab === 'resolved'): ?>
                            <th>Resolved At</th>
                            <th>How</th>
                        <?php else: ?>
                            <th>Status</th>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($conflict_rows)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <?php if ($tab === 'resolved'): ?>
                                <i class="bi bi-archive me-2 text-muted"></i>No resolved conflicts yet. Fixed schedules will appear here automatically.
                            <?php else: ?>
                                <i class="bi bi-check-circle me-2 text-success"></i>No conflicts found. Schedule is clean!
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($conflict_rows as $row): ?>
                    <tr>
                        <td>
                            <?php if ($row['conflict_type'] === 'Faculty'): ?>
                                <span class="badge-danger"><i class="bi bi-person-fill me-1"></i>Faculty</span>
                            <?php elseif ($row['conflict_type'] === 'Room'): ?>
                                <span class="badge-warning"><i class="bi bi-door-closed-fill me-1"></i>Room</span>
                            <?php else: ?>
                                <span class="badge-info"><i class="bi bi-people-fill me-1"></i>Section</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:440px; font-size:13px;">
                            <?= htmlspecialchars($row['description']) ?>
                            <div class="mt-1" style="font-size:11px; color:var(--text-secondary);">
                                Schedule #<?= $row['schedule_id_1'] ?> ↔ Schedule #<?= $row['schedule_id_2'] ?>
                                <?php if ($tab === 'unresolved'): ?>
                                &nbsp;|&nbsp;
                                <a href="schedules.php?tab=list" style="color:var(--accent);">Fix in Timetable →</a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-size:12px; white-space:nowrap;">
                            <?= date('M d, Y h:i A', strtotime($row['detected_at'])) ?>
                        </td>

                        <?php if ($tab === 'resolved'): ?>
                        <td style="font-size:12px; white-space:nowrap; color:#22c55e;">
                            <?= !empty($row['resolved_at']) ? date('M d, Y h:i A', strtotime($row['resolved_at'])) : '—' ?>
                        </td>
                        <td style="font-size:11px; max-width:180px;">
                            <?php $note = $row['resolved_note'] ?? ''; ?>
                            <?php if (strpos($note, 'Auto-resolved') !== false): ?>
                                <span style="color:#22c55e;"><i class="bi bi-magic me-1"></i>Auto — schedule was fixed</span>
                            <?php elseif (strpos($note, 'Manually') !== false): ?>
                                <span style="color:#94a3b8;"><i class="bi bi-hand-index-thumb me-1"></i>Manually dismissed</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>

                        <?php else: ?>
                        <td><span class="badge-danger">Unresolved</span></td>
                        <td>
                            <a href="schedules.php?tab=list" class="btn-icon" title="Go fix in timetable">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Dismiss this conflict without fixing the schedule?\n\nIt will be logged in the Resolved tab as manually dismissed.')">
                                <input type="hidden" name="resolve_conflict" value="1">
                                <input type="hidden" name="conflict_id" value="<?= $row['conflict_id'] ?>">
                                <button type="submit" class="btn-icon text-warning" title="Manually dismiss">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($tab === 'resolved' && !empty($conflict_rows)): ?>
        <div class="mt-3" style="font-size:12px; color:var(--text-secondary); padding:10px;">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Audit Log:</strong> All conflicts that existed this term and how they were resolved.
            <span style="color:#22c55e;"><i class="bi bi-magic me-1"></i>Auto-resolved</span> = you fixed the schedule in the timetable.
            <span style="color:#94a3b8;"><i class="bi bi-hand-index-thumb me-1"></i>Manually dismissed</span> = conflict was dismissed without fixing the schedule.
        </div>
        <?php endif; ?>
    </div>

    <!-- HOW IT WORKS -->
    <div class="content-card mt-4" style="font-size:13px;">
        <h6 style="font-weight:700; color:var(--text-primary); margin-bottom:12px;">
            <i class="bi bi-info-circle-fill me-2" style="color:var(--accent);"></i>How Conflict Detection Works
        </h6>
        <div class="row">
            <div class="col-md-4 mb-2">
                <div style="padding:12px; background:rgba(239,68,68,0.08); border-radius:8px; border-left:3px solid #ef4444;">
                    <strong style="color:#ef4444;"><i class="bi bi-person-fill me-1"></i>Faculty Conflict</strong><br>
                    <span style="color:var(--text-secondary);">Same teacher assigned to two classes on the same day with overlapping times — regardless of section or program.</span>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div style="padding:12px; background:rgba(245,158,11,0.08); border-radius:8px; border-left:3px solid #f59e0b;">
                    <strong style="color:#f59e0b;"><i class="bi bi-door-closed-fill me-1"></i>Room Conflict</strong><br>
                    <span style="color:var(--text-secondary);">Same room used by two classes at the same day and overlapping time — even if they are from different sections or programs.</span>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div style="padding:12px; background:rgba(99,102,241,0.08); border-radius:8px; border-left:3px solid #6366f1;">
                    <strong style="color:#6366f1;"><i class="bi bi-people-fill me-1"></i>Section Conflict</strong><br>
                    <span style="color:var(--text-secondary);">Same section scheduled for two different subjects at the same day and overlapping time.</span>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
.conflict-tabs { display:flex; gap:8px; border-bottom:1px solid var(--color-border); padding-bottom:0; }
.conflict-tab { padding:8px 16px; border-radius:8px 8px 0 0; text-decoration:none; font-weight:600; font-size:13px; color:var(--text-secondary); border:1px solid transparent; border-bottom:none; position:relative; bottom:-1px; display:flex; align-items:center; gap:6px; transition:all 0.15s; }
.conflict-tab:hover { color:var(--text-primary); background:rgba(255,255,255,0.03); }
.conflict-tab.active { color:var(--text-primary); background:var(--color-surface); border-color:var(--color-border); border-bottom-color:var(--color-surface); }
.tab-badge { background:#ef4444; color:#fff; border-radius:20px; padding:1px 7px; font-size:10px; font-weight:700; }
.resolved-badge { background:#22c55e; }
.badge-info { background:rgba(99,102,241,0.15); color:#6366f1; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.stats-card.orange { background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.05)); border-color:rgba(245,158,11,0.2); }
.stats-card.orange .number { color:#f59e0b; }
.stats-card.red { background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(239,68,68,0.05)); border-color:rgba(239,68,68,0.2); }
.stats-card.red .number { color:#ef4444; }
.stats-card.purple { background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(99,102,241,0.05)); border-color:rgba(99,102,241,0.2); }
.stats-card.purple .number { color:#6366f1; }
</style>

<?php include '../includes/footer.php'; ?>