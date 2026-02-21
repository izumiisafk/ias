<?php
require_once 'config/db.php';
$page_title = 'Schedules - Class Scheduling System';


/* =================================================
   HANDLE INSERT
================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get active term safely
    $termStmt = $conn->prepare("SELECT term_id, term_name FROM terms WHERE status='Active' LIMIT 1");
    $termStmt->execute();
    $termResult = $termStmt->get_result();

    if ($termResult->num_rows === 0) {
        die("No active term found. Please activate a term first.");
    }

    $termData = $termResult->fetch_assoc();
    $term_id = $termData['term_id'];

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

        // Safe self reload (NO PATH ISSUES)
        echo "<script>
                alert('Schedule added successfully!');
                window.location.href = window.location.pathname;
              </script>";
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}


/* =================================================
   GET ACTIVE TERM
================================================= */
$activeTerm = $conn->query("SELECT term_id, term_name FROM terms WHERE status='Active' LIMIT 1")->fetch_assoc();
$active_term_id = $activeTerm['term_id'];
$active_term_name = $activeTerm['term_name'];


/* =================================================
   FETCH SCHEDULES
================================================= */
$schedules = $conn->query("
    SELECT s.*, 
           sec.section_name,
           sub.subject_name,
           f.first_name, f.last_name,
           r.room_name
    FROM schedules s
    JOIN sections sec ON s.section_id = sec.section_id
    JOIN subjects sub ON s.subject_id = sub.subject_id
    JOIN faculty f ON s.faculty_id = f.faculty_id
    JOIN rooms r ON s.room_id = r.room_id
    WHERE s.term_id = $active_term_id
    ORDER BY 
        FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
        s.start_time
");


/* =================================================
   DROPDOWNS
================================================= */
$sections = $conn->query("SELECT * FROM sections WHERE status='Active'");
$subjects = $conn->query("SELECT * FROM subjects WHERE status='Active'");
$faculty  = $conn->query("SELECT * FROM faculty WHERE status='Active'");
$rooms    = $conn->query("SELECT * FROM rooms WHERE status='Available'");
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Schedule Management</h1>
            <p>Active Term: <strong><?= $active_term_name; ?></strong></p>
        </div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="bi bi-plus-lg me-2"></i>Add Schedule
        </button>
    </div>

    <div class="content-card">
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
                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                        <?php while($row = $schedules->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $row['section_name']; ?></strong></td>
                            <td><?= $row['subject_name']; ?></td>
                            <td><?= $row['first_name'] . ' ' . $row['last_name']; ?></td>
                            <td><?= $row['room_name']; ?></td>
                            <td><?= $row['day_of_week']; ?></td>
                            <td><?= date("h:i A", strtotime($row['start_time'])); ?></td>
                            <td><?= date("h:i A", strtotime($row['end_time'])); ?></td>
                            <td><span class="badge-success"><?= $row['status']; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No schedules found.</td>
                        </tr>
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
                <h5 class="modal-title">Add New Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Section</label>
                            <select name="section_id" class="form-select" required>
                                <option value="">Select Section</option>
                                <?php while($row = $sections->fetch_assoc()): ?>
                                    <option value="<?= $row['section_id']; ?>">
                                        <?= $row['section_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php while($row = $subjects->fetch_assoc()): ?>
                                    <option value="<?= $row['subject_id']; ?>">
                                        <?= $row['subject_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Faculty</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Select Faculty</option>
                                <?php while($row = $faculty->fetch_assoc()): ?>
                                    <option value="<?= $row['faculty_id']; ?>">
                                        <?= $row['first_name'].' '.$row['last_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label>Room</label>
                            <select name="room_id" class="form-select" required>
                                <option value="">Select Room</option>
                                <?php while($row = $rooms->fetch_assoc()): ?>
                                    <option value="<?= $row['room_id']; ?>">
                                        <?= $row['room_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Day</label>
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
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary-custom btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-custom btn-sm">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
