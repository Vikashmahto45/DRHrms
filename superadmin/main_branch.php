<?php
// /superadmin/main_branch.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = '';
$msgType = '';

// --- Auto-detect or Auto-create the HQ Company ---
$stmt = $pdo->prepare("SELECT * FROM companies WHERE login_slug = 'hq' OR is_main_branch = 1 ORDER BY id ASC LIMIT 1");
$stmt->execute();
$hq = $stmt->fetch();

if (!$hq) {
    // Determine expiry (lifetime for HQ)
    $expiry = date('Y-m-d H:i:s', strtotime('+10 years'));
    $pdo->prepare("INSERT INTO companies (name, status, subscription_end_date, user_limit, lead_limit, storage_limit_mb, is_main_branch, parent_id, commission_rate, login_slug) VALUES ('Headquarters', 'active', ?, 9999, 99999, 50000, 1, NULL, 0.00, 'hq')")->execute([$expiry]);
    $hq_id = $pdo->lastInsertId();
    
    // Auto-enable all modules for HQ
    $modules = ['leads', 'hrms', 'payroll', 'company_management'];
    foreach ($modules as $mod) {
        $pdo->prepare("INSERT INTO permissions_map (company_id, module_name, is_enabled) VALUES (?, ?, 1)")->execute([$hq_id, $mod]);
    }
    
    // Fetch newly created HQ
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$hq_id]);
    $hq = $stmt->fetch();
}

$hq_id = $hq['id'];

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_login') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $role = 'admin';
        
        if ($name && $email && $pass) {
            if (strlen($pass) < 6) {
                $msg = "Password must be at least 6 characters."; $msgType = "error";
            } else {
                try {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$hq_id, $name, $email, $hash, $role]);
                    $msg = "Success! Created {$role} login for {$name}."; $msgType = "success";
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $msg = "Error: Email is already in use.";
                    } else {
                        $msg = "Database Error: " . $e->getMessage();
                    }
                    $msgType = "error";
                }
            }
        } else {
            $msg = "All fields are required."; $msgType = "error";
        }
    }
    
    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?")->execute([$uid, $hq_id]);
        $msg = "User permanently deleted."; $msgType = "warning";
    }
    
    if ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND company_id = ?")->execute([$new_status, $uid, $hq_id]);
        $msg = "User status updated."; $msgType = "success";
    }
}

// --- Fetch HQ Users ---
$usersQuery = $pdo->prepare("SELECT * FROM users WHERE company_id = ? ORDER BY created_at DESC");
$usersQuery->execute([$hq_id]);
$users = $usersQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Main Branch (HQ) - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        .hq-banner { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .hq-stat { display: flex; gap: 0.8rem; margin-top: 1.2rem; flex-wrap: wrap; }
        .hq-stat-item { background: rgba(255,255,255,0.06); padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); flex: 1; min-width: 120px; }
        .hq-stat-item label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .hq-stat-item .stat-val { font-size: 1.4rem; font-weight: 700; color: #fff; }
        .hq-link-box { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .hq-link-input { background: transparent; border: none; color: #38bdf8; flex: 1; min-width: 0; outline: none; font-family: monospace; font-size: 0.8rem; }

        /* Mobile-first: form and table stack vertically */
        .hq-layout { display: flex; flex-direction: column; gap: 1.5rem; }

        /* Side-by-side only on large screens */
        @media (min-width: 900px) {
            .hq-layout { flex-direction: row; align-items: flex-start; }
            .hq-form-col { width: 320px; flex-shrink: 0; }
            .hq-table-col { flex: 1; min-width: 0; }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Main Branch (HQ) Control Panel</h1>
            <p style="color:var(--text-muted)">Directly create and manage logins for your internal headquarters team without treating them like a client company.</p>
        </div>
    </div>

    <!-- HQ Status Banner -->
    <div class="hq-banner">
        <h2><?= htmlspecialchars($hq['name']) ?></h2>
        <p style="color:#94a3b8; font-size:0.9rem; margin-top:0.3rem;">This entity is automatically managed behind the scenes. Its database mapping is permanently active.</p>
        
        <div class="hq-stat">
            <div class="hq-stat-item">
                <label>Active Logins</label>
                <div class="stat-val"><?= count($users) ?></div>
            </div>
            <div class="hq-stat-item">
                <label>HQ Login Link</label>
                <?php $login_url = BASE_URL . 'login.php?company=' . urlencode($hq['login_slug']); ?>
                <div class="hq-link-box">
                    <input type="text" id="hqLink" value="<?= htmlspecialchars($login_url) ?>" readonly class="hq-link-input" onclick="this.select()">
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('hqLink').value); alert('Copied!')" style="background:rgba(56,189,248,0.2); color:#38bdf8; border:1px solid rgba(56,189,248,0.4); padding:3px 8px; border-radius:6px; cursor:pointer; font-size:0.75rem; white-space:nowrap;">Copy</button>
                    <a href="<?= htmlspecialchars($login_url) ?>" target="_blank" style="color:#10b981; font-size:0.8rem; white-space:nowrap;">Open →</a>
                </div>
            </div>
        </div>
    </div>

    <div class="hq-layout">
        
        <!-- Create Login Form -->
        <div class="hq-form-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Add HQ Staff Login</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_login">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Jane Doe">
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" required placeholder="jane@mainbranch.com">
                </div>
                
                <input type="hidden" name="role" value="admin">
                <div class="form-group">
                    <label>System Role *</label>
                    <input type="text" class="form-control" value="HQ Administrator (HR & Full Access)" readonly style="background:#f8fafc; color:#64748b; cursor:not-allowed;">
                </div>
                
                <div class="form-group">
                    <label>Set Initial Password *</label>
                    <div style="position:relative;">
                        <input type="password" id="hqPass" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters" style="padding-right:42px;">
                        <span onclick="var p=document.getElementById('hqPass'); p.type=(p.type==='password')?'text':'password'; this.textContent=(p.type==='password')?'👁️':'🔒';" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--text-muted);">👁️</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Create Workspace Login</button>
            </form>
        </div><!-- /hq-form-col -->

        <!-- Existing Logins List -->
        <div class="hq-table-col">
        <div class="content-card">
            <div class="card-header">
                <h3>Current HQ Team</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Name</th>
                            <th style="text-align:left;">Role</th>
                            <th style="text-align:left;">Status</th>
                            <th style="text-align:left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['name']) ?></strong><br>
                                <span style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background:#e2e8f0; color:#475569;"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></span>
                            </td>
                            <td>
                                <?php if($u['status'] === 'active'): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive" style="background:#fee2e2; color:#ef4444;">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button class="btn btn-sm btn-outline">Toggle Access</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this user?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-danger">🗑️ Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($users) === 0): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:3rem; color:var(--text-muted);">
                                No HQ users created yet. Use the form to your left to generate your first login!
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /content-card -->
        </div><!-- /hq-table-col -->

    </div><!-- /hq-layout -->
</main>
</body>
</html>
