<?php
// /superadmin/projects.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = $_GET['msg'] ?? ''; $msgType = $_GET['msgType'] ?? 'success';

// Fetch ALL Projects with Branch Info
$stmt = $pdo->prepare("
    SELECT p.*, u.name as salesperson_name, c.name as branch_name 
    FROM projects p 
    LEFT JOIN users u ON p.sales_person_id = u.id 
    LEFT JOIN companies c ON p.branch_id = c.id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$results = $stmt->fetchAll();

$projects = [];
foreach($results as $res) {
    if(empty($res['salesperson_name']) && !empty($res['custom_sales_name'])) {
        $res['salesperson_name'] = $res['custom_sales_name'];
    }
    $projects[] = $res;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Project Tracker - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .src-tag { font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-top: 5px; display: inline-block; }
        .src-Meta { background: rgba(24,119,242,0.1); color: #1877f2; border: 1px solid rgba(24,119,242,0.2); }
        .src-Google { background: rgba(66,133,244,0.1); color: #4285f4; border: 1px solid rgba(66,133,244,0.2); }
        .src-Referral { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .src-Walk-in { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }

        .progress-bar-container { background: #e2e8f0; border-radius: 20px; height: 10px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { background: var(--primary-color); height: 100%; transition: width 0.3s; }
        .st-Pending { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .st-Pending-HQ-Review { background: rgba(239,68,68,0.1); color: #ef4444; }
        .st-Active { background: rgba(16,185,129,0.1); color: #10b981; }
        .st-Hold { background: rgba(107,114,128,0.1); color: #6b7280; }
        .st-Completed { background: rgba(16,185,129,0.1); color: #10b981; }
        .project-card { border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.5rem; background: #fff; margin-bottom: 1.5rem; transition: transform 0.2s; }
        .project-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Global Project Tracker</h1>
            <p style="color:var(--text-muted)">Oversee all branch projects and their execution status across the platform.</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem;">
        <?php foreach ($projects as $p): 
            $status_class = "st-" . str_replace(' ', '-', $p['status']);
        ?>
        <div class="project-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem;"><?= htmlspecialchars($p['project_name']) ?></h3>
                    <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">Branch: <strong style="color:var(--primary-color);"><?= htmlspecialchars($p['branch_name'] ?: 'Main Branch') ?></strong></div>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Client: <?= htmlspecialchars($p['client_name']) ?></div>
                    <?php 
                        $src_class = "src-" . explode(' ', $p['source'] ?? 'Walk-in')[0];
                    ?>
                    <span class="src-tag <?= $src_class ?>"><?= htmlspecialchars($p['source']) ?></span>
                </div>
                <span class="badge <?= $status_class ?>" style="font-size:0.75rem;"><?= $p['status'] ?></span>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom: 5px;">
                    <span>Execution Progress</span>
                    <strong><?= $p['progress_pct'] ?>%</strong>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $p['progress_pct'] ?>%;"></div>
                </div>
                <?php if($p['end_date']): ?>
                    <div style="font-size:0.75rem; color:#ef4444; margin-top:8px; font-weight:600;">
                        📅 Deadline: <?= date('d M, Y', strtotime($p['end_date'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:15px;">
                <div style="font-size:0.8rem; color:var(--text-muted);">
                    👤 Staff: <?= htmlspecialchars($p['salesperson_name'] ?: 'Unassigned') ?>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Full Monitoring</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($projects)): ?>
            <div class="content-card" style="grid-column: 1 / -1; text-align:center; padding:4rem; color:var(--text-muted);">
                No active projects found in any branch.
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
