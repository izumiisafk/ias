<?php
require_once 'config/db.php';
$page_title = 'Faculty Load - Class Scheduling System';
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Teacher Loading Management</h1>
            <p>Monitor faculty teaching loads, prevent overload, ensure fair distribution</p>
        </div>
    </div>

    <div class="content-card">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Max Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM faculty ORDER BY faculty_id DESC");
                    while($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?>
                            </strong>
                        </td>
                        <td><?= htmlspecialchars($row['department']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td><?= htmlspecialchars($row['phone']); ?></td>
                        <td><?= htmlspecialchars($row['max_teaching_hours']); ?> hrs</td>
                        <td>
                            <span class="badge-success">
                                <?= htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
