<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'registrar') {
    header('Location: ../../login.php');
    exit();
}
?>
