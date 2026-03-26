<?php
require_once 'includes/auth.php';
require_once '../config/db.php';
$page_title = 'Dashboard - Class Scheduling System';

// ================================================================
// GET ACTIVE TERM
// ================================================================
$activeTermRow = $conn->query("SELECT term_id, academic_year, semester FROM academic_terms WHERE is_active=TRUE LIMIT 1")->fetch();
$active_term_id   = $activeTermRow['term_id']   ?? 0;
$active_term_name = $activeTermRow ? $activeTermRow['semester'] . ' — ' . $activeTermRow['academic_year'] : 'No Active Term';

// ================================================================
// CONFLICT DETECTION — runs on every dashboard load so the count
// is always accurate without needing to visit conflicts.php first.
// ================================================================

// BACKFILL: Sync room_id into schedules from room_assignments
$conn->query("
    UPDATE schedules s
    SET room_id = ra.room_id
    FROM room_assignments ra
    WHERE ra.section_id = s.section_id AND s.room_id IS NULL AND ra.room_id IS NOT NULL
");

$current_conflicts = [];

// ── FACULTY conflicts ──
$fq = $conn->query("
    SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
           CONCAT(f.first_name,' ',f.last_name) AS faculty_name,
           s1.day_of_week,
           s1.start_time AS s1_start, s1.end_time AS s1_end,
           s2.start_time AS s2_start, s2.end_time AS s2_end,
           sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name,
           sec1.section_name AS sec1_name, sec2.section_name AS sec2_name,
           sec1.program AS prog1, sec2.program AS prog2
    FROM schedules s1
    JOIN schedules s2
        ON  s1.faculty_id  = s2.faculty_id AND s1.day_of_week = s2.day_of_week
        AND s1.schedule_id < s2.schedule_id
        AND s1.start_time  < s2.end_time AND s1.end_time > s2.start_time
    JOIN faculty  f    ON s1.faculty_id = f.faculty_id
    JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
    JOIN sections sec1 ON s1.section_id = sec1.section_id
    JOIN sections sec2 ON s2.section_id = sec2.section_id
    WHERE s1.term_id=$active_term_id AND s2.term_id=$active_term_id
      AND s1.status='Active' AND s2.status='Active'
");
if ($fq) while ($fc = $fq->fetch()) {
    $key  = $fc['sid1'].'_'.$fc['sid2'].'_Faculty';
    $desc = "Faculty conflict: ".$fc['faculty_name']." is double-booked on ".$fc['day_of_week'].
            " — teaching '".$fc['sub1_name']."' (".$fc['sec1_name'].") ".
            date('h:i A',strtotime($fc['s1_start']))."–".date('h:i A',strtotime($fc['s1_end'])).
            " overlaps with '".$fc['sub2_name']."' (".$fc['sec2_name'].") ".
            date('h:i A',strtotime($fc['s2_start']))."–".date('h:i A',strtotime($fc['s2_end']));
    $current_conflicts[$key] = ['sid1'=>$fc['sid1'],'sid2'=>$fc['sid2'],'type'=>'Faculty','desc'=>$desc];
}

// ── ROOM conflicts ──
$rq = $conn->query("
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
        ON  s1.day_of_week = s2.day_of_week AND s1.schedule_id < s2.schedule_id
        AND s1.start_time  < s2.end_time   AND s1.end_time > s2.start_time
    LEFT JOIN room_assignments ra2 ON ra2.section_id = s2.section_id
    JOIN rooms    r    ON r.room_id     = COALESCE(s1.room_id, ra1.room_id)
    JOIN sections sec1 ON s1.section_id = sec1.section_id
    JOIN sections sec2 ON s2.section_id = sec2.section_id
    JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
    WHERE s1.term_id=$active_term_id AND s2.term_id=$active_term_id
      AND s1.status='Active' AND s2.status='Active'
      AND COALESCE(s1.room_id,ra1.room_id) IS NOT NULL
      AND COALESCE(s2.room_id,ra2.room_id) IS NOT NULL
      AND COALESCE(s1.room_id,ra1.room_id) = COALESCE(s2.room_id,ra2.room_id)
");
if ($rq) while ($rc = $rq->fetch()) {
    $key        = $rc['sid1'].'_'.$rc['sid2'].'_Room';
    $sec1_label = $rc['sec1_name'].($rc['prog1']!==$rc['prog2'] ? ' ('.$rc['prog1'].')' : '');
    $sec2_label = $rc['sec2_name'].($rc['prog1']!==$rc['prog2'] ? ' ('.$rc['prog2'].')' : '');
    $desc = "Room conflict: ".$rc['room_name']." (".$rc['room_code'].") is double-booked on ".$rc['day_of_week'].
            " — '".$rc['sub1_name']."' for ".$sec1_label." ".
            date('h:i A',strtotime($rc['s1_start']))."–".date('h:i A',strtotime($rc['s1_end'])).
            " overlaps with '".$rc['sub2_name']."' for ".$sec2_label." ".
            date('h:i A',strtotime($rc['s2_start']))."–".date('h:i A',strtotime($rc['s2_end']));
    $current_conflicts[$key] = ['sid1'=>$rc['sid1'],'sid2'=>$rc['sid2'],'type'=>'Room','desc'=>$desc];
}

// ── SECTION conflicts ──
$sq = $conn->query("
    SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
           sec.section_name, s1.day_of_week,
           s1.start_time AS s1_start, s1.end_time AS s1_end,
           s2.start_time AS s2_start, s2.end_time AS s2_end,
           sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name
    FROM schedules s1
    JOIN schedules s2
        ON  s1.section_id  = s2.section_id AND s1.day_of_week = s2.day_of_week
        AND s1.schedule_id < s2.schedule_id
        AND s1.start_time  < s2.end_time   AND s1.end_time > s2.start_time
    JOIN sections sec  ON s1.section_id = sec.section_id
    JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
    JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
    WHERE s1.term_id=$active_term_id AND s2.term_id=$active_term_id
      AND s1.status='Active' AND s2.status='Active'
");
if ($sq) while ($sc = $sq->fetch()) {
    $key  = $sc['sid1'].'_'.$sc['sid2'].'_Section';
    $desc = "Section conflict: ".$sc['section_name']." has two overlapping classes on ".$sc['day_of_week'].
            " — '".$sc['sub1_name']."' ".date('h:i A',strtotime($sc['s1_start']))."–".date('h:i A',strtotime($sc['s1_end'])).
            " overlaps with '".$sc['sub2_name']."' ".date('h:i A',strtotime($sc['s2_start']))."–".date('h:i A',strtotime($sc['s2_end']));
    $current_conflicts[$key] = ['sid1'=>$sc['sid1'],'sid2'=>$sc['sid2'],'type'=>'Section','desc'=>$desc];
}

// ── Reconcile DB ──
$conn->query("
    DELETE FROM conflicts
    WHERE conflict_id IN (
        SELECT c1.conflict_id
        FROM conflicts c1
        INNER JOIN conflicts c2
            ON LEAST(c1.schedule_id_1,c1.schedule_id_2)    = LEAST(c2.schedule_id_1,c2.schedule_id_2)
            AND GREATEST(c1.schedule_id_1,c1.schedule_id_2) = GREATEST(c2.schedule_id_1,c2.schedule_id_2)
            AND c1.conflict_type = c2.conflict_type AND c1.conflict_id < c2.conflict_id
        WHERE c1.status='Unresolved' AND c2.status='Unresolved'
    )
");
$existing_unresolved = [];
$ex_res = $conn->query("
    SELECT conflict_id,
           LEAST(schedule_id_1,schedule_id_2)    AS sid_lo,
           GREATEST(schedule_id_1,schedule_id_2) AS sid_hi,
           conflict_type
    FROM conflicts WHERE status='Unresolved'
");
if ($ex_res) while ($ex = $ex_res->fetch()) {
    $existing_unresolved[$ex['sid_lo'].'_'.$ex['sid_hi'].'_'.$ex['conflict_type']] = $ex['conflict_id'];
}
$normalized_conflicts = [];
foreach ($current_conflicts as $cf) {
    $lo = min($cf['sid1'],$cf['sid2']); $hi = max($cf['sid1'],$cf['sid2']);
    $normalized_conflicts[$lo.'_'.$hi.'_'.$cf['type']] = $cf;
}
foreach ($existing_unresolved as $key => $cid) {
    if (!isset($normalized_conflicts[$key])) {
        $u = $conn->prepare("UPDATE conflicts SET status='Resolved',resolved_at=NOW(),resolved_note='Auto-resolved: schedule was fixed in the timetable' WHERE conflict_id=?");
        $u->execute([$cid]);
    }
}
foreach ($normalized_conflicts as $key => $cf) {
    if (!isset($existing_unresolved[$key])) {
        $lo=$cf['sid1']<$cf['sid2']?$cf['sid1']:$cf['sid2'];
        $hi=$cf['sid1']<$cf['sid2']?$cf['sid2']:$cf['sid1'];
        $i = $conn->prepare("INSERT INTO conflicts (conflict_type,schedule_id_1,schedule_id_2,description,status) VALUES(?,?,?,?,'Unresolved')");
        $i->execute([$cf['type'],$lo,$hi,$cf['desc']]);
    }
}

// ================================================================
// DASHBOARD STATS (read AFTER reconciliation so counts are correct)
// ================================================================
$sections_count  = $conn->query("SELECT COUNT(*) FROM sections WHERE status='Active'")->fetchColumn() ?? 0;
$schedules_count = $conn->query("SELECT COUNT(*) FROM schedules WHERE status='Active'")->fetchColumn() ?? 0;
$rooms_count     = $conn->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn() ?? 0;
$faculty_count   = $conn->query("SELECT COUNT(*) FROM faculty WHERE status='Active'")->fetchColumn() ?? 0;
$conflicts_count = $conn->query("SELECT COUNT(*) FROM conflicts WHERE status='Unresolved'")->fetchColumn() ?? 0;

$overloaded_count = 0;
$ol = $conn->query("
    SELECT COUNT(*) as total FROM (
        SELECT f.faculty_id
        FROM faculty f
        JOIN schedules s  ON f.faculty_id = s.faculty_id
        JOIN academic_terms t ON s.term_id = t.term_id
        WHERE s.status='Active' AND t.is_active=TRUE
        GROUP BY f.faculty_id, f.max_teaching_hours
        HAVING SUM(EXTRACT(EPOCH FROM (s.end_time - s.start_time)::interval) / 3600) > f.max_teaching_hours
    ) ol
");
if ($ol && $r = $ol->fetch()) $overloaded_count = $r['total'];

$today_day   = date('l');
$today_count = $conn->query("
    SELECT COUNT(*) FROM schedules s
    JOIN academic_terms t ON s.term_id=t.term_id
    WHERE s.status='Active' AND t.is_active=TRUE AND s.day_of_week='$today_day'
")->fetchColumn() ?? 0;
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Registrar Dashboard</h1>
            <p>Class Scheduling System — Read & Edit Access</p>
        </div>
        <span class="header-badge">
            <i class="bi bi-calendar2-week me-1"></i><?= htmlspecialchars($active_term_name) ?>
        </span>
    </div>

    <!-- ROW 1 -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card blue">
                <h3>Active Sections</h3>
                <div class="number"><?= $sections_count ?></div>
                <i class="bi bi-people-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Scheduled Classes</h3>
                <div class="number"><?= $schedules_count ?></div>
                <i class="bi bi-calendar-check-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card orange">
                <h3>Available Rooms</h3>
                <div class="number"><?= $rooms_count ?></div>
                <i class="bi bi-door-open-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card purple">
                <h3>Active Faculty</h3>
                <div class="number"><?= $faculty_count ?></div>
                <i class="bi bi-person-workspace icon"></i>
            </div>
        </div>
    </div>

    <!-- ROW 2 -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stats-card red">
                <h3>Unresolved Conflicts</h3>
                <div class="number"><?= $conflicts_count ?></div>
                <i class="bi bi-exclamation-triangle-fill icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card cyan">
                <h3>Overloaded Faculty</h3>
                <div class="number"><?= $overloaded_count ?></div>
                <i class="bi bi-person-fill-exclamation icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card green">
                <h3>Classes Today (<?= $today_day ?>)</h3>
                <div class="number"><?= $today_count ?></div>
                <i class="bi bi-clock-fill icon"></i>
            </div>
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="content-card">
        <h5 style="font-weight:700; color:var(--text-primary); margin-bottom:16px;">
            <i class="bi bi-lightning-fill me-2" style="color:var(--accent);"></i>Quick Access
        </h5>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="schedules.php" class="quick-link-card">
                    <i class="bi bi-calendar3"></i><span>View Timetable</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="sections.php" class="quick-link-card">
                    <i class="bi bi-people-fill"></i><span>Sections</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="rooms.php" class="quick-link-card">
                    <i class="bi bi-door-open-fill"></i><span>Room Status</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="faculty_load.php" class="quick-link-card">
                    <i class="bi bi-bar-chart-fill"></i><span>Faculty Load</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($conflicts_count > 0): ?>
    <!-- CONFLICT ALERT BANNER -->
    <div class="content-card mt-4" style="border-left:4px solid #ef4444; background:rgba(239,68,68,0.04);">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:28px; color:#ef4444; flex-shrink:0;"></i>
            <div>
                <div style="font-weight:700; color:#ef4444; font-size:15px;">
                    <?= $conflicts_count ?> Unresolved Conflict<?= $conflicts_count > 1 ? 's' : '' ?> Detected
                </div>
                <div style="font-size:13px; color:var(--text-secondary); margin-top:3px;">
                    Please notify the Admin to resolve these schedule conflicts.
                </div>
            </div>
            <a href="conflicts.php" class="btn-secondary-custom ms-auto" style="white-space:nowrap;">
                <i class="bi bi-eye me-1"></i>View Conflicts
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.quick-link-card {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:10px; padding:20px; border-radius:12px;
    background:var(--color-surface2); border:1px solid var(--color-border);
    text-decoration:none; color:var(--text-secondary);
    font-size:13px; font-weight:600; transition:all 0.18s; text-align:center;
}
.quick-link-card i { font-size:24px; color:var(--accent); }
.quick-link-card:hover { background:rgba(79,163,255,0.08); border-color:rgba(79,163,255,0.3); color:var(--text-primary); transform:translateY(-2px); }
.stats-card.cyan { background:linear-gradient(135deg,rgba(6,182,212,0.15),rgba(6,182,212,0.05)); border-color:rgba(6,182,212,0.2); }
.stats-card.cyan .number { color:#06b6d4; }
</style>

<?php include '../includes/footer.php'; ?>