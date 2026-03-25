<?php
// /admin/sales_dashboard.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['sales_person', 'staff', 'manager']); // Allow sales-oriented roles

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];

// 1. Personal Stats
$total_assigned = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND company_id = ?");
$total_assigned->execute([$uid, $cid]);
$total_assigned = $total_assigned->fetchColumn();

$converted = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND company_id = ? AND status = 'Converted'");
$converted->execute([$uid, $cid]);
$converted = $converted->fetchColumn();

$pending = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND company_id = ? AND status IN ('New', 'In Progress')");
$pending->execute([$uid, $cid]);
$pending = $pending->fetchColumn();

$ratio = ($total_assigned > 0) ? round(($converted / $total_assigned) * 100, 1) : 0;

// 2. My Recent Leads
$my_leads = $pdo->prepare("SELECT * FROM leads WHERE assigned_to = ? AND company_id = ? ORDER BY created_at DESC LIMIT 5");
$my_leads->execute([$uid, $cid]);
$my_leads = $my_leads->fetchAll();

// 3. My Tasks
$my_tasks = $pdo->prepare("
    SELECT t.*, l.client_name 
    FROM lead_tasks t 
    JOIN leads l ON t.lead_id = l.id 
    WHERE l.assigned_to = ? AND t.company_id = ? AND t.is_done = 0 
    ORDER BY t.due_date ASC LIMIT 5
");
$my_tasks->execute([$uid, $cid]);
$my_tasks = $my_tasks->fetchAll();

// Helper for source badges (Reusable from leads.php - ideally move to a shared helper later)
function getSourceBadge($source) {
    $colors = [
        'Meta Ads' => '#1877F2',
        'Google Ads' => '#4285F4',
        'Referral' => '#10b981',
        'Website' => '#f59e0b',
        'Walk-in' => '#8b5cf6'
    ];
    $color = $colors[$source] ?? '#6b7280';
    return "<span class='source-tag' style='background:".($color."22")."; color:$color; border:1px solid ".($color."44")."; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; text-transform:uppercase;'>$source</span>";
}

// Fetch Global Announcements
$ann_stmt = $pdo->prepare("SELECT message, type FROM announcements WHERE target IN ('all', 'staff', 'sales_person') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
$ann_stmt->execute();
$announcements = $ann_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Dashboard - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { padding: 1.5rem; text-align: center; }
        .stat-card h3 { font-size: 2rem; margin: 0.5rem 0; color: var(--primary-color); }
        .stat-card p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        
        .dashboard-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; }
        @media (max-width: 1024px) { .dashboard-layout { grid-template-columns: 1fr; } }

        .lead-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--glass-border); }
        .lead-item:last-child { border-bottom: none; }
        .lead-info h4 { margin: 0 0 4px 0; font-size: 1rem; }
        .lead-info p { margin: 0; font-size: 0.8rem; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <!-- Global Announcements -->
        <?php foreach ($announcements as $ann): ?>
            <div style="background: #fff; border-left: 5px solid <?= $ann['type'] === 'danger' ? '#ef4444' : ($ann['type'] === 'warning' ? '#f59e0b' : ($ann['type'] === 'success' ? '#10b981' : '#3b82f6')) ?>; padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; position: relative;">
                <span style="font-size: 1.5rem;">
                    <?php 
                        if ($ann['type'] === 'danger') echo '🚨';
                        elseif ($ann['type'] === 'warning') echo '⚠️';
                        elseif ($ann['type'] === 'success') echo '✅';
                        else echo '📢';
                    ?>
                </span>
                <div style="flex: 1;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: #1e293b;"><?= htmlspecialchars($ann['message']) ?></div>
                </div>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem; line-height: 1;">&times;</button>
            </div>
        <?php endforeach; ?>
        
        <div class="page-header">
            <div>
                <h1>Sales Performance</h1>
                <p style="color:var(--text-muted)">Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>. Here is your pipeline summary.</p>
            </div>
            <a href="leads.php" class="btn btn-primary">Go to Lead CRM</a>
        </div>

        <div class="stats-grid">
            <div class="glass-card stat-card">
                <p>Total Assigned</p>
                <h3><?= $total_assigned ?></h3>
            </div>
            <div class="glass-card stat-card">
                <p>Active Prospects</p>
                <h3><?= $pending ?></h3>
            </div>
            <div class="glass-card stat-card">
                <p>Converted Leads</p>
                <h3><?= $converted ?></h3>
            </div>
            <div class="glass-card stat-card">
                <p>Win Ratio</p>
                <h3><?= $ratio ?>%</h3>
            </div>
        </div>

        <div class="dashboard-layout">
            <div class="content-card">
                <div class="card-header">
                    <h2>My Recent Leads</h2>
                    <a href="leads.php" style="font-size: 0.85rem; color: var(--primary-color);">View All</a>
                </div>
                <div class="leads-list">
                    <?php if (empty($my_leads)): ?>
                        <p style="padding: 2rem; text-align: center; color: var(--text-muted);">No leads assigned yet. Leads from website/ads will appear here.</p>
                    <?php else: ?>
                        <?php foreach ($my_leads as $l): ?>
                        <div class="lead-item">
                            <div class="lead-info">
                                <h4><?= htmlspecialchars($l['client_name']) ?></h4>
                                <p><?= getSourceBadge($l['source']) ?> • Last Updated: <?= date('M d', strtotime($l['updated_at'] ?? $l['created_at'])) ?></p>
                            </div>
                            <div style="text-align: right;">
                                <div class="badge badge-<?= strtolower(str_replace(' ', '-', $l['status'])) ?>"><?= $l['status'] ?></div>
                                <div style="margin-top: 8px;"><a href="lead_profile.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline">Profile</a></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h2>Pending Tasks</h2>
                </div>
                <div class="tasks-list" style="padding: 1rem;">
                    <?php if (empty($my_tasks)): ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 1rem;">Everything caught up! ✅</p>
                    <?php else: ?>
                        <?php foreach ($my_tasks as $t): ?>
                        <div class="glass-card" style="padding: 1rem; margin-bottom: 1rem; border-left: 4px solid var(--primary-color);">
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">Lead: <?= htmlspecialchars($t['client_name']) ?></div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($t['task_desc']) ?></div>
                            <div style="font-size: 0.75rem; margin-top: 8px; color: <?= (strtotime($t['due_date']) < time()) ? '#ef4444' : 'var(--text-muted)' ?>">
                                📅 Due: <?= date('M d, Y', strtotime($t['due_date'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
