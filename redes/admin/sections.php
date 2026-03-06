<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Sections - Class Scheduling System';

// ---------- ADD ----------
if (isset($_POST['add_section'])) {
    $section_name   = trim($_POST['section_name']);
    $program        = $_POST['program'];
    $year_level     = $_POST['year_level'];
    $total_students = intval($_POST['total_students']);
    $adviser_id     = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;

    $stmt = $conn->prepare("INSERT INTO sections 
        (section_name, program, year_level, total_students, adviser_id, status) 
        VALUES (?, ?, ?, ?, ?, 'Active')");
    $stmt->bind_param("sssis", $section_name, $program, $year_level, $total_students, $adviser_id);

    if ($stmt->execute()) {
        header("Location: sections.php?success=added");
        exit();
    }
}

// ---------- EDIT ----------
if (isset($_POST['edit_section'])) {
    $section_id     = intval($_POST['section_id']);
    $section_name   = trim($_POST['section_name']);
    $program        = $_POST['program'];
    $year_level     = $_POST['year_level'];
    $total_students = intval($_POST['total_students']);
    $adviser_id     = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;
    $status         = $_POST['status'];

    $stmt = $conn->prepare("UPDATE sections SET
        section_name=?, program=?, year_level=?, total_students=?, adviser_id=?, status=?
        WHERE section_id=?");
    $stmt->bind_param("sssissi",
        $section_name, $program, $year_level,
        $total_students, $adviser_id, $status, $section_id);

    if ($stmt->execute()) {
        header("Location: sections.php?success=updated");
        exit();
    }
}

// ---------- DELETE ----------
if (isset($_POST['delete_section'])) {
    $section_id = intval($_POST['section_id']);
    $stmt = $conn->prepare("DELETE FROM sections WHERE section_id=?");
    $stmt->bind_param("i", $section_id);
    if ($stmt->execute()) {
        header("Location: sections.php?success=deleted");
    } else {
        header("Location: sections.php?error=cannot_delete");
    }
    exit();
}

$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added')   $success_msg = 'Section added successfully!';
    if ($_GET['success'] === 'updated') $success_msg = 'Section updated successfully!';
    if ($_GET['success'] === 'deleted') $success_msg = 'Section deleted successfully!';
}
$error_msg = isset($_GET['error']) ? 'Cannot delete — section may have active schedules.' : '';
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

    <?php if ($success_msg): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-check-circle-fill"></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-exclamation-circle-fill"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

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
                    <?php
                    $result = $conn->query("
                        SELECT s.*, 
                        CONCAT(f.first_name,' ',f.last_name) AS adviser_name
                        FROM sections s
                        LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                        ORDER BY s.section_id DESC
                    ");
                    if ($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['program']) ?></td>
                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                        <td><?= $row['total_students'] ?></td>
                        <td><?= htmlspecialchars($row['adviser_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['status'] === 'Active'): ?>
                                <span class="badge-success">Active</span>
                            <?php elseif ($row['status'] === 'Inactive'): ?>
                                <span class="badge-danger">Inactive</span>
                            <?php else: ?>
                                <span class="badge-warning">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon" title="Edit"
                                onclick='openEditSection(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger" title="Delete"
                                onclick="confirmDeleteSection(<?= $row['section_id'] ?>, '<?= htmlspecialchars($row['section_name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center py-3">No sections found. Add one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ADD SECTION MODAL -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Add New Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" name="section_name" class="form-control" required placeholder="e.g. BSIT-1A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select name="program" class="form-select" required>
                            <option value="BS Information Technology">BS Information Technology</option>
                            <option value="BS Computer Science">BS Computer Science</option>
                            <option value="BS Information Systems">BS Information Systems</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level" class="form-select" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Students</label>
                        <input type="number" name="total_students" class="form-control" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select name="adviser_id" class="form-select">
                            <option value="">— No Adviser —</option>
                            <?php
                            $faculty = $conn->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status='Active' ORDER BY last_name");
                            if ($faculty) while($f = $faculty->fetch_assoc()):
                            ?>
                                <option value="<?= $f['faculty_id'] ?>">
                                    <?= htmlspecialchars($f['first_name'].' '.$f['last_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_section" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Add Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- EDIT SECTION MODAL -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Section Name <span class="text-danger">*</span></label>
                        <input type="text" name="section_name" id="edit_section_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program</label>
                        <select name="program" id="edit_program" class="form-select">
                            <option value="BS Information Technology">BS Information Technology</option>
                            <option value="BS Computer Science">BS Computer Science</option>
                            <option value="BS Information Systems">BS Information Systems</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" id="edit_year_level" class="form-select">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Students</label>
                        <input type="number" name="total_students" id="edit_total_students" class="form-control" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select name="adviser_id" id="edit_adviser_id" class="form-select">
                            <option value="">— No Adviser —</option>
                            <?php
                            $faculty2 = $conn->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status='Active' ORDER BY last_name");
                            if ($faculty2) while($f2 = $faculty2->fetch_assoc()):
                            ?>
                                <option value="<?= $f2['faculty_id'] ?>">
                                    <?= htmlspecialchars($f2['first_name'].' '.$f2['last_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_section" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- DELETE FORM (hidden) -->
<form method="POST" id="deleteSectionForm" style="display:none;">
    <input type="hidden" name="delete_section" value="1">
    <input type="hidden" name="section_id" id="delete_section_id">
</form>

<script>
function openEditSection(s) {
    document.getElementById('edit_section_id').value     = s.section_id;
    document.getElementById('edit_section_name').value   = s.section_name;
    document.getElementById('edit_program').value        = s.program;
    document.getElementById('edit_year_level').value     = s.year_level;
    document.getElementById('edit_total_students').value = s.total_students;
    document.getElementById('edit_adviser_id').value     = s.adviser_id ?? '';
    document.getElementById('edit_status').value         = s.status;
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function confirmDeleteSection(id, name) {
    if (confirm('Delete section "' + name + '"?\n\nNote: Cannot delete if it has active schedules.')) {
        document.getElementById('delete_section_id').value = id;
        document.getElementById('deleteSectionForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>