<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);

// Get unresolved conflict count for the nav badge
require_once __DIR__ . '/conflict_count.php';
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-calendar2-week-fill"></i></div>
        <div style="flex:1;">
            <div class="brand-text">ClassSync</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
        <button id="themeBtn" onclick="toggleTheme()" title="Toggle Light/Dark Mode">
            <i class="theme-icon bi bi-sun-fill"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
        <div>
            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
            <div class="user-role">Administrator</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php" class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="sections.php" class="nav-item <?= $current === 'sections.php' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Sections
        </a>
        <a href="schedules.php" class="nav-item <?= $current === 'schedules.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check-fill"></i> Schedules
        </a>
        <a href="rooms.php" class="nav-item <?= $current === 'rooms.php' ? 'active' : '' ?>">
            <i class="bi bi-door-open-fill"></i> Rooms
        </a>
        <a href="faculty_load.php" class="nav-item <?= $current === 'faculty_load.php' ? 'active' : '' ?>">
            <i class="bi bi-person-workspace"></i> Faculty Load
        </a>

        <!-- CONFLICTS — red badge when unresolved conflicts exist -->
        <a href="conflicts.php" class="nav-item <?= $current === 'conflicts.php' ? 'active' : '' ?>"
           style="position:relative;">
            <i class="bi bi-exclamation-triangle-fill"></i> Conflicts
            <?php if ($unresolved_conflict_count > 0): ?>
                <span class="conflict-nav-badge" title="<?= $unresolved_conflict_count ?> unresolved conflict<?= $unresolved_conflict_count > 1 ? 's' : '' ?>">
                    <?= $unresolved_conflict_count <= 99 ? $unresolved_conflict_count : '99+' ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="staff_management.php" class="nav-item <?= $current === 'staff_management.php' ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill"></i> Staff Management
        </a>
        <div class="nav-label">Account</div>
        <a href="logout.php" class="nav-item" onclick="return confirmLogout();">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</div>

<style>
/* ── Conflict nav badge ── */
.conflict-nav-badge {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    line-height: 1;
    /* Subtle pulse so it catches the eye */
    animation: conflict-pulse 2.4s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(239,68,68,0.5);
}
@keyframes conflict-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(239,68,68,0.55); }
    60%  { box-shadow: 0 0 0 5px rgba(239,68,68,0);    }
    100% { box-shadow: 0 0 0 0   rgba(239,68,68,0);    }
}
/* When the nav item is active (currently on conflicts page),
   keep the badge but adjust colors so it stays visible */
.nav-item.active .conflict-nav-badge {
    background: rgba(255,255,255,0.25);
    box-shadow: none;
    animation: none;
}
</style>

<script>
(function() {
    var icon = document.querySelector('.theme-icon');
    if (icon && localStorage.getItem('classsync_theme') === 'light') {
        icon.className = 'theme-icon bi bi-moon-fill';
    }
})();

function confirmLogout() {
    return confirm("Are you sure you want to logout?");
}
</script>