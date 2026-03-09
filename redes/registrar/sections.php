<?php
require_once 'includes/auth.php'; require_once '../config/db.php';
$page_title = 'Sections - Class Scheduling System';

// ================================================================
// REGISTRAR: EDIT ONLY — no add, no delete
// Only editable fields: total_students, adviser_id, status
// ================================================================
$success_msg = '';
$error_msg   = '';

if (isset($_POST['edit_section'])) {
    $section_id     = intval($_POST['section_id']);
    $total_students = intval($_POST['total_students']);
    $adviser_id     = !empty($_POST['adviser_id']) ? intval($_POST['adviser_id']) : NULL;
    $status         = $_POST['status'];

    // Registrar can only update student count, adviser, and status
    $stmt = $conn->prepare("
        UPDATE sections SET total_students=?, adviser_id=?, status=?
        WHERE section_id=?
    ");
    $stmt->bind_param("iisi", $total_students, $adviser_id, $status, $section_id);
    if ($stmt->execute()) {
        header("Location: sections.php?success=updated");
        exit();
    } else {
        header("Location: sections.php?error=update_fail");
        exit();
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'updated') $success_msg = 'Section updated successfully!';
if (isset($_GET['error']))  $error_msg = 'Failed to update section. Please try again.';

// ================================================================
// FETCH FACULTY (for adviser dropdown, filtered by program JS-side)
// ================================================================
$all_faculty_js = [];
$fac_res = $conn->query("SELECT faculty_id, first_name, last_name, department FROM faculty WHERE status='Active' ORDER BY last_name, first_name");
if ($fac_res) while ($f = $fac_res->fetch_assoc()) $all_faculty_js[] = $f;

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

    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Section Management</h1>
            <p>View sections and update student counts or adviser assignments</p>
        </div>
        <div class="registrar-access-badge">
            <i class="bi bi-pencil-square me-1"></i> Edit Access Only
        </div>
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
        <!-- SEARCH -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="color:var(--text-primary); font-weight:700; margin:0;">Section List</h5>
            <input type="text" id="sectionSearch" class="form-control form-control-sm"
                placeholder="Search section, program…" style="width:220px;"
                oninput="filterSections()">
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="sectionTable">
                <thead>
                    <tr>
                        <th>Section Name</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Total Students</th>
                        <th>Adviser</th>
                        <th>Academic Term</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sectionTbody">
                    <?php
                    $result = $conn->query("
                        SELECT s.*,
                            CONCAT(f.first_name,' ',f.last_name) AS adviser_name,
                            at.semester AS term_semester,
                            at.academic_year AS term_year
                        FROM sections s
                        LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                        LEFT JOIN academic_terms at ON s.term_id = at.term_id
                        ORDER BY s.section_id DESC
                    ");
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                        <td><?= htmlspecialchars($programNames[$row['program']] ?? $row['program']) ?></td>
                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                        <td style="text-align:center; font-weight:700;"><?= $row['total_students'] ?></td>
                        <td><?= htmlspecialchars($row['adviser_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['term_semester'] && $row['term_year']): ?>
                                <span class="badge bg-info text-dark" style="font-size:11px;">
                                    <?= htmlspecialchars($row['term_semester']) ?> (<?= htmlspecialchars($row['term_year']) ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
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
                            <!-- EDIT ONLY -->
                            <button class="btn-icon" title="Edit"
                                onclick='openEditSection(<?= json_encode($row) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <!-- DELETE disabled for registrar -->
                            <button class="btn-icon text-muted" title="Delete (Admin Only)" disabled
                                style="opacity:0.35; cursor:not-allowed;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-3">No sections found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="sectionNoResults" class="text-center py-3 text-muted" style="display:none;">
                No sections match your search.
            </div>
        </div>
    </div>
</div>


<!-- ================================================================
     EDIT SECTION MODAL (Registrar — limited fields)
================================================================ -->
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

                    <!-- READONLY INFO (cannot be changed by registrar) -->
                    <div class="p-3 mb-3 rounded" style="background:var(--color-surface2); border:1px solid var(--color-border);">
                        <div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:8px;">
                            <i class="bi bi-lock-fill me-1"></i>Read-Only (Admin Only)
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:11px;">Section Name</label>
                                <input type="text" id="edit_section_name" class="form-control form-control-sm" readonly
                                    style="background:transparent; cursor:not-allowed;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:11px;">Program</label>
                                <input type="text" id="edit_program_display" class="form-control form-control-sm" readonly
                                    style="background:transparent; cursor:not-allowed;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:11px;">Year Level</label>
                                <input type="text" id="edit_year_level" class="form-control form-control-sm" readonly
                                    style="background:transparent; cursor:not-allowed;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:11px;">Academic Term</label>
                                <input type="text" id="edit_term_label" class="form-control form-control-sm" readonly
                                    style="background:transparent; cursor:not-allowed;">
                            </div>
                        </div>
                    </div>

                    <!-- EDITABLE FIELDS -->
                    <div class="mb-3">
                        <label class="form-label">Total Students</label>
                        <input type="number" name="total_students" id="edit_total_students" class="form-control" min="0" max="60">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select name="adviser_id" id="edit_adviser_id" class="form-select">
                            <option value="">— No Adviser —</option>
                        </select>
                        <small id="edit_adviser_hint" class="text-muted d-block mt-1"></small>
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

<script>
const ALL_FACULTY = <?= json_encode($all_faculty_js) ?>;
const PROGRAM_TO_DEPT = {
    'BSIT':   'College of Computer Studies (CCS)',
    'BSTM':   'College of Hospitality and Tourism Management (CHTM)',
    'BSBA':   'College of Business Administration (CBA)',
    'BSCRIM': 'College of Criminal Justice Education (CCJE)',
    'BSCE':   'College of Engineering (COE)'
};
const PROGRAM_TO_SHORT = {
    'BSIT':'CCS Dept','BSTM':'CHTM Dept','BSBA':'CBA Dept','BSCRIM':'CCJE Dept','BSCE':'COE Dept'
};
const PROGRAM_NAMES = <?= json_encode($programNames) ?>;

function filterAdviserDropdown(programCode, selectedId) {
    const sel  = document.getElementById('edit_adviser_id');
    const hint = document.getElementById('edit_adviser_hint');
    sel.innerHTML = '<option value="">— No Adviser —</option>';
    const dept = PROGRAM_TO_DEPT[programCode] || '';
    const short = PROGRAM_TO_SHORT[programCode] || '';
    const filtered = ALL_FACULTY.filter(f => f.department.trim() === dept.trim());
    filtered.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.faculty_id;
        opt.textContent = f.last_name + ', ' + f.first_name;
        if (String(f.faculty_id) === String(selectedId)) opt.selected = true;
        sel.appendChild(opt);
    });
    hint.innerHTML = filtered.length > 0
        ? '<i class="bi bi-funnel-fill me-1"></i>Showing <strong>' + filtered.length + '</strong> from <strong>' + short + '</strong>'
        : '<i class="bi bi-exclamation-triangle-fill me-1" style="color:#f59e0b;"></i>No active faculty in ' + short;
}

function openEditSection(s) {
    document.getElementById('edit_section_id').value    = s.section_id;
    document.getElementById('edit_section_name').value  = s.section_name;
    document.getElementById('edit_program_display').value = PROGRAM_NAMES[s.program] || s.program;
    document.getElementById('edit_year_level').value    = s.year_level;
    document.getElementById('edit_term_label').value    = (s.term_semester && s.term_year)
        ? s.term_semester + ' (' + s.term_year + ')' : '—';
    document.getElementById('edit_total_students').value = s.total_students;
    document.getElementById('edit_status').value        = s.status;

    filterAdviserDropdown(s.program, s.adviser_id ?? '');
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

// Search filter
function filterSections() {
    const q    = document.getElementById('sectionSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#sectionTbody tr');
    let vis = 0;
    rows.forEach(row => {
        const show = row.textContent.toLowerCase().includes(q);
        row.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    document.getElementById('sectionNoResults').style.display =
        (vis === 0 && q.length > 0) ? 'block' : 'none';
}
</script>

<style>
.registrar-access-badge {
    background: rgba(79,163,255,0.1); border: 1px solid rgba(79,163,255,0.25);
    color: var(--accent); padding: 7px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 700;
}
</style>

<?php include '../includes/footer.php'; ?>