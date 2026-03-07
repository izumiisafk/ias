<?php if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-calendar2-week-fill"></i></div>
        <div style="flex:1;">
            <div class="brand-text">ClassSync</div>
            <div class="brand-sub">Registrar</div>
        </div>
        <button id="themeBtn" onclick="toggleTheme()" title="Toggle Light/Dark Mode">
            <i class="theme-icon bi bi-sun-fill"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
        <div>
            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Registrar') ?></div>
            <div class="user-role">Registrar Officer</div>
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
        <a href="conflicts.php" class="nav-item <?= $current === 'conflicts.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle-fill"></i> Conflicts
        </a>
        <div class="nav-label">Account</div>
        <a href="logout.php" class="nav-item">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</div>
<script>
(function() {
    var icon = document.querySelector('.theme-icon');
    if (icon && localStorage.getItem('classsync_theme') === 'light') {
        icon.className = 'theme-icon bi bi-moon-fill';
    }
})();
</script>