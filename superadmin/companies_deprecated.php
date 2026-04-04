<?php
// /superadmin/companies.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = '';
$msgType = '';

// ── Handle POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name        = trim($_POST['name'] ?? '');
        $admin_name  = trim($_POST['admin_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_pass  = $_POST['admin_password'] ?? '';
        
        $duration    = $_POST['duration'] ?? '1 Month';
        $user_limit  = (int)($_POST['user_limit'] ?? 10);
        $lead_limit  = (int)($_POST['lead_limit'] ?? 100);
        $storage_mb  = (int)($_POST['storage_limit'] ?? 500);
        $status      = $_POST['status'] ?? 'active';
        $sel_modules = $_POST['modules'] ?? [];
        
        $is_main_branch = isset($_POST['is_main_branch']) ? (int)$_POST['is_main_branch'] : 1;
        $parent_id = ($is_main_branch === 0 && !empty($_POST['parent_id'])) ? (int)$_POST['parent_id'] : null;
        $commission_rate = !empty($_POST['commission_rate']) ? (float)$_POST['commission_rate'] : 80.00;

        // Calculate Expiry
        $date = new DateTime();
        if ($duration === '3 Months') $date->modify('+3 months');
        elseif ($duration === '6 Months') $date->modify('+6 months');
        elseif ($duration === '1 Year') $date->modify('+1 year');
        else $date->modify('+1 month'); // Default
        $expiry = $date->format('Y-m-d H:i:s');

        if ($name && $admin_name && $admin_email && $admin_pass) {
            if (strlen($admin_pass) < 6) {
                $msg = "Password must be at least 6 characters."; $msgType = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    $login_slug = strtolower(substr(md5(uniqid($name, true)), 0, 10));
                    $stmt = $pdo->prepare("INSERT INTO companies (name, status, subscription_end_date, user_limit, lead_limit, storage_limit_mb, is_main_branch, parent_id, commission_rate, login_slug) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$name, $status, $expiry, $user_limit, $lead_limit, $storage_mb, $is_main_branch, $parent_id, $commission_rate, $login_slug]);
                    $company_id = $pdo->lastInsertId();

                    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id,name,email,password,role,status) VALUES (?,?,?,?,'admin','active')");
                    $stmt->execute([$company_id, $admin_name, $admin_email, $hash]);

                    // Permissions
                    foreach ($sel_modules as $mod) {
                        $pdo->prepare("INSERT INTO permissions_map (company_id,module_name,is_enabled) VALUES (?,?,1)")->execute([$company_id, $mod]);
                    }

                    $pdo->commit();
                    logActivity('company_created', "Created company: $name with admin: $admin_email", $company_id);
                    $_SESSION['flash_message'] = "Company '<strong>{$name}</strong>' created! Expiry: " . date('M d, Y', strtotime($expiry));
                    header("Location: companies.php"); exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error: " . $e->getMessage(); $msgType = 'error';
                }
            }
        } else {
            $msg = "All fields are required."; $msgType = 'error';
        }
    }

    if ($action === 'login_as') {
        $company_id = (int)$_POST['company_id'];
        // Find the primary admin for this company
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE company_id = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$company_id]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            logActivity('login_as', "Super Admin impersonated company admin: {$admin['name']} (ID: {$admin['id']})", $company_id);
            $_SESSION['impersonator_id'] = $_SESSION['sa_user_id'];
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['company_id'] = $company_id;
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $admin['name'] . " (Impersonated)";
            header("Location: ../admin/dashboard.php"); exit();
        }
    }

    if ($action === 'renew') {
        $id = (int)$_POST['company_id'];
        $pdo->prepare("UPDATE companies SET subscription_end_date = DATE_ADD(IFNULL(subscription_end_date, NOW()), INTERVAL 1 MONTH) WHERE id = ?")->execute([$id]);
        $_SESSION['flash_message'] = "Subscription extended by 1 month.";
        header("Location: companies.php"); exit();
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['company_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'active';
        $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?")->execute([$new_status, $id]);
        logActivity('company_status_toggle', "Status changed to $new_status for company ID: $id", $id);
        header("Location: companies.php"); exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['company_id'] ?? 0);
        $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$id]);
        logActivity('company_deleted', "Deleted company ID: $id");
        header("Location: companies.php"); exit();
    }

    if ($action === 'toggle_module') {
        $company_id = (int)($_POST['company_id'] ?? 0);
        $module     = $_POST['module'] ?? '';
        $enabled    = (int)($_POST['is_enabled'] ?? 0);

        $stmt = $pdo->prepare("SELECT id FROM permissions_map WHERE company_id=? AND module_name=?");
        $stmt->execute([$company_id, $module]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE permissions_map SET is_enabled=? WHERE company_id=? AND module_name=?")->execute([$enabled, $company_id, $module]);
        } else {
            $pdo->prepare("INSERT INTO permissions_map (company_id,module_name,is_enabled) VALUES (?,?,?)")->execute([$company_id, $module, $enabled]);
        }
        header("Location: companies.php"); exit();
    }
}

// ── Fetch data ─────────────────────────────────────────────────────
$companies = $pdo->query("
    SELECT c.*, COUNT(u.id) AS user_count, p.name AS parent_name
    FROM companies c 
    LEFT JOIN users u ON c.id = u.company_id 
    LEFT JOIN companies p ON c.parent_id = p.id
    GROUP BY c.id 
    ORDER BY c.created_at DESC
")->fetchAll();

// Fetch Main Branches for the dropdown
$main_branches = $pdo->query("SELECT id, name FROM companies WHERE is_main_branch = 1 ORDER BY name ASC")->fetchAll();

// Fetch module permissions for each company
$permissions = [];
$rows = $pdo->query("SELECT * FROM permissions_map")->fetchAll();
foreach ($rows as $r) {
    $permissions[$r['company_id']][$r['module_name']] = $r['is_enabled'];
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
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
            <h1>Manage Companies</h1>
            <p style="color:var(--text-muted)">Create, suspend or delete tenant companies.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">+ New Company</button>
    </div>

    <!-- Companies Table -->
    <div class="content-card">
        <div class="card-header">
            <h2>All Companies (<?= count($companies) ?>)</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th><th>Users</th><th>Expiry</th><th>Status</th>
                        <th>Modules</th><th>Login Link</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $c): ?>
                    <?php 
                        $perms = $permissions[$c['id']] ?? []; 
                        $expiry_date = $c['subscription_end_date'] ? new DateTime($c['subscription_end_date']) : null;
                        $now = new DateTime();
                        $days_left = $expiry_date ? $now->diff($expiry_date)->format("%r%a") : '∞';
                        $expiry_class = ($days_left !== '∞' && $days_left < 7) ? 'color:#ef4444;font-weight:700' : '';
                    ?>
                    <tr>
                        <td style="font-weight:600">
                            <?= htmlspecialchars($c['name']) ?><br>
                            <span style="font-size:0.75rem;color:var(--text-muted)">ID: #<?= $c['id'] ?> <?= $c['is_main_branch'] ? '<span style="color:#10b981">(Main)</span>' : "(Branch of {$c['parent_name']})" ?></span>
                        </td>
                        <td>
                            <span style="font-weight:600"><?= $c['user_count'] ?></span> / <?= $c['user_limit'] ?><br>
                            <div style="width:60px;height:4px;background:rgba(0,0,0,0.05);border-radius:2px;margin-top:4px;">
                                <div style="width:<?= min(100, ($c['user_count']/$c['user_limit'])*100) ?>%;height:100%;background:var(--primary-color);border-radius:2px;"></div>
                            </div>
                        </td>
                        <td style="<?= $expiry_class ?>">
                            <?= $expiry_date ? $expiry_date->format('M d, Y') : 'Never' ?><br>
                            <span style="font-size:0.75rem;opacity:0.8"><?= ($days_left === '∞') ? 'Lifetime' : ($days_left < 0 ? 'Expired' : $days_left . ' days left') ?></span>
                        </td>
                        <td><span class="badge badge-<?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
                        <td>
                            <div style="display:flex;gap:4px;flex-wrap:wrap">
                                <?php foreach($perms as $mod => $on): if($on): ?>
                                    <span style="font-size:0.65rem;padding:2px 5px;background:rgba(99,102,241,0.2);border-radius:4px;color:#818cf8"><?= strtoupper($mod) ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </td>
                        <td style="color:var(--text-muted);font-size:0.85rem">
                            <?php
                                $slug = $c['login_slug'] ?? '';
                                $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                                $login_url = $base . '/DR%20Hrms/login.php' . ($slug ? '?company=' . urlencode($slug) : '');
                            ?>
                            <?php if ($slug): ?>
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <input type="text" id="url_<?= $c['id'] ?>" value="<?= htmlspecialchars($login_url) ?>" readonly
                                        style="font-size:0.7rem; padding:3px 6px; border:1px solid #e8edf3; border-radius:6px; background:#f8fafc; color:#374151; width:160px; cursor:pointer;"
                                        onclick="this.select()">
                                    <button onclick="copyUrl('url_<?= $c['id'] ?>')"
                                        style="font-size:0.75rem; padding:3px 8px; border-radius:6px; border:1px solid #6366f1; background:#fff; color:#6366f1; cursor:pointer; white-space:nowrap"
                                        title="Copy login link">📋 Copy</button>
                                </div>
                                <a href="<?= htmlspecialchars($login_url) ?>" target="_blank" style="font-size:0.7rem; color:#6366f1; margin-top:3px; display:block;">Open →</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:0.75rem;">Run add_login_slug.php</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="renew">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="border-color:#10b981;color:#10b981">Renew</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="login_as">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary" style="padding:0.3rem 0.6rem">Login As</button>
                            </form>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $c['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Toggle</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this company?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" style="padding:0.3rem 0.6rem">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($companies)): ?>
                        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No companies yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Create Company Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="max-width:650px;">
        <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        <h3>Create New Company</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Company / Agency Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group">
                    <label>Subscription Duration</label>
                    <select name="duration" class="form-control">
                        <option value="1 Month">1 Month (Trial)</option>
                        <option value="3 Months">3 Months</option>
                        <option value="6 Months">6 Months</option>
                        <option value="1 Year">1 Year (Best Value)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Admin Full Name *</label>
                    <input type="text" name="admin_name" class="form-control" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Admin Email *</label>
                    <input type="email" name="admin_email" class="form-control" required placeholder="admin@company.com">
                </div>
            </div>
            <div class="form-group">
                <label>Set Admin Password *</label>
                <div style="position:relative;">
                    <input type="password" id="adminPass" name="admin_password" class="form-control" required minlength="6" style="padding-right:42px;">
                    <span onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--text-muted);">👁️</span>
                </div>
            </div>
            
            <div style="margin:1.5rem 0;padding:1.2rem;background:#f8fafc;border:1px solid var(--glass-border);border-radius:12px;">
                <label style="display:block;margin-bottom:1rem;font-weight:600;font-size:0.95rem;color:var(--primary-color)">Module Access Control</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.9rem"><input type="checkbox" name="modules[]" value="leads" checked> Lead CRM Pipeline</label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.9rem"><input type="checkbox" name="modules[]" value="hrms" checked> HRMS Core (Staff/Attend)</label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.9rem"><input type="checkbox" name="modules[]" value="payroll"> Payroll & Salary</label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.9rem"><input type="checkbox" name="modules[]" value="company_management" checked> Client Settings Panel</label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Branch Type</label>
                    <select name="is_main_branch" id="branchType" class="form-control" onchange="toggleBranchFields()">
                        <option value="1">Main Branch (Finance Control)</option>
                        <option value="0">Sub-Branch (Sales Only)</option>
                    </select>
                </div>
                <div class="form-group" id="parentBranchGroup" style="display:none;">
                    <label>Parent Branch</label>
                    <select name="parent_id" class="form-control">
                        <option value="">Select Parent...</option>
                        <?php foreach($main_branches as $mb): ?>
                            <option value="<?= $mb['id'] ?>"><?= htmlspecialchars($mb['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="commissionGroup" style="display:none;">
                    <label>Commission Rate (%)</label>
                    <input type="number" step="0.01" name="commission_rate" class="form-control" value="80.00">
                </div>
            </div>



            <div class="form-group">
                <label>Initial Account Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active (Immediate Access)</option>
                    <option value="inactive">Inactive (Suspended)</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Create Company & Admin</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleBranchFields() {
    const isMain = document.getElementById('branchType').value === '1';
    document.getElementById('parentBranchGroup').style.display = isMain ? 'none' : 'block';
    document.getElementById('commissionGroup').style.display = isMain ? 'none' : 'block';
}

function togglePass() {
    const inp = document.getElementById('adminPass');
    const icon = event.currentTarget;
    if (inp.type === 'password') { inp.type = 'text'; icon.textContent = '🔒'; }
    else { inp.type = 'password'; icon.textContent = '👁️'; }
}

function copyUrl(id) {
    const el = document.getElementById(id);
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(() => {
        const btn = el.nextElementSibling;
        const orig = btn.textContent;
        btn.textContent = '✅ Copied!';
        btn.style.background = '#f0fdf4';
        btn.style.color = '#10b981';
        btn.style.borderColor = '#10b981';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.background = '';
            btn.style.color = '#6366f1';
            btn.style.borderColor = '#6366f1';
        }, 2000);
    });
}
</script>
</body>
</html>
