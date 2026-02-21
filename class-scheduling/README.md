# Class Scheduling System
Professional Academic Scheduling System with Conflict Detection

## Project Structure
```
/class-scheduling/
├── /config
│   └── db.php                    # Database connection
├── /includes
│   ├── header.php                # HTML header
│   ├── sidebar.php               # Left navigation sidebar
│   └── footer.php                # HTML footer
├── /assets
│   └── custom.css                # Custom styles
├── dashboard.php                 # Main dashboard
├── sections.php                  # Section management
├── schedules.php                 # Schedule management
├── rooms.php                     # Room management
├── faculty_load.php              # Faculty load monitoring
├── conflicts.php                 # Conflict detection
├── database_schema.sql           # Database structure
└── sql_queries.sql               # All SQL queries
```

## Features
- Dashboard with statistics
- Section management
- Schedule management with conflict detection
- Room availability tracking
- Faculty load monitoring
- Automatic conflict detection (faculty & room)
- Responsive design
- XAMPP compatible

## Installation

1. **Copy project to XAMPP htdocs**
   ```
   C:\xampp\htdocs\class-scheduling\
   ```

2. **Import Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create new database: `class_scheduling`
   - Import `database_schema.sql`

3. **Configure Database Connection**
   - Edit `config/db.php` if needed
   - Default: localhost, root, no password

4. **Access System**
   - Open: http://localhost/class-scheduling/dashboard.php

## Database Tables
- **faculty** - Faculty members
- **rooms** - Classroom/lab information
- **subjects** - Course subjects
- **sections** - Student sections
- **schedules** - Class schedules
- **conflicts** - Detected conflicts

## Key Queries
See `sql_queries.sql` for:
- Dashboard statistics
- Faculty load calculations
- Room utilization
- Conflict detection (faculty/room)
- CRUD operations

## Design
- Dark sidebar (#1e293b)
- Light background (#f4f6f9)
- White content cards
- Corporate/professional theme
- Bootstrap 5 + Custom CSS

## Pages
1. **Dashboard** - Overview statistics
2. **Sections** - Manage student sections
3. **Schedules** - Class schedule management
4. **Rooms** - Room tracking & utilization
5. **Faculty Load** - Teaching load monitoring
6. **Conflicts** - Automatic conflict detection

## Status
Frontend complete with backend-ready structure.
PHP placeholders ready for dynamic data.
