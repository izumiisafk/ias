<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
    exit();
}

require_once 'config/db.php';

$hardcoded = [
    'admin' => ['password' => 'admin123', 'role' => 'admin', 'full_name' => 'System Administrator'],
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $logged   = false;

    if (isset($hardcoded[$username]) && $hardcoded[$username]['password'] === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['role']      = $hardcoded[$username]['role'];
        $_SESSION['username']  = $username;
        $_SESSION['full_name'] = $hardcoded[$username]['full_name'];
        $logged = true;
    }

    if (!$logged) {
        $stmt = $conn->prepare("SELECT * FROM system_accounts WHERE email=? AND status='Active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                if ($row['password'] === $password) {
                    $_SESSION['logged_in'] = true;
                    $_SESSION['role']      = $row['role'];
                    $_SESSION['username']  = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $logged = true;
                }
            }
        }
    }

    if ($logged) {
        header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
        exit();
    } else {
        $error = 'Invalid pekpek.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ClassSync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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

        body {
            font-family: var(--font-b);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            width: 400px; min-height: 100vh;
            background: var(--surface2);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 44px 40px;
            position: relative; overflow: hidden; flex-shrink: 0;
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

        .lp-brand {
            display: flex; align-items: center; gap: 12px;
            position: relative; z-index: 1;
        }
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
        }
        .lp-card-icon {
            width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }
        .lp-card-icon.admin    { background: rgba(79,163,255,0.12); color: var(--accent); }
        .lp-card-icon.registrar { background: rgba(0,229,160,0.1); color: var(--green); }
        .lp-card-label { font-size: 10px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px; }
        .lp-card-creds { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.6); font-family: monospace; }

        .lp-footer { font-size: 12px; color: rgba(255,255,255,0.15); position: relative; z-index: 1; }

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
            transition: border-color 0.15s, box-shadow 0.15s;
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
</head>
<body>

<div class="left-panel">
    <div class="lp-brand">
        <div class="lp-brand-icon"><i class="bi bi-calendar2-week-fill"></i></div>
        <div class="lp-brand-name">ClassSync</div>
    </div>

    <div class="lp-body">
        <h2>Your Schedule,<br><span>Our Priority.</span></h2>
        <p>Manage users, roles and scheduling with a modern admin panel.</p>
        <div class="lp-cards">
            <div class="lp-card">
                <div class="lp-card-icon admin"><i class="bi bi-shield-fill"></i></div>
                <div>
                    <div class="lp-card-label">Admin</div>
                    <div class="lp-card-creds">admin / admin123</div>
                </div>
            </div>
            <div class="lp-card">
                <div class="lp-card-icon registrar"><i class="bi bi-person-badge-fill"></i></div>
                <div>
                    <div class="lp-card-label">Registrar</div>
                    <div class="lp-card-creds">Created by Admin</div>
                </div>
            </div>
        </div>
    </div>

    <div class="lp-footer">Class Scheduling System &copy; 2025–2026</div>
</div>

<div class="right-panel">
    <div class="login-box">
        <h1>Welcome back.</h1>
        <p class="subtitle">Sign in to your account to continue.</p>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
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
function togglePw() {
    const i = document.getElementById('pwInput');
    const ic = document.getElementById('pwIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
