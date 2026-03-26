<?php
/**
 * conflict_count.php  (same file goes in BOTH admin/includes/ AND registrar/includes/)
 *
 * KEY FIX: Conflict detection (INSERT/UPDATE) now runs inside the poll endpoint.
 * This means the DB is always up-to-date on every poll — the sidebar badge
 * appears/disappears in real-time WITHOUT needing to visit conflicts.php first.
 */

// ── POLL ENDPOINT ────────────────────────────────────────────────
if (!empty($_GET['rt_poll'])) {
    if (!isset($conn)) require_once __DIR__ . '/../../config/db.php';

    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $tid = 0;
    $r = $conn->query("SELECT term_id FROM academic_terms WHERE is_active=TRUE LIMIT 1")->fetch();
    if ($r) $tid = intval($r['term_id']);

    // ================================================================
    // RE-SCAN: detect all current conflicts and sync DB
    // Runs on EVERY poll so the badge is always accurate everywhere.
    // ================================================================
    $current_conflicts = [];

    // ── FACULTY conflicts ──
    $fq = $conn->query("
        SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
               CONCAT(f.first_name,' ',f.last_name) AS faculty_name,
               s1.day_of_week,
               s1.start_time AS s1_start, s1.end_time AS s1_end,
               s2.start_time AS s2_start, s2.end_time AS s2_end,
               sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name,
               sec1.section_name AS sec1_name, sec2.section_name AS sec2_name
        FROM schedules s1
        JOIN schedules s2
            ON  s1.faculty_id  = s2.faculty_id
            AND s1.day_of_week = s2.day_of_week
            AND s1.schedule_id < s2.schedule_id
            AND s1.start_time  < s2.end_time
            AND s1.end_time    > s2.start_time
        JOIN faculty  f    ON s1.faculty_id = f.faculty_id
        JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
        JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
        JOIN sections sec1 ON s1.section_id = sec1.section_id
        JOIN sections sec2 ON s2.section_id = sec2.section_id
        WHERE s1.term_id=$tid AND s2.term_id=$tid
          AND s1.status='Active' AND s2.status='Active'
    ");
    if ($fq) while ($fc = $fq->fetch()) {
        $lo = min($fc['sid1'],$fc['sid2']); $hi = max($fc['sid1'],$fc['sid2']);
        $key = "{$lo}_{$hi}_Faculty";
        $desc = "Faculty conflict: {$fc['faculty_name']} is double-booked on {$fc['day_of_week']}"
              . " — teaching '{$fc['sub1_name']}' ({$fc['sec1_name']}) "
              . date('h:i A',strtotime($fc['s1_start']))."–".date('h:i A',strtotime($fc['s1_end']))
              . " overlaps with '{$fc['sub2_name']}' ({$fc['sec2_name']}) "
              . date('h:i A',strtotime($fc['s2_start']))."–".date('h:i A',strtotime($fc['s2_end']));
        $current_conflicts[$key] = ['lo'=>$lo,'hi'=>$hi,'type'=>'Faculty','desc'=>$desc];
    }

    // ── ROOM conflicts ──
    $rq = $conn->query("
        SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
               r.room_name, r.room_code, s1.day_of_week,
               s1.start_time AS s1_start, s1.end_time AS s1_end,
               s2.start_time AS s2_start, s2.end_time AS s2_end,
               sec1.section_name AS sec1_name, sec2.section_name AS sec2_name,
               sec1.program AS prog1, sec2.program AS prog2,
               sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name
        FROM schedules s1
        LEFT JOIN room_assignments ra1 ON ra1.section_id = s1.section_id
        JOIN schedules s2
            ON  s1.day_of_week = s2.day_of_week
            AND s1.schedule_id < s2.schedule_id
            AND s1.start_time  < s2.end_time
            AND s1.end_time    > s2.start_time
        LEFT JOIN room_assignments ra2 ON ra2.section_id = s2.section_id
        JOIN rooms    r    ON r.room_id = COALESCE(s1.room_id, ra1.room_id)
        JOIN sections sec1 ON s1.section_id = sec1.section_id
        JOIN sections sec2 ON s2.section_id = sec2.section_id
        JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
        JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
        WHERE s1.term_id=$tid AND s2.term_id=$tid
          AND s1.status='Active' AND s2.status='Active'
          AND COALESCE(s1.room_id,ra1.room_id) IS NOT NULL
          AND COALESCE(s2.room_id,ra2.room_id) IS NOT NULL
          AND COALESCE(s1.room_id,ra1.room_id) = COALESCE(s2.room_id,ra2.room_id)
    ");
    if ($rq) while ($rc = $rq->fetch()) {
        $lo = min($rc['sid1'],$rc['sid2']); $hi = max($rc['sid1'],$rc['sid2']);
        $key = "{$lo}_{$hi}_Room";
        $s1l = $rc['sec1_name'].($rc['prog1']!==$rc['prog2'] ? " ({$rc['prog1']})" : '');
        $s2l = $rc['sec2_name'].($rc['prog1']!==$rc['prog2'] ? " ({$rc['prog2']})" : '');
        $desc = "Room conflict: {$rc['room_name']} ({$rc['room_code']}) is double-booked on {$rc['day_of_week']}"
              . " — '{$rc['sub1_name']}' for $s1l "
              . date('h:i A',strtotime($rc['s1_start']))."–".date('h:i A',strtotime($rc['s1_end']))
              . " overlaps with '{$rc['sub2_name']}' for $s2l "
              . date('h:i A',strtotime($rc['s2_start']))."–".date('h:i A',strtotime($rc['s2_end']));
        $current_conflicts[$key] = ['lo'=>$lo,'hi'=>$hi,'type'=>'Room','desc'=>$desc];
    }

    // ── SECTION conflicts ──
    $sq = $conn->query("
        SELECT s1.schedule_id AS sid1, s2.schedule_id AS sid2,
               sec.section_name, s1.day_of_week,
               s1.start_time AS s1_start, s1.end_time AS s1_end,
               s2.start_time AS s2_start, s2.end_time AS s2_end,
               sub1.subject_name AS sub1_name, sub2.subject_name AS sub2_name
        FROM schedules s1
        JOIN schedules s2
            ON  s1.section_id  = s2.section_id
            AND s1.day_of_week = s2.day_of_week
            AND s1.schedule_id < s2.schedule_id
            AND s1.start_time  < s2.end_time
            AND s1.end_time    > s2.start_time
        JOIN sections sec  ON s1.section_id = sec.section_id
        JOIN subjects sub1 ON s1.subject_id = sub1.subject_id
        JOIN subjects sub2 ON s2.subject_id = sub2.subject_id
        WHERE s1.term_id=$tid AND s2.term_id=$tid
          AND s1.status='Active' AND s2.status='Active'
    ");
    if ($sq) while ($sc = $sq->fetch()) {
        $lo = min($sc['sid1'],$sc['sid2']); $hi = max($sc['sid1'],$sc['sid2']);
        $key = "{$lo}_{$hi}_Section";
        $desc = "Section conflict: {$sc['section_name']} has two overlapping classes on {$sc['day_of_week']}"
              . " — '{$sc['sub1_name']}' "
              . date('h:i A',strtotime($sc['s1_start']))."–".date('h:i A',strtotime($sc['s1_end']))
              . " overlaps with '{$sc['sub2_name']}' "
              . date('h:i A',strtotime($sc['s2_start']))."–".date('h:i A',strtotime($sc['s2_end']));
        $current_conflicts[$key] = ['lo'=>$lo,'hi'=>$hi,'type'=>'Section','desc'=>$desc];
    }

    // ── Load existing Unresolved from DB ──
    $ex = $conn->query("
        SELECT conflict_id,
               LEAST(schedule_id_1,schedule_id_2)    AS sid_lo,
               GREATEST(schedule_id_1,schedule_id_2) AS sid_hi,
               conflict_type
        FROM conflicts WHERE status='Unresolved'
    ");
    if ($ex) while ($e = $ex->fetch()) {
        $existing["{$e['sid_lo']}_{$e['sid_hi']}_{$e['conflict_type']}"] = (int)$e['conflict_id'];
    }

    foreach ($existing as $key => $cid) {
        if (!isset($current_conflicts[$key])) {
            $u = $conn->prepare("UPDATE conflicts SET status='Resolved', resolved_at=NOW(), resolved_note='Auto-resolved: schedule was fixed in the timetable' WHERE conflict_id=?");
            $u->execute([$cid]);
        }
    }

    // ── Insert newly detected conflicts ──
    foreach ($current_conflicts as $key => $cf) {
        if (!isset($existing[$key])) {
            $i = $conn->prepare("INSERT INTO conflicts (conflict_type,schedule_id_1,schedule_id_2,description,status) VALUES (?,?,?,?,'Unresolved')");
            $i->execute([$cf['type'],$cf['lo'],$cf['hi'],$cf['desc']]);
        }
    }

    // ── Tally fresh counts from DB ──
    $total = $faculty = $room = $section = $resolved = 0;
    $cr = $conn->query("
        SELECT c.conflict_type, c.status, COUNT(*) AS cnt
        FROM conflicts c
        JOIN schedules s1 ON c.schedule_id_1 = s1.schedule_id
        JOIN schedules s2 ON c.schedule_id_2 = s2.schedule_id
        WHERE s1.term_id=$tid OR s2.term_id=$tid
        GROUP BY c.conflict_type, c.status
    ");
    if ($cr) while ($r = $cr->fetch()) {
        if ($r['status'] === 'Unresolved') {
            $total += $r['cnt'];
            if ($r['conflict_type']==='Faculty') $faculty += $r['cnt'];
            if ($r['conflict_type']==='Room')    $room    += $r['cnt'];
            if ($r['conflict_type']==='Section') $section += $r['cnt'];
        } else { $resolved += $r['cnt']; }
    }

    // ── Full rows (only on conflict page) ──
    $rows = [];
    if (!empty($_GET['full'])) {
        $rr = $conn->query("
            SELECT c.*
            FROM conflicts c
            JOIN schedules s1 ON c.schedule_id_1 = s1.schedule_id
            JOIN schedules s2 ON c.schedule_id_2 = s2.schedule_id
            WHERE s1.term_id=$tid OR s2.term_id=$tid
            ORDER BY CASE c.status WHEN 'Unresolved' THEN 0 ELSE 1 END, c.detected_at DESC
            LIMIT 200
        ");
        if ($rr) while ($r = $rr->fetch()) {
            $rows[] = [
                'conflict_id'   => (int)$r['conflict_id'],
                'conflict_type' => $r['conflict_type'],
                'description'   => $r['description'],
                'status'        => $r['status'],
                'detected_at'   => $r['detected_at'],
                'resolved_at'   => $r['resolved_at'],
                'resolved_note' => $r['resolved_note'],
                'schedule_id_1' => (int)$r['schedule_id_1'],
                'schedule_id_2' => (int)$r['schedule_id_2'],
            ];
        }
    }

    echo json_encode([
        'total'        => $total,
        'faculty'      => $faculty,
        'room'         => $room,
        'section'      => $section,
        'resolved'     => $resolved,
        'rows'         => $rows,
        'last_checked' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// ── PHP INITIAL COUNT (sidebar badge on first page render) ───────
if (!isset($unresolved_conflict_count)) {
    $unresolved_conflict_count = 0;
    if (isset($conn)) {
        $atRow = $conn->query("SELECT term_id FROM academic_terms WHERE is_active=TRUE LIMIT 1")->fetch();
        if ($atRow) {
            $tid = intval($atRow['term_id']);
            $cr  = $conn->query("
                SELECT COUNT(*) AS cnt
                FROM conflicts c
                JOIN schedules s1 ON c.schedule_id_1 = s1.schedule_id
                JOIN schedules s2 ON c.schedule_id_2 = s2.schedule_id
                WHERE c.status = 'Unresolved'
                  AND (s1.term_id = $tid OR s2.term_id = $tid)
            ");
            if ($cr) $unresolved_conflict_count = (int)($cr->fetchColumn() ?? 0);
        }
    }
}

// ── INJECT JS ONCE PER PAGE ──────────────────────────────────────
static $rt_injected = false;
if ($rt_injected) return;
$rt_injected = true;

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$web_path = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', __FILE__));
$poll_url = $protocol . '://' . $host . $web_path . '?rt_poll=1';

$is_admin    = (strpos($_SERVER['PHP_SELF'], '/admin/')      !== false) ? 'true' : 'false';
$is_conflict = (isset($page_title) && stripos($page_title, 'Conflict') !== false) ? 'true' : 'false';
?>
<script>
(function () {
  'use strict';

  const POLL_URL       = '<?= $poll_url ?>';
  const IS_ADMIN       = <?= $is_admin ?>;
  const IS_CONFLICT_PG = <?= $is_conflict ?>;
  // Background refresh — the real "instant" update comes from form-submit hooks below
  const POLL_MS        = IS_CONFLICT_PG ? 5000 : 8000;

  let prevTotal    = null;
  let prevResolved = null;
  let activeTab    = new URLSearchParams(location.search).get('tab') || 'unresolved';
  let paused       = false;

  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* ══════════════════════════════════════════════════════════════
     SIDEBAR BADGE
     Appears/disappears in real-time on EVERY page without needing
     to visit conflicts.php — because detection runs in the poll.
  ══════════════════════════════════════════════════════════════ */
  function updateSidebarBadge(total) {
    const link = $$('a.nav-item').find(a => (a.getAttribute('href') || '').includes('conflicts.php'));
    if (!link) return;

    let badge = link.querySelector('.conflict-nav-badge');

    if (total > 0) {
      const label = total <= 99 ? String(total) : '99+';

      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'conflict-nav-badge';
        link.style.position = 'relative';
        link.appendChild(badge);
        badge.style.opacity    = '0';
        badge.style.transform  = 'translateY(-50%) scale(0.3)';
        badge.style.transition = 'opacity .3s ease, transform .3s ease';
        requestAnimationFrame(() => requestAnimationFrame(() => {
          badge.style.opacity   = '1';
          badge.style.transform = 'translateY(-50%) scale(1)';
        }));
      }

      if (badge.textContent !== label) {
        badge.textContent = label;
        badge.title = `${total} unresolved conflict${total !== 1 ? 's' : ''}`;
        badge.style.transition = 'transform .12s ease';
        badge.style.transform  = 'translateY(-50%) scale(1.5)';
        setTimeout(() => {
          badge.style.transition = 'transform .2s ease';
          badge.style.transform  = 'translateY(-50%) scale(1)';
        }, 140);
      }

    } else {
      if (badge) {
        badge.style.transition = 'opacity .35s ease, transform .35s ease';
        badge.style.opacity    = '0';
        badge.style.transform  = 'translateY(-50%) scale(0.3)';
        setTimeout(() => { if (badge.parentNode) badge.remove(); }, 380);
      }
    }
  }

  /* ══════════════════════════════════════════════════════════════
     CONFLICT PAGE — stat cards, table, toasts (no live bar)
  ══════════════════════════════════════════════════════════════ */
  function injectConflictChrome() {
    if ($('#rt-chrome-ready')) return;
    const marker = document.createElement('meta');
    marker.id = 'rt-chrome-ready';
    document.head.appendChild(marker);

    if (!$('#rt-toast-wrap')) {
      const w = document.createElement('div');
      w.id = 'rt-toast-wrap';
      w.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
      document.body.appendChild(w);
    }

    if (!$('#rt-styles')) {
      const st = document.createElement('style');
      st.id = 'rt-styles';
      st.textContent = `
        @keyframes rt-flash{0%,100%{filter:brightness(1)}50%{filter:brightness(1.45)}}
        @keyframes rt-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes rt-out{to{opacity:0;transform:translateY(10px)}}
        .rt-toast{pointer-events:auto;padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;
          display:flex;align-items:center;gap:10px;min-width:240px;max-width:340px;
          box-shadow:0 8px 32px rgba(0,0,0,.4);animation:rt-in .25s ease;}
        .rt-toast.conflict{background:#2d1515;border:1px solid #ef4444;color:#fca5a5;}
        .rt-toast.resolved{background:#132d1f;border:1px solid #22c55e;color:#86efac;}
        .rt-toast.info{background:#1e1e2e;border:1px solid var(--color-border,#333);color:var(--text-secondary,#aaa);}`;
      document.head.appendChild(st);
    }
  }

  function toast(msg, type, ms = 4000) {
    const w = $('#rt-toast-wrap'); if (!w) return;
    const icons = { conflict:'⚠️', resolved:'✅', info:'ℹ️' };
    const el = document.createElement('div');
    el.className = `rt-toast ${type}`;
    el.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
    w.appendChild(el);
    setTimeout(() => { el.style.animation = 'rt-out .3s ease forwards'; setTimeout(() => el.remove(), 300); }, ms);
  }

  function flash(card) {
    if (!card) return;
    card.style.animation = 'none'; void card.offsetWidth;
    card.style.animation = 'rt-flash .5s ease';
  }

  function tagStatCards() {
    [['red','rt-stat-total'],['orange','rt-stat-faculty'],['purple','rt-stat-room'],['green','rt-stat-resolved']]
      .forEach(([cls, id]) => {
        const c = $$('.stats-card').find(x => x.classList.contains(cls));
        if (c) { const n = $('.number', c); if (n && !n.id) n.id = id; }
      });
  }

  function updateStats(data) {
    [['total','rt-stat-total'],['faculty','rt-stat-faculty'],['room','rt-stat-room'],['resolved','rt-stat-resolved']]
      .forEach(([k, id]) => {
        const el = $(`#${id}`);
        if (el && el.textContent !== String(data[k])) {
          el.textContent = data[k];
          flash(el.closest('.stats-card'));
        }
      });
    $$('.conflict-tab').forEach(tab => {
      const b = tab.querySelector('.tab-badge'); if (!b) return;
      const v = (tab.getAttribute('href') || '').includes('resolved') ? data.resolved : data.total;
      b.textContent = v; b.style.display = v > 0 ? '' : 'none';
    });
  }

  const esc = s => (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const fmt = iso => {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ','T'));
    return d.toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'})
         + ' ' + d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
  };

  function buildRow(row, tab) {
    const badge = row.conflict_type === 'Faculty'
      ? `<span class="badge-danger"><i class="bi bi-person-fill me-1"></i>Faculty</span>`
      : row.conflict_type === 'Room'
        ? `<span class="badge-warning"><i class="bi bi-door-closed-fill me-1"></i>Room</span>`
        : `<span class="badge-info"><i class="bi bi-people-fill me-1"></i>Section</span>`;

    let extra = '';
    if (tab === 'resolved') {
      const note = row.resolved_note || '';
      const how = note.includes('Auto-resolved')
        ? `<span style="color:#22c55e;"><i class="bi bi-magic me-1"></i>Auto — schedule was fixed</span>`
        : note.includes('Manually')
          ? `<span style="color:#94a3b8;"><i class="bi bi-hand-index-thumb me-1"></i>Manually dismissed</span>`
          : `<span style="color:#94a3b8;">—</span>`;
      extra = `<td style="font-size:12px;white-space:nowrap;color:#22c55e;">${fmt(row.resolved_at)}</td>
               <td style="font-size:11px;">${how}</td>`;
    } else {
      const action = IS_ADMIN
        ? `<td>
            <a href="schedules.php?tab=list" class="btn-icon" title="Fix in timetable"><i class="bi bi-pencil-square"></i></a>
            <button class="btn-icon text-warning rt-dismiss-btn" data-id="${row.conflict_id}" title="Dismiss"><i class="bi bi-x-circle-fill"></i></button>
           </td>`
        : `<td><span class="badge-danger">Unresolved</span>
            <div style="font-size:10px;color:var(--text-secondary);margin-top:3px;">Contact Admin to resolve</div></td>`;
      extra = `<td><span class="badge-danger">Unresolved</span></td>${action}`;
    }

    return `<tr data-conflict-id="${row.conflict_id}">
      <td>${badge}</td>
      <td style="max-width:440px;font-size:13px;">${esc(row.description)}
        <div class="mt-1" style="font-size:11px;color:var(--text-secondary);">
          Schedule #${row.schedule_id_1} ↔ Schedule #${row.schedule_id_2}
          ${tab !== 'resolved' ? '&nbsp;|&nbsp;<a href="schedules.php?tab=list" style="color:var(--accent);">Fix in Timetable →</a>' : ''}
        </div></td>
      <td style="font-size:12px;white-space:nowrap;">${fmt(row.detected_at)}</td>
      ${extra}</tr>`;
  }

  function updateTable(data) {
    const tbody = $('table.custom-table tbody');
    if (!tbody || paused) return;
    const filtered = data.rows.filter(r => activeTab === 'resolved' ? r.status === 'Resolved' : r.status === 'Unresolved');
    const empty = activeTab === 'resolved'
      ? `<tr><td colspan="5" class="text-center py-4"><i class="bi bi-archive me-2 text-muted"></i>No resolved conflicts yet.</td></tr>`
      : `<tr><td colspan="5" class="text-center py-4"><i class="bi bi-check-circle me-2 text-success"></i>No conflicts found. Schedule is clean!</td></tr>`;
    const html = filtered.length ? filtered.map(r => buildRow(r, activeTab)).join('') : empty;
    if (tbody.dataset.rtHash !== html) {
      tbody.innerHTML = html;
      tbody.dataset.rtHash = html;
      if (IS_ADMIN) attachDismiss();
    }
    if (activeTab === 'unresolved') {
      if (data.total === 0 && !$('.rt-clean-alert')) {
        const a = document.createElement('div');
        a.className = 'alert alert-success d-flex align-items-center gap-2 mb-4 rt-clean-alert';
        a.innerHTML = `<i class="bi bi-check-circle-fill" style="font-size:20px;"></i><div><strong>No conflicts detected!</strong> All schedules are clean.</div>`;
        $('.content-card')?.insertAdjacentElement('beforebegin', a);
        const ex = $('.alert-success:not(.rt-clean-alert)'); if (ex) ex.remove();
      } else if (data.total > 0) {
        $$('.rt-clean-alert').forEach(el => el.remove());
        const ex = $('.alert-success'); if (ex && !ex.classList.contains('rt-clean-alert')) ex.remove();
      }
    }
  }

  function attachDismiss() {
    $$('.rt-dismiss-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Dismiss this conflict without fixing the schedule?\n\nIt will be logged as manually dismissed.')) return;
        const fd = new FormData();
        fd.append('resolve_conflict', '1');
        fd.append('conflict_id', btn.dataset.id);
        await fetch(location.pathname, { method: 'POST', body: fd });
        poll(true);
      });
    });
  }

  /* ══════════════════════════════════════════════════════════════
     MAIN POLL — runs detection + syncs DB on every call
  ══════════════════════════════════════════════════════════════ */
  async function poll(force = false) {
    const url = POLL_URL + (IS_CONFLICT_PG ? '&full=1' : '') + '&_=' + Date.now();
    try {
      const res = await fetch(url);
      if (!res.ok) return;
      const data = await res.json();

      // Sidebar badge — EVERY page, EVERY poll
      updateSidebarBadge(data.total);

      // Conflict page extras
      if (IS_CONFLICT_PG) {
        updateStats(data);
        updateTable(data);

        if (prevTotal !== null) {
          if (data.total > prevTotal) {
            toast(`${data.total - prevTotal} new conflict${data.total - prevTotal > 1 ? 's' : ''} detected!`, 'conflict', 5000);
          } else if (data.total < prevTotal) {
            toast(`${prevTotal - data.total} conflict${prevTotal - data.total > 1 ? 's' : ''} resolved ✓`, 'resolved', 4000);
          } else if (force && data.resolved > prevResolved) {
            toast('Conflict dismissed successfully.', 'resolved', 3000);
          }
        }
      }

      prevTotal    = data.total;
      prevResolved = data.resolved;

    } catch (e) {
      if (IS_CONFLICT_PG) { const d = $('#rt-dot'); if (d) d.style.background = '#6b7280'; }
    }
  }

  /* ── Boot ─────────────────────────────────────────────────── */
  function boot() {
    if (IS_CONFLICT_PG) {
      injectConflictChrome();
      tagStatCards();
      $$('.conflict-tab').forEach(a => {
        a.addEventListener('click', () => {
          activeTab = new URL(a.href, location.href).searchParams.get('tab') || 'unresolved';
        });
      });
      const tbl = $('table.custom-table');
      if (tbl) {
        tbl.addEventListener('mouseenter', () => { paused = true;  });
        tbl.addEventListener('mouseleave', () => { paused = false; });
      }
      if (IS_ADMIN) attachDismiss();
    }

    // ── Instant poll: fire immediately when ANY form is submitted on this page ──
    // This is what makes the badge update in ~0.1s after saving/editing a schedule.
    document.addEventListener('submit', () => {
      // Poll immediately after the form POST completes (give server ~150ms to process)
      setTimeout(() => poll(), 150);
    });

    // ── Instant poll: fire when page becomes visible again (user switches tabs/windows) ──
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) poll();
    });

    // ── Also hook into any fetch/XHR that other pages might use for AJAX saves ──
    // Wraps window.fetch so any successful POST triggers a re-scan within 150ms
    const _origFetch = window.fetch;
    window.fetch = async function(...args) {
      const res = await _origFetch.apply(this, args);
      const method = (typeof args[1] === 'object' ? args[1].method : 'GET') || 'GET';
      // Only re-poll on POST/PUT/DELETE, and not on the poll itself
      const url = String(args[0] || '');
      if (/^(POST|PUT|DELETE)$/i.test(method) && !url.includes('rt_poll')) {
        setTimeout(() => poll(), 150);
      }
      return res;
    };

    poll();                          // instant on page load
    setInterval(poll, POLL_MS);      // background refresh
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot)
    : boot();
})();
</script>