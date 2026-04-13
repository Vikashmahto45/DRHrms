<?php
// /superadmin/project_permissions.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$uid = $_SESSION['sa_user_id'] ?? 0;

// Handle Permission Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    try {
        $pdo->beginTransaction();
        
        $roles_to_update = ['hq_admin', 'hq_manager', 'branch_admin', 'branch_manager', 'sales_person', 'staff'];
        $actions = ['can_add', 'can_edit', 'can_delete', 'can_update_progress', 'can_verify', 'can_instruction'];

        foreach ($roles_to_update as $role_key) {
            $updates = [];
            $params = [];
            foreach ($actions as $action) {
                $val = isset($_POST['perm'][$role_key][$action]) ? 1 : 0;
                $updates[] = "$action = ?";
                $params[] = $val;
            }
            $params[] = $role_key;
            $stmt = $pdo->prepare("UPDATE project_permissions SET " . implode(', ', $updates) . " WHERE role_key = ?");
            $stmt->execute($params);
        }

        $pdo->commit();
        $msg = "Permissions updated successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch current permissions
$stmt = $pdo->query("SELECT * FROM project_permissions");
$permissions = [];
while ($row = $stmt->fetch()) {
    $permissions[$row['role_key']] = $row;
}

// Define display names for roles
$role_names = [
    'hq_admin' => 'HQ Admin',
    'hq_manager' => 'HQ Manager',
    'branch_admin' => 'Branch Admin',
    'branch_manager' => 'Branch Manager',
    'sales_person' => 'Sales Person (Hunter)',
    'staff' => 'Staff (Maker/Developer)'
];

$action_names = [
    'can_add' => 'Add Project',
    'can_edit' => 'Edit Details',
    'can_delete' => 'Delete Project',
    'can_update_progress' => 'Update Progress',
    'can_verify' => 'HQ Verification',
    'can_instruction' => 'Post Instruction'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Action Permissions - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/loom_premium_v2.css?v=<?= time() ?>">
    <style>
        .perm-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: var(--glass-shadow); border: 1px solid var(--glass-border); }
        .perm-table th, .perm-table td { padding: 1.25rem; text-align: center; border-bottom: 1px solid #f1f5f9; }
        .perm-table th { background: #f8fafc; font-weight: 700; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
        .perm-table td:first-child { text-align: left; font-weight: 600; color: #1e293b; background: #f8fafc; width: 250px; }
        .perm-checkbox { width: 20px; height: 20px; cursor: pointer; accent-color: var(--primary-color); }
        .save-bar { position: sticky; bottom: 2rem; background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); padding: 1rem 2rem; border-radius: 50px; border: 1px solid var(--glass-border); box-shadow: 0 10px 30px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-top: 3rem; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="header">
            <div>
                <h1>Project Action Permissions</h1>
                <p style="color: var(--text-muted);">Tick or untick boxes to enable/disable features for specific roles across the system.</p>
            </div>
        </div>

        <?php if (isset($msg)): ?>
            <div class="flash-success" style="margin-bottom:2rem;"><?= $msg ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="flash-error" style="margin-bottom:2rem;"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save_permissions">
            <div style="overflow-x:auto;">
                <table class="perm-table">
                    <thead>
                        <tr>
                            <th>Role / Action</th>
                            <?php foreach ($action_names as $key => $name): ?>
                                <th><?= $name ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($role_names as $r_key => $r_name): ?>
                            <tr>
                                <td><?= $r_name ?></td>
                                <?php foreach ($action_names as $a_key => $a_name): ?>
                                    <td>
                                        <input type="checkbox" name="perm[<?= $r_key ?>][<?= $a_key ?>]" class="perm-checkbox" <?= ($permissions[$r_key][$a_key] ?? 0) ? 'checked' : '' ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="save-bar">
                <div style="font-size:0.9rem; color: #64748b;">
                    💡 <strong>Note:</strong> Changes take effect immediately for all active users.
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; border-radius: 30px;">Save All Permissions</button>
            </div>
        </form>
    </main>
</body>
</html>
