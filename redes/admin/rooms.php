<?php
require_once 'includes/auth.php';
require_once '../config/db.php';
$page_title = 'Rooms - Class Scheduling System';

// ================================================================
// FORCE PHILIPPINE STANDARD TIME (UTC+8)
// ================================================================
date_default_timezone_set('Asia/Manila');
$pht_day  = date('l');       // e.g. "Monday"
$pht_time = date('H:i:s');   // e.g. "14:30:00"

// ================================================================
// ASSIGN ROOM
// ================================================================
if (isset($_POST['assign_room'])) {
    $section_id = $_POST['section_id'];
    $room_id    = $_POST['room_id'];
    $stmt = $conn->prepare("INSERT INTO room_assignments (section_id, room_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $section_id, $room_id);
    if ($stmt->execute()) {
        header("Location: rooms.php?success=assigned");
        exit();
    }
}

// ================================================================
// EDIT ROOM ASSIGNMENT
// ================================================================
if (isset($_POST['edit_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $room_id       = intval($_POST['room_id']);
    $stmt = $conn->prepare("UPDATE room_assignments SET room_id=? WHERE assignment_id=?");
    $stmt->bind_param("ii", $room_id, $assignment_id);
    if ($stmt->execute()) {
        header("Location: rooms.php?success=updated&tab=assigned");
        exit();
    }
}

// ================================================================
// DELETE ROOM ASSIGNMENT
// ================================================================
if (isset($_POST['delete_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $stmt = $conn->prepare("DELETE FROM room_assignments WHERE assignment_id=?");
    $stmt->bind_param("i", $assignment_id);
    if ($stmt->execute()) {
        header("Location: rooms.php?success=deleted&tab=assigned");
        exit();
    }
}

// ================================================================
// STATS
// ================================================================
$total_rooms = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms");
if ($result && $row = $result->fetch_assoc()) $total_rooms = $row['total'];

$available_rooms = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status='Available'");
if ($result && $row = $result->fetch_assoc()) $available_rooms = $row['total'];

$labs = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE room_type='Laboratory'");
if ($result && $row = $result->fetch_assoc()) $labs = $row['total'];

$lectures = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE room_type='Lecture'");
if ($result && $row = $result->fetch_assoc()) $lectures = $row['total'];

// Occupied NOW — use PHP PHT values, not MySQL NOW()
$occupied_now = 0;
$occ_result   = $conn->query("
    SELECT COUNT(DISTINCT s.room_id) AS total
    FROM schedules s
    JOIN academic_terms t ON s.term_id = t.term_id
    WHERE s.status      = 'Active'
      AND t.is_active   = 1
      AND s.day_of_week = '$pht_day'
      AND s.start_time <= '$pht_time'
      AND s.end_time   >  '$pht_time'
      AND s.room_id IS NOT NULL
");
if ($occ_result && $row = $occ_result->fetch_assoc()) $occupied_now = $row['total'];

// ================================================================
// FETCH ALL ROOMS WITH LIVE STATUS
// ================================================================
$rooms = $conn->query("
    SELECT r.*,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM schedules s
                JOIN academic_terms t ON s.term_id = t.term_id
                WHERE s.room_id     = r.room_id
                  AND s.status      = 'Active'
                  AND t.is_active   = 1
                  AND s.day_of_week = '$pht_day'
                  AND s.start_time <= '$pht_time'
                  AND s.end_time   >  '$pht_time'
            ) THEN 'Occupied'
            ELSE 'Available'
        END AS live_status
    FROM rooms r
    ORDER BY r.building, r.floor, r.room_code
");

// ================================================================
// FETCH SECTION ASSIGNMENTS
// ================================================================
$assigned_rooms = $conn->query("
    SELECT ra.assignment_id,
           s.section_name, s.section_id, s.program,
           r.room_id, r.room_code, r.room_name,
           r.building, r.floor, r.room_type
    FROM room_assignments ra
    JOIN sections s ON ra.section_id = s.section_id
    JOIN rooms    r ON ra.room_id    = r.room_id
    ORDER BY s.section_name
");
$assigned_count = $assigned_rooms ? $assigned_rooms->num_rows : 0;

// ================================================================
// FETCH OCCUPIED NOW
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
      AND t.is_active   = 1
      AND s.day_of_week = '$pht_day'
      AND s.start_time <= '$pht_time'
      AND s.end_time   >  '$pht_time'
      AND s.room_id IS NOT NULL
    ORDER BY r.building, r.floor, r.room_code
");
$occupied_count = $occupied_rows ? $occupied_rows->num_rows : 0;

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
            <h1>Room Management &amp; Availability</h1>
            <p>Track room availability and prevent double-booking &bull; Live as of <strong><?= date('h:i A', strtotime($pht_time)) ?></strong></p>
        </div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#assignRoomModal">
            <i class="bi bi-plus-lg me-2"></i>Assign Room
        </button>
    </div>

    <!-- SUCCESS MESSAGES -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill"></i>
        <?php
            if ($_GET['success'] === 'assigned') echo "Room successfully assigned!";
            if ($_GET['success'] === 'updated')  echo "Assignment updated successfully!";
            if ($_GET['success'] === 'deleted')  echo "Assignment deleted successfully!";
        ?>
    </div>
    <?php endif; ?>

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
                    <?php if ($rooms && $rooms->num_rows > 0):
                        while ($room = $rooms->fetch_assoc()): ?>
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
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center py-3">No rooms found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="roomNoResults" class="text-center py-3 text-muted" style="display:none;">
                No rooms match your search.
            </div>
        </div>

        <!-- ============================================================
             TAB: SECTION ASSIGNMENTS (Admin: edit + delete enabled)
        ============================================================ -->
        <?php elseif ($active_tab === 'assigned'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">
                <i class="bi bi-building-check me-2" style="color:var(--accent);"></i>Section Room Assignments
            </h5>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#assignRoomModal">
                <i class="bi bi-plus-lg me-2"></i>Assign Room
            </button>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assigned_rooms && $assigned_rooms->num_rows > 0):
                        while ($row = $assigned_rooms->fetch_assoc()): ?>
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
                        <td>
                            <!-- ADMIN: edit + delete -->
                            <button class="btn-icon" title="Edit"
                                onclick='openEditAssignment(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger" title="Delete"
                                onclick="confirmDeleteAssignment(<?= $row['assignment_id'] ?>, '<?= htmlspecialchars($row['room_name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-3">No room assignments found. Use "Assign Room" to add one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
                    <?php while ($row = $occupied_rows->fetch_assoc()):
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
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="mt-2" style="font-size:12px; color:var(--text-secondary);">
            <i class="bi bi-info-circle me-1"></i>
            This list reflects the current time. Reload the page to refresh.
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


<!-- ================================================================
     ASSIGN ROOM MODAL
================================================================ -->
<div class="modal fade" id="assignRoomModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<div class="modal-header">
    <h5 class="modal-title"><i class="bi bi-building-check me-2"></i>Assign Room</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label">Section <span class="text-danger">*</span></label>
        <select name="section_id" id="section_id" class="form-select" required>
            <option value="">Select Section</option>
            <?php
            $sections = $conn->query("SELECT section_id, section_name, program FROM sections WHERE status='Active' ORDER BY section_name");
            if ($sections) while ($sec = $sections->fetch_assoc()): ?>
            <option value="<?= $sec['section_id'] ?>" data-program="<?= htmlspecialchars($sec['program']) ?>">
                <?= htmlspecialchars($sec['section_name']) ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Available Room <span class="text-danger">*</span></label>
        <select name="room_id" id="room_id" class="form-select" required>
            <option value="">Select Room</option>
            <?php
            $available = $conn->query("SELECT room_id, room_code, room_name, room_type, allowed_program FROM rooms WHERE status='Available' ORDER BY room_code");
            if ($available) while ($r = $available->fetch_assoc()):
                echo '<option value="' . $r['room_id'] . '" data-type="' . $r['room_type'] . '" data-program="' . htmlspecialchars($r['allowed_program']) . '">'
                   . htmlspecialchars($r['room_code'] . ' - ' . $r['room_name']) . '</option>';
            endwhile; ?>
        </select>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" name="assign_room" class="btn-primary-custom">
        <i class="bi bi-check-lg me-1"></i>Assign Room
    </button>
</div>
</form>
</div>
</div>
</div>


<!-- ================================================================
     EDIT ASSIGNMENT MODAL
================================================================ -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<input type="hidden" name="assignment_id" id="edit_assignment_id">
<div class="modal-header">
    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Assigned Room</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label">Section</label>
        <input type="text" id="edit_section_name" class="form-control" readonly
            style="background:var(--color-surface2); cursor:not-allowed;">
    </div>
    <div class="mb-3">
        <label class="form-label">Room <span class="text-danger">*</span></label>
        <select name="room_id" id="edit_room_id" class="form-select" required>
            <option value="">Select Room</option>
            <?php
            $all_rooms = $conn->query("SELECT room_id, room_code, room_name, room_type, allowed_program FROM rooms WHERE status='Available' ORDER BY room_code");
            if ($all_rooms) while ($r = $all_rooms->fetch_assoc()):
                echo '<option value="' . $r['room_id'] . '" data-type="' . $r['room_type'] . '" data-program="' . htmlspecialchars($r['allowed_program']) . '">'
                   . htmlspecialchars($r['room_code'] . ' - ' . $r['room_name']) . '</option>';
            endwhile; ?>
        </select>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" name="edit_assignment" class="btn-primary-custom">
        <i class="bi bi-check-lg me-1"></i>Save Changes
    </button>
</div>
</form>
</div>
</div>
</div>


<!-- DELETE FORM (hidden) -->
<form method="POST" id="deleteAssignmentForm" style="display:none;">
    <input type="hidden" name="delete_assignment" value="1">
    <input type="hidden" name="assignment_id" id="delete_assignment_id">
</form>


<script>
// ── SEARCH FILTER (All Rooms tab) ──
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

// ── EDIT ASSIGNMENT MODAL ──
function openEditAssignment(a) {
    const editRoomSelect = document.getElementById('edit_room_id');

    document.getElementById('edit_assignment_id').value = a.assignment_id;
    document.getElementById('edit_section_name').value  = a.section_name;

    // Store all options once
    if (!editRoomSelect.allOptions) {
        editRoomSelect.allOptions = Array.from(editRoomSelect.options);
    }

    // Rebuild filtered list
    editRoomSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = ''; placeholder.text = 'Select Room';
    editRoomSelect.appendChild(placeholder);

    editRoomSelect.allOptions.forEach(opt => {
        if (!opt.value) return;
        if (opt.dataset.type === 'Lecture') {
            editRoomSelect.appendChild(opt.cloneNode(true));
        }
        if (opt.dataset.type === 'Laboratory') {
            if (!opt.dataset.program || opt.dataset.program.split(',').includes(a.program)) {
                editRoomSelect.appendChild(opt.cloneNode(true));
            }
        }
    });

    editRoomSelect.value = a.room_id ?? '';
    new bootstrap.Modal(document.getElementById('editAssignmentModal')).show();
}

// ── DELETE CONFIRM ──
function confirmDeleteAssignment(id, roomName) {
    if (confirm('Delete assignment for "' + roomName + '"? This cannot be undone.')) {
        document.getElementById('delete_assignment_id').value = id;
        document.getElementById('deleteAssignmentForm').submit();
    }
}

// ── FILTER ROOMS IN ASSIGN MODAL by section program ──
const sectionSelect = document.getElementById('section_id');
const roomSelect    = document.getElementById('room_id');

if (sectionSelect && roomSelect) {
    roomSelect.allOptions = Array.from(roomSelect.options);

    sectionSelect.addEventListener('change', function () {
        const selectedProgram = this.selectedOptions[0]?.dataset.program ?? '';
        roomSelect.innerHTML = '';

        const ph = document.createElement('option');
        ph.value = ''; ph.text = 'Select Room';
        roomSelect.appendChild(ph);

        roomSelect.allOptions.forEach(opt => {
            if (!opt.value) return;
            if (opt.dataset.type === 'Lecture') {
                roomSelect.appendChild(opt.cloneNode(true));
            }
            if (opt.dataset.type === 'Laboratory') {
                if (!opt.dataset.program || opt.dataset.program.split(',').includes(selectedProgram)) {
                    roomSelect.appendChild(opt.cloneNode(true));
                }
            }
        });
    });
}
</script>

<style>
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
.room-tab.active .tab-count     { background: var(--accent); color: #fff; }
.tab-count-red                  { background: rgba(239,68,68,0.18) !important; color: #ef4444 !important; }
.room-tab.active .tab-count-red { background: #ef4444 !important; color: #fff !important; }

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
    width: 9px; height: 9px;
    border-radius: 50%;
    margin-right: 4px;
    vertical-align: middle;
}

/* ── LIVE INDICATOR ── */
.live-indicator {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #ef4444;
}
.blink-dot {
    width: 8px; height: 8px;
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

/* ── STATS CARD VARIANTS ── */
.stats-card.red {
    background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(239,68,68,0.05));
    border-color: rgba(239,68,68,0.2);
}
.stats-card.red .number { color: #ef4444; }
.stats-card.orange {
    background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05));
    border-color: rgba(245,158,11,0.2);
}
.stats-card.orange .number { color: #f59e0b; }
</style>

<?php include '../includes/footer.php'; ?>