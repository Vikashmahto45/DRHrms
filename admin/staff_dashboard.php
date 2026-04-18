<?php
// /admin/staff_dashboard.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['staff', 'manager', 'sales_person', 'admin']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];

// 1. My Attendance Today
$stmt = $pdo->prepare("SELECT clock_in, clock_out FROM attendance WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$uid]);
$today_att = $stmt->fetch();

$status = "Not Clocked In";
$statusColor = "text-muted";
if ($today_att) {
    if (!$today_att['clock_out']) {
        $status = "Clocked In at " . date('h:i A', strtotime($today_att['clock_in']));
        $statusColor = "success";
    } else {
        $status = "Clocked Out at " . date('h:i A', strtotime($today_att['clock_out']));
        $statusColor = "warning";
    }
}

// 2. My Leave Stats (Current Year)
$yr = date('Y');
$stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM leave_requests WHERE user_id = ? AND YEAR(start_date) = ? GROUP BY status");
$stmt->execute([$uid, $yr]);
$leave_stats = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
foreach($stmt->fetchAll() as $row) {
    $leave_stats[$row['status']] = $row['cnt'];
}

// 3. Announcements
$ann_stmt = $pdo->prepare("SELECT message, type FROM announcements WHERE target IN ('all', 'staff', 'sub_branch') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
$ann_stmt->execute();
$announcements = $ann_stmt->fetchAll();

// 4. Company Info
$comp = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$comp->execute([$cid]);
$company_name = $comp->fetchColumn();

// 5. My Assigned Projects
$my_projects = [];
// Staff only see approved (Active) projects. Others see all non-completed.
$status_filter = ($role === 'staff') ? "AND status = 'Active'" : "AND status NOT IN ('Completed', 'Cancelled')";

$stmt = $pdo->prepare("
    SELECT * FROM projects 
    WHERE (sales_person_id = ? OR created_by = ?) $status_filter 
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$uid, $uid]);
$my_projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <?php $css_v = time(); ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= $css_v ?>">
    <link rel="stylesheet" href="../assets/css/loom_premium_v2.css?v=<?= $css_v ?>">
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
                <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</h1>
                <p style="color:var(--text-muted)"><?= htmlspecialchars($company_name) ?> Employee Access Panel</p>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:1.5rem;margin-bottom:2rem;">
            <!-- Attendance -->
            <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
                <div style="width:48px;height:48px;border-radius:10px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">🕒</div>
                <div>
                    <div style="color:var(--text-muted);font-size:.85rem;">Today's Status</div>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--<?= $statusColor ?>);"><?= $status ?></div>
                </div>
            </div>
            
            <!-- Leaves Approved -->
            <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
                <div style="width:48px;height:48px;border-radius:10px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">✈️</div>
                <div>
                    <div style="color:var(--text-muted);font-size:.85rem;">Leaves Approved (<?= $yr ?>)</div>
                    <div style="font-size:1.6rem;font-weight:800;color:#10b981;"><?= $leave_stats['approved'] ?></div>
                </div>
            </div>

            <!-- Leaves Pending -->
            <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
                <div style="width:48px;height:48px;border-radius:10px;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">⏳</div>
                <div>
                    <div style="color:var(--text-muted);font-size:.85rem;">Leaves Pending</div>
                    <div style="font-size:1.6rem;font-weight:800;color:#f59e0b;"><?= $leave_stats['pending'] ?></div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns: 1fr 1fr; gap:1.5rem;">
            <div class="content-card">
                <div class="card-header">
                    <h2>Quick Actions</h2>
                </div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="staff_attendance.php" class="btn btn-primary" style="text-align:center;">Mark Attendance</a>
                    <a href="apply_leave.php" class="btn btn-outline" style="text-align:center;">Apply for Leave</a>
                    <a href="staff_profile.php" class="btn btn-outline" style="text-align:center;">My Profile</a>
                </div>
            </div>

            <!-- New Assigned Projects Section -->
            <div class="content-card">
                <div class="card-header">
                    <h2>My Assigned Projects</h2>
                </div>
                <div class="projects-list">
                    <?php if (empty($my_projects)): ?>
                        <p style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No active projects assigned to you.</p>
                    <?php else: ?>
                        <?php foreach ($my_projects as $p): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--glass-border);">
                            <div>
                                <h4 style="margin:0; font-size: 0.95rem;"><?= htmlspecialchars($p['client_name']) ?></h4>
                                <p style="margin:0; font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($p['project_name']) ?></p>
                            </div>
                            <div style="text-align: right;">
                                <div class="badge badge-<?= strtolower(str_replace(' ', '-', $p['status'])) ?>" style="font-size: 0.7rem;"><?= htmlspecialchars($p['status']) ?></div>
                                <div style="margin-top: 5px;"><a href="project_view.php?id=<?= $p['id'] ?>" style="font-size: 0.75rem; color: var(--primary-color); text-decoration: none; font-weight: 600;">View Details →</a></div>
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
