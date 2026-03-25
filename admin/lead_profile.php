<?php
// /admin/lead_profile.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'sales_person', 'staff', 'manager']);

$cid = $_SESSION['company_id'];
$lead_id = (int)($_GET['id'] ?? 0);

if (!$lead_id) {
    header("Location: leads.php");
    exit();
}

// 1. Fetch Lead Details
$stmt = $pdo->prepare("
    SELECT l.*, u.name as assignee 
    FROM leads_crm l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.id = ? AND l.company_id = ?
");
$stmt->execute([$lead_id, $cid]);
$lead = $stmt->fetch();

if (!$lead) {
    die("<div style='background:#f1f5f9;color:var(--text-main);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;'>
            <div style='text-align:center;'>
                <h2 style='color:#ef4444'>Lead Not Found</h2>
                <p style='color:var(--text-muted)'>The lead record you are looking for does not exist or has been removed.</p>
                <a href='leads.php' style='color:var(--primary-color);text-decoration:none;'>← Back to Lead CRM</a>
            </div>
         </div>");
}

// 1.5 Role-Based Ownership Check
$role = strtolower($_SESSION['user_role'] ?? '');
$uid  = (int)($_SESSION['user_id'] ?? 0);

if ($role === 'sales_person' || $role === 'staff') {
    if ((int)$lead['assigned_to'] !== $uid) {
        header("Location: leads.php");
        exit();
    }
}

// 2. Fetch Tasks
$tasks_stmt = $pdo->prepare("SELECT * FROM lead_tasks WHERE lead_id = ? AND company_id = ? ORDER BY due_date ASC");
$tasks_stmt->execute([$lead_id, $cid]);
$tasks = $tasks_stmt->fetchAll();

// 3. Fetch History
$history_stmt = $pdo->prepare("
    SELECT h.*, u.name as user_name 
    FROM lead_history h 
    JOIN users u ON h.user_id = u.id 
    WHERE h.lead_id = ? 
    ORDER BY h.created_at DESC
");
$history_stmt->execute([$lead_id]);
$history = $history_stmt->fetchAll();

// 4. Handle Post Actions (Status, Note, Task)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid = $_SESSION['user_id'];

    if ($action === 'add_note') {
        $new_note = trim($_POST['note'] ?? '');
        if ($new_note) {
            $pdo->prepare("UPDATE leads_crm SET note = ? WHERE id = ?")->execute([$new_note, $lead_id]);
            $pdo->prepare("INSERT INTO lead_history (lead_id, user_id, event_type, details) VALUES (?, ?, 'note_added', ?)")
                ->execute([$lead_id, $uid, "Updated internal note/context."]);
            header("Location: lead_profile.php?id=$lead_id");
            exit();
        }
    }

    if ($action === 'change_status') {
        $old_status = $lead['status'];
        $new_status = $_POST['status'];
        
        // Final Security Gate
        $can_edit = ($role === 'admin' || $role === 'manager' || (int)$lead['assigned_to'] === $uid);
        
        if ($can_edit && $old_status !== $new_status) {
            $pdo->prepare("UPDATE leads_crm SET status = ? WHERE id = ? AND company_id = ?")
                ->execute([$new_status, $lead_id, $cid]);
            $pdo->prepare("INSERT INTO lead_history (lead_id, user_id, event_type, details) VALUES (?, ?, 'status_change', ?)")
                ->execute([$lead_id, $uid, "Changed status from $old_status to $new_status (via Profile)"]);
            header("Location: lead_profile.php?id=$lead_id&msg=updated");
            exit();
        }
    }

    if ($action === 'add_task') {
        $desc = trim($_POST['task_desc'] ?? '');
        $due = $_POST['due_date'] ?? date('Y-m-d');
        if ($desc) {
            $pdo->prepare("INSERT INTO lead_tasks (company_id, lead_id, task_desc, due_date) VALUES (?, ?, ?, ?)")
                ->execute([$cid, $lead_id, $desc, $due]);
            $pdo->prepare("INSERT INTO lead_history (lead_id, user_id, event_type, details) VALUES (?, ?, 'task_created', ?)")
                ->execute([$lead_id, $uid, "Created follow-up task: $desc"]);
            header("Location: lead_profile.php?id=$lead_id");
            exit();
        }
    }
}

// Helper for source badges
function getSourceBadge($source) {
    $colors = [
        'Meta Ads' => '#1877F2',
        'Google Ads' => '#4285F4',
        'Referral' => '#10b981',
        'Walk-in' => '#8b5cf6',
        'Website' => '#f59e0b',
        'Social Media' => '#06b6d4',
        'Email' => '#ec4899'
    ];
    $color = $colors[$source] ?? '#6b7280';
    return "<span class='source-tag' style='background:".($color."22")."; color:$color; border:1px solid ".($color."44")."; font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:6px; text-transform:uppercase;'>$source</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lead['client_name']) ?> - Profile | DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434221">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434221">
    <style>
        .profile-container { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: flex-start; }
        .sidebar-info { display: flex; flex-direction: column; gap: 1.5rem; }
        .info-card { padding: 1.5rem; }
        
        .timeline { position: relative; padding-left: 2rem; border-left: 2px solid var(--glass-border); margin-left: 1rem; }
        .timeline-item { position: relative; margin-bottom: 2rem; }
        .timeline-item::before { content: ""; position: absolute; left: -2.45rem; top: 0.25rem; width: 12px; height: 12px; background: var(--primary-color); border: 3px solid #fff; border-radius: 50%; box-shadow: 0 0 10px rgba(99,102,241,0.2); }
        .timeline-item .time { font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .timeline-item .event-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 4px; display: block; color: var(--text-main); }
        .timeline-item .event-details { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid var(--glass-border); }
        
        .task-list-mini { list-style: none; padding: 0; }
        .task-list-mini li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--glass-border); font-size: 0.9rem; }
        .task-list-mini li:last-child { border-bottom: none; }
        .task-list-mini input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .btn-sm-action { padding: 6px 12px; font-size: 0.8rem; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; padding: 2rem 3rem;">
        
        <div class="page-header" style="margin-bottom: 2rem;">
            <div>
                <a href="leads.php" style="color: var(--primary-color); display: flex; align-items: center; gap: 5px; margin-bottom: 0.8rem; font-size: 0.9rem; text-decoration: none;">🔙 Back to CRM</a>
                <div style="display:flex; align-items: center; gap: 1rem;">
                    <h1 style="margin:0;"><?= htmlspecialchars($lead['client_name']) ?></h1>
                    <?php if($lead['status'] === 'Converted'): ?>
                        <span class="badge" style="background:#059669; color:#fff; font-size:0.8rem; padding: 4px 12px;">✅ CONVERTED CLIENT</span>
                    <?php endif; ?>
                </div>
                <p style="color:var(--text-muted); margin-top: 5px;">Lead ID: #<?= $lead_id ?> • Profile overview and activity feed.</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <label style="font-size: 0.85rem; color: var(--text-muted);">Pipeline Status:</label>
                <form method="POST">
                    <input type="hidden" name="action" value="change_status">
                    <select name="status" class="form-control" onchange="this.form.submit()" style="width: 160px; font-weight: 600; background: #f8fafc;">
                        <option value="New" <?= $lead['status']==='New'?'selected':'' ?>>🔵 New</option>
                        <option value="In Progress" <?= $lead['status']==='In Progress'?'selected':'' ?>>🟡 In Progress</option>
                        <option value="Converted" <?= $lead['status']==='Converted'?'selected':'' ?>>🟢 Converted</option>
                        <option value="Lost" <?= $lead['status']==='Lost'?'selected':'' ?>>🔴 Lost</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="profile-container">
            <!-- Left Info Panel -->
            <div class="sidebar-info">
                <div class="glass-card info-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">📋 Basic Details</h3>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 6px; letter-spacing: 1px; font-weight: 700;">PRODUCT INTEREST</label>
                        <span style="font-size: 0.9rem; font-weight: 700; color: var(--primary-color); background: rgba(99,102,241,0.1); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99,102,241,0.2);">
                            📦 <?= htmlspecialchars($lead['product'] ?: 'General Inquiry') ?>
                        </span>
                    </div>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 6px; letter-spacing: 1px; font-weight: 700;">ORIGIN SOURCE</label>
                        <?= getSourceBadge($lead['source']) ?>
                    </div>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 6px; letter-spacing: 1px; font-weight: 700;">CONTACT NUMBER</label>
                        <?php if ($lead['phone']): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lead['phone']) ?>" target="_blank" style="color: #25d366; font-size: 1.1rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px;">
                                💬 <?= htmlspecialchars($lead['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">Not Provided</span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-bottom: 1.2rem;">
                        <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 6px; letter-spacing: 1px; font-weight: 700;">ACCOUNT OWNER</label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width:24px; height:24px; background:var(--primary-color); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:700;">
                                <?= strtoupper(substr($lead['assignee'] ?? 'U', 0, 1)) ?>
                            </div>
                            <span style="font-weight: 600;"><?= htmlspecialchars($lead['assignee'] ?? 'Unassigned') ?></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 0;">
                        <label style="font-size: 0.7rem; color: var(--text-muted); display: block; margin-bottom: 6px; letter-spacing: 1px; font-weight: 700;">REGISTRATION DATE</label>
                        <span style="font-size: 0.9rem; color: var(--text-main);"><?= date('M d, Y', strtotime($lead['created_at'])) ?></span>
                    </div>
                </div>

                <div class="glass-card info-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; margin-bottom: 1.2rem;">📝 Internal Note</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_note">
                        <textarea name="note" class="form-control" rows="4" placeholder="Briefly summarize current status or requirement..." style="margin-bottom: 12px; font-size: 0.85rem; line-height: 1.5; background: #fff;"><?= htmlspecialchars($lead['note'] ?? '') ?></textarea>
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Save Context</button>
                    </form>
                </div>

                <div class="glass-card info-card">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
                        <h3 style="margin:0; font-size: 1.1rem;">📌 Tasks</h3>
                        <span style="font-size: 0.75rem; background: rgba(0,0,0,0.05); padding: 2px 8px; border-radius: 4px;"><?= count($tasks) ?> Total</span>
                    </div>
                    <ul class="task-list-mini">
                        <?php foreach ($tasks as $t): ?>
                            <li>
                                <input type="checkbox" onclick="return false;" <?= $t['is_done']?'checked':'' ?>>
                                <div style="flex: 1;">
                                    <span style="<?= $t['is_done']?'text-decoration: line-through; opacity: 0.5;':'' ?>"><?= htmlspecialchars($t['task_desc']) ?></span>
                                    <div style="font-size: 0.7rem; color: <?= (strtotime($t['due_date']) < time() && !$t['is_done']) ? '#ef4444' : 'var(--text-muted)' ?>;">
                                        Due: <?= date('M d', strtotime($t['due_date'])) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($tasks)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem 0;">No tasks scheduled yet.</p>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Main Timeline Panel -->
            <div class="main-profile">
                <div class="content-card" style="padding: 2.5rem; min-height: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
                        <h2 style="margin: 0; font-size: 1.5rem;">Activity History</h2>
                        <button class="btn btn-sm btn-outline" style="border-radius: 20px; font-size: 0.8rem;">Refresh Timeline</button>
                    </div>
                    
                    <div class="timeline">
                        <?php if (empty($history)): ?>
                            <div style="text-align: center; padding: 4rem 1rem;">
                                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">📜</div>
                                <p style="color: var(--text-muted); font-size: 1rem;">No history records found for this lead.</p>
                                <p style="font-size: 0.85rem; color: var(--text-muted);">Activity such as status changes or note updates will be logged here automatically.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                            <div class="timeline-item">
                                <span class="time"><?= date('M d, Y • h:i A', strtotime($h['created_at'])) ?></span>
                                <span class="event-title">
                                    <?php 
                                    switch($h['event_type']) {
                                        case 'note_added': echo "🖊️ Note Updated"; break;
                                        case 'status_change': echo "🚀 Status Transitioned"; break;
                                        case 'task_created': echo "🎯 Follow-up Scheduled"; break;
                                        default: echo ucwords(str_replace('_',' ',$h['event_type']));
                                    }
                                    ?>
                                </span>
                                <div class="event-details">
                                    <?= htmlspecialchars($h['details']) ?>
                                    <div style="font-size: 0.75rem; color: var(--primary-color); margin-top: 8px; font-weight: 600;">— Action by <?= htmlspecialchars($h['user_name']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
