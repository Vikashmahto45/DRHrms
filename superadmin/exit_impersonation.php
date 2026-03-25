<?php
// /superadmin/exit_impersonation.php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['sa_user_id']) && isset($_SESSION['impersonator_id'])) {
    // Simply clear the tenant "impersonation" keys
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_role']);
    unset($_SESSION['company_id']);
    unset($_SESSION['impersonator_id']);
    
    $_SESSION['sa_flash_message'] = "Exited impersonation mode.";
    header("Location: dashboard.php");
    exit();
}

// Fallback if something fails
header("Location: ../login.php");
exit();
?>
