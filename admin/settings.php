<?php
// /admin/settings.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$flash = ''; $flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($cur, $hash)) {
            $flash = "Incorrect current password"; $flashType = 'error';
        } elseif (strlen($new) < 6) {
            $flash = "New password must be at least 6 characters"; $flashType = 'error';
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$new_hash, $_SESSION['user_id']]);
            $flash = "Password updated successfully"; $flashType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company Settings - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div><h1>Company Settings</h1><p style="color:var(--text-muted)">Account security and branding options.</p></div>
    </div>

    <?php if ($flash): ?><div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="content-card">
        <div class="card-header"><h2>🔐 Change Account Password</h2></div>
        <form method="POST" style="max-width:400px">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Save New Password</button>
        </form>
    </div>
</main>
</body>
</html>
