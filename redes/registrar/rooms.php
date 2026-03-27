<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Rooms - Class Scheduling System';

// ================================================================
// FORCE PHILIPPINE STANDARD TIME (UTC+8)
// ================================================================
date_default_timezone_set('Asia/Manila');
$pht_day  = date('l');       // e.g. "Monday"
$pht_time = date('H:i:s');   // e.g. "14:30:00"

// ================================================================
// REGISTRAR: VIEW ONLY — no add, edit, or delete
// ================================================================

// STATS
$total_rooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$available_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
$labs = $conn->query("SELECT COUNT(*) FROM rooms WHERE room_type='Laboratory'")->fetchColumn();
$lectures = $conn->query("SELECT COUNT(*) FROM rooms WHERE room_type='Lecture'")->fetchColumn();

// Occupied NOW — use PHP PHT values, not MySQL NOW()
$occupied_now = $conn->query("
    SELECT COUNT(DISTINCT s.room_id)
    FROM schedules s
    JOIN academic_terms t ON s.term_id = t.term_id
    WHERE s.status      = 'Active'
      AND t.is_active   = TRUE
      AND s.day_of_week = '$pht_day'
      AND s.start_time <= '$pht_time'
      AND s.end_time   >  '$pht_time'
      AND s.room_id IS NOT NULL
")->fetchColumn();

// ================================================================
// FETCH ALL ROOMS WITH LIVE STATUS — using PHT day/time
// ================================================================
$rooms_result = $conn->query("
    SELECT r.*,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM schedules s
                JOIN academic_terms t ON s.term_id = t.term_id
                WHERE s.room_id     = r.room_id
                  AND s.status      = 'Active'
                  AND t.is_active   = TRUE
                  AND s.day_of_week = '$pht_day'
                  AND s.start_time <= '$pht_time'
                  AND s.end_time   >  '$pht_time'
            ) THEN 'Occupied'
            ELSE 'Available'
        END AS live_status
    FROM rooms r
    ORDER BY r.building, r.floor, r.room_code
")->fetchAll();

// ================================================================
// FETCH SECTION ASSIGNMENTS
// ================================================================
$assigned_rooms = $conn->query("
    SELECT ra.assignment_id,
           s.section_name, s.program,
           r.room_id, r.room_code, r.room_name,
           r.building, r.floor, r.room_type
    FROM room_assignments ra
    JOIN sections s ON ra.section_id = s.section_id
    JOIN rooms    r ON ra.room_id    = r.room_id
    ORDER BY s.section_name
")->fetchAll();
$assigned_count = count($assigned_rooms);

// ================================================================
// FETCH OCCUPIED NOW — rooms in use right now with class details
// ================================================================
$occupied_rows = $conn->query("
    SELECT r.room_id, r.room_code, r.room_name, r.building, r.floor, r.room_type,
           sec.section_name, sec.program,
           sub.subject_name, sub.subject_code,
           CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
           s.start_time, s.end_time
    FROM schedules s
    JOIN rooms    r   ON s.room_id    = r.room_id
    JOIN sections sec ON s.section_id = sec.section_id
    JOIN subjects sub ON s.subject_id = sub.subject_id
    JOIN faculty  f   ON s.faculty_id = f.faculty_id
    JOIN academic_terms t ON s.term_id = t.term_id
    WHERE s.status      = 'Active'
      AND t.is_active   = TRUE
      AND s.day_of_week = '$pht_day'
      AND s.start_time <= '$pht_time'
      AND s.end_time   >  '$pht_time'
      AND s.room_id IS NOT NULL
    ORDER BY r.building, r.floor, r.room_code
")->fetchAll();
$occupied_count = count($occupied_rows);

$active_tab = $_GET['tab'] ?? 'all';

$programNames = [
    'BSIT'   => 'BS Information Technology',
    'BSTM'   => 'BS Tourism Management',
    'BSBA'   => 'BS Business Administration',
    'BSCRIM' => 'BS Criminology',
    'BSCE'   => 'BS Civil Engineering',
];
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Room Availability</h1>
            <p>Track room status and section assignments &bull; Live as of <strong><?= date('h:i A', strtotime($pht_time)) ?></strong></p>
        </div>
        <div class="registrar-access-badge">
            <i class="bi bi-eye me-1"></i> View Only
        </div>
    </div>

    <!-- STATS -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card blue">
                <h3>Total Rooms</h3>
                <div class="number"><?= $total_rooms ?></div>
                <i class="bi bi-door-open-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Available</h3>
                <div class="number"><?= $available_rooms ?></div>
                <i class="bi bi-check-circle-fill icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card orange">
                <h3>Laboratories</h3>
                <div class="number"><?= $labs ?></div>
                <i class="bi bi-pc-display icon"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card red">
                <h3>Occupied Now</h3>
                <div class="number"><?= $occupied_now ?></div>
                <i class="bi bi-door-closed-fill icon"></i>
            </div>
        </div>
    </div>

    <!-- CONTENT CARD WITH TABS -->
    <div class="content-card">

        <div class="room-tabs mb-4">
            <a href="?tab=all" class="room-tab <?= $active_tab === 'all' ? 'active' : '' ?>">
                <i class="bi bi-door-open-fill me-1"></i> All Rooms
                <span class="tab-count"><?= $total_rooms ?></span>
            </a>
            <a href="?tab=assigned" class="room-tab <?= $active_tab === 'assigned' ? 'active' : '' ?>">
                <i class="bi bi-building-check me-1"></i> Section Assignments
                <span class="tab-count"><?= $assigned_count ?></span>
            </a>
            <a href="?tab=occupied" class="room-tab <?= $active_tab === 'occupied' ? 'active' : '' ?>">
                <i class="bi bi-door-closed-fill me-1"></i> Occupied Now
                <span class="tab-count <?= $occupied_count > 0 ? 'tab-count-red' : '' ?>"><?= $occupied_count ?></span>
            </a>
        </div>

        <!-- ============================================================
             TAB: ALL ROOMS
        ============================================================ -->
        <?php if ($active_tab === 'all'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">
                <i class="bi bi-grid-fill me-2" style="color:var(--accent);"></i>Room Status
            </h5>
            <div class="d-flex align-items-center gap-3">
                <div class="live-legend">
                    <span><span class="legend-dot" style="background:#22c55e;"></span> Available</span>
                    <span><span class="legend-dot" style="background:#ef4444;"></span> Occupied</span>
                </div>
                <input type="text" id="roomSearch" class="form-control form-control-sm"
                    placeholder="Search room…" style="width:200px;" oninput="filterRooms()">
            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="roomTable">
                <thead>
                    <tr>
                        <th>Room Code</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Building</th>
                        <th>Floor</th>
                        <th>Capacity</th>
                        <th>Live Status</th>
                    </tr>
                </thead>
                <tbody id="roomTbody">
                    <?php if (!empty($rooms_result)):
                        foreach ($rooms_result as $room): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($room['room_code']) ?></strong></td>
                        <td><?= htmlspecialchars($room['room_name']) ?></td>
                        <td>
                            <?php if ($room['room_type'] === 'Laboratory'): ?>
                                <span class="type-badge lab">
                                    <i class="bi bi-pc-display me-1"></i>Lab
                                </span>
                            <?php else: ?>
                                <span class="type-badge lecture">
                                    <i class="bi bi-building me-1"></i>Lecture
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($room['building']) ?></td>
                        <td><?= htmlspecialchars($room['floor']) ?></td>
                        <td><?= $room['capacity'] ?> students</td>
                        <td>
                            <?php if ($room['live_status'] === 'Occupied'): ?>
                                <span class="badge-danger">
                                    <i class="bi bi-door-closed-fill me-1"></i>Occupied
                                </span>
                            <?php else: ?>
                                <span class="badge-success">
                                    <i class="bi bi-check-circle me-1"></i>Available
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center py-3">No rooms found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="roomNoResults" class="text-center py-3 text-muted" style="display:none;">
                No rooms match your search.
            </div>
        </div>

        <!-- ============================================================
             TAB: SECTION ASSIGNMENTS
        ============================================================ -->
        <?php elseif ($active_tab === 'assigned'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">
                <i class="bi bi-building-check me-2" style="color:var(--accent);"></i>Section Room Assignments
            </h5>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Program</th>
                        <th>Room Code</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Building</th>
                        <th>Floor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($assigned_rooms)):
                        foreach ($assigned_rooms as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                        <td style="font-size:12px;">
                            <?= htmlspecialchars($programNames[$row['program']] ?? $row['program']) ?>
                        </td>
                        <td><strong><?= htmlspecialchars($row['room_code']) ?></strong></td>
                        <td><?= htmlspecialchars($row['room_name']) ?></td>
                        <td>
                            <?php if ($row['room_type'] === 'Laboratory'): ?>
                                <span class="type-badge lab"><i class="bi bi-pc-display me-1"></i>Lab</span>
                            <?php else: ?>
                                <span class="type-badge lecture"><i class="bi bi-building me-1"></i>Lecture</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['building']) ?></td>
                        <td><?= htmlspecialchars($row['floor']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center py-3">No room assignments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-2" style="font-size:12px; color:var(--text-secondary);">
            <i class="bi bi-info-circle me-1"></i>
            Room assignments are managed by Admin. Contact Admin to change a section's assigned room.
        </div>

        <!-- ============================================================
             TAB: OCCUPIED NOW
        ============================================================ -->
        <?php elseif ($active_tab === 'occupied'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">
                <i class="bi bi-door-closed-fill me-2" style="color:#ef4444;"></i>
                Rooms In Use Right Now
                <span style="font-size:13px; color:var(--text-secondary); font-weight:400; margin-left:8px;">
                    <?= date('l, F j, Y') ?> &bull; <?= date('h:i A') ?>
                </span>
            </h5>
            <?php if ($occupied_count === 0): ?>
                <span style="font-size:12px; color:#22c55e; font-weight:600;">
                    <i class="bi bi-check-circle-fill me-1"></i>All rooms currently free
                </span>
            <?php else: ?>
                <span class="live-indicator">
                    <span class="blink-dot"></span>
                    <?= $occupied_count ?> room<?= $occupied_count > 1 ? 's' : '' ?> in use
                </span>
            <?php endif; ?>
        </div>

        <?php if ($occupied_count === 0): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill" style="font-size:20px;"></i>
                <div>
                    <strong>No rooms are currently occupied.</strong>
                    There are no active classes running right now (<?= date('h:i A') ?>).
                </div>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Building / Floor</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Schedule</th>
                        <th>Time Left</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($occupied_rows as $row):
                        $endTs    = strtotime(date('Y-m-d') . ' ' . $row['end_time']);
                        $nowTs    = strtotime(date('Y-m-d') . ' ' . $pht_time);
                        $diffMins = max(0, round(($endTs - $nowTs) / 60));
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['room_code']) ?></strong>
                            <div style="font-size:11px; color:var(--text-secondary);">
                                <?= htmlspecialchars($row['room_name']) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($row['room_type'] === 'Laboratory'): ?>
                                <span class="type-badge lab"><i class="bi bi-pc-display me-1"></i>Lab</span>
                            <?php else: ?>
                                <span class="type-badge lecture"><i class="bi bi-building me-1"></i>Lecture</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['building']) ?>
                            <span style="color:var(--text-secondary);"> · </span>
                            <?= htmlspecialchars($row['floor']) ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['section_name']) ?></strong>
                            <div style="font-size:11px; color:var(--text-secondary);">
                                <?= htmlspecialchars($programNames[$row['program']] ?? $row['program']) ?>
                            </div>
                        </td>
                        <td>
                            <strong style="color:var(--accent);"><?= htmlspecialchars($row['subject_code']) ?></strong>
                            <div style="font-size:11px; color:var(--text-secondary);">
                                <?= htmlspecialchars($row['subject_name']) ?>
                            </div>
                        </td>
                        <td style="font-size:13px;">
                            <i class="bi bi-person-fill me-1" style="color:var(--accent);"></i>
                            <?= htmlspecialchars($row['faculty_name']) ?>
                        </td>
                        <td style="font-size:12px; white-space:nowrap;">
                            <?= date('h:i A', strtotime($row['start_time'])) ?>
                            <span style="color:var(--text-secondary);">–</span>
                            <?= date('h:i A', strtotime($row['end_time'])) ?>
                        </td>
                        <td>
                            <span class="time-left-badge <?= $diffMins <= 15 ? 'urgent' : 'normal' ?>">
                                <?php if ($diffMins <= 0): ?>
                                    Ending now
                                <?php elseif ($diffMins < 60): ?>
                                    <?= $diffMins ?>m left
                                <?php else: ?>
                                    <?= floor($diffMins / 60) ?>h <?= $diffMins % 60 ?>m left
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="mt-2" style="font-size:12px; color:var(--text-secondary);">
            <i class="bi bi-info-circle me-1"></i>
            This list reflects the current time. Reload the page to refresh the status.
        </div>

        <?php endif; ?>

    </div><!-- end content-card -->

    <!-- HOW IT WORKS -->
    <div class="content-card mt-4" style="font-size:13px;">
       
        <div class="row">
            <div class="col-md-6 mb-2">
                <div style="padding:12px; background:rgba(34,197,94,0.08); border-radius:8px; border-left:3px solid #22c55e;">
                    <strong style="color:#22c55e;"><i class="bi bi-check-circle me-1"></i>Available</strong><br>
                    <span style="color:var(--text-secondary);">No active class is scheduled in this room right now.</span>
                </div>
            </div>
            <div class="col-md-6 mb-2">
                <div style="padding:12px; background:rgba(239,68,68,0.08); border-radius:8px; border-left:3px solid #ef4444;">
                    <strong style="color:#ef4444;"><i class="bi bi-door-closed-fill me-1"></i>Occupied</strong><br>
                    <span style="color:var(--text-secondary);">A class is actively running in this room at the current time.</span>
                </div>
            </div>
        </div>
    </div>

</div><!-- end main-content -->

<script>
function filterRooms() {
    const q    = document.getElementById('roomSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#roomTbody tr');
    let vis = 0;
    rows.forEach(row => {
        const show = row.textContent.toLowerCase().includes(q);
        row.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    document.getElementById('roomNoResults').style.display =
        (vis === 0 && q.length > 0) ? 'block' : 'none';
}
</script>

<style>
/* ── REGISTRAR BADGE ── */
.registrar-access-badge {
    background: rgba(79,163,255,0.1);
    border: 1px solid rgba(79,163,255,0.25);
    color: var(--accent);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
}

/* ── TABS ── */
.room-tabs {
    display: flex;
    gap: 6px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 0;
    margin-bottom: 20px;
}
.room-tab {
    padding: 9px 18px;
    border-radius: 8px 8px 0 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid transparent;
    border-bottom: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s;
    position: relative;
    bottom: -1px;
    font-family: var(--font-body);
}
.room-tab:hover { color: var(--text-primary); background: rgba(255,255,255,0.04); }
.room-tab.active {
    color: var(--text-primary);
    background: var(--color-surface);
    border-color: var(--color-border);
    border-bottom-color: var(--color-surface);
}

/* ── TAB COUNTS ── */
.tab-count {
    background: rgba(255,255,255,0.07);
    color: var(--text-secondary);
    border-radius: 20px;
    padding: 1px 8px;
    font-size: 11px;
    font-weight: 700;
}
.room-tab.active .tab-count           { background: var(--accent); color: #fff; }
.tab-count-red                        { background: rgba(239,68,68,0.18) !important; color: #ef4444 !important; }
.room-tab.active .tab-count-red       { background: #ef4444 !important; color: #fff !important; }

/* ── TYPE BADGES ── */
.type-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}
.type-badge.lab     { background: rgba(99,102,241,0.12); color: #6366f1; }
.type-badge.lecture { background: rgba(6,182,212,0.12);  color: #06b6d4; }

/* ── LEGEND ── */
.live-legend {
    display: flex;
    gap: 14px;
    font-size: 12px;
    color: var(--text-secondary);
    align-items: center;
}
.legend-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 4px;
    vertical-align: middle;
}

/* ── LIVE INDICATOR (Occupied Now tab) ── */
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #ef4444;
}
.blink-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
    animation: blink 1.2s infinite;
    flex-shrink: 0;
}
@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.25; }
}

/* ── TIME LEFT BADGE ── */
.time-left-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.time-left-badge.normal { background: rgba(34,197,94,0.1);  color: #22c55e; }
.time-left-badge.urgent { background: rgba(239,68,68,0.12); color: #ef4444; }

/* ── STATS CARD RED ── */
.stats-card.red {
    background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(239,68,68,0.05));
    border-color: rgba(239,68,68,0.2);
}
.stats-card.red .number { color: #ef4444; }
</style>

<?php include '../includes/footer.php'; ?>