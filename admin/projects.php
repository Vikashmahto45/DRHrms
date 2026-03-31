<?php
// /admin/projects.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';

// 0. Auto-patch for Projects Table
try {
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
        advance_paid DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('Pending Approval', 'Active', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Pending Approval',
        progress_pct INT DEFAULT 0,
        is_verified TINYINT(1) DEFAULT 0,
        verified_by INT NULL,
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
} catch (Exception $e) { /* Table creation failed, but we continue */ }

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
            $sp_id = !empty($_POST['sales_person_id']) ? (int)$_POST['sales_person_id'] : $uid;

            if ($client && $pname) {
                $stmt = $pdo->prepare("INSERT INTO projects (company_id, sales_person_id, client_name, project_name, project_description, total_value, advance_paid, status, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 1)");
                $stmt->execute([$cid, $sp_id, $client, $pname, $desc, $val, $adv]);
                $msg = "Project created and verified successfully!"; $msgType = "success";
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
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p JOIN users u ON p.sales_person_id = u.id WHERE p.sales_person_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$uid]);
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p JOIN users u ON p.sales_person_id = u.id WHERE p.company_id IN ($cids_in) ORDER BY p.created_at DESC");
    $stmt->execute();
}
$projects = $stmt->fetchAll();

// Fetch Sales Persons for direct entry
$sp_stmt = $pdo->prepare("SELECT id, name FROM users WHERE company_id = ? AND role = 'sales_person'");
$sp_stmt->execute([$cid]);
$sales_persons = $sp_stmt->fetchAll();
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
        .progress-bar-container { background: #e2e8f0; border-radius: 20px; height: 10px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { background: var(--primary-color); height: 100%; transition: width 0.3s; }
        .st-Pending { background: rgba(245,158,11,0.1); color: #f59e0b; }
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
            <?php foreach ($projects as $p): 
                $status_class = $p['status'] === 'Pending Approval' ? 'st-Pending' : ($p['status'] === 'Active' ? 'st-Active' : 'st-Hold');
            ?>
            <div class="project-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1.15rem;"><?= htmlspecialchars($p['project_name']) ?></h3>
                        <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">Client: <strong><?= htmlspecialchars($p['client_name']) ?></strong></div>
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
                        <?php if (!$p['is_verified'] && ($role === 'admin' || $role === 'manager')): ?>
                            <a href="?verify=<?= $p['id'] ?>" class="btn btn-sm btn-outline" style="color:#10b981; border-color:#10b981;">Verify Project</a>
                        <?php endif; ?>
                        <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
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
            <div class="form-group">
                <label>Assign to Sales Person (Optional)</label>
                <select name="sales_person_id" class="form-control">
                    <option value="">-- Assign to self (Admin) --</option>
                    <?php foreach($sales_persons as $sp): ?>
                        <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
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
