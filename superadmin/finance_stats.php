<?php
// /superadmin/finance_stats.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$role = strtolower($_SESSION['sa_user_role'] ?? $_SESSION['user_role'] ?? '');
$cid = (int)($_SESSION['company_id'] ?? 0);

if ($role === 'super_admin') {
    $branch_ids = []; // Super Admin sees overall system
} else {
    checkAccess(['admin']);
    $stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
    $stmt->execute([$cid]);
    if ($stmt->fetchColumn() == 0) {
        die("Access Denied: You do not have global financial oversight.");
    }
    $branch_ids = getAccessibleBranchIds($pdo, $cid);
}

$cids_in = !empty($branch_ids) ? implode(',', $branch_ids) : 'all';

// 1. Global Summaries
$sql = "SELECT SUM(amount) as global_sales, SUM(admin_cut) as total_commissions, SUM(franchise_share) as total_franchise_payouts, COUNT(id) as total_transactions FROM franchise_payments WHERE status = 'approved'";
if ($cids_in !== 'all') {
    $sql .= " AND company_id IN ($cids_in)";
}
$totals = $pdo->query($sql)->fetch();

// 2. Performance by Company
$sql2 = "SELECT c.name as company_name, SUM(p.amount) as total_generated, SUM(p.admin_cut) as commission_earned, COUNT(p.id) as approved_count FROM franchise_payments p JOIN companies c ON p.company_id = c.id WHERE p.status = 'approved'";
if ($cids_in !== 'all') {
    $sql2 .= " AND p.company_id IN ($cids_in)";
}
$sql2 .= " GROUP BY c.id ORDER BY total_generated DESC";
$company_performance = $pdo->query($sql2)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Financial Stats - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        .stat-banner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .banner-card { background: #fff; padding: 2rem; border-radius: 16px; border: 1px solid var(--glass-border); text-align: center; }
        .banner-label { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .banner-val { font-size: 2.2rem; font-weight: 800; margin: 1rem 0; color: var(--text-main); }
        @media (max-width: 768px) {
            .stat-banner { grid-template-columns: 1fr !important; gap: 1rem !important; }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1>Global Financial Overview</h1>
        <p style="color:var(--text-muted)">Real-time performance metrics across the entire platform ecosystem.</p>
    </div>

    <div class="stat-banner">
        <div class="banner-card">
            <span class="banner-label">Platform Gross Sale</span>
            <div class="banner-val">₹<?= number_format($totals['global_sales'] ?: 0, 0) ?></div>
            <p style="color:#10b981; font-size:0.8rem;">↑ Total Volume</p>
        </div>
        <div class="banner-card" style="border-top: 5px solid var(--primary-color);">
            <span class="banner-label">Super Admin Profit</span>
            <div class="banner-val" style="color:var(--primary-color);">₹<?= number_format($totals['total_commissions'] ?: 0, 0) ?></div>
            <p style="color:var(--text-muted); font-size:0.8rem;">Total Commissions</p>
        </div>
        <div class="banner-card">
            <span class="banner-label">Franchise Earnings</span>
            <div class="banner-val">₹<?= number_format($totals['total_franchise_payouts'] ?: 0, 0) ?></div>
            <p style="color:var(--text-muted); font-size:0.8rem;">Paid to Partners</p>
        </div>
        <div class="banner-card">
            <span class="banner-label">Settled Assets</span>
            <div class="banner-val"><?= $totals['total_transactions'] ?: 0 ?></div>
            <p style="color:var(--text-muted); font-size:0.8rem;">Approved Records</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h4>Performance by Agency / Franchise</h4>
        </div>
        <table class="table">
            <thead>
                <tr><th>Company Name</th><th>Sales Volume</th><th>Commission (Your Cut)</th><th>Orders</th><th>Rank</th></tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach($company_performance as $cp): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($cp['company_name']) ?></strong>
                    </td>
                    <td style="font-weight:600;">₹<?= number_format($cp['total_generated'], 2) ?></td>
                    <td style="color:var(--primary-color); font-weight:600;">₹<?= number_format($cp['commission_earned'], 2) ?></td>
                    <td><?= $cp['approved_count'] ?></td>
                    <td><span class="badge" style="background:#f1f5f9; color:#1e293b;">#<?= $rank++ ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if(!count($company_performance)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted)">No approved financial data available yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
