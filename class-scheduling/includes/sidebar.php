<div class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-calendar3"></i>
        <span>Class Scheduling</span>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="sections.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sections.php' ? 'active' : ''; ?>">
            <i class="bi bi-people-fill"></i>
            <span>Sections</span>
        </a>
        <a href="schedules.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedules.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-check-fill"></i>
            <span>Schedules</span>
        </a>
        <a href="rooms.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>">
            <i class="bi bi-door-open-fill"></i>
            <span>Rooms</span>
        </a>
        <a href="faculty_load.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'faculty_load.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-workspace"></i>
            <span>Faculty Load</span>
        </a>
        <a href="conflicts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'conflicts.php' ? 'active' : ''; ?>">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Conflicts</span>
        </a>
    </nav>
</div>
