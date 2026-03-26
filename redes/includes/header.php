<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Class Scheduling System' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/custom.css" rel="stylesheet">
    <!-- Supabase SDK & Client -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="../assets/js/supabase-client.js"></script>
    <style>
    body.light-mode {
        --color-bg: #f0f4f8;
        --color-surface: #ffffff;
        --color-surface2: #e8edf3;
        --color-border: rgba(0,0,0,0.08);
        --text-primary: #1a202c;
        --text-secondary: #718096;
        --text-muted: rgba(0,0,0,0.35);
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 10px 24px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
    }
    body.light-mode .nav-item { color: rgba(0,0,0,0.45); }
    body.light-mode .nav-item:hover { background: rgba(0,0,0,0.05); color: rgba(0,0,0,0.75); }
    body.light-mode .nav-item.active { background: var(--accent); color: #fff; }
    body.light-mode .custom-table tbody tr:hover td { background: rgba(0,0,0,0.02); }
    body.light-mode .modal-header .btn-close { filter: none; opacity: 0.5; }
    body.light-mode .form-control::placeholder { color: rgba(0,0,0,0.25); }
    body.light-mode .btn-secondary-custom:hover { background: rgba(0,0,0,0.05); }
    body.light-mode .btn-icon:hover { background: rgba(0,0,0,0.05); }
    body.light-mode .staff-tab:hover { background: rgba(0,0,0,0.03); }
    body.light-mode .tab-count { background: rgba(0,0,0,0.07); }

    #themeBtn {
        width: 36px; height: 36px;
        border-radius: 50%;
        border: 1px solid var(--color-border);
        background: var(--color-bg);
        color: var(--accent);
        font-size: 16px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    #themeBtn:hover { transform: scale(1.1); }
    </style>
</head>
<body>
<script>
if (localStorage.getItem('classsync_theme') === 'light') {
    document.body.classList.add('light-mode');
}
function toggleTheme() {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('classsync_theme', isLight ? 'light' : 'dark');
    document.querySelectorAll('.theme-icon').forEach(function(el) {
        el.className = 'theme-icon bi ' + (isLight ? 'bi-moon-fill' : 'bi-sun-fill');
    });
}
</script>