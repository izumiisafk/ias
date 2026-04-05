<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
    exit();
}

$error = '';
$db_error = ''; 

$hardcoded = [
    'admin' => ['password' => 'admin123', 'role' => 'admin', 'full_name' => 'System Administrator'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/db.php';
    require_once 'includes/activity_helper.php';
    require_once 'includes/settings_helper.php';
    require_once 'includes/permissions_helper.php';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $logged   = false;

    if (isset($hardcoded[$username]) && $hardcoded[$username]['password'] === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['role']      = $hardcoded[$username]['role'];
        $_SESSION['username']  = $username;
        $_SESSION['full_name'] = $hardcoded[$username]['full_name'];
        $logged = true;
        
        // Log hardcoded admin login
        logActivity(null, 'login', 'Successful login (Hardcoded Admin)|' . $username);

        header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
        exit();
    }

    if (!$logged && isset($conn)) {
        try {
            // Search public.users_ums for email or student_employee_id
            $stmt = $conn->prepare("
                SELECT u.*, r.name as role_name 
                FROM public.users_ums u 
                JOIN public.roles_ums r ON u.role_id = r.id 
                WHERE (u.email = :u OR u.student_employee_id = :u) 
                AND u.status = 'active' 
                LIMIT 1
            ");
            $stmt->execute(['u' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // ── CHECK ACCOUNT LOCKOUT ──
                if (getAuthSetting('account_lockout', '1') === '1') {
                    if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                        $error = "Account is temporarily locked. Please try again later.";
                        $logged = true; // Prevents further processing
                    }
                }

                if (!$error && password_verify($password, $row['password_hash'])) {
                    // Reset failed attempts if we had a counter, but here we just proceed
                    
                    // ── CHECK 2FA SETTING ──
                    if (getAuthSetting('two_factor_auth', '1') === '1') {
                        // Log successful login (pre-OTP)
                        logActivity($row['id'], 'login', 'Successful login|' . $row['email']);

                        // Generate OTP
                        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        
                        try {
                            // Store OTP
                            $stmt_otp = $conn->prepare("INSERT INTO public.user_otps_ums (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
                            $stmt_otp->execute([$row['id'], $otp_code, $expires_at]);
                            
                            // Send OTP
                            require_once 'includes/resend_helper.php';
                            $resend_result = sendOTP($row['email'], $otp_code);
                            
                            // Session setup
                            $_SESSION['otp_user_id'] = $row['id'];
                            $_SESSION['otp_email']   = $row['email'];
                            $_SESSION['otp_full_name'] = $row['full_name'];
                            $_SESSION['otp_role']      = strtolower($row['role_name']);
                            $_SESSION['otp_role_id']   = $row['role_id'];
                            if ($_SESSION['otp_role'] === 'administrator') $_SESSION['otp_role'] = 'admin';
                            
                            header('Location: otp_verify.php');
                            exit();
                        } catch (Exception $e) {
                            $error = "OTP Error: " . $e->getMessage();
                        }
                    } else {
                        // 2FA is DISABLED -> Log in immediately
                        $_SESSION['logged_in'] = true;
                        $_SESSION['role']      = strtolower($row['role_name']);
                        if ($_SESSION['role'] === 'administrator') $_SESSION['role'] = 'admin';
                        $_SESSION['username']  = $row['email'];
                        $_SESSION['full_name'] = $row['full_name'];
                        $_SESSION['account_id'] = $row['id'];
                        $_SESSION['role_id']    = $row['role_id'];

                        // Fetch and store permissions
                        $_SESSION['permissions'] = fetchRolePermissions($conn, $row['role_id']);

                        logActivity($row['id'], 'login', 'Successful login (2FA Disabled)|' . $row['email']);
                        
                        header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
                        exit();
                    }
                } elseif (!$error) {
                    // ── FAILED PASSWORD ──
                    logActivity($row['id'], 'login', 'Failed login attempt (bad password)|' . $row['email']);
                    
                    // Check if we should lock the account
                    if (getAuthSetting('account_lockout', '1') === '1') {
                        $max_attempts = (int)getAuthSetting('max_attempts', '5');
                        
                        // Count recent failures from logs
                        $count_stmt = $conn->prepare("
                            SELECT COUNT(*) FROM public.activity_logs_ums 
                            WHERE user_id = ? AND event_type = 'login' 
                            AND action LIKE 'Failed login attempt (bad password)%'
                            AND created_at > NOW() - INTERVAL '30 minutes'
                        ");
                        $count_stmt->execute([$row['id']]);
                        $fail_count = $count_stmt->fetchColumn();

                        if ($fail_count >= $max_attempts) {
                            $duration = (int)getAuthSetting('lockout_duration', '30'); // minutes
                            $locked_until = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
                            
                            $lock_stmt = $conn->prepare("UPDATE public.users_ums SET locked_until = ? WHERE id = ?");
                            $lock_stmt->execute([$locked_until, $row['id']]);
                            
                            logActivity($row['id'], 'security', "Account locked until $locked_until due to $fail_count failed attempts");
                            $error = "Too many failed attempts. Your account has been locked for $duration minutes.";
                        }
                    }
                }
            } else {
                logActivity(null, 'login', 'Failed login attempt (user not found)|' . $username);
            }
        } catch (PDOException $e) {
            $error = "Authentication Error: " . $e->getMessage();
        }
    }

    if (empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ClassSync</title>
    
    <!-- Resource Hints & Preloads -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@700;800&display=swap">
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- Optimized Fonts & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@700;800&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@700;800&display=swap">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    </noscript>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --bg:       #0d1220;
            --surface:  #101422;
            --surface2: #161a2e;
            --border:   rgba(255,255,255,0.06);
            --accent:   #4fa3ff;
            --green:    #00e5a0;
            --danger:   #ff5f5f;
            --text:     #e8eaf2;
            --muted:    #4e5369;
            --font-d:   'Syne', sans-serif;
            --font-b:   'DM Sans', sans-serif;
        }

        /* ── LIGHT MODE ── */
        body.light-mode {
            --bg:       #f0f4f8;
            --surface:  #ffffff;
            --surface2: #e8edf3;
            --border:   rgba(0,0,0,0.08);
            --text:     #1a202c;
            --muted:    #718096;
        }
        body.light-mode .left-panel {
            background: #e2e8f0;
            border-right-color: rgba(0,0,0,0.08);
        }
        body.light-mode .lp-card {
            background: rgba(0,0,0,0.03);
            border-color: rgba(0,0,0,0.08);
        }
        body.light-mode .lp-footer { color: rgba(0,0,0,0.25); }
        body.light-mode .field-wrap input {
            background: #ffffff;
            border-color: rgba(0,0,0,0.12);
            color: #1a202c;
        }
        body.light-mode .field-wrap input::placeholder { color: rgba(0,0,0,0.25); }
        body.light-mode .field-wrap input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,163,255,0.1);
        }
        body.light-mode #themeLoginBtn {
            background: #e2e8f0;
            border-color: rgba(0,0,0,0.1);
        }

        body {
            font-family: var(--font-b);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            transition: background 0.2s, color 0.2s;
        }

        /* ── THEME TOGGLE TOP RIGHT ── */
        #themeLoginBtn {
            position: fixed;
            top: 16px;
            right: 20px;
            z-index: 9999;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--accent);
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        #themeLoginBtn:hover { transform: scale(1.1); }

        /* ── LEFT PANEL ── */
        .left-panel {
            width: 400px; min-height: 100vh;
            background: var(--surface2);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 44px 40px;
            position: relative; overflow: hidden; flex-shrink: 0;
            transition: background 0.2s, border-color 0.2s;
        }

        .left-panel::before {
            content: '';
            position: absolute; top: -100px; right: -100px;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(79,163,255,0.08) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .left-panel::after {
            content: '';
            position: absolute; bottom: -80px; left: -80px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(0,229,160,0.06) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }

        .lp-brand { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
        .lp-brand-icon {
            width: 38px; height: 38px; background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; color: #fff;
        }
        .lp-brand-name {
            font-family: var(--font-d); font-size: 18px;
            font-weight: 800; color: var(--text); letter-spacing: -0.02em;
        }

        .lp-body { position: relative; z-index: 1; }
        .lp-body h2 {
            font-family: var(--font-d); font-size: 30px;
            font-weight: 800; color: var(--text);
            letter-spacing: -0.03em; margin-bottom: 12px; line-height: 1.15;
        }
        .lp-body h2 span { color: var(--accent); }
        .lp-body p { font-size: 13px; color: var(--muted); line-height: 1.65; }

        .lp-cards { display: flex; flex-direction: column; gap: 10px; margin-top: 28px; position: relative; z-index: 1; }
        .lp-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 14px 16px;
            display: flex; align-items: center; gap: 12px;
            transition: background 0.2s, border-color 0.2s;
        }
        .lp-card-icon {
            width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }
        .lp-card-icon.admin    { background: rgba(79,163,255,0.12); color: var(--accent); }
        .lp-card-icon.registrar { background: rgba(0,229,160,0.1); color: var(--green); }
        .lp-card-label { font-size: 10px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px; }
        .lp-card-creds { font-size: 13px; font-weight: 500; color: var(--muted); font-family: monospace; }

        .lp-footer { font-size: 12px; color: rgba(255,255,255,0.15); position: relative; z-index: 1; transition: color 0.2s; }

        /* ── RIGHT PANEL ── */
        .right-panel {
            flex: 1; display: flex;
            align-items: center; justify-content: center;
            padding: 48px 40px;
        }

        .login-box { width: 100%; max-width: 380px; }

        .login-box h1 {
            font-family: var(--font-d); font-size: 24px;
            font-weight: 800; color: var(--text);
            letter-spacing: -0.02em; margin-bottom: 4px;
        }
        .login-box .subtitle { font-size: 13px; color: var(--muted); margin-bottom: 32px; }

        .field-group { margin-bottom: 16px; }
        .field-group label {
            display: block; font-size: 11px; font-weight: 600;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: 0.07em; margin-bottom: 6px;
        }
        .field-wrap { position: relative; }
        .field-icon {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted); font-size: 15px; pointer-events: none;
        }
        .field-wrap input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 11px 40px 11px 38px;
            font-size: 14px; font-family: var(--font-b);
            color: var(--text); background: var(--surface2);
            transition: border-color 0.15s, box-shadow 0.15s, background 0.2s;
        }
        .field-wrap input::placeholder { color: rgba(255,255,255,0.18); }
        .field-wrap input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,163,255,0.1);
        }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none;
            color: var(--muted); cursor: pointer; font-size: 15px;
            padding: 2px 4px; transition: color 0.15s;
        }
        .toggle-pw:hover { color: var(--text); }

        .error-box {
            background: rgba(255,95,95,0.08);
            border: 1px solid rgba(255,95,95,0.18);
            border-radius: 9px; padding: 11px 14px;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: var(--danger); margin-bottom: 16px;
        }

        .btn-sign-in {
            width: 100%; background: var(--accent); color: #fff;
            border: none; border-radius: 9px;
            padding: 12px; font-size: 14px; font-weight: 700;
            font-family: var(--font-b); cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.15s; margin-top: 8px;
            box-shadow: 0 4px 16px rgba(79,163,255,0.3);
        }
        .btn-sign-in:hover {
            background: #6db5ff;
            box-shadow: 0 6px 20px rgba(79,163,255,0.45);
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .left-panel { display: none; }
            .right-panel { padding: 32px 24px; }
        }
    </style>
    <script>
    // Apply theme immediately to avoid flash
    if (localStorage.getItem('classsync_theme') === 'light') {
        document.documentElement.style.setProperty('background','#f0f4f8');
    }
    </script>
</head>
<body>

<!-- THEME TOGGLE TOP RIGHT -->
<button id="themeLoginBtn" onclick="toggleLoginTheme()" title="Toggle Light/Dark Mode">
    <i class="bi bi-sun-fill" id="loginThemeIcon"></i>
</button>

<div class="left-panel">
    <div class="lp-brand">
        <div class="lp-brand-icon"><i class="bi bi-calendar2-week-fill"></i></div>
        <div class="lp-brand-name">ClassSync</div>
    </div>

    <div class="lp-body">
        <h2>Your Schedule,<br><span>Our Priority.</span></h2>
        <p>Manage users, roles and scheduling with a modern admin panel.</p>
    </div>

    <div class="lp-footer">Class Scheduling System &copy; 2025–2026</div>
</div>

<div class="right-panel">
    <div class="login-box">
        <h1>Welcome</h1>
        <p class="subtitle">Sign in to your account to continue.</p>

        <form method="POST">
            <?php if ($error): ?>
                <div class="error-box"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="field-group">
                <label>Email</label>
                <div class="field-wrap">
                    <i class="bi bi-envelope field-icon"></i>
                    <input type="text" name="username" placeholder="Enter your email"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>
            <div class="field-group">
                <label>Password</label>
                <div class="field-wrap">
                    <i class="bi bi-lock field-icon"></i>
                    <input type="password" name="password" id="pwInput"
                           placeholder="Enter your password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw()">
                        <i class="bi bi-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-sign-in">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>
    </div>
</div>

<script>
// Apply saved theme on load
(function() {
    if (localStorage.getItem('classsync_theme') === 'light') {
        document.body.classList.add('light-mode');
        document.getElementById('loginThemeIcon').className = 'bi bi-moon-fill';
    }
})();

function toggleLoginTheme() {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('classsync_theme', isLight ? 'light' : 'dark');
    document.getElementById('loginThemeIcon').className = isLight ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
}

function togglePw() {
    const i = document.getElementById('pwInput');
    const ic = document.getElementById('pwIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>