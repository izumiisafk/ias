<?php
require_once 'includes/auth.php'; require_once '../config/db.php';

if (!$conn instanceof PDO) {
    die("<div style='padding:20px; font-family:sans-serif; color:#ef4444; background:rgba(239,68,68,0.05); border:1px solid #ef4444; border-radius:8px; margin:20px;'>
            <h3 style='margin-top:0;'>Database Connection Error</h3>
            <p>Could not connect to the database. Please check your <strong>.env</strong> file and network connection.</p>
            <p style='font-size:13px; color:#666;'>Error Details: " . htmlspecialchars($db_error ?: 'Unknown Error') . "</p>
            <a href='../login.php' style='color:#3b82f6;'>&larr; Back to Login</a>
         </div>");
}
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

    try {
        $stmt = $conn->prepare("
            UPDATE sections SET total_students=?, adviser_id=?, status=?
            WHERE section_id=?
        ");
        if ($stmt->execute([$total_students, $adviser_id, $status, $section_id])) {
            header("Location: sections.php?success=updated");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: sections.php?error=update_fail");
        exit();
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'updated') $success_msg = 'Section updated successfully!';
if (isset($_GET['error']))  $error_msg = 'Failed to update section. Please try again.';

// ================================================================
// FETCH FACULTY (for adviser dropdown, filtered by program JS-side)
// ================================================================
$all_faculty_js = $conn->query("SELECT faculty_id, first_name, last_name, department FROM faculty WHERE status='Active' ORDER BY last_name, first_name")->fetchAll();

// ================================================================
// FETCH ALL SUBJECTS (for subject preview panel)
// ================================================================
$all_subjects_js = [];
$sub_res = $conn->query("SELECT subject_id, subject_code, subject_name, units, year_level, department FROM subjects WHERE status='Active' ORDER BY subject_code");
if ($sub_res) {
    while ($sub = $sub_res->fetch()) $all_subjects_js[] = $sub;
}

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
                    $sections_all = $conn->query("
                        SELECT s.*,
                            CONCAT(f.first_name,' ',f.last_name) AS adviser_name,
                            at.semester AS term_semester,
                            at.academic_year AS term_year
                        FROM sections s
                        LEFT JOIN faculty f ON s.adviser_id = f.faculty_id
                        LEFT JOIN academic_terms at ON s.term_id = at.term_id
                        ORDER BY s.section_id DESC
                    ")->fetchAll();
                    if (!empty($sections_all)):
                        foreach ($sections_all as $row):
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
                    <?php endforeach; else: ?>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">

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

                        <!-- RIGHT COLUMN: SUBJECT PREVIEW -->
                        <div class="col-md-6">
                            <div class="subject-preview-panel">
                                <div class="subject-preview-header">
                                    <i class="bi bi-book-fill me-2" style="color:var(--accent);"></i>
                                    <span>Subjects for this Section</span>
                                    <span id="edit_subject_count_badge" class="subject-count-badge" style="display:none;"></span>
                                </div>
                                <div id="edit_subject_preview_body" class="subject-preview-body">
                                    <div class="subject-preview-empty">
                                        <i class="bi bi-journal-x" style="font-size:28px; color:var(--text-secondary); opacity:0.4;"></i>
                                        <p>Loading subjects…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
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

<style>
.registrar-access-badge {
    background: rgba(79,163,255,0.1); border: 1px solid rgba(79,163,255,0.25);
    color: var(--accent); padding: 7px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 700;
}
/* ── Subject Preview Panel ── */
.subject-preview-panel {
    border: 1px solid var(--color-border);
    border-radius: 10px;
    overflow: hidden;
    height: 100%;
    min-height: 380px;
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
}
.subject-preview-header {
    background: var(--color-surface2);
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 4px;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}
.subject-count-badge {
    margin-left: auto;
    background: var(--accent);
    color: #fff;
    border-radius: 20px;
    padding: 1px 9px;
    font-size: 11px;
    font-weight: 700;
}
.subject-preview-body {
    flex: 1;
    overflow-y: auto;
    max-height: 340px;
    padding: 8px 0;
}
.subject-preview-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 280px;
    color: var(--text-secondary);
    font-size: 12px;
    text-align: center;
    gap: 10px;
    padding: 20px;
}
.subject-preview-empty p { margin: 0; line-height: 1.6; }
.subject-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 7px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
}
.subject-item:last-child { border-bottom: none; }
.subject-item:hover { background: rgba(255,255,255,0.03); }
.subject-code {
    background: rgba(79,163,255,0.12);
    color: var(--accent);
    border-radius: 5px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
    margin-top: 1px;
}
.subject-name {
    color: var(--text-primary);
    font-weight: 500;
    line-height: 1.4;
    flex: 1;
}
.subject-units {
    color: var(--text-secondary);
    font-size: 10px;
    white-space: nowrap;
    flex-shrink: 0;
    margin-top: 2px;
}
.subject-no-results {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    color: var(--text-secondary);
    font-size: 12px;
    text-align: center;
    gap: 8px;
    padding: 20px;
}
</style>

<script>
const ALL_FACULTY = <?= json_encode($all_faculty_js) ?>;
const ALL_SUBJECTS = <?= json_encode($all_subjects_js) ?>;

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
const PROGRAM_TO_SUBJECT_DEPT = {
    'BSIT':   'BS Information Technology',
    'BSTM':   'BS Tourism Management',
    'BSBA':   'BS Business Administration',
    'BSCRIM': 'BS Criminology',
    'BSCE':   'BS Civil Engineering'
};
const PROGRAM_NAMES = <?= json_encode($programNames) ?>;

/**
 * Render subjects into the edit preview panel.
 */
function renderSubjectPreview(program, yearLevel, semLabel) {
    const body  = document.getElementById('edit_subject_preview_body');
    const badge = document.getElementById('edit_subject_count_badge');

    if (!program || !yearLevel || !semLabel) {
        badge.style.display = 'none';
        body.innerHTML = `
            <div class="subject-preview-empty">
                <i class="bi bi-journal-x" style="font-size:28px; color:var(--text-secondary); opacity:0.4;"></i>
                <p>No subject data available.</p>
            </div>`;
        return;
    }

    const yearNum     = yearLevel.replace(' Year', '');
    const semNum      = semLabel.replace(' Sem', '');
    const yearKey     = yearNum + ' Year (' + semNum + ' Sem)';
    const subjectDept = PROGRAM_TO_SUBJECT_DEPT[program] ?? '';

    const filtered = ALL_SUBJECTS.filter(sub =>
        sub.department.trim() === subjectDept.trim() &&
        sub.year_level.trim() === yearKey.trim()
    );

    if (filtered.length === 0) {
        badge.style.display = 'none';
        body.innerHTML = `
            <div class="subject-no-results">
                <i class="bi bi-exclamation-circle" style="font-size:24px; color:#f59e0b;"></i>
                <p>No subjects found for<br><strong>${yearLevel} — ${semLabel}</strong><br>in ${subjectDept}.</p>
            </div>`;
        return;
    }

    badge.textContent   = filtered.length + ' subjects';
    badge.style.display = 'inline-block';

    let html = '';
    filtered.forEach(sub => {
        html += `
            <div class="subject-item">
                <span class="subject-code">${sub.subject_code}</span>
                <span class="subject-name">${sub.subject_name}</span>
                <span class="subject-units">${sub.units} units</span>
            </div>`;
    });
    body.innerHTML = html;
}

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

    // ── Derive semLabel from term_semester for subject preview ──
    let semLabel = '';
    if (s.term_semester) {
        semLabel = s.term_semester.indexOf('1st') !== -1 ? '1st Sem' : '2nd Sem';
    }
    renderSubjectPreview(s.program, s.year_level, semLabel);

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

<?php include '../includes/footer.php'; ?>