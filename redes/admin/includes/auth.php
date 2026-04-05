<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/permissions_helper.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

// ── RE-VERIFY STATUS FROM DATABASE ──
if (isset($_SESSION['account_id'])) {
    require_once __DIR__ . '/../../config/db.php';
    try {
        $status_stmt = $conn->prepare("SELECT status FROM public.users_ums WHERE id = ?");
        $status_stmt->execute([$_SESSION['account_id']]);
        $curr_status = $status_stmt->fetchColumn();

        // If user not found or status not active, force logout
        // Use trim and strtolower for a more robust comparison
        if ($curr_status === false || strtolower(trim($curr_status)) !== 'active') {
            session_destroy();
            $msg = ($curr_status === false) ? 'Account validation failed.' : 'Account is no longer active.';
            header('Location: ../login.php?error=' . urlencode($msg));
            exit();
        }
    } catch (PDOException $e) {
        // DB error - handle gracefully or ignore for auth check
    }
}
?>
