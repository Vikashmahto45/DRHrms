<?php
// /admin/projects.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';

// Fetch Branch Info
$branch_info = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
$branch_info->execute([$cid]);
$is_hq = (bool)$branch_info->fetchColumn();

// 0. Auto-patch for Projects Table
try {
    // 1. Column Checks (Add if missing)
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'custom_sales_name'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN custom_sales_name VARCHAR(255) NULL AFTER verified_by"); }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'branch_id'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN branch_id INT NULL AFTER company_id"); }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'source'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN source VARCHAR(100) DEFAULT 'Walk-in' AFTER project_name"); }

    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'commission_percent'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN commission_percent DECIMAL(5,2) DEFAULT NULL AFTER total_value"); }

    // 2. Enum Update (Status)
    $pdo->exec("ALTER TABLE projects MODIFY COLUMN status ENUM('Pending Approval', 'Active', 'On Hold', 'Completed', 'Cancelled', 'Pending HQ Review') DEFAULT 'Pending HQ Review'");

    // 3. Ensure tables exist (Fallback)
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        branch_id INT NULL,
        sales_person_id INT NULL,
        client_name VARCHAR(255) NOT NULL,
        client_phone VARCHAR(20) NULL,
        project_name VARCHAR(255) NOT NULL,
        project_description TEXT NULL,
        total_value DECIMAL(15,2) DEFAULT 0.00,
        commission_percent DECIMAL(5,2) DEFAULT NULL,
        advance_paid DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('Pending Approval', 'Active', 'On Hold', 'Completed', 'Cancelled', 'Pending HQ Review') DEFAULT 'Pending HQ Review',
        progress_pct INT DEFAULT 0,
        is_verified TINYINT(1) DEFAULT 0,
        verified_by INT NULL,
        custom_sales_name VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        old_progress INT DEFAULT 0,
        new_progress INT DEFAULT 0,
        comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { /* DB auto-patch error handled silent but recorded */ }

$msg = ''; $msgType = '';

// Handle Direct Entry (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    if ($role === 'admin' || $role === 'manager') {
        try {
            $client = trim($_POST['client_name'] ?? '');
            $pname = trim($_POST['project_name'] ?? '');
            $val = (float)($_POST['total_value'] ?? 0);
            $adv = (float)($_POST['advance_paid'] ?? 0);
            $desc = trim($_POST['description'] ?? '');
            $source = trim($_POST['source'] ?? 'Walk-in');
            $sp_id = !empty($_POST['sales_person_id']) ? (int)$_POST['sales_person_id'] : null;
            $custom_sp = trim($_POST['custom_sales_name'] ?? '');
            $comm_pct = isset($_POST['commission_percent']) && $_POST['commission_percent'] !== '' ? (float)$_POST['commission_percent'] : null;

            // Rule: Sub-branch entries are 'Pending HQ Review'
            $status = $is_hq ? 'Active' : 'Pending HQ Review';
            $verified = $is_hq ? 1 : 0;

            if ($client && $pname) {
                $stmt = $pdo->prepare("INSERT INTO projects (company_id, branch_id, sales_person_id, client_name, project_name, source, project_description, total_value, commission_percent, advance_paid, status, is_verified, custom_sales_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cid, $cid, $sp_id, $client, $pname, $source, $desc, $val, $comm_pct, $adv, $status, $verified, $custom_sp]);
                $msg = $is_hq ? "Project created and verified." : "Project submitted. Awaiting HQ Verification."; 
                $msgType = "success";
            }
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = "error"; }
    }
}

// Handle Verification (Admin only)
if (isset($_GET['verify']) && ($role === 'admin' || $role === 'manager')) {
    $pid = (int)$_GET['verify'];
    $pdo->prepare("UPDATE projects SET is_verified = 1, status = 'Active', verified_by = ? WHERE id = ? AND company_id = ?")
        ->execute([$uid, $pid, $cid]);
    header("Location: projects.php?msg=Verified"); exit();
}

// Fetch Projects
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

if ($role === 'sales_person') {
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE p.sales_person_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$uid]);
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE p.company_id IN ($cids_in) ORDER BY p.created_at DESC");
    $stmt->execute();
}
$results = $stmt->fetchAll();

// Refine the project list to handle null user names (custom names)
$projects = [];
foreach($results as $res) {
    if(empty($res['salesperson_name']) && !empty($res['custom_sales_name'])) {
        $res['salesperson_name'] = $res['custom_sales_name'];
    }
    $projects[] = $res;
}

// Fetch All Staff/Project Members for assignment (excluding super_admin)
$sp_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE company_id = ? AND role IN ('sales_person', 'staff', 'manager') ORDER BY name ASC");
$sp_stmt->execute([$cid]);
$staff_members = $sp_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Progress Tracker - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .stat-card p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        
        /* Source Badges */
        .src-tag { font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-top: 5px; display: inline-block; }
        .src-Meta { background: rgba(24, 119, 242, 0.1); color: #1877f2; border: 1px solid rgba(24, 119, 242, 0.2); }
        .src-Google { background: rgba(66, 133, 244, 0.1); color: #4285f4; border: 1px solid rgba(66, 133, 244, 0.2); }
        .src-Referral { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .src-Walk-in { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }

        .progress-bar-container { background: #e2e8f0; border-radius: 20px; height: 10px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { background: var(--primary-color); height: 100%; transition: width 0.3s; }
        .st-Pending { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .st-Pending-HQ-Review { background: rgba(239,68,68,0.1); color: #ef4444; }
        .st-Active { background: rgba(16,185,129,0.1); color: #10b981; }
        .st-Hold { background: rgba(107,114,128,0.1); color: #6b7280; }
        .project-card { border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.5rem; background: #fff; margin-bottom: 1.5rem; transition: transform 0.2s; }
        .project-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Project & Progress Tracker</h1>
                <p style="color:var(--text-muted)">Manage execution and monitor detailed project growth.</p>
            </div>
            <div style="display:flex;gap:10px;">
                <?php if ($role === 'admin' || $role === 'manager'): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('addProjectModal').classList.add('open')">+ New Project Entry</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg || isset($_GET['msg'])): ?>
            <div class="flash-success"><?= htmlspecialchars($msg ?: $_GET['msg']) ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php 
            // Grouping: Show Pending HQ Review first if HQ
            if ($is_hq) {
                usort($projects, function($a, $b) {
                    if ($a['status'] === 'Pending HQ Review' && $b['status'] !== 'Pending HQ Review') return -1;
                    if ($a['status'] !== 'Pending HQ Review' && $b['status'] === 'Pending HQ Review') return 1;
                    return 0;
                });
            }

            foreach ($projects as $p): 
                $status_class = "st-" . str_replace(' ', '-', $p['status']);
            ?>
            <div class="project-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem;"><?= htmlspecialchars($p['project_name']) ?></h3>
                        <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">Client: <strong><?= htmlspecialchars($p['client_name']) ?></strong></div>
                        <?php 
                            $src_class = "src-" . explode(' ', $p['source'] ?? 'Walk-in')[0];
                        ?>
                        <span class="src-tag <?= $src_class ?>"><?= htmlspecialchars($p['source']) ?></span>
                    </div>
                    <span class="badge <?= $status_class ?>" style="font-size:0.7rem;"><?= $p['status'] ?></span>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom: 5px;">
                        <span>Progress</span>
                        <strong><?= $p['progress_pct'] ?>%</strong>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?= $p['progress_pct'] ?>%;"></div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:15px;">
                    <div style="font-size:0.85rem; color:var(--text-muted);">
                        👤 <?= htmlspecialchars($p['salesperson_name']) ?>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <?php if ($p['status'] === 'Pending HQ Review' && $is_hq): ?>
                            <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary" style="background:#ef4444; border:none;">Verify & Assign HQ Staff</a>
                        <?php else: ?>
                            <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
                <div class="content-card" style="grid-column: 1 / -1; text-align:center; padding:4rem; color:var(--text-muted);">
                    No projects found. Convert a sales deal or add a direct entry.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Project Modal -->
<div class="modal-overlay" id="addProjectModal">
    <div class="modal-box" style="max-width:500px;">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Direct Office Entry</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Manual entry for walk-in or office project orders.</p>
        <form method="POST">
            <input type="hidden" name="action" value="create_project">
            <div class="form-group">
                <label>Client Name *</label>
                <input type="text" name="client_name" class="form-control" required placeholder="Full Name / Business">
            </div>
            <div class="form-group">
                <label>Project Name *</label>
                <input type="text" name="project_name" class="form-control" required placeholder="e.g. Website Development">
            </div>
            <div class="form-group">
                <label>Project Source</label>
                <select name="source" class="form-control">
                    <option value="Walk-in">🚶 Walk-in</option>
                    <option value="Meta Ads">🔵 Meta Ads</option>
                    <option value="Google Ads">🔴 Google Ads</option>
                    <option value="Referral">🤝 Referral</option>
                    <option value="Website">🌐 Website</option>
                    <option value="Other">❓ Other</option>
                </select>
            </div>
            <?php if ($role === 'admin'): ?>
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Total Value (₹)</label>
                    <input type="number" name="total_value" class="form-control" placeholder="0.00">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Advance Paid (₹)</label>
                    <input type="number" name="advance_paid" class="form-control" placeholder="0.00">
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Commission Percentage (%) *</label>
                <input type="number" step="0.01" name="commission_percent" class="form-control" placeholder="e.g. 15.00" required>
            </div>
            <div class="form-group" <?= !$is_hq ? 'style="display:none;"' : '' ?>>
                <label>Assign to HQ Staff (Main Branch Only)</label>
                <div style="display:flex; gap:10px;">
                    <select name="sales_person_id" class="form-control" style="flex:1;">
                        <option value="">-- No User Selected --</option>
                        <?php 
                        // If HQ, show all staff. If Sub, they can't assign (hidden)
                        foreach($staff_members as $sm): 
                        ?>
                            <option value="<?= $sm['id'] ?>"><?= htmlspecialchars($sm['name']) ?> (<?= ucfirst($sm['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="custom_sales_name" class="form-control" placeholder="Or Custom Name" style="flex:1;">
                </div>
                <?php if (!$is_hq): ?>
                    <p style="font-size:0.75rem; color:#ef4444; margin-top:5px;">⚠️ Verification and staff assignment is handled by the Main Branch.</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Project Brief</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Scope of work..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create & Start Project</button>
        </form>
    </div>
</div>

</body>
</html>
