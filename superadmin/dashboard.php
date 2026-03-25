<?php
// /superadmin/dashboard.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Ensure user is super_admin
checkAccess('super_admin');

// Fetch basic stats
try {
    $stats = [];

    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_main_branch = 1");
    $stats['total_main_branches'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_main_branch = 0");
    $stats['total_sub_branches'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('staff','manager','sales_person') AND status = 'active'");
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
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        body { background: #f1f5f9; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            padding: 1.4rem 1.5rem;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #e8edf3;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.25s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .icon-blue { background: rgba(59,130,246,0.1); }
        .icon-green { background: rgba(16,185,129,0.1); }
        .icon-purple { background: rgba(139,92,246,0.1); }
        .icon-pink { background: rgba(236,72,153,0.1); }
        .stat-details p { color: #64748b; font-size: 0.82rem; margin: 0 0 2px 0; font-weight: 500; }
        .stat-details h3 { font-size: 2rem; margin: 0; color: #0f172a; font-weight: 800; line-height: 1; }

        /* Branch Performance Cards */
        .branch-section { margin-bottom: 2.5rem; }
        .branch-section-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }

        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }

        /* Announcements */
        .announcement-card { background: #fff; padding: 1.5rem; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 2.5rem; }
        .announcement-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: flex-end; margin-top: 1rem; }
        .announcement-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 10px; }
        .ann-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-radius: 10px; font-size: 0.9rem; border-left: 4px solid #64748b; background: #f8fafc; }
        .ann-info { border-color: #3b82f6; }
        .ann-warning { border-color: #f59e0b; }
        .ann-danger { border-color: #ef4444; }
        .ann-success { border-color: #10b981; }

        .branch-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e8edf3;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.25s ease;
        }
        .branch-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-2px); }

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
        .bstat { text-align: center; padding: 0.6rem; background: #f8fafc; border-radius: 10px; }
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
            border-top: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }
        .revenue-chip { background: #f0fdf4; color: #059669; font-weight: 700; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; }
        .pending-chip { background: #fff7ed; color: #f59e0b; font-weight: 600; padding: 4px 10px; border-radius: 8px; font-size: 0.8rem; }

        /* Sub-Branches inside main card */
        .sub-branches-list { border-top: 1px solid #f1f5f9; }
        .sub-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 1.4rem;
            border-bottom: 1px solid #f8fafc;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .sub-row:last-child { border-bottom: none; }
        .sub-row-name { font-weight: 600; color: #334155; flex: 1; }
        .sub-mini-stat { display: flex; gap: 0.5rem; }
        .mini-chip { font-size: 0.72rem; background: #f1f5f9; padding: 2px 7px; border-radius: 6px; color: #475569; font-weight: 500; white-space: nowrap; }
        .mini-chip.rev { background: #f0fdf4; color: #059669; }
        .mini-chip.dsr { background: #eef2ff; color: #6366f1; }

        /* Announcements Management Styles */
        .announcement-card { background: #fff; padding: 1.5rem; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 2.5rem; }
        .announcement-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: flex-end; margin-top: 1rem; }
        .announcement-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 10px; }
        .ann-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-radius: 10px; font-size: 0.9rem; border-left: 4px solid #64748b; background: #f8fafc; }
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

        <!-- Branch Performance Overview -->
        <div class="branch-section">
            <div class="branch-section-title">
                📊 Branch Performance Overview
                <span style="font-size:0.8rem; color:#94a3b8; font-weight:400;">— Live data across all branches</span>
            </div>

            <?php if (empty($main_branches) && empty($sub_branches)): ?>
                <div class="content-card" style="text-align:center; padding:3rem; color:var(--text-muted);">
                    <div style="font-size:2.5rem; margin-bottom:1rem;">🏢</div>
                    <p>No branches created yet. <a href="companies.php" style="color:var(--primary-color)">Create your first company →</a></p>
                </div>
            <?php else: ?>
                <div class="branch-grid">
                    <?php foreach ($main_branches as $branch): ?>
                    <div class="branch-card">
                        <div class="branch-card-header main">
                            <div>
                                <div class="branch-name"><?= htmlspecialchars($branch['name']) ?></div>
                                <?php if ($branch['sub_branch_count'] > 0): ?>
                                    <div style="font-size:0.75rem; color:#64748b; margin-top:2px;"><?= $branch['sub_branch_count'] ?> sub-branch<?= $branch['sub_branch_count'] > 1 ? 'es' : '' ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                <span class="branch-type-badge badge-main">Main Branch</span>
                                <?php if ($branch['status'] !== 'active'): ?>
                                    <span class="branch-type-badge badge-inactive"><?= ucfirst($branch['status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="branch-stats">
                            <div class="bstat">
                                <div class="bstat-val blue"><?= number_format($branch['total_leads']) ?></div>
                                <div class="bstat-lbl">Total Leads</div>
                            </div>
                            <div class="bstat">
                                <div class="bstat-val green"><?= number_format($branch['converted_leads']) ?></div>
                                <div class="bstat-lbl">Converted</div>
                            </div>
                            <div class="bstat">
                                <div class="bstat-val purple"><?= number_format($branch['total_dsrs']) ?></div>
                                <div class="bstat-lbl">DSR Reports</div>
                            </div>
                            <div class="bstat">
                                <div class="bstat-val orange"><?= number_format($branch['today_dsrs']) ?></div>
                                <div class="bstat-lbl">Today's Visits</div>
                            </div>
                        </div>

                        <div class="branch-footer">
                            <span class="revenue-chip">₹<?= number_format($branch['approved_revenue'], 0) ?> Revenue</span>
                            <?php if ($branch['pending_payments'] > 0): ?>
                                <span class="pending-chip"><?= $branch['pending_payments'] ?> Pending</span>
                            <?php else: ?>
                                <span style="font-size:0.78rem; color:#94a3b8;">No pending payments</span>
                            <?php endif; ?>
                            <a href="main_branch.php?action=edit&id=<?= $branch['id'] ?>" style="font-size:0.8rem; color:var(--primary-color); font-weight:600;">Manage →</a>
                        </div>

                        <!-- Sub-branches inside -->
                        <?php if (!empty($branch['subs'])): ?>
                        <div class="sub-branches-list">
                            <div style="padding:0.5rem 1.4rem; font-size:0.72rem; color:#94a3b8; font-weight:600; letter-spacing:0.5px; background:#f8fafc; text-transform:uppercase;">Sub-Branches</div>
                            <?php foreach ($branch['subs'] as $sub): ?>
                            <div class="sub-row">
                                <div>
                                    <div class="sub-row-name">🏬 <?= htmlspecialchars($sub['name']) ?></div>
                                    <?php if ($sub['status'] !== 'active'): ?>
                                        <span class="branch-type-badge badge-inactive" style="font-size:0.65rem;"><?= ucfirst($sub['status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sub-mini-stat">
                                    <span class="mini-chip">👥 <?= $sub['total_leads'] ?> leads</span>
                                    <span class="mini-chip dsr">📝 <?= $sub['total_dsrs'] ?> DSR</span>
                                    <span class="mini-chip rev">₹<?= number_format($sub['approved_revenue'], 0) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Standalone Sub-Branches (not linked to a main) -->
                    <?php foreach ($sub_branches as $sub):
                        if (isset($main_branches[$sub['parent_id']])) continue; // Already shown above
                    ?>
                    <div class="branch-card">
                        <div class="branch-card-header sub">
                            <div>
                                <div class="branch-name"><?= htmlspecialchars($sub['name']) ?></div>
                                <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">Parent: <?= htmlspecialchars($sub['parent_name'] ?? 'Unassigned') ?></div>
                            </div>
                            <span class="branch-type-badge badge-sub">Sub-Branch</span>
                        </div>
                        <div class="branch-stats">
                            <div class="bstat"><div class="bstat-val blue"><?= $sub['total_leads'] ?></div><div class="bstat-lbl">Leads</div></div>
                            <div class="bstat"><div class="bstat-val green"><?= $sub['converted_leads'] ?></div><div class="bstat-lbl">Converted</div></div>
                            <div class="bstat"><div class="bstat-val purple"><?= $sub['total_dsrs'] ?></div><div class="bstat-lbl">DSRs</div></div>
                            <div class="bstat"><div class="bstat-val orange"><?= $sub['today_dsrs'] ?></div><div class="bstat-lbl">Today</div></div>
                        </div>
                        <div class="branch-footer">
                            <span class="revenue-chip">₹<?= number_format($sub['approved_revenue'], 0) ?></span>
                            <a href="sub_branches.php?action=edit&id=<?= $sub['id'] ?>" style="font-size:0.8rem; color:var(--primary-color); font-weight:600;">Manage →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Incoming Demo Requests -->
        <div class="content-card" style="margin-bottom: 2.5rem; border-color: rgba(236,72,153,0.3);">
            <div class="card-header">
                <h2>Incoming Concierge Requests</h2>
                <a href="concierge_requests.php" style="color: #ec4899; font-size: 0.9rem;">View All →</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead><tr><th>Name</th><th>Company/Agency</th><th>Contact Info</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (count($recent_demo_requests) > 0): ?>
                            <?php foreach ($recent_demo_requests as $request): ?>
                                <tr>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($request['name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['company_name']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($request['email']); ?></div>
                                        <div style="color:var(--text-muted);font-size:0.85rem;"><?php echo htmlspecialchars($request['phone']); ?></div>
                                    </td>
                                    <td style="color:var(--text-muted);font-size:0.9rem;"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    <td><a href="process_demo.php?id=<?php echo $request['id']; ?>" class="btn btn-outline" style="color:#ec4899;border-color:#ec4899;font-size:0.8rem;padding:0.4rem 0.8rem;">Process Setup</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">No pending demo requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>
</html>
