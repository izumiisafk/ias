<?php
require_once 'config/db.php';
$page_title = 'Rooms - Class Scheduling System';

// ==========================
// STATS
// ==========================

// Total Rooms
$total_rooms = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms");
if ($result && $row = $result->fetch_assoc()) {
    $total_rooms = $row['total'];
}

// Available Rooms
$available_rooms = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status='Available'");
if ($result && $row = $result->fetch_assoc()) {
    $available_rooms = $row['total'];
}

// Laboratories
$labs = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE room_type='Laboratory'");
if ($result && $row = $result->fetch_assoc()) {
    $labs = $row['total'];
}

// Lecture Rooms
$lectures = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE room_type='Lecture'");
if ($result && $row = $result->fetch_assoc()) {
    $lectures = $row['total'];
}

// ==========================
// FETCH ROOMS
// ==========================
$rooms = $conn->query("SELECT * FROM rooms ORDER BY building, floor");
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>Room Management & Availability</h1>
        <p>Track room availability and prevent double-booking</p>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card blue">
                <h3>Total Rooms</h3>
                <div class="number"><?= $total_rooms; ?></div>
                <i class="bi bi-door-open-fill icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card green">
                <h3>Available</h3>
                <div class="number"><?= $available_rooms; ?></div>
                <i class="bi bi-check-circle-fill icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card orange">
                <h3>Laboratories</h3>
                <div class="number"><?= $labs; ?></div>
                <i class="bi bi-pc-display icon"></i>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card purple">
                <h3>Lecture Rooms</h3>
                <div class="number"><?= $lectures; ?></div>
                <i class="bi bi-building icon"></i>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Room Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Building</th>
                        <th>Floor</th>
                        <th>Capacity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>

                    <?php if ($rooms && $rooms->num_rows > 0): ?>
                        <?php while($room = $rooms->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= $room['room_code']; ?></strong></td>
                            <td><?= $room['room_name']; ?></td>
                            <td><?= $room['room_type']; ?></td>
                            <td><?= $room['building']; ?></td>
                            <td><?= $room['floor']; ?></td>
                            <td><?= $room['capacity']; ?> students</td>
                            <td>
                                <?php if ($room['status'] == 'Available'): ?>
                                    <span class="badge-success">Available</span>
                                <?php else: ?>
                                    <span class="badge-warning">Maintenance</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
