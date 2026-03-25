<?php
// /superadmin_login.php — Redirect to unified login portal
require_once 'config/database.php';
header("Location: " . BASE_URL . "login.php?tab=superadmin");
exit();
?>
