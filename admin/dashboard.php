<?php
// /admin/dashboard.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'staff']);

$cid = $_SESSION['company_id'];
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Fetch company info
$company = $pdo->prepare("SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON c.plan_id=p.id WHERE c.id=?");
$company->execute([$cid]);
$company = $company->fetch();

// Stats
$total_staff    = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id IN ($cids_in) AND role IN ('staff','manager','sales_person')");
$total_staff->execute(); $total_staff = $total_staff->fetchColumn();

$total_leads    = $pdo->prepare("SELECT COUNT(*) FROM leads_crm WHERE company_id IN ($cids_in)");
$total_leads->execute(); $total_leads = $total_leads->fetchColumn();

$today_attendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE company_id=? AND date=CURDATE()");
$today_attendance->execute([$cid]); $today_attendance = $today_attendance->fetchColumn();

$open_leads = $pdo->prepare("SELECT COUNT(*) FROM leads_crm WHERE company_id IN ($cids_in) AND status='New'");
$open_leads->execute(); $open_leads = $open_leads->fetchColumn();

// Recent Staff
$staff = $pdo->prepare("SELECT id,name,email,role,status,created_at FROM users WHERE company_id IN ($cids_in) AND role IN ('staff','manager','sales_person') ORDER BY created_at DESC LIMIT 5");
$staff->execute(); $staff = $staff->fetchAll();

// Recent leads_crm
$leads_crm = $pdo->prepare("SELECT l.*, u.name AS assigned_name, c.name as company_name FROM leads_crm l LEFT JOIN users u ON l.assigned_to=u.id LEFT JOIN companies c ON l.company_id = c.id WHERE l.company_id IN ($cids_in) ORDER BY l.created_at DESC LIMIT 5");
$leads_crm->execute(); $leads_crm = $leads_crm->fetchAll();

// Check which modules are enabled
$mods = $pdo->prepare("SELECT module_name, is_enabled FROM permissions_map WHERE company_id=?");
$mods->execute([$cid]);
$modules = [];
foreach ($mods->fetchAll() as $m) $modules[$m['module_name']] = $m['is_enabled'];

// Staff Performance: Top closers this month
$perf_stmt = $pdo->prepare("
    SELECT u.name, COUNT(l.id) as conversions 
    FROM users u 
    LEFT JOIN leads_crm l ON u.id = l.assigned_to 
    WHERE u.company_id IN ($cids_in) AND l.status = 'converted' 
    AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.id 
    ORDER BY conversions DESC 
    LIMIT 3
");
$perf_stmt->execute();
$staff_performance = $perf_stmt->fetchAll();

// Active Staff Count (Clocked in today)
$active_staff_stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE company_id IN ($cids_in) AND date = CURDATE() AND clock_out IS NULL");
$active_staff_stmt->execute();
$active_staff_count = $active_staff_stmt->fetchColumn();

// Daily Task List
$tasks_stmt = $pdo->prepare("
    SELECT t.*, l.client_name 
    FROM lead_tasks t 
    JOIN leads_crm l ON t.lead_id = l.id 
    WHERE t.company_id = ? AND t.due_date = CURDATE() 
    ORDER BY t.is_done ASC, t.created_at DESC
");
$tasks_stmt->execute([$cid]);
$daily_tasks = $tasks_stmt->fetchAll();

// New Stats for Phase 9
// Simulated Revenue (Total leads_crm * 500 for demonstration)
$total_revenue = $total_leads * 500; 

// Daily Tasks (Incomplete tasks)
$pending_tasks = $pdo->prepare("SELECT COUNT(*) FROM lead_tasks WHERE company_id=? AND is_done=0");
$pending_tasks->execute([$cid]);
$pending_tasks = $pending_tasks->fetchColumn();

// Subscription Days Left
$expiry = new DateTime($company['subscription_end_date'] ?? 'now');
$now = new DateTime();
$days_left = (int)$now->diff($expiry)->format("%r%a");
if ($days_left < 0) $days_left = 0;

// Fetch Global Announcements
$is_main = $company['is_main_branch'];
$target_types = ['all', ($is_main ? 'main_branch' : 'sub_branch')];
$placeholders = implode(',', array_fill(0, count($target_types), '?'));
$ann_stmt = $pdo->prepare("SELECT message, type FROM announcements WHERE target IN ($placeholders) AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
$ann_stmt->execute($target_types);
$announcements = $ann_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
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
            <h1><?= htmlspecialchars($company['name']) ?></h1>
            <p style="color:var(--text-muted)">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>. Plan: <strong style="color:var(--primary-color)"><?= $company['plan_name'] ?? 'Starter' ?></strong></p>
        </div>
        <div style="display:flex;gap:1rem;">
            <?php if ($modules['leads_crm'] ?? 0): ?>
                <a href="leads_crm.php?action=new" class="btn btn-primary">+ New Lead</a>
            <?php endif; ?>
            <a href="staff.php?action=new" class="btn btn-outline">+ Add Staff</a>
        </div>
    </div>

    <!-- 4-Column KPI Stats -->
    <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:1.5rem;margin-bottom:2rem;">
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">📈</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">Total leads_crm</div>
                <div style="font-size:1.6rem;font-weight:800;color:#6366f1;"><?= number_format($total_leads) ?></div>
            </div>
        </div>
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">🕐</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">Attendance</div>
                <div style="font-size:1.6rem;font-weight:800;color:#10b981;"><?= $today_attendance ?></div>
            </div>
        </div>
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(236,72,153,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">💰</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">Revenue (Est)</div>
                <div style="font-size:1.6rem;font-weight:800;color:#ec4899;">₹<?= number_format($total_revenue) ?></div>
            </div>
        </div>
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">📅</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">Daily Tasks</div>
                <div style="font-size:1.6rem;font-weight:800;color:#f59e0b;"><?= $pending_tasks ?></div>
            </div>
        </div>
    </div>

    <!-- Subscription Banner (Only for Main Branch HQ) -->
    <?php if ($company['is_main_branch'] == 1 && empty($company['parent_id']) && $days_left <= 15): ?>
    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 1rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="font-size: 1.5rem;">⚠️</span>
            <div>
                <strong style="color: #f59e0b;">Subscription Alert</strong>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Your plan expires in <?= $days_left ?> days. Renew now to avoid service interruption.</p>
            </div>
        </div>
        <div style="flex: 1; max-width: 300px; height: 8px; background: rgba(0,0,0,0.05); border-radius: 4px; margin: 0 2rem; position: relative;">
            <div style="width: <?= ($days_left/30)*100 ?>%; height: 100%; background: #f59e0b; border-radius: 4px;"></div>
        </div>
        <a href="settings.php#billing" class="btn btn-sm btn-primary">Renew Plan</a>
    </div>
    <?php endif; ?>

    <!-- Dynamic Dashboard Widgets -->
    <div style="display:grid;grid-template-columns: 1fr 1.5fr; gap:1.5rem;">
        
        <!-- Left Column: Tasks & Performance -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <!-- Follow-up Reminders -->
            <div class="content-card" style="margin-bottom:0; border-left: 4px solid #f59e0b;">
                <div class="card-header">
                    <h2>📅 Daily Tasks</h2>
                    <span class="badge" style="background:rgba(245,158,11,0.1); color:#f59e0b;"><?= count($daily_tasks) ?></span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($daily_tasks as $t): ?>
                    <div style="padding: 1rem; border-bottom: 1px solid var(--glass-border); display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" <?= $t['is_done'] ? 'checked' : '' ?> disabled>
                        <div>
                            <div style="font-weight: 600; font-size: 0.9rem; text-decoration: <?= $t['is_done'] ? 'line-through' : 'none' ?>;"><?= htmlspecialchars($t['task_desc']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">Lead: <?= htmlspecialchars($t['client_name']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!count($daily_tasks)): ?>
                        <div style="padding: 2rem; text-align:center; color:var(--text-muted); font-size: 0.9rem;">No tasks scheduled for today.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Staff Performance -->
            <div class="content-card" style="margin-bottom:0;">
                <div class="card-header">
                    <h2>🏆 Top Closers</h2>
                    <span style="font-size: 0.75rem; color:var(--text-muted);">Last 30 Days</span>
                </div>
                <?php foreach($staff_performance as $p): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; padding: 0.8rem 0; border-bottom: 1px solid var(--glass-border);">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:32px; height:32px; background:rgba(16,185,129,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#10b981; font-weight:700; font-size:0.75rem;"><?= strtoupper(substr($p['name'],0,1)) ?></div>
                        <span style="font-size: 0.9rem; font-weight: 500;"><?= htmlspecialchars($p['name']) ?></span>
                    </div>
                    <span style="font-weight: 700; color: #10b981;"><?= $p['conversions'] ?> <span style="font-size: 0.7rem; color:var(--text-muted); font-weight: 400;">wins</span></span>
                </div>
                <?php endforeach; ?>
                <?php if (!count($staff_performance)): ?>
                    <div style="padding: 1.5rem; text-align:center; color:var(--text-muted); font-size: 0.9rem;">No conversions recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Recent Activity & Attendance -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <!-- Attendance monitor -->
            <div class="content-card" style="margin-bottom:0;">
                <div class="card-header">
                    <h2>💼 Active Staff</h2>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="display:inline-block; width:8px; height:8px; background:#10b981; border-radius:50%; box-shadow: 0 0 10px #10b981;"></span>
                        <span style="font-size: 0.85rem; font-weight: 600; color:#10b981;"><?= $active_staff_count ?> Online</span>
                    </div>
                </div>
                <div style="display:flex; gap: 10px; margin-bottom: 1rem;">
                    <button class="btn btn-sm btn-outline" style="flex:1;">View Map</button>
                    <button class="btn btn-sm btn-outline" style="flex:1;">Quick Export</button>
                </div>
                <div style="color:var(--text-muted); font-size:0.8rem; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                    <?= $total_staff - $active_staff_count ?> staff members are currently clocked out.
                </div>
            </div>

            <!-- Recent leads_crm -->
            <?php if ($modules['leads_crm'] ?? 0): ?>
            <div class="content-card" style="margin-bottom:0;">
                <div class="card-header">
                    <h2>Recent leads_crm</h2>
                    <a href="leads_kanban.php" style="color:var(--primary-color);font-size:.9rem;">Pipeline View →</a>
                </div>
                <table class="table">
                    <thead><tr><th>Client</th><th>Status</th><th>Assigned</th></tr></thead>
                    <tbody>
                        <?php foreach ($leads_crm as $l): ?>
                        <tr>
                            <td style="font-weight:600"><?= htmlspecialchars($l['client_name']) ?></td>
                            <td><span class="badge badge-<?= strtolower($l['status'])==='new'?'pending':'active' ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                            <td style="color:var(--text-muted);font-size:.85rem"><?= htmlspecialchars($l['assigned_name'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!count($leads_crm)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:1.5rem">No leads_crm yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    </main>
</div>
</body>
</html>
