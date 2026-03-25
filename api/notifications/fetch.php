<?php
// /api/notifications/fetch.php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit();
}

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];

// Fetch unread notifications
$stmt = $pdo->prepare("SELECT * FROM system_notifications WHERE user_id = ? AND company_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$uid, $cid]);
$notifications = $stmt->fetchAll();

// Mark as read immediately for this demo polling
if ($notifications) {
    $pdo->prepare("UPDATE system_notifications SET is_read = 1 WHERE user_id = ? AND company_id = ?")->execute([$uid, $cid]);
}

echo json_encode($notifications);
