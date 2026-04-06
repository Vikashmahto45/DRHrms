<?php
// /superadmin/project_view.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['super_admin']);

$uid = $_SESSION['user_id'] ?? 0;
$pid = (int)($_GET['id'] ?? 0);

// Fetch Project with Branch Name
$stmt = $pdo->prepare("
    SELECT p.*, u.name as system_salesperson_name, c.name as branch_name 
    FROM projects p 
    LEFT JOIN users u ON p.sales_person_id = u.id 
    LEFT JOIN companies c ON p.branch_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pid]);
$p = $stmt->fetch();

if (!$p) { die("Project not found."); }

// Determine Salesperson name
$display_salesperson = $p['system_salesperson_name'] ?: ($p['custom_sales_name'] ?: 'Unassigned');

// Handle Project Edit (Super Admin has full authority)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project') {
    try {
        $pname = trim($_POST['project_name']);
        $client = trim($_POST['client_name']);
        $total = (float)$_POST['total_value'];
        $adv = (float)$_POST['advance_paid'];
        $comm = (float)$_POST['commission_percent'];
        $s_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $e_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET project_name = ?, client_name = ?, total_value = ?, advance_paid = ?, commission_percent = ?, start_date = ?, end_date = ? WHERE id = ?")
            ->execute([$pname, $client, $total, $adv, $comm, $s_date, $e_date, $pid]);

        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project details updated by Super Admin. New Value: ₹$total, Commission: $comm%"]);
        
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Updated Successfully"); exit();
    } catch (Exception $e) { $msg = $e->getMessage(); }
}

// Handle Project Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM project_logs WHERE project_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$pid]);
        $pdo->commit();
        header("Location: projects.php?msg=Project Deleted Successfully&msgType=success"); exit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// Fetch Logs
$log_stmt = $pdo->prepare("SELECT l.*, u.name as updater_name, u.company_id as updater_cid FROM project_logs l JOIN users u ON l.user_id = u.id WHERE l.project_id = ? ORDER BY l.created_at DESC");
$log_stmt->execute([$pid]);
$logs = $log_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['project_name']) ?> - Monitoring Detail</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .log-item { border-left: 2px solid var(--primary-color); padding-left: 20px; margin-bottom: 20px; position: relative; }
        .log-item::before { content: ''; width: 12px; height: 12px; background: #fff; border: 2px solid var(--primary-color); border-radius: 50%; position: absolute; left: -7px; top: 0; }
        .progress-indicator { background: #f1f5f9; padding: 1.5rem; border-radius: 12px; text-align: center; }
        .progress-circle { width: 100px; height: 100px; border-radius: 50%; border: 8px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0 auto 10px auto; border-top-color: var(--primary-color); }
        .timeline-container { position: relative; margin-top: 1rem; }
        .timeline-container::after { content: ''; position: absolute; width: 2px; background: #e2e8f0; top: 0; bottom: 0; left: 6px; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <a href="projects.php" style="text-decoration:none; color:var(--text-muted); font-size:0.9rem;">← Back to All Projects</a>
            <h1 style="margin-top:10px;"><?= htmlspecialchars($p['project_name']) ?></h1>
            <div style="font-size:0.9rem; color:var(--text-muted);">Branch: <strong><?= htmlspecialchars($p['branch_name'] ?: 'Main Branch') ?></strong></div>
        </div>
        <div style="display:flex; align-items:center; gap:15px;">
            <div class="badge st-<?= str_replace(' ', '-', $p['status']) ?>"><?= $p['status'] ?></div>
            <button class="btn btn-sm btn-outline" style="border-color:#ef4444; color:#ef4444;" onclick="if(confirm('Are you sure you want to delete this project? Data will be lost forever.')) window.location.href='project_view.php?id=<?= $pid ?>&action=delete'">Delete</button>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash-success" style="margin-bottom:2rem;"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 300px; gap: 2rem;">
        <!-- Left: Details & History -->
        <div>
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3 style="margin:0;">Project Configuration</h3>
                    <button class="btn btn-sm btn-outline" onclick="document.getElementById('editProjectModal').classList.add('open')">Master Edit</button>
                </div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1.5rem; margin-top:1rem;">
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Client</label>
                        <div style="font-weight:600;"><?= htmlspecialchars($p['client_name']) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Branch Staff</label>
                        <div style="font-weight:600;"><?= htmlspecialchars($display_salesperson) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Source</label>
                        <div style="font-weight:600;"><?= htmlspecialchars($p['source']) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Total Value</label>
                        <div style="font-weight:600; color:#10b981;">₹<?= number_format($p['total_value'], 2) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Advance Paid</label>
                        <div style="font-weight:600; color:#3b82f6;">₹<?= number_format($p['advance_paid'], 2) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Commission Rate</label>
                        <div style="font-weight:600; color:#6366f1;"><?= number_format($p['commission_percent'], 2) ?>%</div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Start Date</label>
                        <div style="font-weight:600;"><?= $p['start_date'] ? date('d M, Y', strtotime($p['start_date'])) : 'Not Set' ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Target Deadline</label>
                        <div style="font-weight:600; color:#ef4444;"><?= $p['end_date'] ? date('d M, Y', strtotime($p['end_date'])) : 'No Deadline' ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Verification Status</label>
                        <div style="font-weight:600;"><?= $p['is_verified'] ? '✅ Verified' : '⏳ Pending HQ Verification' ?></div>
                    </div>
                </div>
            </div>

            <div class="content-card" style="margin-top:2rem;">
                <h3>Execution Timeline & Global Logs</h3>
                <div class="timeline-container" style="margin-top:2rem;">
                    <?php foreach($logs as $l): ?>
                    <div class="log-item">
                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></div>
                        <div style="font-weight:600; margin:5px 0;">
                            <?php if($l['log_type'] === 'instruction'): ?>
                                <span style="color:#f59e0b;">💡 Client Instruction</span>
                            <?php elseif($l['new_progress'] > 0): ?>
                                Progress Update: <?= $l['new_progress'] ?>%
                            <?php else: ?>
                                System / Admin Activity
                            <?php endif; ?>
                        </div>
                        <p style="font-size:0.9rem; margin:0; color:#64748b;"><?= nl2br(htmlspecialchars($l['comment'])) ?></p>
                        <div style="font-size:0.75rem; color:var(--primary-color); margin-top:5px;">
                            Action by: <?= htmlspecialchars($l['updater_name']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="log-item">
                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                        <div style="font-weight:600; margin:5px 0;">🏁 Project Creation</div>
                        <p style="font-size:0.85rem; color:var(--text-muted);">Initiated by branch office.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Status Indicator -->
        <div>
            <div class="content-card progress-indicator">
                <div class="progress-circle" style="border-top-color: var(--primary-color); border-right-color: <?= $p['progress_pct'] >= 25 ? 'var(--primary-color)' : '#e2e8f0' ?>; border-bottom-color: <?= $p['progress_pct'] >= 50 ? 'var(--primary-color)' : '#e2e8f0' ?>; border-left-color: <?= $p['progress_pct'] >= 75 ? 'var(--primary-color)' : '#e2e8f0' ?>;">
                    <span><?= $p['progress_pct'] ?>%</span>
                </div>
                <p style="font-weight:600; color:#1e293b;">Overall Progress</p>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:5px;">Current Execution Level</div>
            </div>

            <div class="content-card" style="margin-top:1.5rem; text-align:center; padding:1.5rem; background:#fff; border:1px solid var(--glass-border);">
                <h4 style="margin:0 0 10px 0; color:var(--text-muted); font-size:0.8rem; text-transform:uppercase;">Verification Audit</h4>
                <div style="font-size:2rem; margin-bottom:5px;"><?= $p['is_verified'] ? '✅' : '⚠️' ?></div>
                <div style="font-size:0.9rem; font-weight:700;"><?= $p['is_verified'] ? 'HQ Verified' : 'Unverified' ?></div>
            </div>
        </div>
    </div>
</main>

<!-- Master Edit Modal -->
<div class="modal-overlay" id="editProjectModal">
    <div class="modal-box" style="max-width:550px;">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Master Project Editor</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Modify core project parameters (Super Admin Overrides).</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_project">
            <div class="form-group">
                <label>Project Name</label>
                <input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($p['project_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Client Name</label>
                <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($p['client_name']) ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total Value (₹)</label>
                    <input type="number" step="0.01" name="total_value" class="form-control" value="<?= $p['total_value'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Advance Paid (₹)</label>
                    <input type="number" step="0.01" name="advance_paid" class="form-control" value="<?= $p['advance_paid'] ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Commission Percentage (%)</label>
                <input type="number" step="0.01" name="commission_percent" class="form-control" value="<?= $p['commission_percent'] ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $p['start_date'] ?>">
                </div>
                <div class="form-group">
                    <label>Target Deadline (End Date)</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $p['end_date'] ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Apply Global Changes</button>
        </form>
    </div>
</div>

</body>
</html>
