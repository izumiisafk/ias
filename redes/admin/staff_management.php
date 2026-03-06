<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Staff Management - Admin Panel';

$errors   = [];
$success  = '';
$active_tab = $_GET['tab'] ?? 'teachers';

// ================================================================
// HANDLE TEACHER (FACULTY) ACTIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- ADD TEACHER ----------
    if (isset($_POST['action']) && $_POST['action'] === 'add_teacher') {
        $faculty_code       = trim($_POST['faculty_code']);
        $first_name         = trim($_POST['first_name']);
        $last_name          = trim($_POST['last_name']);
        $department         = trim($_POST['department']);
        $email              = trim($_POST['email']);
        $phone              = trim($_POST['phone']);
        $max_teaching_hours = intval($_POST['max_teaching_hours']);
        $status             = $_POST['status'];

        $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE faculty_code=? OR email=?");
        $check->bind_param("ss", $faculty_code, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Faculty code or email already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO faculty 
                (faculty_code, first_name, last_name, department, email, phone, max_teaching_hours, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssis",
                $faculty_code, $first_name, $last_name,
                $department, $email, $phone, $max_teaching_hours, $status);
            if ($stmt->execute()) {
                $success = "Teacher added successfully!";
                $active_tab = 'teachers';
            } else {
                $errors[] = "Error adding teacher: " . $conn->error;
            }
        }
    }

    // ---------- EDIT TEACHER ----------
    if (isset($_POST['action']) && $_POST['action'] === 'edit_teacher') {
        $faculty_id         = intval($_POST['faculty_id']);
        $faculty_code       = trim($_POST['faculty_code']);
        $first_name         = trim($_POST['first_name']);
        $last_name          = trim($_POST['last_name']);
        $department         = trim($_POST['department']);
        $email              = trim($_POST['email']);
        $phone              = trim($_POST['phone']);
        $max_teaching_hours = intval($_POST['max_teaching_hours']);
        $status             = $_POST['status'];

        $stmt = $conn->prepare("UPDATE faculty SET
            faculty_code=?, first_name=?, last_name=?, department=?,
            email=?, phone=?, max_teaching_hours=?, status=?
            WHERE faculty_id=?");
        $stmt->bind_param("ssssssisi",
            $faculty_code, $first_name, $last_name,
            $department, $email, $phone, $max_teaching_hours, $status, $faculty_id);
        if ($stmt->execute()) {
            $success = "Teacher updated successfully!";
            $active_tab = 'teachers';
        } else {
            $errors[] = "Error updating teacher: " . $conn->error;
        }
    }

    // ---------- DELETE TEACHER ----------
    if (isset($_POST['action']) && $_POST['action'] === 'delete_teacher') {
        $faculty_id = intval($_POST['faculty_id']);
        $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id=?");
        $stmt->bind_param("i", $faculty_id);
        if ($stmt->execute()) {
            $success = "Teacher deleted successfully!";
        } else {
            $errors[] = "Cannot delete — teacher may be assigned to schedules or sections.";
        }
        $active_tab = 'teachers';
    }

    // ---------- ADD REGISTRAR ACCOUNT ----------
    if (isset($_POST['action']) && $_POST['action'] === 'add_registrar') {
        $password   = trim($_POST['password']);
        $full_name  = trim($_POST['full_name']);
        $email      = trim($_POST['reg_email']);
        $phone      = trim($_POST['reg_phone']);
        $department = trim($_POST['reg_department']);
        $status     = $_POST['reg_status'];

        // Auto-generate username from email (part before @)
        $username = strtolower(explode('@', $email)[0]);

        $check = $conn->prepare("SELECT account_id FROM system_accounts WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "Email already exists.";
            $active_tab = 'registrars';
        } else {
            $stmt = $conn->prepare("INSERT INTO system_accounts
                (username, password, full_name, email, phone, department, role, status)
                VALUES (?, ?, ?, ?, ?, ?, 'registrar', ?)");
            $stmt->bind_param("sssssss",
                $username, $password, $full_name,
                $email, $phone, $department, $status);
            if ($stmt->execute()) {
                $success = "Registrar account created successfully!";
            } else {
                $errors[] = "Error creating account: " . $conn->error;
            }
            $active_tab = 'registrars';
        }
    }

    // ---------- EDIT REGISTRAR ----------
    if (isset($_POST['action']) && $_POST['action'] === 'edit_registrar') {
        $account_id = intval($_POST['account_id']);
        $full_name  = trim($_POST['full_name']);
        $email      = trim($_POST['reg_email']);
        $phone      = trim($_POST['reg_phone']);
        $department = trim($_POST['reg_department']);
        $status     = $_POST['reg_status'];
        $new_pass   = trim($_POST['new_password']);

        if (!empty($new_pass)) {
            $stmt = $conn->prepare("UPDATE system_accounts SET
                full_name=?, email=?, phone=?, department=?, status=?, password=?
                WHERE account_id=?");
            $stmt->bind_param("ssssssi",
                $full_name, $email, $phone, $department, $status, $new_pass, $account_id);
        } else {
            $stmt = $conn->prepare("UPDATE system_accounts SET
                full_name=?, email=?, phone=?, department=?, status=?
                WHERE account_id=?");
            $stmt->bind_param("sssssi",
                $full_name, $email, $phone, $department, $status, $account_id);
        }

        if ($stmt->execute()) {
            $success = "Registrar account updated successfully!";
        } else {
            $errors[] = "Error updating account: " . $conn->error;
        }
        $active_tab = 'registrars';
    }

    // ---------- DELETE REGISTRAR ----------
    if (isset($_POST['action']) && $_POST['action'] === 'delete_registrar') {
        $account_id = intval($_POST['account_id']);
        $stmt = $conn->prepare("DELETE FROM system_accounts WHERE account_id=?");
        $stmt->bind_param("i", $account_id);
        if ($stmt->execute()) {
            $success = "Registrar account deleted.";
        } else {
            $errors[] = "Error deleting account.";
        }
        $active_tab = 'registrars';
    }
}

// ================================================================
// FETCH DATA
// ================================================================
$teachers   = $conn->query("SELECT * FROM faculty ORDER BY last_name, first_name");
$registrars = $conn->query("SELECT * FROM system_accounts WHERE role='registrar' ORDER BY full_name");

$teacher_count   = $teachers ? $teachers->num_rows : 0;
$registrar_count = $registrars ? $registrars->num_rows : 0;
?>

<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Staff Management</h1>
            <p>Manage teachers and registrar accounts</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($e) ?>
        </div>
    <?php endforeach; ?>

    <!-- STATS -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="stats-card blue">
                <h3>Total Teachers</h3>
                <div class="number"><?= $teacher_count ?></div>
                <i class="bi bi-person-workspace icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card green">
                <h3>Registrar Accounts</h3>
                <div class="number"><?= $registrar_count ?></div>
                <i class="bi bi-person-badge-fill icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="stats-card purple">
                <h3>Total Staff</h3>
                <div class="number"><?= $teacher_count + $registrar_count ?></div>
                <i class="bi bi-people-fill icon"></i>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="content-card">
        <div class="staff-tabs mb-4">
            <a href="?tab=teachers"
               class="staff-tab <?= $active_tab === 'teachers' ? 'active' : '' ?>">
                <i class="bi bi-person-workspace me-1"></i> Teachers
                <span class="tab-count"><?= $teacher_count ?></span>
            </a>
            <a href="?tab=registrars"
               class="staff-tab <?= $active_tab === 'registrars' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill me-1"></i> Registrar Accounts
                <span class="tab-count"><?= $registrar_count ?></span>
            </a>
        </div>

        <!-- ==================== TEACHERS TAB ==================== -->
        <?php if ($active_tab === 'teachers'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
           <h5 class="mb-0" style="color:var(--text-primary); font-weight:700;">Teacher List</h5>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-plus-lg me-2"></i>Add Teacher
            </button>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Faculty Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Max Hrs</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Re-fetch for display after possible POST
                    $teachers_display = $conn->query("SELECT * FROM faculty ORDER BY last_name, first_name");
                    if ($teachers_display && $teachers_display->num_rows > 0):
                        while ($t = $teachers_display->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['faculty_code']) ?></strong></td>
                        <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
                        <td><?= htmlspecialchars($t['department']) ?></td>
                        <td><?= htmlspecialchars($t['email']) ?></td>
                        <td><?= htmlspecialchars($t['phone'] ?? '—') ?></td>
                        <td><?= $t['max_teaching_hours'] ?> hrs</td>
                        <td>
                            <?php if ($t['status'] === 'Active'): ?>
                                <span class="badge-success">Active</span>
                            <?php elseif ($t['status'] === 'On Leave'): ?>
                                <span class="badge-warning">On Leave</span>
                            <?php else: ?>
                                <span class="badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon"
                                title="Edit"
                                onclick='openEditTeacher(<?= json_encode($t) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger"
                                title="Delete"
                                onclick="confirmDeleteTeacher(<?= $t['faculty_id'] ?>, '<?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-3">No teachers found. Add one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ==================== REGISTRARS TAB ==================== -->
        <?php elseif ($active_tab === 'registrars'): ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
               <h5 class="mb-0" style="color:var(--text-primary); font-weight:700;">Registrar Accounts</h5>
                <small class="text-muted">These accounts can log in to the Registrar side of the system.</small>
            </div>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRegistrarModal">
                <i class="bi bi-plus-lg me-2"></i>Add Registrar Account
            </button>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $reg_display = $conn->query("SELECT * FROM system_accounts WHERE role='registrar' ORDER BY full_name");
                    if ($reg_display && $reg_display->num_rows > 0):
                        while ($r = $reg_display->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['username']) ?></strong></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
                        <td>
                            <?php if ($r['status'] === 'Active'): ?>
                                <span class="badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon"
                                title="Edit"
                                onclick='openEditRegistrar(<?= json_encode($r) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger"
                                title="Delete"
                                onclick="confirmDeleteRegistrar(<?= $r['account_id'] ?>, '<?= htmlspecialchars($r['username']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center py-3">No registrar accounts found. Add one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>
    </div><!-- end content-card -->
</div><!-- end main-content -->


<!-- ================================================================
     ADD TEACHER MODAL
================================================================ -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_teacher">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Faculty Code <span class="text-danger">*</span></label>
                            <input type="text" name="faculty_code" class="form-control" placeholder="e.g. FAC-001" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. College of IT" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 09XXXXXXXXX">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Teaching Hours</label>
                            <input type="number" name="max_teaching_hours" class="form-control" value="24" min="1" max="40">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Add Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ================================================================
     EDIT TEACHER MODAL
================================================================ -->
<div class="modal fade" id="editTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_teacher">
                <input type="hidden" name="faculty_id" id="edit_faculty_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Faculty Code</label>
                            <input type="text" name="faculty_code" id="edit_faculty_code" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" id="edit_department" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Teaching Hours</label>
                            <input type="number" name="max_teaching_hours" id="edit_max_hours" class="form-control" min="1" max="40">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ================================================================
     DELETE TEACHER FORM (hidden)
================================================================ -->
<form method="POST" id="deleteTeacherForm" style="display:none;">
    <input type="hidden" name="action" value="delete_teacher">
    <input type="hidden" name="faculty_id" id="delete_faculty_id">
</form>


<!-- ================================================================
     ADD REGISTRAR MODAL
================================================================ -->
<div class="modal fade" id="addRegistrarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_registrar">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add Registrar Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="reg_email" class="form-control" required placeholder="Used for login">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control" required placeholder="Set initial password">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="reg_department" class="form-control" placeholder="e.g. Registrar's Office">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="reg_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="reg_phone" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="reg_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ================================================================
     EDIT REGISTRAR MODAL
================================================================ -->
<div class="modal fade" id="editRegistrarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_registrar">
                <input type="hidden" name="account_id" id="edit_account_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Registrar Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="edit_reg_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="text" name="new_password" class="form-control" placeholder="Enter new password or leave blank">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="reg_department" id="edit_reg_department" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="reg_email" id="edit_reg_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="reg_phone" id="edit_reg_phone" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="reg_status" id="edit_reg_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- DELETE REGISTRAR FORM (hidden) -->
<form method="POST" id="deleteRegistrarForm" style="display:none;">
    <input type="hidden" name="action" value="delete_registrar">
    <input type="hidden" name="account_id" id="delete_account_id">
</form>


<style>
.staff-tabs {
     display: flex;
    gap: 6px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 0;
    margin-bottom: 20px;
}
.staff-tab {
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
.staff-tab:hover {  color: var(--text-primary);
    background: rgba(255,255,255,0.04); }
.staff-tab.active {
  color: var(--text-primary);
    background: var(--color-surface);
    border-color: var(--color-border);
    border-bottom-color: var(--color-surface);
}
.tab-count {
     background: rgba(255,255,255,0.07);
    color: var(--text-secondary);
    border-radius: 20px;
    padding: 1px 8px;
    font-size: 11px;
    font-weight: 700;
}
.staff-tab.active .tab-count { background: var(--accent);
    color: #fff;
}
</style>

<script>
// ---- TEACHER EDIT ----
function openEditTeacher(t) {
    document.getElementById('edit_faculty_id').value   = t.faculty_id;
    document.getElementById('edit_faculty_code').value = t.faculty_code;
    document.getElementById('edit_first_name').value   = t.first_name;
    document.getElementById('edit_last_name').value    = t.last_name;
    document.getElementById('edit_department').value   = t.department;
    document.getElementById('edit_email').value        = t.email;
    document.getElementById('edit_phone').value        = t.phone ?? '';
    document.getElementById('edit_max_hours').value    = t.max_teaching_hours;
    document.getElementById('edit_status').value       = t.status;
    new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
}

function confirmDeleteTeacher(id, name) {
    if (confirm('Delete teacher "' + name + '"?\n\nNote: Cannot delete if assigned to active schedules or sections.')) {
        document.getElementById('delete_faculty_id').value = id;
        document.getElementById('deleteTeacherForm').submit();
    }
}

// ---- REGISTRAR EDIT ----
function openEditRegistrar(r) {
    document.getElementById('edit_account_id').value     = r.account_id;
    document.getElementById('edit_reg_fullname').value   = r.full_name;
    document.getElementById('edit_reg_department').value = r.department ?? '';
    document.getElementById('edit_reg_email').value      = r.email ?? '';
    document.getElementById('edit_reg_phone').value      = r.phone ?? '';
    document.getElementById('edit_reg_status').value     = r.status;
    new bootstrap.Modal(document.getElementById('editRegistrarModal')).show();
}

function confirmDeleteRegistrar(id, username) {
    if (confirm('Delete registrar account "' + username + '"? This cannot be undone.')) {
        document.getElementById('delete_account_id').value = id;
        document.getElementById('deleteRegistrarForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
