<?php
// /superadmin/admins.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $new = $_POST['new_status'];
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='admin'")->execute([$new, $id]);
        header("Location: admins.php"); exit();
    }

    if ($action === 'reset_password') {
        $id = (int)$_POST['user_id'];
        $raw = bin2hex(random_bytes(4));
        $hash = password_hash($raw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='admin'")->execute([$hash, $id]);
        $_SESSION['sa_flash_message'] = "Password reset! New temporary password: <strong>{$raw}</strong> — Share this securely.";
        header("Location: admins.php"); exit();
    }
}

// INNER JOIN ensures orphaned admins (company deleted) don't appear
$admins = $pdo->query("
    SELECT u.*, c.name AS company_name, c.status AS company_status, c.is_main_branch
    FROM users u
    INNER JOIN companies c ON u.company_id = c.id
    WHERE u.role = 'admin'
    ORDER BY u.created_at DESC
")->fetchAll();

$flash = $_SESSION['sa_flash_message'] ?? null;
unset($_SESSION['sa_flash_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if ($flash): ?>
        <div class="flash-success"><?= $flash ?> <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;">&times;</button></div>
    <?php endif; ?>
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Manage Admins</h1>
            <p style="color:var(--text-muted)">Enable or disable Company Admins. Deleting a company removes their admin automatically.</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h2>All Company Admins (<?= count($admins) ?>)</h2></div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Company</th><th>Branch</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($admin['name']) ?></td>
                        <td style="color:var(--text-muted);font-size:0.9rem"><?= htmlspecialchars($admin['email']) ?></td>
                        <td>
                            <?= htmlspecialchars($admin['company_name'] ?? '—') ?>
                            <?php if ($admin['company_status'] !== 'active'): ?>
                                <span style="font-size:0.7rem;color:#ef4444;margin-left:4px">(Suspended)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($admin['is_main_branch'] == 1): ?>
                                <span style="font-size:0.75rem;background:rgba(16,185,129,.12);color:#059669;padding:2px 8px;border-radius:20px;font-weight:600">Main</span>
                            <?php else: ?>
                                <span style="font-size:0.75rem;background:rgba(99,102,241,.1);color:#6366f1;padding:2px 8px;border-radius:20px;font-weight:600">Sub</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $admin['status'] ?>"><?= ucfirst($admin['status']) ?></span></td>
                        <td style="color:var(--text-muted);font-size:.9rem"><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                        <td style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $admin['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button class="btn btn-sm btn-outline" style="<?= $admin['status'] === 'active' ? 'color:#f59e0b;border-color:#f59e0b' : 'color:#10b981;border-color:#10b981' ?>">
                                    <?= $admin['status'] === 'active' ? '🔒 Disable' : '✅ Enable' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Reset this admin\'s password?')">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                <button class="btn btn-sm btn-outline" style="color:#6366f1;border-color:#6366f1">Reset PW</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($admins)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem">No admins yet. Create a company first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
