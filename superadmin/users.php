<?php
// /superadmin/users.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $user_id = (int)$_POST['user_id'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $msg = "Password must be at least 6 characters."; $msgType = 'error';
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "Passwords do not match."; $msgType = 'error';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
        $msg = "Password successfully updated for user #$user_id."; $msgType = 'success';
    }
}

// Fetch all users except superadmin
$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, c.name as company_name 
    FROM users u 
    LEFT JOIN companies c ON u.company_id = c.id 
    WHERE u.role != 'super_admin' 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users Management - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(\"../assets/css/style.css\") ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(\"../assets/css/admin.css\") ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1>All Users Management</h1>
            <p style="color:var(--text-muted)">Manage staff and admin accounts across all branches. You can force-change passwords from here.</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Registered Users (<?= count($users) ?>)</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge" style="background:#f1f5f9;color:#475569;"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span></td>
                        <td><?= htmlspecialchars($u['company_name'] ?: 'N/A') ?></td>
                        <td>
                            <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline" style="color:var(--primary-color); border-color:var(--primary-color);" 
                                onclick="openPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                🔑 Force Password
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Change Password Modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box" style="max-width:450px;">
        <button class="modal-close" onclick="document.getElementById('passwordModal').classList.remove('open')">&times;</button>
        <h3>Force Change Password</h3>
        <p style="color:var(--text-muted);font-size:0.9rem;">Change password for: <strong id="pw_user_name" style="color:#0f172a;"></strong></p>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="pw_user_id">
            
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            
            <div class="modal-footer" style="margin-top:1.5rem;">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('passwordModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPasswordModal(id, name) {
    document.getElementById('pw_user_id').value = id;
    document.getElementById('pw_user_name').textContent = name;
    document.getElementById('passwordModal').classList.add('open');
}
</script>
</body>
</html>
