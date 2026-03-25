<?php
// /admin/manage_branches.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = '';
$msgType = '';

// Check if this is a main branch
$stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
$stmt->execute([$cid]);
if ($stmt->fetchColumn() == 0) {
    die("Access Denied: Only Main Branches can manage sub-branches.");
}

// ── Handle POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $admin_name  = trim($_POST['admin_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_pass  = $_POST['admin_password'] ?? '';
        $commission  = (float)($_POST['commission_rate'] ?? 80.00);

        if ($name && $admin_name && $admin_email && $admin_pass) {
            if (strlen($admin_pass) < 6) {
                $msg = "Password must be at least 6 characters."; $msgType = 'error';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Auto-generate unique login_slug from branch name
                    $base_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
                    $base_slug = trim($base_slug, '-');
                    $slug = $base_slug;
                    $i = 1;
                    while ($pdo->prepare("SELECT id FROM companies WHERE login_slug = ?")->execute([$slug]) && $pdo->query("SELECT id FROM companies WHERE login_slug = '$slug'")->fetchColumn()) {
                        $slug = $base_slug . '-' . $i++;
                    }

                    $stmt = $pdo->prepare("INSERT INTO companies (name, login_slug, status, subscription_end_date, user_limit, lead_limit, storage_limit_mb, is_main_branch, parent_id, commission_rate) VALUES (?, ?, 'active', NULL, 10, 100, 500, 0, ?, ?)");
                    $stmt->execute([$name, $slug, $cid, $commission]);
                    $new_company_id = $pdo->lastInsertId();

                    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id,name,email,password,role,status) VALUES (?,?,?,?,'admin','active')");
                    $stmt->execute([$new_company_id, $admin_name, $admin_email, $hash]);

                    // Default Permissions
                    $modules = ['leads', 'hrms', 'company_management'];
                    foreach ($modules as $mod) {
                        $pdo->prepare("INSERT INTO permissions_map (company_id,module_name,is_enabled) VALUES (?,?,1)")->execute([$new_company_id, $mod]);
                    }

                    $pdo->commit();
                    logActivity('sub_branch_created', "Created branch: $name with commission: $commission%", $cid);
                    $msg = "Branch '<strong>{$name}</strong>' created! Login link: <strong><a href='<?= BASE_URL ?>login.php?company={$slug}' target='_blank'>login.php?company={$slug}</a></strong>"; $msgType = 'success';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error: " . $e->getMessage(); $msgType = 'error';
                }
            }
        } else {
            $msg = "All fields are required."; $msgType = 'error';
        }
    }
}

// ── Fetch Branches ──────────────────────────────────────────────────
$branches = $pdo->prepare("
    SELECT c.*, COUNT(u.id) AS user_count 
    FROM companies c 
    LEFT JOIN users u ON c.id = u.company_id 
    WHERE c.parent_id = ? 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$branches->execute([$cid]);
$branchList = $branches->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Branches - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= $msg ?> <button onclick="this.parentElement.remove()" style="float:right;background:none;border:none;color:inherit;font-size:1.2rem;cursor:pointer;">&times;</button></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Manage Sub-Branches</h1>
            <p style="color:var(--text-muted)">Create and oversee franchise branches under your main office.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">+ Add Branch</button>
    </div>

    <!-- Branches Table -->
    <div class="content-card">
        <div class="card-header">
            <h2>Your Sub-Branches (<?= count($branchList) ?>)</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Branch Name</th><th>Admin Contact</th><th>Users</th>
                        <th>Commission Cut</th><th>Status</th><th>Login Link</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branchList as $b): ?>
                    <tr>
                        <td style="font-weight:600">
                            <?= htmlspecialchars($b['name']) ?><br>
                            <span style="font-size:0.75rem;color:var(--text-muted)">ID: #<?= $b['id'] ?></span>
                        </td>
                        <td>
                            <?php 
                                $adminQ = $pdo->prepare("SELECT name, email FROM users WHERE company_id=? AND role='admin' LIMIT 1");
                                $adminQ->execute([$b['id']]);
                                $ad = $adminQ->fetch();
                                echo htmlspecialchars($ad['name'] ?? 'N/A') . "<br>";
                                echo "<small style='color:var(--text-muted)'>" . htmlspecialchars($ad['email'] ?? '') . "</small>";
                            ?>
                        </td>
                        <td><?= $b['user_count'] ?> Users</td>
                        <td style="color:#10b981;font-weight:bold;"><?= $b['commission_rate'] ?>%</td>
                        <td><span class="badge badge-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td>
                            <?php if ($b['login_slug']): ?>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <code style="font-size:.75rem;background:#f1f5f9;padding:3px 8px;border-radius:6px;color:#6366f1;">?company=<?= htmlspecialchars($b['login_slug']) ?></code>
                                    <button onclick="navigator.clipboard.writeText('<?= BASE_URL ?>login.php?company=<?= htmlspecialchars($b['login_slug']) ?>');this.textContent='✅';setTimeout(()=>this.textContent='📋',2000);" style="background:none;border:none;cursor:pointer;font-size:1rem;" title="Copy login link">📋</button>
                                </div>
                            <?php else: ?>
                                <span style="color:#ef4444;font-size:.8rem;">No slug set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="../superadmin/companies.php" style="display:inline">
                                <button type="button" class="btn btn-sm btn-outline" onclick="alert('Impersonation from Main Branch to Sub Branch coming soon.')" >Login As</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($branchList)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem">You have not created any sub-branches yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Create Branch Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="max-width:650px;">
        <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        <h3>Register New Sub-Branch</h3>
        <p style="color:var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">This branch will automatically be linked to your main office.</p>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Branch Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Acme Corp - Location B">
                </div>
                <div class="form-group">
                    <label>Commission Rate (%) *</label>
                    <input type="number" step="0.01" name="commission_rate" class="form-control" value="80.00" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Admin Full Name *</label>
                    <input type="text" name="admin_name" class="form-control" required placeholder="Branch Manager Name">
                </div>
                <div class="form-group">
                    <label>Admin Email *</label>
                    <input type="email" name="admin_email" class="form-control" required placeholder="manager@branch.com">
                </div>
            </div>
            <div class="form-group">
                <label>Set Admin Password *</label>
                <input type="password" name="admin_password" class="form-control" required minlength="6">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Create Branch</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
