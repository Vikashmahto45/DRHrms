<?php
// logout.php
session_start();
$role = $_GET['role'] ?? 'all';

if ($role === 'sa') {
    unset($_SESSION['sa_user_id']);
    unset($_SESSION['sa_user_name']);
    unset($_SESSION['sa_user_role']);
    header("Location: superadmin_login.php");
} elseif ($role === 'user') {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_role']);
    unset($_SESSION['company_id']);
    unset($_SESSION['impersonator_id']);
    header("Location: login.php");
} else {
    session_unset();
    session_destroy();
    header("Location: login.php");
}
exit();
?>
