<?php
// logout.php
session_start();
require_once 'config/database.php';

$role = $_GET['role'] ?? 'user';
$cid = $_SESSION['company_id'] ?? null;
$slug = '';

// If branch user, try to find their login slug before destroying session
if ($role === 'user' && $cid) {
    try {
        $stmt = $pdo->prepare("SELECT login_slug FROM companies WHERE id = ?");
        $stmt->execute([$cid]);
        $slug = $stmt->fetchColumn();
    } catch (Exception $e) {}
}

// Clear all sessions
session_unset();
session_destroy();

// Redirect back to the correct portal
if ($role === 'sa') {
    header("Location: login.php?tab=superadmin");
} elseif ($slug && $slug !== 'hq') {
    header("Location: login.php?company=" . urlencode($slug));
} else {
    header("Location: login.php");
}
exit();
?>
