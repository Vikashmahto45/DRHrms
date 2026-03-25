<?php
// /superadmin/expiry_alerts.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

// Fetch companies expiring in the next 7 days
$stmt = $pdo->query("
    SELECT c.*, DATEDIFF(c.subscription_end_date, NOW()) AS days_left
    FROM companies c
    WHERE c.subscription_end_date IS NOT NULL 
    AND DATEDIFF(c.subscription_end_date, NOW()) <= 7
    AND c.status = 'active'
    ORDER BY c.subscription_end_date ASC
");
$expiring_companies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expiry Alerts - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Subscription Expiry Alerts</h1>
            <p style="color:var(--text-muted)">Companies expiring within the next 7 days.</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Critical Expiries (<?= count($expiring_companies) ?>)</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Expiry Date</th>
                        <th>Days Remaining</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiring_companies as $c): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= date('M d, Y', strtotime($c['subscription_end_date'])) ?></td>
                        <td>
                            <?php if ($c['days_left'] < 0): ?>
                                <span style="color:#ef4444; font-weight:700">Expired (<?= abs($c['days_left']) ?> days ago)</span>
                            <?php else: ?>
                                <span style="color:#f59e0b; font-weight:700"><?= $c['days_left'] ?> days left</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="companies.php" style="display:inline">
                                <input type="hidden" name="action" value="renew">
                                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="border-color:#10b981;color:#10b981">Renew 1 Month</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($expiring_companies)): ?>
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem">No companies are expiring soon. Great job!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
