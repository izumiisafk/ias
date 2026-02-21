<?php
require_once 'config/db.php';
$page_title = 'Conflicts - Class Scheduling System';

// ==========================
// STATS
// ==========================
$total_conflicts = 0;
$faculty_conflicts = 0;
$room_conflicts = 0;

$result = $conn->query("SELECT * FROM conflicts WHERE status='Unresolved'");
$conflict_rows = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $conflict_rows[] = $row;
        $total_conflicts++;
        if ($row['conflict_type'] == 'Faculty') $faculty_conflicts++;
        if ($row['conflict_type'] == 'Room') $room_conflicts++;
    }
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>Schedule Conflicts</h1>
        <p>Detect and resolve scheduling conflicts</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-4"><div class="stats-card red"><h3>Total Conflicts</h3><div class="number"><?= $total_conflicts ?></div></div></div>
        <div class="col-md-4"><div class="stats-card orange"><h3>Faculty Conflicts</h3><div class="number"><?= $faculty_conflicts ?></div></div></div>
        <div class="col-md-4"><div class="stats-card purple"><h3>Room Conflicts</h3><div class="number"><?= $room_conflicts ?></div></div></div>
    </div>

    <div class="content-card">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Detected At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach($conflict_rows as $row): ?>

                <tr>
                    <td>
                        <?php if($row['conflict_type']=='Faculty'): ?>
                            <span class="badge-danger">Faculty</span>
                        <?php else: ?>
                            <span class="badge-warning">Room</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['description'] ?></td>
                    <td><?= $row['detected_at'] ?></td>
                    <td><?= $row['status'] ?></td>
                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
