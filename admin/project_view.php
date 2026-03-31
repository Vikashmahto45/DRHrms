<?php
// /admin/project_view.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$pid = (int)($_GET['id'] ?? 0);

// Fetch Project
$stmt = $pdo->prepare("SELECT p.*, u.name as salesperson_name FROM projects p JOIN users u ON p.sales_person_id = u.id WHERE p.id = ? AND p.company_id = ?");
$stmt->execute([$pid, $cid]);
$p = $stmt->fetch();

if (!$p) { die("Project not found."); }

// Handle Progress Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    $new_progress = (int)$_POST['progress_pct'];
    $comment = trim($_POST['comment'] ?? '');
    $old_progress = $p['progress_pct'];

    if ($new_progress >= 0 && $new_progress <= 100) {
        $pdo->beginTransaction();
        // Update Project
        $pdo->prepare("UPDATE projects SET progress_pct = ?, status = ? WHERE id = ?")
            ->execute([$new_progress, ($new_progress == 100 ? 'Completed' : 'Active'), $pid]);
        
        // Log Update
        $pdo->prepare("INSERT INTO project_logs (project_id, user_id, old_progress, new_progress, comment) VALUES (?,?,?,?,?)")
            ->execute([$pid, $uid, $old_progress, $new_progress, $comment]);
            
        $pdo->commit();
        header("Location: project_view.php?id=$pid&msg=Progress Updated"); exit();
    }
}

// Fetch Logs
$log_stmt = $pdo->prepare("SELECT l.*, u.name as updater_name FROM project_logs l JOIN users u ON l.user_id = u.id WHERE l.project_id = ? ORDER BY l.created_at DESC");
$log_stmt->execute([$pid]);
$logs = $log_stmt->fetchAll();
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
        .progress-circle { width: 100px; height: 100px; border-radius: 50%; border: 8px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0 auto 10px auto; border-top-color: var(--primary-color); transform: rotate(-45deg); }
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
            <div class="badge <?= $p['status'] === 'Active' ? 'st-Active' : 'st-Pending' ?>"><?= $p['status'] ?></div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 300px; gap: 2rem;">
            <!-- Left: Logs & Details -->
            <div>
                <div class="content-card">
                    <h3>Project Information</h3>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-top:1rem;">
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">CLIENT</label>
                            <div style="font-weight:600;"><?= htmlspecialchars($p['client_name']) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">SALES PERSON</label>
                            <div style="font-weight:600;"><?= htmlspecialchars($p['salesperson_name']) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">TOTAL VALUE</label>
                            <div style="font-weight:600; color:#10b981;">₹<?= number_format($p['total_value'], 2) ?></div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; color:var(--text-muted);">ADVANCE PAID</label>
                            <div style="font-weight:600; color:#3b82f6;">₹<?= number_format($p['advance_paid'], 2) ?></div>
                        </div>
                    </div>
                </div>

                <div class="content-card" style="margin-top:2rem;">
                    <h3>Progress Timeline</h3>
                    <div style="margin-top:2rem;">
                        <?php foreach($logs as $l): ?>
                        <div class="log-item">
                            <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></div>
                            <div style="font-weight:600; margin:5px 0;">Updated to <?= $l['new_progress'] ?>% <span style="font-weight:400; color:var(--text-muted); font-size:0.8rem;">(Was <?= $l['old_progress'] ?>%)</span></div>
                            <p style="font-size:0.9rem; margin:0; color:#475569;"><?= nl2br(htmlspecialchars($l['comment'])) ?></p>
                            <div style="font-size:0.75rem; color:var(--primary-color); margin-top:5px;">By: <?= htmlspecialchars($l['updater_name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <div class="log-item">
                            <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                            <div style="font-weight:600; margin:5px 0;">Project Created</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Update Form -->
            <div>
                <div class="content-card progress-indicator">
                    <div class="progress-circle" style="transform: rotate(<?= ($p['progress_pct'] * 3.6 - 45) ?>deg);">
                        <span style="transform: rotate(<?= -($p['progress_pct'] * 3.6 - 45) ?>deg);"><?= $p['progress_pct'] ?>%</span>
                    </div>
                    <p style="font-weight:600; color:#1e293b;">Overall Progress</p>
                </div>

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
                            <textarea name="comment" class="form-control" rows="3" placeholder="What task was completed?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">Post Update</button>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
