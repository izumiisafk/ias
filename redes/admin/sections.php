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
    $term_id        = !empty($_POST['term_id']) ? intval($_POST['term_id']) : NULL;

    $checkStmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name=? AND term_id=? LIMIT 1");
    $checkStmt->bind_param("si", $section_name, $term_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        header("Location: sections.php?error=duplicate");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO sections 
        (section_name, program, year_level, total_students, adviser_id, term_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'Active')");
    $stmt->bind_param("sssiis",
        $section_name, $program, $year_level,
        $total_students, $adviser_id, $term_id
    );

    if ($stmt->execute()) {
        header("Location: sections.php?success=added");
        exit();
    } else {
        header("Location: sections.php?error=insert_fail");
        exit();
    }
}

// --- ERROR / SUCCESS MESSAGES ---
$success_msg = '';
$error_msg   = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added')   $success_msg = 'Section added successfully!';
    if ($_GET['success'] === 'updated') $success_msg = 'Section updated successfully!';
    if ($_GET['success'] === 'deleted') $success_msg = 'Section deleted successfully!';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'duplicate')       $error_msg = 'Section already exists for this term!';
    elseif ($_GET['error'] === 'insert_fail') $error_msg = 'Failed to add section. Try again!';
    else $error_msg = 'Cannot delete — section may have active schedules.';
}

// ---------- EDIT ----------
if (isset($_POST['edit_section'])) {
    $section_id = intval($_POST['section_id']);
    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : NULL;
    $term_id    = !empty($_POST['term_id']) ? intval($_POST['term_id']) : NULL;
    $status     = $_POST['status'];

    $stmt = $conn->prepare("UPDATE sections SET adviser_id=?, term_id=?, status=? WHERE section_id=?");
    $stmt->bind_param("iisi", $adviser_id, $term_id, $status, $section_id);

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

// ---------- FETCH ACADEMIC TERMS ----------
$terms = [];
$termResult = $conn->query("SELECT term_id, academic_year, semester, is_active FROM academic_terms ORDER BY academic_year DESC, term_id ASC");
if ($termResult) {
    while ($t = $termResult->fetch_assoc()) $terms[] = $t;
}

$activeTerm = null;
foreach ($terms as $t) {
    if ($t['is_active']) { $activeTerm = $t; break; }
}

// ---------- FETCH ALL FACULTY FOR JS FILTERING ----------
$all_faculty_js = [];
$fac_res = $conn->query("SELECT faculty_id, first_name, last_name, department FROM faculty WHERE status='Active' ORDER BY last_name, first_name");
if ($fac_res) {
    while ($f = $fac_res->fetch_assoc()) $all_faculty_js[] = $f;
}

// ---------- FETCH EXISTING SECTION NAMES FOR JS (to disable duplicates) ----------
$existing_sections_js = [];
$sec_res = $conn->query("SELECT section_name, term_id FROM sections");
if ($sec_res) {
    while ($s = $sec_res->fetch_assoc()) $existing_sections_js[] = $s;
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
                        <th>Academic Term</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
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
                        <td>
                            <?php
                            $programNames = [
                                "BSIT"   => "BS Information Technology",
                                "BSTM"   => "BS Tourism Management",
                                "BSBA"   => "BS Business Administration",
                                "BSCRIM" => "BS Criminology",
                                "BSCE"   => "BS Civil Engineering"
                            ];
                            echo htmlspecialchars($programNames[$row['program']] ?? $row['program']);
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                        <td><?= $row['total_students'] ?></td>
                        <td><?= htmlspecialchars($row['adviser_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['term_semester'] && $row['term_year']): ?>
                                <span class="badge bg-info text-dark">
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
                    <tr><td colspan="8" class="text-center py-3">No sections found. Add one!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- ================================================================
     ADD SECTION MODAL
================================================================ -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">

<div class="modal-header">
    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i>Add New Section</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <!-- PROGRAM -->
    <div class="mb-3">
        <label class="form-label">Program <span class="text-danger">*</span></label>
        <select id="add_program" name="program" class="form-select" required
            onchange="generateSections(); filterAdviser('add', '');">
            <option value="">Select Program</option>
            <option value="BSIT">BS Information Technology (BSIT)</option>
            <option value="BSTM">BS Tourism Management (BSTM)</option>
            <option value="BSBA">BS Business Administration (BSBA)</option>
            <option value="BSCRIM">BS Criminology (BS Crim)</option>
            <option value="BSCE">BS Civil Engineering (BSCE)</option>
        </select>
    </div>

    <!-- YEAR LEVEL -->
    <div class="mb-3">
        <label class="form-label">Year Level <span class="text-danger">*</span></label>
        <select id="year_level" name="year_level" class="form-select" required onchange="generateSections()">
            <option value="">Select Year</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
        </select>
    </div>

    <!-- SEMESTER -->
    <div class="mb-3">
        <label class="form-label">Semester <span class="text-danger">*</span></label>
        <select name="term_id" id="semester" class="form-select" required onchange="generateSections()">
            <option value="">Select Semester</option>
            <?php foreach ($terms as $term):
                $semCode = (strpos($term['semester'], '1st') !== false) ? '1' : '2';
            ?>
            <option value="<?= $term['term_id'] ?>"
                data-sem="<?= $semCode ?>"
                <?= !$term['is_active'] ? 'disabled style="color:#aaa;"' : '' ?>
                <?= ($activeTerm && $activeTerm['term_id'] == $term['term_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($term['semester']) ?> (<?= htmlspecialchars($term['academic_year']) ?>)<?= !$term['is_active'] ? ' — Inactive' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- SECTION NAME (generated) -->
    <div class="mb-3">
        <label class="form-label">Section <span class="text-danger">*</span></label>
        <select id="section_name" name="section_name" class="form-select" required>
            <option value="">Select Program / Year / Semester first</option>
        </select>
        <small id="section_availability_hint" class="text-muted mt-1 d-block"></small>
    </div>

    <!-- TOTAL STUDENTS -->
    <div class="mb-3">
        <label class="form-label">Total Students</label>
        <input type="number" name="total_students" class="form-control" min="0" max="40" value="0">
    </div>

    <!-- ADVISER — filtered by program -->
    <div class="mb-3">
        <label class="form-label">Adviser</label>
        <select name="adviser_id" id="add_adviser_id" class="form-select">
            <option value="">— Select a Program first —</option>
        </select>
        <small id="add_adviser_hint" class="text-muted mt-1 d-block"></small>
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


<!-- ================================================================
     EDIT SECTION MODAL
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

    <div class="mb-3">
        <label class="form-label">Section Name</label>
        <input type="text" id="edit_section_name" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label class="form-label">Program</label>
        <input type="text" id="edit_program_display" class="form-control" readonly>
        <!-- hidden: stores raw program code for JS filtering -->
        <input type="hidden" id="edit_program_code">
    </div>

    <div class="mb-3">
        <label class="form-label">Year Level</label>
        <input type="text" id="edit_year_level" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label class="form-label">Total Students</label>
        <input type="number" id="edit_total_students" class="form-control" readonly>
    </div>

    <div class="mb-3">
        <label class="form-label">Semester</label>
        <input type="text" id="edit_term_label" class="form-control" readonly>
        <input type="hidden" name="term_id" id="edit_term_id">
    </div>

    <!-- ADVISER — filtered by section's program -->
    <div class="mb-3">
        <label class="form-label">Adviser</label>
        <select name="adviser_id" id="edit_adviser_id" class="form-select">
            <option value="">— No Adviser —</option>
        </select>
        <small id="edit_adviser_hint" class="text-muted mt-1 d-block"></small>
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
// ── ALL ACTIVE FACULTY (from PHP, used for client-side filtering) ──
const ALL_FACULTY = <?= json_encode($all_faculty_js) ?>;

// ── EXISTING SECTIONS (used to disable already-added ones) ──
const EXISTING_SECTIONS = <?= json_encode($existing_sections_js) ?>;

// Program code → exact department string (must match faculty.department in DB)
const PROGRAM_TO_DEPT = {
    'BSIT':   'College of Computer Studies (CCS)',
    'BSTM':   'College of Hospitality and Tourism Management (CHTM)',
    'BSBA':   'College of Business Administration (CBA)',
    'BSCRIM': 'College of Criminal Justice Education (CCJE)',
    'BSCE':   'College of Engineering (COE)'
};

// Short label shown in hint text
const PROGRAM_TO_SHORT_DEPT = {
    'BSIT':   'CCS Department',
    'BSTM':   'CHTM Department',
    'BSBA':   'CBA Department',
    'BSCRIM': 'CCJE Department',
    'BSCE':   'COE Department'
};

/**
 * Populate an adviser <select> with only faculty from the matching department.
 * @param {string} mode          - 'add' or 'edit'
 * @param {string} selectedId    - faculty_id to pre-select (pass '' if none)
 * @param {string} programCode   - override program (used by edit modal); if empty, reads from add_program
 */
function filterAdviser(mode, selectedId, programCode) {
    const program = programCode
        || document.getElementById('add_program').value;

    const select  = document.getElementById(mode + '_adviser_id');
    const hint    = document.getElementById(mode + '_adviser_hint');

    // Reset
    select.innerHTML = '<option value="">— No Adviser —</option>';

    if (!program) {
        hint.textContent = 'Select a program to see available advisers.';
        return;
    }

    const dept      = PROGRAM_TO_DEPT[program]       || '';
    const shortDept = PROGRAM_TO_SHORT_DEPT[program] || '';

    // Filter faculty by exact department match
    const filtered = ALL_FACULTY.filter(f => f.department.trim() === dept.trim());

    filtered.forEach(f => {
        const opt       = document.createElement('option');
        opt.value       = f.faculty_id;
        opt.textContent = f.last_name + ', ' + f.first_name;
        if (String(f.faculty_id) === String(selectedId)) opt.selected = true;
        select.appendChild(opt);
    });

    if (filtered.length > 0) {
        hint.innerHTML = '<i class="bi bi-funnel-fill me-1"></i>Showing <strong>'
            + filtered.length + '</strong> adviser(s) from <strong>' + shortDept + '</strong>';
        hint.style.color = 'var(--text-secondary)';
    } else {
        hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>No active faculty found in '
            + shortDept + '. Add faculty first via Staff Management.';
        hint.style.color = '#f59e0b';
    }
}

// ── OPEN EDIT MODAL ──
function openEditSection(s) {
    document.getElementById('edit_section_id').value       = s.section_id;
    document.getElementById('edit_section_name').value     = s.section_name;
    document.getElementById('edit_program_display').value  = s.program;
    document.getElementById('edit_program_code').value     = s.program;
    document.getElementById('edit_year_level').value       = s.year_level;
    document.getElementById('edit_total_students').value   = s.total_students;
    document.getElementById('edit_term_id').value          = s.term_id ?? '';
    document.getElementById('edit_term_label').value       = (s.term_semester && s.term_year)
        ? s.term_semester + ' (' + s.term_year + ')'
        : '—';
    document.getElementById('edit_status').value           = s.status;

    // Filter advisers and pre-select current adviser
    filterAdviser('edit', s.adviser_id ?? '', s.program);

    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

// ── DELETE CONFIRM ──
function confirmDeleteSection(id, name) {
    if (confirm('Delete section "' + name + '"?\n\nNote: Cannot delete if it has active schedules.')) {
        document.getElementById('delete_section_id').value = id;
        document.getElementById('deleteSectionForm').submit();
    }
}

// ── GENERATE SECTION CODE OPTIONS (with duplicate detection) ──
function generateSections() {
    let program   = document.getElementById("add_program").value;
    let year      = document.getElementById("year_level").value;
    let semSelect = document.getElementById("semester");
    let semOption = semSelect.options[semSelect.selectedIndex];
    let sem       = semOption ? semOption.getAttribute("data-sem") : "";
    let termId    = semOption ? semOption.value : "";
    let dropdown  = document.getElementById("section_name");
    let hint      = document.getElementById("section_availability_hint");

    dropdown.innerHTML = "";

    if (!program || !year || !sem) {
        dropdown.innerHTML = '<option value="">Select Program / Year / Semester first</option>';
        if (hint) hint.textContent = '';
        return;
    }

    const yearMap = { "1st Year": "1", "2nd Year": "2", "3rd Year": "3", "4th Year": "4" };
    let yearNum = yearMap[year] || year;

    // Build a Set of taken section names for this specific term
    const takenInTerm = new Set(
        EXISTING_SECTIONS
            .filter(s => String(s.term_id) === String(termId))
            .map(s => s.section_name)
    );

    let totalSlots    = 40;
    let takenCount    = 0;
    let firstAvailable = true;

    for (let i = 1; i <= totalSlots; i++) {
        let num    = i.toString().padStart(2, '0');
        let code   = program + " - " + yearNum + sem + num;
        let option = document.createElement("option");
        option.value = code;

        if (takenInTerm.has(code)) {
            // Already added — grey out and disable
            option.text     = code + " — (Already Added)";
            option.disabled = true;
            option.style.color = "#aaa";
            takenCount++;
        } else {
            option.text = code;
            // Auto-select the first available slot
            if (firstAvailable) {
                option.selected = true;
                firstAvailable  = false;
            }
        }

        dropdown.appendChild(option);
    }

    // Show availability summary hint
    if (hint) {
        let available = totalSlots - takenCount;
        if (available === 0) {
            hint.innerHTML = '<i class="bi bi-x-circle-fill me-1" style="color:#dc3545;"></i>'
                + '<strong style="color:#dc3545;">All 40 sections are already added for this term.</strong>';
        } else {
            hint.innerHTML = '<i class="bi bi-info-circle-fill me-1" style="color:#0d6efd;"></i>'
                + '<strong>' + available + '</strong> of ' + totalSlots
                + ' slots available &nbsp;|&nbsp; <strong>' + takenCount + '</strong> already added.';
            hint.style.color = 'var(--text-secondary)';
        }
    }
}

// Reset add modal on open
document.getElementById('addSectionModal').addEventListener('show.bs.modal', function () {
    document.getElementById('add_adviser_hint').textContent    = 'Select a program to see available advisers.';
    document.getElementById('add_adviser_id').innerHTML        = '<option value="">— Select a Program first —</option>';
    document.getElementById('section_availability_hint').textContent = '';

    // If active term is already selected by default, auto-generate sections
    let semSelect = document.getElementById("semester");
    if (semSelect.value) {
        generateSections();
    }
});
</script>

<?php include '../includes/footer.php'; ?>