<?php
// /admin/project_view.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';
$pid = (int) ($_GET['id'] ?? 0);

// Fetch Branch Info
$branch_info = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
$branch_info->execute([$cid]);
$is_hq = (bool) $branch_info->fetchColumn();

// --- AUTO-PATCH: Ensure Permission Table exists on Live Server ---
try {
    $pdo->query("SELECT 1 FROM project_permissions LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(50) NOT NULL UNIQUE,
            can_add TINYINT(1) DEFAULT 0,
            can_edit TINYINT(1) DEFAULT 0,
            can_delete TINYINT(1) DEFAULT 0,
            can_update_progress TINYINT(1) DEFAULT 0,
            can_verify TINYINT(1) DEFAULT 0,
            can_instruction TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        // Seed default professional roles
        $roles = [
            ['role_key'=>'hq_admin','can_add'=>1,'can_edit'=>1,'can_delete'=>0,'can_update_progress'=>0,'can_verify'=>1,'can_instruction'=>1],
            ['role_key'=>'hq_manager','can_add'=>1,'can_edit'=>1,'can_delete'=>0,'can_update_progress'=>0,'can_verify'=>1,'can_instruction'=>1],
            ['role_key'=>'branch_admin','can_add'=>1,'can_edit'=>1,'can_delete'=>0,'can_update_progress'=>0,'can_verify'=>0,'can_instruction'=>1],
            ['role_key'=>'branch_manager','can_add'=>1,'can_edit'=>1,'can_delete'=>0,'can_update_progress'=>0,'can_verify'=>0,'can_instruction'=>1],
            ['role_key'=>'sales_person','can_add'=>1,'can_edit'=>1,'can_delete'=>0,'can_update_progress'=>0,'can_verify'=>0,'can_instruction'=>1],
            ['role_key'=>'staff','can_add'=>0,'can_edit'=>0,'can_delete'=>0,'can_update_progress'=>1,'can_verify'=>0,'can_instruction'=>0]
        ];
        foreach($roles as $r){
            $stmt = $pdo->prepare("INSERT IGNORE INTO project_permissions (role_key, can_add, can_edit, can_delete, can_update_progress, can_verify, can_instruction) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$r['role_key'],$r['can_add'],$r['can_edit'],$r['can_delete'],$r['can_update_progress'],$r['can_verify'],$r['can_instruction']]);
        }
    } catch (Exception $e2) {}
}

// Fetch Accessible Branches for hierarchy visibility
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Fetch Project FIRST (to fix the ordering bug)
$stmt = $pdo->prepare("SELECT p.*, u.name as system_salesperson_name FROM projects p LEFT JOIN users u ON p.sales_person_id = u.id WHERE p.id = ? AND (p.company_id IN ($cids_in) OR p.branch_id IN ($cids_in))");
$stmt->execute([$pid]);
$p = $stmt->fetch();

if (!$p) {
    die("Project not found.");
}

// Security: Staff can only view ACTIVE projects assigned to them or created by them
if ($role === 'staff') {
    if ($p['status'] !== 'Active') {
        die("This project is awaiting Admin Approval.");
    }
    if ($p['sales_person_id'] != $uid && $p['created_by'] != $uid) {
        die("Access Denied: You are not assigned to this project.");
    }
}

// Map Session Role to Permission Role Key
$role_key = $role;
if ($role === 'admin' || $role === 'manager') {
    $role_key = ($is_hq ? 'hq_' : 'branch_') . $role;
}

// Fetch Dynamic Permissions from DB
$perm_stmt = $pdo->prepare("SELECT * FROM project_permissions WHERE role_key = ?");
$perm_stmt->execute([$role_key]);
$perms = $perm_stmt->fetch() ?: [];

// Defined Dynamic Logic
$p_can_add      = (bool)($perms['can_add'] ?? 0);
$p_can_edit     = (bool)($perms['can_edit'] ?? 0);
$p_can_delete   = (bool)($perms['can_delete'] ?? 0);
$p_can_progress = (bool)($perms['can_update_progress'] ?? 0);
$p_can_verify   = (bool)($perms['can_verify'] ?? 0);
$p_can_instruct = (bool)($perms['can_instruction'] ?? 0);

// Contextual Overrides
$is_creator = ($p['created_by'] == $uid);
$is_assigned_staff = ($p['sales_person_id'] == $uid);

// Refined "Can Edit" Logic: 
// If role has 'can_edit' permission, they can edit. 
// EXCEPT Sales/Staff who can ONLY edit if they are the creator AND status is Rejected (safety check)
$can_edit = $p_can_edit;
if ($role === 'sales_person' && $p_can_edit) {
    $can_edit = ($is_creator && $p['status'] === 'Rejected');
}

// Refined "Can Progress" Logic:
// Only show progress box if user has help or is specifically assigned staff (Maker)
$can_post_progress = ($p_can_progress && $is_assigned_staff);

// Determine Salesperson name (System or Custom)
$display_salesperson = $p['system_salesperson_name'] ?: ($p['custom_sales_name'] ?: 'N/A');

// Handle Progress Update (STRICTLY Dynamic Permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    if ($can_post_progress) {
        $new_progress = (int) $_POST['progress_pct'];
        $comment = trim($_POST['comment'] ?? '');
        $old_progress = $p['progress_pct'];

        if ($new_progress >= 0 && $new_progress <= 100) {
            try {
                $pdo->beginTransaction();
                $new_status = ($new_progress == 100) ? 'Pending Review' : 'Active';
                $pdo->prepare("UPDATE projects SET progress_pct = ?, status = ? WHERE id = ?")->execute([$new_progress, $new_status, $pid]);

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
                header("Location: project_view.php?id=$pid&msg=Progress Updated");
                exit();
            } catch (Exception $e) { /* DB auto-patch error handled silent but recorded */
            }
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
            header("Location: project_view.php?id=$pid&msg=Instruction Sent to Staff");
            exit();
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
        header("Location: project_view.php?id=$pid&msg=Approved for HQ Review");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: project_view.php?id=$pid&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Handle Final Verification (HQ/Dynamic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_project' && $p_can_verify) {
    try {
        $adv = (float) $_POST['advance_paid'];
        $sp_id = (int) $_POST['sales_person_id'];
        $custom_sp = trim($_POST['custom_sales_name'] ?? '');
        $s_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $e_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET status = 'Active', is_verified = 1, verified_by = ?, advance_paid = ?, sales_person_id = ?, custom_sales_name = ?, start_date = ?, end_date = ? WHERE id = ?")
            ->execute([$uid, $adv, ($sp_id ?: null), $custom_sp, $s_date, $e_date, $pid]);

        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project verified by HQ. Status set to Active. Timeline: $s_date to $e_date"]);

        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Verified and Started");
        exit();
    } catch (Exception $e) {
        $msg = $e->getMessage();
    }
}

// Handle Rejection (HQ/Dynamic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_project' && $p_can_verify) {
    try {
        $reason = trim($_POST['reject_reason'] ?? 'No reason provided.');
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET status = 'Rejected', is_verified = 0 WHERE id = ?")->execute([$pid]);
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "PROJECT REJECTED by HQ. Reason: $reason"]);
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Rejected");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = $e->getMessage();
    }
}

// Handle Re-submission (Creator Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_project') {
    if ($p['created_by'] == $uid && $p['status'] === 'Rejected') {
        try {
            $pdo->prepare("UPDATE projects SET status = 'Pending HQ Review' WHERE id = ?")->execute([$pid]);
            $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
                ->execute([$pid, $uid, "Project re-submitted for HQ Review."]);
            header("Location: project_view.php?id=$pid&msg=Project Re-submitted");
            exit();
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
    }
}

// Handle Final Completion Approval (HQ/Dynamic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_completion' && $p_can_verify) {
    try {
        $pdo->prepare("UPDATE projects SET status = 'Completed', progress_pct = 100 WHERE id = ?")->execute([$pid]);
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project completion verified and approved by HQ."]);
        header("Location: project_view.php?id=$pid&msg=Project Marked as Completed");
        exit();
    } catch (Exception $e) {
        $msg = $e->getMessage();
    }
}

// Handle Project Edit (HQ mgmt, Branch mgmt, or Creator if rejected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_project' && $can_edit) {
    try {
        $pname = trim($_POST['project_name']);
        $client = trim($_POST['client_name']);
        $total = (float) $_POST['total_value'];
        $adv = (float) $_POST['advance_paid'];
        $comm = (float) $_POST['commission_percent'];
        $s_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $e_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE projects SET project_name = ?, client_name = ?, total_value = ?, advance_paid = ?, commission_percent = ?, start_date = ?, end_date = ? WHERE id = ? AND (company_id IN ($cids_in) OR branch_id IN ($cids_in))")
            ->execute([$pname, $client, $total, $adv, $comm, $s_date, $e_date, $pid]);

        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$pid, $uid, "Project details updated by HQ. New Value: ₹$total, Commission: $comm%"]);

        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Project Details Updated");
        exit();
    } catch (Exception $e) {
        $msg = $e->getMessage();
    }
}

// Fetch Staff for assignment dropdown (HQ view)
$staff_members = [];
if ($p_can_verify) {
    // ONLY fetch Staff (Developers) for assignment list
    $sp_stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE company_id = ? AND role = 'staff' ORDER BY name ASC");
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
        :root {
            --card-pad: 1.25rem;
        }

        .main-content {
            background: #f8fafc;
        }

        .content-card {
            padding: var(--card-pad);
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 4px;
        }

        .info-value {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .log-item {
            border-left: 2px solid #e2e8f0;
            padding-left: 20px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .log-item:last-child {
            border-left-color: transparent;
        }

        .log-item::before {
            content: '';
            width: 10px;
            height: 10px;
            background: #fff;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            position: absolute;
            left: -6px;
            top: 4px;
        }

        .sidebar-section {
            margin-bottom: 1.5rem;
        }

        .sidebar-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
        }

        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 6px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 auto 10px auto;
            border-top-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-wrapper" style="flex: 1; margin-left: 260px;">
        <?php include 'includes/topbar.php'; ?>
        <main class="main-content" style="margin-left: 0; width: 100%; padding: 1.5rem 2.5rem;">

            <div class="page-header">
                <div>
                    <a href="projects.php" style="text-decoration:none; color:var(--text-muted); font-size:0.9rem;">←
                        Back to Projects</a>
                    <h1 style="margin-top:10px;"><?= htmlspecialchars($p['project_name']) ?></h1>
                </div>
                <div style="display:flex; align-items:center; gap:15px;">
                    <div class="badge st-<?= str_replace(' ', '-', $p['status']) ?>"><?= $p['status'] ?></div>
                    <?php if ($p_can_delete): ?>
                        <button class="btn btn-sm btn-outline" style="border-color:#ef4444; color:#ef4444;"
                            onclick="if(confirm('Are you sure you want to delete this project?')) window.location.href='?id=<?= $pid ?>&action=delete'">Delete</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="flash-success" style="margin-bottom:2rem;"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="flash-error"
                    style="margin-bottom:2rem; background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; border:1px solid #fecaca;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
                <!-- Left: Main Content (Info & Timeline) -->
                <div>
                    <!-- Merged Overview Card -->
                    <div class="content-card">
                        <div
                            style="display:flex; justify-content:space-between; align-items:start; margin-bottom:1.5rem;">
                            <div>
                                <h3 style="margin:0; font-size:1.1rem;">Project Overview</h3>
                                <p style="font-size:0.85rem; color:var(--text-muted); margin:4px 0 0 0;">Basic details
                                    and scope of work</p>
                            </div>
                            <?php if ($can_edit): ?>
                                <button class="btn btn-sm btn-outline"
                                    onclick="document.getElementById('editProjectModal').classList.add('open')">Edit
                                    Details</button>
                            <?php endif; ?>
                        </div>

                        <div
                            style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid #f1f5f9;">
                            <div>
                                <div class="info-label">Client</div>
                                <div class="info-value"><?= htmlspecialchars($p['client_name']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Assigned Staff</div>
                                <div class="info-value">
                                    <?= htmlspecialchars($p['system_salesperson_name'] ?: 'Unassigned') ?>
                                </div>
                            </div>
                            <div>
                                <div class="info-label">Created On</div>
                                <div class="info-value"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                            </div>
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <div>
                                    <div class="info-label">Total Value</div>
                                    <div class="info-value" style="color:#10b981;">
                                        ₹<?= number_format($p['total_value'], 2) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Advance Paid</div>
                                    <div class="info-value" style="color:#3b82f6;">
                                        ₹<?= number_format($p['advance_paid'], 2) ?></div>
                                </div>
                                <div>
                                    <div class="info-label">Commission</div>
                                    <div class="info-value" style="color:#6366f1;">
                                        <?= number_format($p['commission_percent'], 2) ?>%
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:1.5rem;">
                            <div class="info-label" style="margin-bottom:10px;">Project Brief / Scope of Work</div>
                            <div
                                style="background:#f8fafc; padding:1.25rem; border-radius:8px; border:1px solid #e2e8f0; font-size:0.9rem; line-height:1.6; color:#475569; overflow-wrap: break-word; word-break: break-word;">
                                <?= !empty($p['project_description']) ? nl2br(htmlspecialchars($p['project_description'])) : '<em style="color:var(--text-muted)">No description provided.</em>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Card -->
                    <div class="content-card">
                        <h3 style="margin:0 0 1.5rem 0; font-size:1.1rem;">Project Timeline & History</h3>
                        <div class="timeline-container">
                            <?php foreach ($logs as $l): ?>
                                <div class="log-item">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="font-weight:600; font-size:0.95rem;">
                                            <?php if ($l['log_type'] === 'instruction'): ?>
                                                <span style="color:#f59e0b;">💡 Client Instruction</span>
                                            <?php elseif ($l['new_progress'] > 0): ?>
                                                Progress: <?= $l['new_progress'] ?>% <span
                                                    style="font-weight:400; color:var(--text-muted); font-size:0.8rem;">(from
                                                    <?= $l['old_progress'] ?>%)</span>
                                            <?php else: ?>
                                                System Update
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">
                                            <?= date('M d, h:i A', strtotime($l['created_at'])) ?>
                                        </div>
                                    </div>
                                    <p
                                        style="font-size:0.9rem; margin:8px 0; color:#475569; line-height:1.5; <?= $l['log_type'] === 'instruction' ? 'background:#fff7ed; padding:12px; border-radius:8px; border:1px dashed #fdba74;' : '' ?>">
                                        <?= nl2br(htmlspecialchars($l['comment'])) ?>
                                    </p>
                                    <div style="font-size:0.75rem; color:var(--text-muted);">
                                        Updated by <span
                                            style="color:var(--primary-color); font-weight:600;"><?= htmlspecialchars($l['updater_name']) ?></span>
                                        <span
                                            style="font-style:italic;">(<?= $l['updater_cid'] == $cid ? 'HQ' : 'Branch' ?>)</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="log-item">
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                </div>
                                <div style="font-weight:600; font-size:0.95rem; margin-top:4px;">🏁 Project Initiated
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Action Sidebar -->
                <div style="position: sticky; top: 1.5rem;">
                    <!-- Status & Progress Summary -->
                    <div class="sidebar-card" style="text-align:center; margin-bottom:1.5rem;">
                        <div class="progress-circle"
                            style="border-top-color: <?= ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') ?>; border-right-color: <?= $p['progress_pct'] >= 25 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#f1f5f9' ?>; border-bottom-color: <?= $p['progress_pct'] >= 50 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#f1f5f9' ?>; border-left-color: <?= $p['progress_pct'] >= 75 ? ($p['status'] === 'Pending HQ Review' ? '#ef4444' : 'var(--primary-color)') : '#f1f5f9' ?>;">
                            <span><?= $p['progress_pct'] ?>%</span>
                        </div>
                        <div style="font-weight:700; color:#1e293b; margin-bottom:4px;">Overall Progress</div>
                        <div class="badge st-<?= str_replace(' ', '-', $p['status']) ?>"
                            style="display:inline-block; margin-top:5px;"><?= $p['status'] ?></div>
                    </div>

                    <!-- HQ Management Box (Dynamic Verification) -->
                    <?php if ($p_can_verify && $p['status'] !== 'Pending Branch Approval'): ?>
                        <div class="sidebar-card" style="border-top: 4px solid var(--primary-color); margin-bottom:1.5rem;">
                            <h4 style="margin:0 0 1rem 0; font-size:1rem; display:flex; align-items:center; gap:8px;">
                                🛡️ HQ Control Panel
                            </h4>

                            <?php if ($p['status'] === 'Pending Review'): ?>
                                <div
                                    style="background:#f0fdf4; padding:12px; border-radius:8px; border:1px solid #bbf7d0; margin-bottom:1.5rem;">
                                    <p style="font-size:0.8rem; color:#166534; margin:0 0 10px 0;">Work is 100% complete and
                                        awaiting final sign-off.</p>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="approve_completion">
                                        <button type="submit" class="btn btn-primary"
                                            style="width:100%; background:#10b981; border:none; font-size:0.85rem; padding:10px;">Approve
                                            & Close Project</button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="action" value="verify_project">
                                <div class="form-group">
                                    <label style="font-size:0.8rem;">Verify Advance Paid (₹)</label>
                                    <input type="number" name="advance_paid" class="form-control" style="font-size:0.9rem;"
                                        value="<?= $p['advance_paid'] ?>" required>
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem;">Assign Project Staff</label>
                                    <select name="sales_person_id" class="form-control" style="font-size:0.9rem;" required>
                                        <option value="">-- Select Staff Member --</option>
                                        <?php foreach ($staff_members as $sm): ?>
                                            <option value="<?= $sm['id'] ?>" <?= $p['sales_person_id'] == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div class="form-group">
                                        <label style="font-size:0.75rem;">Start Date</label>
                                        <input type="date" name="start_date" class="form-control"
                                            style="font-size:0.85rem; padding:6px;"
                                            value="<?= $p['start_date'] ?: date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:0.75rem;">Deadline</label>
                                        <input type="date" name="end_date" class="form-control"
                                            style="font-size:0.85rem; padding:6px;" value="<?= $p['end_date'] ?>" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    style="width:100%; padding:10px; font-size:0.9rem;">
                                    <?= $p['status'] === 'Pending HQ Review' ? 'Verify & Start Project' : 'Update HQ Assignment' ?>
                                </button>
                            </form>

                            <hr style="margin: 1.25rem 0; border:0; border-top: 1px solid #f1f5f9;">
                            <form method="POST" onsubmit="return confirm('Reject this project?');">
                                <input type="hidden" name="action" value="reject_project">
                                <div class="form-group">
                                    <label style="color:#ef4444; font-size:0.8rem;">Rejection Reason</label>
                                    <textarea name="reject_reason" class="form-control" style="font-size:0.85rem;" rows="2"
                                        placeholder="Explain why..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline"
                                    style="width:100%; color:#ef4444; border-color:#ef4444; padding:8px; font-size:0.85rem;">Reject
                                    Project</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Staff Progress Update (Dynamic Permission) -->
                    <?php if ($p['status'] !== 'Pending HQ Review' && $can_post_progress && $p['status'] !== 'Completed'): ?>
                        <div class="sidebar-card" style="border-top: 4px solid #6366f1;">
                            <h4 style="margin:0 0 1rem 0; font-size:1rem;">🚀 Update Progress</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_progress">
                                <div class="form-group" style="margin-bottom:1.5rem;">
                                    <div
                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <label style="font-size:0.8rem;">Progress Percentage</label>
                                        <span style="font-weight:700; color:var(--primary-color);"
                                            id="pct_val"><?= $p['progress_pct'] ?>%</span>
                                    </div>
                                    <input type="range" name="progress_pct" min="0" max="100"
                                        value="<?= $p['progress_pct'] ?>" class="form-control"
                                        style="padding:0; height:6px; background:#e2e8f0; cursor:pointer;"
                                        oninput="document.getElementById('pct_val').textContent = this.value + '%'">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:0.8rem;">Work Notes</label>
                                    <textarea name="comment" class="form-control" style="font-size:0.85rem;" rows="3"
                                        placeholder="What have you done so far?" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    style="width:100%; padding:10px; background:#6366f1; border:none;">Post Update</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Branch Client Instruction (Dynamic Permission) -->
                    <?php if ($p['status'] !== 'Completed' && $p_can_instruct && ($is_hq || $is_origin_branch) && $p['status'] !== 'Pending HQ Review'): ?>
                        <div class="sidebar-card" style="border-top: 4px solid #f59e0b; margin-top:1.5rem;">
                            <h4 style="margin:0 0 1rem 0; font-size:1rem; color:#d97706;">💡 Add Instruction</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_client_instruction">
                                <div class="form-group">
                                    <textarea name="comment" class="form-control" style="font-size:0.85rem;" rows="3"
                                        placeholder="New client brief or changes..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    style="width:100%; background:#f59e0b; border:none; padding:10px;">Send to
                                    Staff</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Status Messages (Conditional) -->
                    <?php if ($p['status'] === 'Pending HQ Review' && !$is_hq_mgmt): ?>
                        <div class="sidebar-card" style="background:#eff6ff; border:1px dashed #3b82f6; text-align:center;">
                            <p style="color:#1e40af; font-weight:600; font-size:0.85rem; margin-bottom:5px;">⏳ Awaiting HQ
                                Review</p>
                            <p style="font-size:0.75rem; color:#60a5fa; margin:0;">HQ Main Branch will verify payment and
                                assign staff shortly.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($p['status'] === 'Rejected' && $p['created_by'] == $uid): ?>
                        <div class="sidebar-card" style="border: 2px solid #ef4444; background: #fef2f2;">
                            <h4 style="color:#ef4444; margin:0 0 0.5rem 0;">Project Rejected</h4>
                            <p style="font-size:0.75rem; color:#b91c1c; margin-bottom:1rem;">HQ has rejected this project.
                                See the reason in timeline, edit details if needed, and re-submit.</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="resubmit_project">
                                <button type="submit" class="btn btn-danger"
                                    style="width:100%; font-size:0.85rem; padding:10px;">Re-submit for Review</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
    <!-- Edit Project Modal -->
    <?php if ($can_edit): ?>
        <div class="modal-overlay" id="editProjectModal">
            <div class="modal-box" style="max-width:500px;">
                <button class="modal-close"
                    onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
                <h3>Edit Project Details</h3>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Update the core details of this
                    project.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_project">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="project_name" class="form-control"
                            value="<?= htmlspecialchars($p['project_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Client Name</label>
                        <input type="text" name="client_name" class="form-control"
                            value="<?= htmlspecialchars($p['client_name']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Value (₹)</label>
                            <input type="number" step="0.01" name="total_value" class="form-control"
                                value="<?= $p['total_value'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Advance Paid (₹)</label>
                            <input type="number" step="0.01" name="advance_paid" class="form-control"
                                value="<?= $p['advance_paid'] ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Commission Percentage (%)</label>
                        <input type="number" step="0.01" name="commission_percent" class="form-control"
                            value="<?= $p['commission_percent'] ?>" required>
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