<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Staff Management - Admin Panel';

$errors     = [];
$success    = '';
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
                $success   = "Teacher added successfully!";
                $active_tab = 'teachers';
            } else {
                $errors[] = "Error adding teacher: " . $conn->error;
            }
        }
    }

    // ---------- EDIT TEACHER ----------
    if (isset($_POST['action']) && $_POST['action'] === 'edit_teacher') {
        $faculty_id         = intval($_POST['faculty_id']);
        $first_name         = trim($_POST['first_name']);
        $last_name          = trim($_POST['last_name']);
        $department         = trim($_POST['department']);
        $email              = trim($_POST['email']);
        $phone              = trim($_POST['phone']);
        $max_teaching_hours = intval($_POST['max_teaching_hours']);
        $status             = $_POST['status'];

        // ── DUPLICATE EMAIL CHECK (exclude self) ──
        $dupCheck = $conn->prepare("SELECT faculty_id FROM faculty WHERE email=? AND faculty_id != ?");
        $dupCheck->bind_param("si", $email, $faculty_id);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
            $errors[]   = "That email is already used by another teacher.";
            $active_tab = 'teachers';
        } else {
            // faculty_code is NOT updated — kept readonly
            $stmt = $conn->prepare("UPDATE faculty SET
                first_name=?, last_name=?, department=?,
                email=?, phone=?, max_teaching_hours=?, status=?
                WHERE faculty_id=?");
            $stmt->bind_param("sssssisi",
                $first_name, $last_name, $department,
                $email, $phone, $max_teaching_hours, $status, $faculty_id);
            if ($stmt->execute()) {
                $success   = "Teacher updated successfully!";
                $active_tab = 'teachers';
            } else {
                $errors[] = "Error updating teacher: " . $conn->error;
            }
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

    // ---------- ADD REGISTRAR ----------
    if (isset($_POST['action']) && $_POST['action'] === 'add_registrar') {
        $password   = trim($_POST['password']);
        $full_name  = trim($_POST['full_name']);
        $email      = trim($_POST['reg_email']);
        $phone      = trim($_POST['reg_phone']);
        $department = trim($_POST['reg_department']);
        $status     = $_POST['reg_status'];
        $username   = strtolower(explode('@', $email)[0]);

        $pw_errors = [];
        if (strlen($password) < 8)                    $pw_errors[] = "at least 8 characters";
        if (!preg_match('/[0-9]/', $password))         $pw_errors[] = "at least 1 number";
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) $pw_errors[] = "at least 1 special character";

        if (!empty($pw_errors)) {
            $errors[]   = "Password must have: " . implode(', ', $pw_errors) . ".";
            $active_tab = 'registrars';
        } else {
            $check = $conn->prepare("SELECT account_id FROM system_accounts WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors[]   = "Email already exists.";
                $active_tab = 'registrars';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $conn->prepare("INSERT INTO system_accounts
                    (username, password, full_name, email, phone, department, role, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'registrar', ?)");
                $stmt->bind_param("sssssss",
                    $username, $hashed, $full_name,
                    $email, $phone, $department, $status);
                if ($stmt->execute()) {
                    $success = "Registrar account created successfully!";
                } else {
                    $errors[] = "Error creating account: " . $conn->error;
                }
                $active_tab = 'registrars';
            }
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
            $pw_errors = [];
            if (strlen($new_pass) < 8)                    $pw_errors[] = "at least 8 characters";
            if (!preg_match('/[0-9]/', $new_pass))         $pw_errors[] = "at least 1 number";
            if (!preg_match('/[^a-zA-Z0-9]/', $new_pass)) $pw_errors[] = "at least 1 special character";

            if (!empty($pw_errors)) {
                $errors[]   = "Password must have: " . implode(', ', $pw_errors) . ".";
                $active_tab = 'registrars';
            } else {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt   = $conn->prepare("UPDATE system_accounts SET
                    full_name=?, email=?, phone=?, department=?, status=?, password=?
                    WHERE account_id=?");
                $stmt->bind_param("ssssssi",
                    $full_name, $email, $phone, $department, $status, $hashed, $account_id);
                if ($stmt->execute()) {
                    $success = "Registrar account updated successfully!";
                } else {
                    $errors[] = "Error updating account: " . $conn->error;
                }
                $active_tab = 'registrars';
            }
        } else {
            $stmt = $conn->prepare("UPDATE system_accounts SET
                full_name=?, email=?, phone=?, department=?, status=?
                WHERE account_id=?");
            $stmt->bind_param("sssssi",
                $full_name, $email, $phone, $department, $status, $account_id);
            if ($stmt->execute()) {
                $success = "Registrar account updated successfully!";
            } else {
                $errors[] = "Error updating account: " . $conn->error;
            }
            $active_tab = 'registrars';
        }
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

$teacher_count   = $teachers   ? $teachers->num_rows   : 0;
$registrar_count = $registrars ? $registrars->num_rows : 0;

// ── AUTO-GENERATE NEXT FACULTY CODE ──
// Find highest existing FAC-XXX number and increment
$maxCode = $conn->query("SELECT faculty_code FROM faculty WHERE faculty_code LIKE 'FAC-%' ORDER BY faculty_code DESC")->fetch_assoc();
$nextNum = 1;
if ($maxCode) {
    $parts   = explode('-', $maxCode['faculty_code']);
    $nextNum = intval(end($parts)) + 1;
}
// Build list of all available codes (any gaps + next new one)
$usedCodes = [];
$usedRes   = $conn->query("SELECT faculty_code FROM faculty WHERE faculty_code LIKE 'FAC-%'");
while ($uc = $usedRes->fetch_assoc()) $usedCodes[] = $uc['faculty_code'];

$availableCodes = [];
for ($i = 1; $i <= $nextNum; $i++) {
    $code = "FAC-" . str_pad($i, 3, "0", STR_PAD_LEFT);
    if (!in_array($code, $usedCodes)) $availableCodes[] = $code;
}

// Department options (shared between teacher + registrar)
$departments = [
    'College of Computer Studies (CCS)'                     => 'CCS — BS Information Technology (BSIT)',
    'College of Hospitality and Tourism Management (CHTM)'  => 'CHTM — BS Tourism Management (BSTM)',
    'College of Business Administration (CBA)'              => 'CBA — BS Business Administration (BSBA)',
    'College of Criminal Justice Education (CCJE)'          => 'CCJE — BS Criminology (BS Crim)',
    'College of Engineering (COE)'                          => 'COE — BS Civil Engineering (BSCE)',
];
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
            <div class="d-flex gap-2 align-items-center">
                <!-- SEARCH BOX -->
                <input type="text" id="teacherSearch" class="form-control form-control-sm"
                    placeholder="Search name, code, dept…" style="width:220px;"
                    oninput="filterTeachers()">
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="bi bi-plus-lg me-2"></i>Add Teacher
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="teacherTable">
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
                <tbody id="teacherTbody">
                    <?php
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
                            <button class="btn-icon" title="Edit"
                                onclick='openEditTeacher(<?= json_encode($t) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger" title="Delete"
                                onclick="confirmDeleteTeacher(<?= $t['faculty_id'] ?>, '<?= htmlspecialchars($t['first_name'].' '.$t['last_name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr id="noTeacherRow"><td colspan="8" class="text-center py-3">No teachers found. Add one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="teacherNoResults" class="text-center py-3 text-muted" style="display:none;">
                No teachers match your search.
            </div>
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
                            <button class="btn-icon" title="Edit"
                                onclick='openEditRegistrar(<?= json_encode($r) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn-icon text-danger" title="Delete"
                                onclick="confirmDeleteRegistrar(<?= $r['account_id'] ?>, '<?= htmlspecialchars($r['username'], ENT_QUOTES) ?>')">
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
    </div>
</div>


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
                        <!-- AUTO-GENERATED FACULTY CODE (no manual entry needed) -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Faculty Code <span class="text-danger">*</span></label>
                            <select name="faculty_code" class="form-select" required>
                                <option value="">Select Code</option>
                                <?php foreach ($availableCodes as $code): ?>
                                    <option value="<?= $code ?>"><?= $code ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Next available: <strong><?= $availableCodes[0] ?? 'None' ?></strong></small>
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
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $val => $label):
                                    if ($val === "Registrar's Office") continue; // Teachers don't use this ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <!-- FACULTY CODE: readonly — cannot be changed after creation -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Faculty Code</label>
                            <input type="text" id="edit_faculty_code_display" class="form-control" readonly
                                style="background:var(--color-surface2); cursor:not-allowed;">
                            <small class="text-muted">Faculty code cannot be changed.</small>
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
                            <select name="department" id="edit_department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $val => $label):
                                    if ($val === "Registrar's Office") continue; ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
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


<!-- DELETE TEACHER FORM (hidden) -->
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
                            <label class="form-label">Email <span class="text-danger">*</span>
                                <small class="text-muted fw-normal">(used as username)</small>
                            </label>
                            <input type="email" name="reg_email" class="form-control" required
                                placeholder="email@school.edu"
                                oninput="previewUsername(this.value)">
                            <small class="text-muted">Username will be: <strong id="add_username_preview">—</strong></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="add_reg_password" class="form-control" required
                                placeholder="Set initial password"
                                oninput="checkPwPolicy(this.value,'add')">
                        </div>
                    </div>
                    <!-- Password Policy Checker -->
                    <div class="mb-3" id="add_pw_policy" style="background:var(--color-surface2);border-radius:8px;padding:10px 14px;font-size:12.5px;display:none;">
                        <div style="font-weight:600;margin-bottom:6px;color:var(--text-secondary);">Password must have:</div>
                        <div id="add_rule_len" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 8 characters</div>
                        <div id="add_rule_num" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 1 number</div>
                        <div id="add_rule_spc" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 1 special character</div>
                    </div>
                    <!-- DEPARTMENT DROPDOWN -->
                    <div class="mb-3">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="reg_department" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="reg_phone" class="form-control">
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
                    <!-- Show username (readonly info) -->
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit_reg_username_display" class="form-control" readonly
                            style="background:var(--color-surface2); cursor:not-allowed;">
                        <small class="text-muted">Username is auto-generated from email and cannot be changed.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="edit_reg_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" name="new_password" id="edit_reg_password" class="form-control"
                            placeholder="Enter new password or leave blank"
                            oninput="checkPwPolicy(this.value,'edit')">
                    </div>
                    <!-- Password Policy Checker -->
                    <div class="mb-3" id="edit_pw_policy" style="background:var(--color-surface2);border-radius:8px;padding:10px 14px;font-size:12.5px;display:none;">
                        <div style="font-weight:600;margin-bottom:6px;color:var(--text-secondary);">Password must have:</div>
                        <div id="edit_rule_len" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 8 characters</div>
                        <div id="edit_rule_num" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 1 number</div>
                        <div id="edit_rule_spc" class="pw-rule"><i class="bi bi-x-circle-fill"></i> At least 1 special character</div>
                    </div>
                    <!-- DEPARTMENT DROPDOWN -->
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select name="reg_department" id="edit_reg_department" class="form-select">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
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
.staff-tabs { display:flex; gap:6px; border-bottom:1px solid var(--color-border); padding-bottom:0; margin-bottom:20px; }
.staff-tab {
    padding:9px 18px; border-radius:8px 8px 0 0; font-size:13px; font-weight:600;
    color:var(--text-secondary); text-decoration:none;
    border:1px solid transparent; border-bottom:none;
    display:flex; align-items:center; gap:8px; transition:all 0.15s;
    position:relative; bottom:-1px; font-family:var(--font-body);
}
.staff-tab:hover { color:var(--text-primary); background:rgba(255,255,255,0.04); }
.staff-tab.active {
    color:var(--text-primary); background:var(--color-surface);
    border-color:var(--color-border); border-bottom-color:var(--color-surface);
}
.tab-count {
    background:rgba(255,255,255,0.07); color:var(--text-secondary);
    border-radius:20px; padding:1px 8px; font-size:11px; font-weight:700;
}
.staff-tab.active .tab-count { background:var(--accent); color:#fff; }
.pw-rule { display:flex; align-items:center; gap:7px; padding:2px 0; color:var(--text-muted); transition:color 0.2s; }
.pw-rule i { font-size:13px; flex-shrink:0; }
</style>

<script>
// ── TEACHER TABLE SEARCH ──
function filterTeachers() {
    const q     = document.getElementById('teacherSearch').value.toLowerCase();
    const rows  = document.querySelectorAll('#teacherTbody tr');
    let visible = 0;

    rows.forEach(row => {
        if (row.id === 'noTeacherRow') return;
        const text = row.textContent.toLowerCase();
        const show = text.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('teacherNoResults').style.display =
        (visible === 0 && q.length > 0) ? 'block' : 'none';
}

// ── USERNAME PREVIEW (Add Registrar) ──
function previewUsername(email) {
    const preview = document.getElementById('add_username_preview');
    const at = email.indexOf('@');
    preview.textContent = at > 0 ? email.substring(0, at).toLowerCase() : '—';
}

// ── TEACHER EDIT ──
function openEditTeacher(t) {
    document.getElementById('edit_faculty_id').value          = t.faculty_id;
    document.getElementById('edit_faculty_code_display').value = t.faculty_code; // readonly display
    document.getElementById('edit_first_name').value          = t.first_name;
    document.getElementById('edit_last_name').value           = t.last_name;
    document.getElementById('edit_email').value               = t.email;
    document.getElementById('edit_phone').value               = t.phone ?? '';
    document.getElementById('edit_max_hours').value           = t.max_teaching_hours;
    document.getElementById('edit_status').value              = t.status;

    // Set department dropdown — handles exact match
    const deptSel = document.getElementById('edit_department');
    deptSel.value = t.department;
    if (deptSel.value !== t.department) {
        // Dept not in list — add it so data isn't lost
        const opt = document.createElement('option');
        opt.value = t.department;
        opt.textContent = t.department;
        deptSel.appendChild(opt);
        deptSel.value = t.department;
    }

    new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
}

function confirmDeleteTeacher(id, name) {
    if (confirm('Delete teacher "' + name + '"?\n\nNote: Cannot delete if assigned to active schedules or sections.')) {
        document.getElementById('delete_faculty_id').value = id;
        document.getElementById('deleteTeacherForm').submit();
    }
}

// ── REGISTRAR EDIT ──
function openEditRegistrar(r) {
    document.getElementById('edit_account_id').value          = r.account_id;
    document.getElementById('edit_reg_username_display').value = r.username; // show username readonly
    document.getElementById('edit_reg_fullname').value        = r.full_name;
    document.getElementById('edit_reg_email').value           = r.email   ?? '';
    document.getElementById('edit_reg_phone').value           = r.phone   ?? '';
    document.getElementById('edit_reg_status').value          = r.status;

    // Set department dropdown
    const deptSel = document.getElementById('edit_reg_department');
    deptSel.value = r.department ?? '';
    if (r.department && deptSel.value !== r.department) {
        const opt = document.createElement('option');
        opt.value = r.department;
        opt.textContent = r.department;
        deptSel.appendChild(opt);
        deptSel.value = r.department;
    }

    new bootstrap.Modal(document.getElementById('editRegistrarModal')).show();
}

function confirmDeleteRegistrar(id, username) {
    if (confirm('Delete registrar account "' + username + '"? This cannot be undone.')) {
        document.getElementById('delete_account_id').value = id;
        document.getElementById('deleteRegistrarForm').submit();
    }
}

// ── PASSWORD POLICY CHECKER ──
function checkPwPolicy(val, prefix) {
    const box = document.getElementById(prefix + '_pw_policy');
    if (!box) return;
    box.style.display = val.length > 0 ? 'block' : 'none';
    setRule(prefix + '_rule_len', val.length >= 8);
    setRule(prefix + '_rule_num', /[0-9]/.test(val));
    setRule(prefix + '_rule_spc', /[^a-zA-Z0-9]/.test(val));
}
function setRule(id, passed) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.color = passed ? '#22c55e' : 'var(--text-muted)';
    el.querySelector('i').className = passed ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill';
}

// ── RESET MODALS ON CLOSE ──
document.addEventListener('DOMContentLoaded', function () {
    ['add','edit'].forEach(function(prefix) {
        const modal = document.getElementById(prefix + 'RegistrarModal');
        if (!modal) return;
        modal.addEventListener('hidden.bs.modal', function () {
            const inp = document.getElementById(prefix + '_reg_password');
            if (inp) inp.value = '';
            const box = document.getElementById(prefix + '_pw_policy');
            if (box) box.style.display = 'none';
            document.querySelectorAll('#' + prefix + '_pw_policy .pw-rule').forEach(function (el) {
                el.style.color = 'var(--text-muted)';
                el.querySelector('i').className = 'bi bi-x-circle-fill';
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>