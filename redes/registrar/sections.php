<?php
require_once 'includes/auth.php'; require_once '../config/db.php';

if (isset($_POST['add_section'])) {

    $section_name   = $_POST['section_name'];
    $program        = $_POST['program'];
    $year_level     = $_POST['year_level'];
    $total_students = $_POST['total_students'];
    $adviser_id     = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;
    $status         = 'Active';

    $stmt = $conn->prepare("INSERT INTO sections 
        (section_name, program, year_level, total_students, adviser_id, status) 
        VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssiss",
        $section_name,
        $program,
        $year_level,
        $total_students,
        $adviser_id,
        $status
    );

    if ($stmt->execute()) {
        header("Location: sections.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Section Management</h1>
            <p>Manage academic sections and student assignments</p>
        </div>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="bi bi-plus-lg me-2"></i>Add Section
        </button>
    </div>

    <div class="content-card">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Section Name</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Total Students</th>
                        <th>Adviser</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $result = $conn->query("
                        SELECT s.*, 
                        CONCAT(f.first_name,' ',f.last_name) AS adviser_name
                        FROM sections s
                        LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                        ORDER BY s.section_id DESC
                    ");

                    while($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?= $row['section_name']; ?></strong></td>
                        <td><?= $row['program']; ?></td>
                        <td><?= $row['year_level']; ?></td>
                        <td><?= $row['total_students']; ?></td>
                        <td><?= $row['adviser_name'] ?? '—'; ?></td>
                        <td>
                            <span class="badge-success"><?= $row['status']; ?></span>
                        </td>
                        <td>
                            <button class="btn-icon" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Section Name</label>
                        <input type="text" name="section_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Program</label>
                        <select name="program" class="form-select" required>
                            <option value="BS Information Technology">BS Information Technology</option>
                            <option value="BS Computer Science">BS Computer Science</option>
                            <option value="BS Information Systems">BS Information Systems</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-select" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Students</label>
                        <input type="number" name="total_students" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select name="adviser_id" class="form-select">
                            <option value="">Select Faculty</option>
                            <?php $faculty = $conn->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status='Active'");
                            while($f = $faculty->fetch_assoc()):
                            ?>
                                <option value="<?= $f['faculty_id']; ?>">
                                    <?= $f['first_name'].' '.$f['last_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_section" class="btn-primary-custom btn-sm">
                        Add Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
