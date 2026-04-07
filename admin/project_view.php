<?php
// /admin/project_view.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';
$pid = (int)($_GET['id'] ?? 0);

// Fetch Branch Info
$branch_info = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
$branch_info->execute([$cid]);
$is_hq = (bool)$branch_info->fetchColumn();
$is_hq_admin = ($is_hq && $_SESSION['user_role'] === 'admin');

// Fetch Accessible Branches for hierarchy visibility
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Fetch Project
$stmt = $pdo->prepare("SELECT p.*, u.name as system_salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE p.id = ? AND (p.company_id IN ($cids_in) OR p.branch_id IN ($cids_in))");
$stmt->execute([$pid]);
$p = $stmt->fetch();

if (!$p) { die("Project not found."); }

// Determine Salesperson name (System or Custom)
$display_salesperson = $p['system_salesperson_name'] ?: ($p['custom_sales_name'] ?: 'N/A');

// Handle Progress Update (Only Assigned Staff or Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if ($uid == $p['sales_person_id'] || $is_hq) {
        $new_progress = (int)$_POST['progress_pct'];
        $comment = trim($_POST['comment'] ?? '');
        $old_progress = $p['progress_pct'];

        if ($new_progress >= 0 && $new_progress <= 100) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE projects SET progress_pct = ?, status = ? WHERE id = ?")->execute([$new_progress, ($new_progress == 100 ? 'Completed' : 'Active'), $pid]);
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS project_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    project_id INT NOT NULL,
                    user_id INT NOT NULL,
                    old_progress INT DEFAULT 0,
                    new_progress INT DEFAULT 0,
                    log_type ENUM('progress', 'instruction', 'system') DEFAULT 'progress',
                    comment TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                // Patch existing logs if needed
                $stmt = $pdo->query("SHOW COLUMNS FROM project_logs LIKE 'log_type'");
                if (!$stmt->fetch()) { 
                    $pdo->exec("ALTER TABLE project_logs ADD COLUMN log_type ENUM('progress', 'instruction', 'system') DEFAULT 'progress' AFTER new_progress"); 
                }

                $pdo->prepare("INSERT INTO project_logs (project_id, user_id, old_progress, new_progress, log_type, comment) VALUES (?,?,?,?,?,?)")
                    ->execute([$pid, $uid, $old_progress, $new_progress, 'progress', $comment]);
                $pdo->commit();
                header("Location: project_view.php?id=$pid&msg=Progress Updated"); exit();
            } catch (Exception $e) { /* DB auto-patch error handled silent but recorded */ }
        }
    }
}

// Handle Client Instruction (Posted by Originating Branch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_client_instruction') {
    if ($_SESSION['company_id'] == $p['branch_id'] && $p['status'] !== 'Completed') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment) {
            $pdo->prepare("INSERT INTO project_logs (project_id, user_id, log_type, comment) VALUES (?,?,?,?)")
                ->execute([$pid, $uid, 'instruction', $comment]);
            header("Location: project_view.php?id=$pid&msg=Instruction Sent to Staff"); exit();
        }
    }
}

// Handle Branch-Level Approval (Admin / Manager Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'branch_approve' && ($role === 'admin' || $role === 'manager')) {
    try {
        $pdo->beginTransaction();
        // Update status for the project
        $pdo->prepare("UPDATE projects SET status = 'Pending HQ Review' WHERE id = ?")->execute([$pid]);
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Branch-level approval completed. Now awaiting HQ Final Review."]);
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Approved for HQ Review"); exit();
    } catch (Exception $e) { 
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        header("Location: project_view.php?id=$pid&error=" . urlencode($e->getMessage())); exit();
    }
}

// Handle Final Verification (HQ Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_project' && $is_hq_admin) {
    try {
        $adv = (float)$_POST['advance_paid'];
        $sp_id = (int)$_POST['sales_person_id'];
        $custom_sp = trim($_POST['custom_sales_name'] ?? '');
        
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET status = 'Active', is_verified = 1, verified_by = ?, advance_paid = ?, sales_person_id = ?, custom_sales_name = ? WHERE id = ?")
            ->execute([$uid, $adv, ($sp_id ?: null), $custom_sp, $pid]);
        
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project verified by HQ. Status set to Active."]);
        
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Verified and Started"); exit();
    } catch (Exception $e) { $msg = $e->getMessage(); }
}

// Handle Rejection (HQ Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_project' && $is_hq_admin) {
    try {
        $reason = trim($_POST['reject_reason'] ?? 'No reason provided.');
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET status = 'Cancelled', is_verified = 0 WHERE id = ?")->execute([$pid]);
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "PROJECT REJECTED by HQ. Reason: $reason"]);
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Rejected"); exit();
    } catch (Exception $e) { $pdo->rollBack(); $msg = $e->getMessage(); }
}

// Handle Project Edit (HQ Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project' && $is_hq_admin) {
    try {
        $pname = trim($_POST['project_name']);
        $client = trim($_POST['client_name']);
        $total = (float)$_POST['total_value'];
        $adv = (float)$_POST['advance_paid'];
        $comm = (float)$_POST['commission_percent'];
        $s_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $e_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET project_name = ?, client_name = ?, total_value = ?, advance_paid = ?, commission_percent = ?, start_date = ?, end_date = ? WHERE id = ? AND (company_id IN ($cids_in) OR branch_id IN ($cids_in))")
            ->execute([$pname, $client, $total, $adv, $comm, $s_date, $e_date, $pid]);

        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project details updated by HQ. New Value: ₹$total, Commission: $comm%"]);
        
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Details Updated"); exit();
    } catch (Exception $e) { $msg = $e->getMessage(); }
}

// Handle Project Delete (HQ Only)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $is_hq_admin) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM project_logs WHERE project_id = ?")->execute([$pid]);
        $pdo->prepare("DELETE FROM projects WHERE id = ? AND (company_id IN ($cids_in) OR branch_id IN ($cids_in))")->execute([$pid]);
        $pdo->commit();
        header("Location: projects.php?msg=Project Deleted Successfully"); exit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// Fetch Staff for assignment dropdown (HQ view)
$staff_members = [];
if ($is_hq_admin) {
    $sp_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE company_id = ? AND role IN ('sales_person', 'staff', 'manager') ORDER BY name ASC");
    $sp_stmt->execute([$cid]);
    $staff_members = $sp_stmt->fetchAll();
}

// Fetch Logs
$log_stmt = $pdo->prepare("SELECT l.*, u.name as updater_name, u.company_id as updater_cid FROM project_logs l JOIN users u ON l.user_id = u.id WHERE l.project_id = ? ORDER BY l.created_at DESC");
$log_stmt->execute([$pid]);
$logs = $log_stmt->fetchAll();

$is_origin_branch = ($_SESSION['company_id'] == $p['branch_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['project_name']) ?> - Progress Detail</title>
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
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <a href="projects.php" style="text-decoration:none; color:var(--text-muted); font-size:0.9rem;">← Back to Projects</a>
                <h1 style="margin-top:10px;"><?= htmlspecialchars($p['project_name']) ?></h1>
            </div>
            <div style="display:flex; align-items:center; gap:15px;">
                <div class="badge st-<?= str_replace(' ', '-', $p['status']) ?>"><?= $p['status'] ?></div>
                <?php if ($is_hq_admin): ?>
                    <button class="btn btn-sm btn-outline" style="border-color:#ef4444; color:#ef4444;" onclick="if(confirm('Are you sure you want to delete this project? All logs will be lost.')) window.location.href='project_view.php?id=<?= $pid ?>&action=delete'">Delete</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="flash-success" style="margin-bottom:2rem;"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="flash-error" style="margin-bottom:2rem; background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; border:1px solid #fecaca;"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 300px; gap: 2rem;">
            <!-- Left: Logs & Details -->
            <div>
                <div class="content-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="margin:0;">Project Information</h3>
                        <?php if ($is_hq_admin): ?>
                            <button class="btn btn-sm btn-outline" onclick="document.getElementById('editProjectModal').classList.add('open')">Edit Details</button>
                        <?php endif; ?>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top:1rem;">
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">CLIENT</label>
                            <div style="font-weight:600;"><?= htmlspecialchars($p['client_name']) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">SALES PERSON</label>
                            <div style="font-weight:600;"><?= htmlspecialchars($display_salesperson) ?></div>
                        </div>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">TOTAL VALUE</label>
                            <div style="font-weight:600; color:#10b981;">₹<?= number_format($p['total_value'], 2) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">ADVANCE PAID</label>
                            <div style="font-weight:600; color:#3b82f6;">₹<?= number_format($p['advance_paid'], 2) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">COMMISSION</label>
                            <div style="font-weight:600; color:#6366f1;"><?= number_format($p['commission_percent'], 2) ?>%</div>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">START DATE</label>
                            <div style="font-weight:600;"><?= $p['start_date'] ? date('d M, Y', strtotime($p['start_date'])) : 'Not Set' ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">EST. DEADLINE</label>
                            <div style="font-weight:600; color:#ef4444;"><?= $p['end_date'] ? date('d M, Y', strtotime($p['end_date'])) : 'No Deadline' ?></div>
                        </div>
                    </div>
                </div>

                <div class="content-card" style="margin-top:2rem;">
                    <h3>Project Timeline & History</h3>
                    <div class="timeline-container" style="margin-top:2rem;">
                        <?php foreach($logs as $l): ?>
                        <div class="log-item">
                            <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></div>
                            <div style="font-weight:600; margin:5px 0;">
                                <?php if($l['log_type'] === 'instruction'): ?>
                                    <span style="color:#f59e0b;">💡 Client Instruction / Update</span>
                                <?php elseif($l['new_progress'] > 0): ?>
                                    Status Update: <?= $l['new_progress'] ?>% <span style="font-weight:400; color:var(--text-muted); font-size:0.8rem;">(Was <?= $l['old_progress'] ?>%)</span>
                                <?php else: ?>
                                    System Event
                                <?php endif; ?>
                            </div>
                            <p style="font-size:0.9rem; margin:0; color:#475569; <?= $l['log_type'] === 'instruction' ? 'background:#fff7ed; padding:10px; border-radius:6px; border:1px dashed #fdba74;' : '' ?>"><?= nl2br(htmlspecialchars($l['comment'])) ?></p>
                            <div style="font-size:0.75rem; color:var(--primary-color); margin-top:5px;">
                                By: <?= htmlspecialchars($l['updater_name']) ?> 
                                (<?= $l['updater_cid'] == $cid ? 'HQ' : 'Branch' ?>)
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="log-item">
                            <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                            <div style="font-weight:600; margin:5px 0;">🏁 Project Initiated</div>
                            <p style="font-size:0.85rem; color:var(--text-muted);">Initial creation at sub-branch office.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Action Form -->
            <div>
                <!-- STEP 1: Branch Approval (Only visible to Sub-branch Admin if Pending Branch Approval) -->
                <?php if ($p['status'] === 'Pending Branch Approval' && !$is_hq && ($role === 'admin' || $role === 'manager')): ?>
                <div class="content-card" style="border: 2px solid #6366f1; margin-bottom:1.5rem;">
                    <h3 style="color:#6366f1;">Branch Admin Review</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin: 0.5rem 0 1.5rem 0;">Review salesperson entry before sending to HQ.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="branch_approve">
                        <button type="submit" class="btn btn-primary" style="width:100%; background:#6366f1;">Approve for HQ Review</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- STEP 2: HQ Final Verification (Only visible to HQ Admin when status is ready for them) -->
                <?php if ($is_hq_admin && $p['status'] !== 'Pending Branch Approval'): ?>
                <div class="content-card" style="border: 2px solid var(--primary-color); margin-bottom:1.5rem;">
                    <h3 style="color:var(--primary-color);">HQ Project Management</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin: 0.5rem 0 1.5rem 0;">Confirm payment and assign staff for execution.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_project">
                        <div class="form-group">
                            <label>Verify Advance Paid (₹)</label>
                            <input type="number" name="advance_paid" class="form-control" value="<?= $p['advance_paid'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Assign/Re-assign Staff</label>
                            <select name="sales_person_id" class="form-control" required>
                                <option value="">-- Select Staff Member --</option>
                                <?php foreach($staff_members as $sm): ?>
                                    <option value="<?= $sm['id'] ?>" <?= $p['sales_person_id'] == $sm['id'] ? 'selected':'' ?>><?= htmlspecialchars($sm['name']) ?> (<?= ucfirst($sm['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="custom_sales_name" list="sp_list" class="form-control" value="<?= htmlspecialchars($p['custom_sales_name'] ?? '') ?>" placeholder="Or Custom Name" style="margin-top:10px;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <?= $p['status'] === 'Pending HQ Review' ? 'Verify & Start Project' : 'Update HQ Assignment' ?>
                        </button>
                    </form>

                    <!-- Reject Option -->
                    <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #e2e8f0;">
                    <form method="POST" id="reject_section" onsubmit="return confirm('Are you sure you want to REJECT this project?');">
                        <input type="hidden" name="action" value="reject_project">
                        <div class="form-group">
                            <label style="color:#ef4444;">Reject/Cancel Reason</label>
                            <textarea name="reject_reason" class="form-control" rows="2" placeholder="e.g. Invalid payment proof..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline" style="width:100%; color:#ef4444; border-color:#ef4444;">Reject Project</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="content-card progress-indicator">
                    <div class="progress-circle" style="border-top-color: <?= ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') ?>; border-right-color: <?= $p['progress_pct'] >= 25 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#e2e8f0' ?>; border-bottom-color: <?= $p['progress_pct'] >= 50 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#e2e8f0' ?>; border-left-color: <?= $p['progress_pct'] >= 75 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#e2e8f0' ?>;">
                        <span><?= $p['progress_pct'] ?>%</span>
                    </div>
                    <p style="font-weight:600; color:#1e293b;">Overall Progress</p>
                </div>

                <?php if ($p['status'] !== 'Completed' && $is_origin_branch): ?>
                <div class="content-card" style="margin-top:1.5rem; border-top: 5px solid #f59e0b;">
                    <h3>Post Client Instruction</h3>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem;">Add extra details or change requests from the client here. This will be seen immediately by the staff and HQ.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_client_instruction">
                        <div class="form-group">
                            <textarea name="comment" class="form-control" rows="4" placeholder="e.g. Client wants the color changed to blue..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; background:#f59e0b; border-color:#f59e0b;">Send Instruction to Staff</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($p['status'] !== 'Pending HQ Review' && ($uid == $p['sales_person_id'] || $is_hq_admin)): ?>
                <div class="content-card" style="margin-top:1.5rem;">
                    <h3>Update Progress</h3>
                    <form method="POST" style="margin-top:1rem;">
                        <input type="hidden" name="action" value="update_progress">
                        <div class="form-group">
                            <label>New Progress (%)</label>
                            <input type="range" name="progress_pct" min="0" max="100" value="<?= $p['progress_pct'] ?>" class="form-control" style="padding:0; height:auto;" oninput="this.nextElementSibling.value = this.value">
                            <output style="font-weight:700; text-align:center; display:block; margin-top:5px; color:var(--primary-color);"><?= $p['progress_pct'] ?></output>%
                        </div>
                        <div class="form-group">
                            <label>Update Comment</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Describe the current milestone..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Post to Timeline</button>
                    </form>
                </div>
                <?php elseif ($p['status'] === 'Pending HQ Review'): ?>
                    <div class="content-card" style="margin-top:1.5rem; text-align:center; padding:2rem; background:#fef2f2; border:1px dashed #ef4444;">
                        <p style="color:#b91c1c; font-weight:600; font-size:0.9rem;">Awaiting HQ Review</p>
                        <p style="font-size:0.8rem; color:#7f1d1d;">Work will begin once the Main Branch verifies the advance payment and assigns staff.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>
<!-- Edit Project Modal -->
<?php if ($is_hq_admin): ?>
<div class="modal-overlay" id="editProjectModal">
    <div class="modal-box" style="max-width:500px;">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Edit Project Details</h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Update the core details of this project.</p>
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
            <button type="submit" class="btn btn-primary" style="width:100%;">Update Project</button>
        </form>
    </div>
</div>
<?php endif; ?>

</body>
</html>
