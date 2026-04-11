<?php
// /admin/projects.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';

// --- AUTO-PATCH: Ensure Database is Correct ---
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'commission_rate'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    }
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            commission_rate DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch(Exception $e2) {}
}

// Fetch Branch Info
$branch_info = $pdo->prepare("SELECT is_main_branch, parent_id FROM companies WHERE id = ?");
$branch_info->execute([$cid]);
$comp_data = $branch_info->fetch();
$is_hq = (bool)($comp_data['is_main_branch'] ?? false);

// Dynamically Find HQ ID
$hq_check = $pdo->prepare("SELECT id FROM companies WHERE is_main_branch = 1 LIMIT 1");
$hq_check->execute();
$hq_id = $hq_check->fetchColumn() ?: 1;

// Determine Catalog Owner (Sub-branches use HQ catalog)
$catalog_owner_id = $hq_id;

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

    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'start_date'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN start_date DATE NULL AFTER updated_at"); }

    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'end_date'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN end_date DATE NULL AFTER start_date"); }

    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'created_by'");
    if (!$stmt->fetch()) { 
        $pdo->exec("ALTER TABLE projects ADD COLUMN created_by INT NULL AFTER sales_person_id"); 
        $pdo->exec("UPDATE projects SET created_by = sales_person_id WHERE created_by IS NULL");
    }

    // 2. Enum Update (Status)
    $pdo->exec("ALTER TABLE projects MODIFY COLUMN status ENUM('Pending Branch Approval', 'Pending HQ Review', 'Active', 'Rejected', 'Pending Review', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Pending HQ Review'");

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
        status ENUM('Pending Branch Approval', 'Pending HQ Review', 'Active', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Pending Branch Approval',
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

// Handle Direct Entry (Admin / Manager / Sales Person)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    if (in_array($role, ['admin', 'manager', 'sales_person'])) {
        try {
            $client = trim($_POST['client_name'] ?? '');
            $pname = trim($_POST['project_name'] ?? '');
            $val = (float)($_POST['total_value'] ?? 0);
            $adv = (float)($_POST['advance_paid'] ?? 0);
            $desc = trim($_POST['description'] ?? '');
            $source = trim($_POST['source'] ?? 'Walk-in');
            $sp_id = !empty($_POST['sales_person_id']) ? (int)$_POST['sales_person_id'] : ($role === 'sales_person' ? $uid : null);
            $custom_sp = trim($_POST['custom_sales_name'] ?? '');
            $comm_pct = isset($_POST['commission_percent']) && $_POST['commission_percent'] !== '' ? (float)$_POST['commission_percent'] : null;
            $s_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $e_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            // DIRECT TO HQ REVIEW Logic
            $status = ($role === 'admin' || $role === 'manager') && $is_hq ? 'Active' : 'Pending HQ Review';
            
            $verified = ($status === 'Active') ? 1 : 0;

            if ($client && $pname) {
                $stmt = $pdo->prepare("INSERT INTO projects (company_id, branch_id, sales_person_id, created_by, client_name, project_name, source, project_description, total_value, commission_percent, advance_paid, status, is_verified, custom_sales_name, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cid, $cid, $sp_id, $uid, $client, $pname, $source, $desc, $val, $comm_pct, $adv, $status, $verified, $custom_sp, $s_date, $e_date]);

                header("Location: projects.php?msg=Project added successfully. Status: $status&type=success"); 
                exit();
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

if ($role === 'sales_person' || $role === 'staff') {
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE p.sales_person_id = ? OR p.created_by = ? ORDER BY p.created_at DESC");
    $stmt->execute([$uid, $uid]);
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE (p.company_id IN ($cids_in) OR p.branch_id IN ($cids_in)) ORDER BY p.created_at DESC");
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

// Fetch ONLY Developers/Staff for assignment (excluding sales_person and managers)
$sp_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE company_id = ? AND role = 'staff' ORDER BY name ASC");
$sp_stmt->execute([$cid]);
$staff_members = $sp_stmt->fetchAll();

// Fetch Service Catalog from Master HQ
$svc_stmt = $pdo->prepare("SELECT id, name, commission_rate FROM products WHERE company_id = ? ORDER BY name ASC");
$svc_stmt->execute([$catalog_owner_id]);
$catalog = $svc_stmt->fetchAll();
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
        .st-Pending-Branch-Approval { background: rgba(99,102,241,0.1); color: #6366f1; }
        .st-Pending-HQ-Review { background: rgba(99,102,241,0.1); color: #6366f1; }
        .st-Rejected { background: rgba(239,68,68,0.1); color: #ef4444; }
        .st-Pending-Review { background: rgba(245,158,11,0.1); color: #f59e0b; }
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
                <?php if (in_array($role, ['admin', 'manager', 'sales_person'])): ?>
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
                        🛠️ <?= htmlspecialchars($p['salesperson_name'] ?: 'Unassigned') ?>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <?php if ($p['status'] === 'Pending HQ Review' && $is_hq && in_array($role, ['admin', 'manager'])): ?>
                            <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary" style="background:#ef4444; border:none;">Verify & Assign HQ Staff</a>
                            <a href="project_view.php?id=<?= $p['id'] ?>#reject_section" class="btn btn-sm btn-outline" style="color:#ef4444; border-color:#ef4444;">Reject</a>
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
                <label>Select Service *</label>
                <select name="project_name" id="project_service_select" class="form-control" required onchange="updateCommissionRate(this)">
                    <option value="" data-comm="0">-- Select Service --</option>
                    <?php foreach($catalog as $svc): ?>
                        <option value="<?= htmlspecialchars($svc['name']) ?>" data-comm="<?= $svc['commission_rate'] ?>"><?= htmlspecialchars($svc['name']) ?> (<?= $svc['commission_rate'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
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
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Commission Percentage (%)</label>
                    <input type="number" step="0.01" name="commission_percent" id="modal_comm_pct" class="form-control" style="background-color: #f1f5f9; cursor: not-allowed;" readonly placeholder="Fixed by HQ">
                </div>
                <div class="form-group" style="flex:1;">
                    <!-- Spacer for alignment if needed -->
                </div>
            </div>
            <?php if ($role === 'admin' || $role === 'manager'): ?>
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Target Deadline (End Date)</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
            </div>
            <?php endif; ?>
            <?php if ($role !== 'sales_person'): ?>
            <div class="form-group">
                <label>Assign to Project Staff</label>
                <div style="display:flex; gap:10px;">
                    <select name="sales_person_id" class="form-control" style="flex:1;">
                        <option value="">-- No Staff Selected --</option>
                        <?php 
                        foreach($staff_members as $sm): 
                        ?>
                            <option value="<?= $sm['id'] ?>"><?= htmlspecialchars($sm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$is_hq): ?>
                    <p style="font-size:0.75rem; color:#ef4444; margin-top:5px;">⚠️ Verification and staff assignment is handled by the Main Branch.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Project Brief</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Scope of work..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create & Start Project</button>
        </form>
    </div>
</div>

<script>
function updateCommissionRate(select) {
    const selectedOption = select.options[select.selectedIndex];
    const commRate = selectedOption.getAttribute('data-comm');
    document.getElementById('modal_comm_pct').value = commRate;
}
</script>

</body>
</html>
