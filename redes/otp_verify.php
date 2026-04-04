<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not coming from login or already logged in
if (!isset($_SESSION['otp_user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
    exit();
}

require_once 'config/db.php';
require_once 'includes/activity_helper.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = trim($_POST['otp'] ?? '');
    $user_id = $_SESSION['otp_user_id'];

    if (empty($otp_input)) {
        $error = "Please enter the verification code.";
    } else {
        try {
            // Verify OTP: must match, not used, and not expired
            $stmt = $conn->prepare("
                SELECT id FROM public.user_otps_ums 
                WHERE user_id = :uid 
                AND otp_code = :otp 
                AND is_used = 0 
                AND expires_at > NOW() 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute(['uid' => $user_id, 'otp' => $otp_input]);
            $otp_row = $stmt->fetch();

            if ($otp_row) {
                // Mark OTP as used
                $update = $conn->prepare("UPDATE public.user_otps_ums SET is_used = 1 WHERE id = ?");
                $update->execute([$otp_row['id']]);

                // Complete login
                $_SESSION['logged_in'] = true;
                $_SESSION['role']      = $_SESSION['otp_role'];
                $_SESSION['username']  = $_SESSION['otp_email'];
                $_SESSION['full_name'] = $_SESSION['otp_full_name'];
                $_SESSION['account_id'] = $_SESSION['otp_user_id'];

                // Log OTP success
                logActivity($user_id, 'login', '2FA OTP verified|' . $_SESSION['otp_email']);

                // Clear temp session data
                unset($_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['otp_full_name'], $_SESSION['otp_role']);

                header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'registrar/dashboard.php'));
                exit();
            } else {
                // Log OTP failure
                logActivity($user_id, 'login', 'Failed OTP verification|' . $_SESSION['otp_email']);
                $error = "Invalid or expired verification code.";
            }
        } catch (PDOException $e) {
            $error = "Verification Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — ClassSync</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --bg:       #0d1220;
            --surface:  #101422;
            --surface2: #161a2e;
            --border:   rgba(255,255,255,0.06);
            --accent:   #4fa3ff;
            --text:     #e8eaf2;
            --muted:    #4e5369;
            --danger:   #ff5f5f;
            --font-d:   'Syne', sans-serif;
            --font-b:   'DM Sans', sans-serif;
        }

        body {
            font-family: var(--font-b);
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            justify-content: center;
        }
        .brand-icon {
            width: 32px; height: 32px; background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: #fff;
        }
        .brand-name {
            font-family: var(--font-d); font-size: 18px;
            font-weight: 800; letter-spacing: -0.02em;
        }

        h1 { font-family: var(--font-d); font-size: 24px; font-weight: 800; text-align: center; margin-bottom: 8px; }
        .subtitle { font-size: 14px; color: var(--muted); text-align: center; margin-bottom: 32px; line-height: 1.5; }
        .subtitle b { color: var(--text); }

        .field-group { margin-bottom: 24px; }
        label { display: block; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 8px; }
        
        .otp-input-wrap { position: relative; }
        .otp-input-wrap input {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 8px;
            color: var(--text);
            transition: all 0.2s;
        }
        .otp-input-wrap input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(79,163,255,0.1);
        }

        .error-box {
            background: rgba(255,95,95,0.08);
            border: 1px solid rgba(255,95,95,0.18);
            border-radius: 12px; padding: 12px 16px;
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: var(--danger); margin-bottom: 20px;
        }

        .btn-verify {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 8px 24px rgba(79,163,255,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-verify:hover {
            background: #6db5ff;
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(79,163,255,0.45);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-calendar2-week-fill"></i></div>
        <div class="brand-name">ClassSync</div>
    </div>

    <h1>Verify Identity</h1>
    <p class="subtitle">Enter the 6-digit code sent to<br><b><?= htmlspecialchars($_SESSION['otp_email']) ?></b></p>

    <form method="POST">
        <?php if ($error): ?>
            <div class="error-box">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="field-group">
            <label>Verification Code</label>
            <div class="otp-input-wrap">
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" inputmode="numeric" 
                       placeholder="000000" required autofocus autocomplete="one-time-code">
            </div>
        </div>

        <button type="submit" class="btn-verify">
            Verify & Sign In <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <a href="logout.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Login
    </a>
</div>

</body>
</html>
