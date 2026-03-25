<?php
// /superadmin/activity_logs.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$activity = $pdo->query("
    SELECT al.*, u.name AS user_name, c.name AS company_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN companies c ON al.company_id = c.id
    ORDER BY al.created_at DESC
    LIMIT 200
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Activity Logs - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>System Activity Logs</h1>
            <p style="color:var(--text-muted)">A master audit trail of all changes across the SaaS platform.</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Recent Activity (Last 200 logs)</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Company</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity as $log): ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size:0.85rem"><?= date('M d, H:i:s', strtotime($log['created_at'])) ?></td>
                        <td style="font-weight:600"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($log['company_name'] ?? 'Global / Super') ?></td>
                        <td><span class="badge badge-active"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td style="font-size:0.9rem"><?= htmlspecialchars($log['details']) ?></td>
                        <td style="color:var(--text-muted); font-size:0.8rem"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($activity)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem">No logs recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
