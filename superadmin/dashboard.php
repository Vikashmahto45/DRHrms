<?php
// /superadmin/dashboard.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Ensure user is super_admin
checkAccess('super_admin');

// Fetch basic stats
try {
    $stats = [];

    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_main_branch = 1 AND status = 'active'");
    $stats['total_main_branches'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_main_branch = 0 AND status = 'active'");
    $stats['total_sub_branches'] = $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM users u 
        INNER JOIN companies c ON u.company_id = c.id 
        WHERE u.role != 'super_admin' 
          AND u.status = 'active' 
          AND c.status = 'active'
    ");
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM demo_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetchColumn();

    // Recent Demo Requests
    $stmt = $pdo->query("SELECT * FROM demo_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $recent_demo_requests = $stmt->fetchAll();

    // Announcement Handling
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_announcement') {
            $msg = trim($_POST['message']);
            $type = $_POST['type'];
            $target = $_POST['target'] ?? 'all';
            if ($msg) {
                $stmt = $pdo->prepare("INSERT INTO announcements (message, type, target, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$msg, $type, $target, $_SESSION['sa_user_id']]);
                $_SESSION['sa_flash_message'] = "Announcement posted successfully!";
                header("Location: dashboard.php"); exit();
            }
        }
        if ($_POST['action'] === 'delete_announcement') {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
            $_SESSION['sa_flash_message'] = "Announcement deleted.";
            header("Location: dashboard.php"); exit();
        }
    }

    // Fetch active announcements
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10")->fetchAll();

    // Per-branch performance (all Main Branches with their data aggregated)
    $branch_perf_stmt = $pdo->query("
        SELECT 
            c.id,
            c.name,
            c.status,
            c.is_main_branch,
            c.parent_id,
            parent.name as parent_name,
            (SELECT COUNT(*) FROM leads_crm WHERE company_id = c.id) as total_leads,
            (SELECT COUNT(*) FROM leads_crm WHERE company_id = c.id AND status = 'Converted') as converted_leads,
            (SELECT COUNT(*) FROM dsr WHERE company_id = c.id) as total_dsrs,
            (SELECT COUNT(*) FROM dsr WHERE company_id = c.id AND visit_date = CURDATE()) as today_dsrs,
            (SELECT COALESCE(SUM(amount), 0) FROM franchise_payments WHERE company_id = c.id AND status = 'approved') as approved_revenue,
            (SELECT COUNT(*) FROM franchise_payments WHERE company_id = c.id AND status = 'pending') as pending_payments,
            (SELECT COUNT(*) FROM companies WHERE parent_id = c.id) as sub_branch_count
        FROM companies c
        LEFT JOIN companies parent ON c.parent_id = parent.id
        ORDER BY c.is_main_branch DESC, c.name ASC
    ");
    $all_branches = $branch_perf_stmt->fetchAll();

    // Group: main branches first, then their subs
    $main_branches = [];
    $sub_branches = [];
    foreach ($all_branches as $b) {
        if ($b['is_main_branch'] == 1) {
            $main_branches[$b['id']] = $b;
            $main_branches[$b['id']]['subs'] = [];
        } else {
            $sub_branches[] = $b;
        }
    }
    foreach ($sub_branches as $sub) {
        $pid = $sub['parent_id'];
        if (isset($main_branches[$pid])) {
            $main_branches[$pid]['subs'][] = $sub;
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <?php $css_v = time(); ?>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= $css_v ?>">
    <link rel="stylesheet" href="../assets/css/loom_premium_v2.css?v=<?= $css_v ?>">
    <style>
        body { background: var(--bg-main); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-premium);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: var(--glass-shadow);
            border: 1px solid var(--glass-border);
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.1); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0; }
        .icon-blue { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
        .icon-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .icon-pink { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
        .stat-details p { color: var(--text-muted); font-size: 0.85rem; margin: 0 0 2px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-details h3 { font-size: 2.2rem; margin: 0; color: var(--text-main); font-weight: 800; line-height: 1; }

        /* Branch Performance Cards */
        .branch-section { margin-bottom: 3rem; }
        .branch-section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }

        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }

        /* Announcements */
        .announcement-card { background: var(--card-bg); padding: 1.5rem; border-radius: var(--radius-premium); border: 1px solid var(--glass-border); margin-bottom: 3rem; box-shadow: var(--glass-shadow); }
        .announcement-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: flex-end; margin-top: 1rem; }
        .announcement-list { margin-top: 2rem; display: flex; flex-direction: column; gap: 12px; }
        .ann-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 18px; border-radius: 12px; font-size: 0.9rem; border-left: 5px solid var(--text-muted); background: var(--bg-main); transition: all 0.2s; }
        .ann-item:hover { transform: translateX(5px); }
        .ann-info { border-color: var(--primary-color); }
        .ann-warning { border-color: #f59e0b; }
        .ann-danger { border-color: #ef4444; }
        .ann-success { border-color: #10b981; }

        .branch-card {
            background: var(--card-bg);
            border-radius: var(--radius-premium);
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s ease;
        }
        .branch-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.1); }

        .branch-card-header {
            padding: 1.1rem 1.4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f1f5f9;
        }
        .branch-card-header.main { background: linear-gradient(135deg, #f0fdf4, #fff); }
        .branch-card-header.sub { background: linear-gradient(135deg, #f8faff, #fff); }

        .branch-name { font-weight: 700; font-size: 1rem; color: #0f172a; }
        .branch-type-badge {
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .badge-main { background: rgba(16,185,129,0.12); color: #059669; border: 1px solid rgba(16,185,129,0.2); }
        .badge-sub { background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.2); }
        .badge-inactive { background: rgba(239,68,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.15); }

        .branch-stats { display: grid; grid-template-columns: 1fr 1fr; padding: 1rem 1.4rem; gap: 0.75rem; }
        .bstat { text-align: center; padding: 0.6rem; background: var(--bg-main); border-radius: 10px; }
        .bstat-val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
        .bstat-lbl { font-size: 0.7rem; color: #94a3b8; margin-top: 2px; font-weight: 500; }
        .bstat-val.green { color: #10b981; }
        .bstat-val.blue { color: #3b82f6; }
        .bstat-val.purple { color: #8b5cf6; }
        .bstat-val.orange { color: #f59e0b; }

        .branch-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.4rem;
            border-top: 1px solid var(--glass-border);
            font-size: 0.8rem;
        }
        .revenue-chip { background: #f0fdf4; color: #059669; font-weight: 700; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; }
        .pending-chip { background: #fff7ed; color: #f59e0b; font-weight: 600; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; }

        /* Sub-Branches inside main card */
        .sub-branches-list { border-top: 1px solid var(--glass-border); }
        .sub-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 1.4rem;
            border-bottom: 1px solid var(--bg-main);
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .sub-row:last-child { border-bottom: none; }
        .sub-row-name { font-weight: 600; color: #334155; flex: 1; }
        .sub-mini-stat { display: flex; gap: 0.5rem; }
        .mini-chip { font-size: 0.72rem; background: var(--bg-main); padding: 2px 7px; border-radius: 6px; color: #475569; font-weight: 500; white-space: nowrap; }
        .mini-chip.rev { background: #f0fdf4; color: #059669; }
        .mini-chip.dsr { background: #eef2ff; color: #6366f1; }

        /* Announcements Management Styles */
        .announcement-card { background: #fff; padding: 1.5rem; border-radius: 14px; border: 1px solid var(--glass-border); margin-bottom: 2.5rem; }
        .announcement-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: flex-end; margin-top: 1rem; }
        .announcement-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 10px; }
        .ann-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-radius: 10px; font-size: 0.9rem; border-left: 4px solid #64748b; background: var(--bg-main); }
        .ann-info { border-color: #3b82f6; }
        .ann-warning { border-color: #f59e0b; }
        .ann-danger { border-color: #ef4444; }
        .ann-success { border-color: #10b981; }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($_SESSION['sa_flash_message'])): ?>
            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div><?php echo $_SESSION['sa_flash_message']; ?></div>
                <button onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:#10b981;cursor:pointer;font-size:1.2rem;">&times;</button>
            </div>
            <?php unset($_SESSION['sa_flash_message']); ?>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1>Overview</h1>
                <p style="color: var(--text-muted);">Welcome back to the system control panel.</p>
            </div>
        </div>

        <!-- Top Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">🏢</div>
                <div class="stat-details">
                    <p>Main Branches</p>
                    <h3><?php echo number_format($stats['total_main_branches']); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green">🏬</div>
                <div class="stat-details">
                    <p>Sub-Branches</p>
                    <h3><?php echo number_format($stats['total_sub_branches']); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple">👥</div>
                <div class="stat-details">
                    <p>Total Users</p>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-pink">📩</div>
                <div class="stat-details">
                    <p>Pending Requests</p>
                    <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                </div>
            </div>
        </div>

        <!-- Global Announcements Manager -->
        <div class="announcement-card">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a;">📢 Global Announcements</h3>
            <p style="font-size:0.82rem; color:#64748b; margin:4px 0 15px 0;">Post a message to all branches and staff dashboards.</p>
            
            <form method="POST" class="announcement-form">
                <input type="hidden" name="action" value="add_announcement">
                <div class="form-group" style="margin:0">
                    <label style="font-size:0.75rem; display:block; margin-bottom:4px; font-weight:600;">Message</label>
                    <input type="text" name="message" class="form-control" placeholder="Type your announcement here..." required style="padding:0.65rem 0.8rem; width:100%; border:1px solid #e2e8f0; border-radius:8px;">
                </div>
                <div class="form-group" style="margin:0">
                    <label style="font-size:0.75rem; display:block; margin-bottom:4px; font-weight:600;">Alert Type</label>
                    <select name="type" class="form-control" style="padding:0.65rem 0.8rem; width:100%; border:1px solid #e2e8f0; border-radius:8px;">
                        <option value="info">Info (Blue)</option>
                        <option value="warning">Warning (Yellow)</option>
                        <option value="danger">Urgent (Red)</option>
                        <option value="success">Success (Green)</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label style="font-size:0.75rem; display:block; margin-bottom:4px; font-weight:600;">Audience</label>
                    <select name="target" class="form-control" style="padding:0.65rem 0.8rem; width:100%; border:1px solid #e2e8f0; border-radius:8px;">
                        <option value="all">Everyone</option>
                        <option value="main_branch">Main Branches Only</option>
                        <option value="sub_branch">Sub-Branches Only</option>
                        <option value="staff">Sales Staff Only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:0.7rem 1.5rem; height:40px;">+ Post</button>
            </form>

            <?php if (count($announcements) > 0): ?>
                <div class="announcement-list">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="ann-item ann-<?= $ann['type'] ?>">
                        <div>
                            <span style="font-weight:700; color:#475569; font-size:0.7rem; display:block; text-transform:uppercase; margin-bottom:2px;"><?= str_replace('_', ' ', $ann['target']) ?></span>
                            <?= htmlspecialchars($ann['message']) ?>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this announcement?')">
                            <input type="hidden" name="action" value="delete_announcement">
                            <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                            <button type="submit" style="background:none; border:none; color:#94a3b8; cursor:pointer;" title="Remove">✕</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Calculate Global Totals (Include ALL branches for full financial history)
        $sys_leads = 0;
        $sys_converted = 0;
        $sys_dsrs = 0;

        // 1. Total Revenue breakdown from ALL payments
        $stmt_rev = $pdo->query("
            SELECT 
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_total,
                SUM(amount) as gross_volume,
                SUM(CASE WHEN (category = 'DSR Field Deal') AND status = 'approved' THEN amount ELSE 0 END) as dsr_approved,
                SUM(CASE WHEN (category = 'DSR Field Deal') THEN amount ELSE 0 END) as dsr_gross,
                SUM(CASE WHEN (project_id IS NOT NULL AND category != 'DSR Field Deal') AND status = 'approved' THEN amount ELSE 0 END) as project_approved,
                SUM(CASE WHEN (project_id IS NOT NULL AND category != 'DSR Field Deal') THEN amount ELSE 0 END) as project_gross,
                SUM(CASE WHEN (project_id IS NULL AND category != 'DSR Field Deal') AND status = 'approved' THEN amount ELSE 0 END) as franchise_approved,
                SUM(CASE WHEN (project_id IS NULL AND category != 'DSR Field Deal') THEN amount ELSE 0 END) as franchise_gross
            FROM franchise_payments
        ");
        $rev_totals = $stmt_rev->fetch();

        $sys_revenue = $rev_totals['approved_total'] ?? 0;
        
        // Sum operational stats
        $all_active_inactive = $pdo->query("SELECT id, 
            (SELECT COUNT(*) FROM leads_crm WHERE company_id = c.id) as leads,
            (SELECT COUNT(*) FROM leads_crm WHERE company_id = c.id AND status = 'Converted') as converted,
            (SELECT COUNT(*) FROM dsr WHERE company_id = c.id) as dsrs
            FROM companies c")->fetchAll();

        foreach($all_active_inactive as $b) {
            $sys_leads += $b['leads'] ?? 0;
            $sys_converted += $b['converted'] ?? 0;
            $sys_dsrs += $b['dsrs'] ?? 0;
        }

        // Fetch Global Sales Ledger (Latest 15)
        $ledger_stmt = $pdo->query("
            SELECT f.*, c.name as branch_name 
            FROM franchise_payments f 
            JOIN companies c ON f.company_id = c.id 
            ORDER BY f.created_at DESC 
            LIMIT 15
        ");
        $global_ledger = $ledger_stmt->fetchAll();

        // Chart Data Prep
        $chart_labels = [];
        $chart_revenues = [];
        foreach($main_branches as $mb) {
            $br_rev = $mb['approved_revenue'] ?? 0;
            if(!empty($mb['subs'])) {
                 foreach($mb['subs'] as $sub) {
                      $br_rev += $sub['approved_revenue'] ?? 0;
                 }
            }
            $chart_labels[] = $mb['name'];
            $chart_revenues[] = $br_rev;
        }
        ?>

        <!-- 1. Global Metrics (Categorized Revenue) -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.5rem; margin-bottom:2.5rem;">
            <!-- Project Sales -->
            <div style="background:linear-gradient(135deg, #4f46e5, #7c3aed); padding:1.8rem; border-radius:16px; color:#fff; box-shadow:0 10px 25px -5px rgba(79,70,229,0.4); position:relative; overflow:hidden;">
                <div style="position:absolute; right:-20px; top:-20px; font-size:6rem; opacity:0.1;">💎</div>
                <div style="font-size:0.8rem; opacity:0.8; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">Project Sales</div>
                <div style="font-size:1.8rem; font-weight:800;">₹<?= number_format($rev_totals['project_approved']) ?></div>
                <div style="font-size:0.75rem; opacity:0.7;">Gross Volume: ₹<?= number_format($rev_totals['project_gross']) ?></div>
            </div>
            <!-- DSR Deals -->
            <div style="background:linear-gradient(135deg, #f59e0b, #d97706); padding:1.8rem; border-radius:16px; color:#fff; box-shadow:0 10px 25px -5px rgba(245,158,11,0.4); position:relative; overflow:hidden;">
                <div style="position:absolute; right:-20px; top:-20px; font-size:6rem; opacity:0.1;">🤝</div>
                <div style="font-size:0.8rem; opacity:0.8; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">DSR Field Deals</div>
                <div style="font-size:1.8rem; font-weight:800;">₹<?= number_format($rev_totals['dsr_approved']) ?></div>
                <div style="font-size:0.75rem; opacity:0.7;">Gross Volume: ₹<?= number_format($rev_totals['dsr_gross']) ?></div>
            </div>
            <!-- Franchise Revenue -->
            <div style="background:linear-gradient(135deg, #10b981, #059669); padding:1.8rem; border-radius:16px; color:#fff; box-shadow:0 10px 25px -5px rgba(16,185,129,0.4); position:relative; overflow:hidden;">
                <div style="position:absolute; right:-20px; top:-20px; font-size:6rem; opacity:0.1;">🏢</div>
                <div style="font-size:0.8rem; opacity:0.8; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">Franchise Revenue / Fees</div>
                <div style="font-size:1.8rem; font-weight:800;">₹<?= number_format($rev_totals['franchise_approved']) ?></div>
                <div style="font-size:0.75rem; opacity:0.7;">Gross Volume: ₹<?= number_format($rev_totals['franchise_gross']) ?></div>
            </div>
            <!-- All System Leads -->
            <div style="background:linear-gradient(135deg, #ec4899, #be185d); padding:1.8rem; border-radius:16px; color:#fff; box-shadow:0 10px 25px -5px rgba(236,72,153,0.4); position:relative; overflow:hidden;">
               <div style="position:absolute; right:-20px; top:-20px; font-size:6rem; opacity:0.1;">🎯</div>
                <div style="font-size:0.8rem; opacity:0.8; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">System Leads</div>
                <div style="font-size:1.8rem; font-weight:800;"><?= number_format($sys_leads) ?></div>
                <div style="font-size:0.75rem; opacity:0.7;">Conversions: <?= number_format($sys_converted) ?></div>
            </div>
        </div>

        <!-- 2. Charts Section -->
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem; margin-bottom:2.5rem;">
            <div class="content-card" style="margin-bottom:0;">
                <div class="card-header" style="margin-bottom:1rem;">
                    <h2>Revenue by Main Branch</h2>
                </div>
                <div style="position: relative; height:300px; width:100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="content-card" style="margin-bottom:0;">
                <div class="card-header" style="margin-bottom:1rem;">
                    <h2>Lead Conversion Rate</h2>
                </div>
                <div style="position: relative; height:300px; width:100%; display:flex; align-items:center; justify-content:center;">
                    <canvas id="leadsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 3. Global Sales Ledger -->
        <div class="content-card" style="margin-bottom:2.5rem;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Master Sales Ledger</h2>
                <a href="finance_stats.php" class="btn btn-sm btn-outline">Full Analytics →</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Client / Item</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($global_ledger as $row): ?>
                        <tr>
                            <td style="font-size:0.8rem; color:var(--text-muted);"><?= date('d M, Y', strtotime($row['payment_date'])) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <td><span class="mini-chip"><?= htmlspecialchars($row['category']) ?></span></td>
                            <td style="font-weight:700; color:var(--text-main);">₹<?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($global_ledger)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No transactions recorded in the master ledger yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. Complete Branch Matrix -->
            </div>
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Branch Name</th>
                            <th>Type</th>
                            <th>Total Leads</th>
                            <th>Converted</th>
                            <th>DSRs Today/Total</th>
                            <th>Approved Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($all_branches)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No active branches found.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($all_branches as $b): ?>
                        <tr>
                            <td style="font-weight:600; color:var(--text-main);">
                                <?= htmlspecialchars($b['name']) ?>
                                <?php if(isset($b['parent_id'])): ?>
                                     <div style="font-size:0.75rem; color:var(--text-muted); margin-top:3px;">Parent: <?= htmlspecialchars($b['parent_name'] ?? 'Unknown') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(isset($b['sub_branch_count'])): ?>
                                    <span style="background:rgba(99,102,241,0.1); color:#4f46e5; padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;">MAIN</span>
                                <?php else: ?>
                                    <span style="background:rgba(148,163,184,0.1); color:#64748b; padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;">SUB</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $b['total_leads'] ?? 0 ?></td>
                            <td><?= $b['converted_leads'] ?? 0 ?></td>
                            <td><span style="color:#f59e0b; font-weight:600;"><?= $b['today_dsrs'] ?? 0 ?></span> <span style="color:var(--text-muted);">/ <?= $b['total_dsrs'] ?? 0 ?></span></td>
                            <td style="font-weight:700; color:#10b981;">₹<?= number_format($b['approved_revenue'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            const revCtx = document.getElementById('revenueChart');
            if (revCtx) {
                new Chart(revCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chart_labels) ?>,
                        datasets: [{
                            label: 'Total Revenue (₹)',
                            data: <?= json_encode($chart_revenues) ?>,
                            backgroundColor: 'rgba(79, 70, 229, 0.85)',
                            hoverBackgroundColor: 'rgba(79, 70, 229, 1)',
                            borderRadius: 6,
                            barThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)', borderDash: [5, 5] }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // Leads Chart
            const leadsCtx = document.getElementById('leadsChart');
            if (leadsCtx) {
                new Chart(leadsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Converted', 'In Progress / Lost'],
                        datasets: [{
                            data: [<?= $sys_converted ?>, <?= max(0, $sys_leads - $sys_converted) ?>],
                            backgroundColor: ['#10b981', '#cbd5e1'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        });
        </script>

    </main>
</body>
</html>
