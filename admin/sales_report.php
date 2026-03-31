<?php
// /admin/sales_report.php
require_once '../includes/auth.php';
require_once '../config/database.php';
ini_set('display_errors', 1); error_reporting(E_ALL);
checkAccess(['admin', 'manager']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];

// Get Date Filters (Default to this month)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch Accessible Branch IDs for HQ visibility
$branch_ids = getAccessibleBranchIds($pdo, $cid);
if (empty($branch_ids)) { $branch_ids = [$cid]; } // Safety fallback
$cids_in = implode(',', $branch_ids);

// 0. Build/Patch Schema for live environment
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsr_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dsr_id INT NOT NULL,
        product_id INT NULL,
        custom_price DECIMAL(15,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'branch_id'");
    if (!$stmt->fetch()) { $pdo->exec("ALTER TABLE projects ADD COLUMN branch_id INT NULL AFTER company_id"); }
} catch (Exception $e) { /* Patching error handled silent */ }

/** 
 * 1. UNIFIED REVENUE QUERY 
 * Aggregates:
 * - A: DSR Items (On-field deals won)
 * - B: Verified Projects (Office/Large contracts)
 */
$query = "
    (
        SELECT 
            d.visit_date as sale_date,
            d.client_name,
            p.name as item_name,
            di.custom_price as amount,
            u.name as staff_name,
            c.name as branch_name,
            c.id as branch_id,
            'DSR Deal' as sale_type
        FROM dsr d
        JOIN dsr_items di ON d.id = di.dsr_id
        JOIN products p ON di.product_id = p.id
        JOIN users u ON d.user_id = u.id
        JOIN companies c ON d.company_id = c.id
        WHERE d.company_id IN ($cids_in) 
        AND d.deal_status = 'Closed Won'
        AND d.visit_date BETWEEN ? AND ?
    )
    UNION ALL
    (
        SELECT 
            DATE(p.created_at) as sale_date,
            p.client_name,
            p.project_name as item_name,
            p.total_value as amount,
            COALESCE(u.name, p.custom_sales_name, 'HQ Assigned') as staff_name,
            c.name as branch_name,
            c.id as branch_id,
            'Project' as sale_type
        FROM projects p
        JOIN companies c ON p.branch_id = c.id
        LEFT JOIN users u ON p.sales_person_id = u.id
        WHERE p.branch_id IN ($cids_in)
        AND p.is_verified = 1
        AND DATE(p.created_at) BETWEEN ? AND ?
    )
    ORDER BY sale_date DESC
";

$sales = []; $sql_error = '';
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $sales = $stmt->fetchAll();
} catch (Exception $e) {
    $sql_error = $e->getMessage();
}

// 2. Summary Stats
$total_revenue = 0;
$dsr_revenue = 0;
$proj_revenue = 0;
foreach ($sales as $s) {
    if ($s['sale_type'] === 'DSR Deal') $dsr_revenue += $s['amount'];
    else $proj_revenue += $s['amount'];
    $total_revenue += (float)$s['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Sales Report (Branch History) - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .sales-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); box-shadow: var(--shadow-sm); }
        .stat-val { font-size: 1.6rem; font-weight: 800; color: #10b981; margin-top: 5px; }
        .type-badge { font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; }
        .type-DSR { background: #eef2ff; color: #6366f1; border: 1px solid #c7d2fe; }
        .type-Project { background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8; }
        
        .filter-bar { background: #fff; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: flex-end; border: 1px solid var(--glass-border); }
        @media (max-width: 768px) { .filter-bar { flex-direction: column; } }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Unified Sales & Revenue Report</h1>
                <p style="color:var(--text-muted)">Consolidated income from DSR Deals and Verified Office Projects.</p>
            </div>
            <button onclick="window.print()" class="btn btn-outline">🖨️ Print Report</button>
        </div>

        <?php if ($sql_error): ?>
            <div class="alert alert-danger" style="margin-bottom: 2rem; background: #fee2e2; border: 1px solid #ef4444; padding: 1.5rem; border-radius: 8px; color: #b91c1c;">
                <strong>⚠️ Database Synchronization Issue:</strong><br>
                <?= htmlspecialchars($sql_error) ?><br><br>
                <em>Action Required: Ensure the DSR and Projects tables are up to date.</em>
            </div>
        <?php endif; ?>

        <!-- Date Filters -->
        <form class="filter-bar no-print">
            <div class="form-group" style="margin-bottom:0;">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="height:42px;">Filter Results</button>
            <a href="sales_report.php" class="btn btn-outline" style="height:42px; display:flex; align-items:center;">Reset</a>
        </form>

        <div class="sales-stats">
            <div class="stat-card">
                <span style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">CONSIDERED REVENUE</span>
                <div class="stat-val">₹<?= number_format($total_revenue, 2) ?></div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">PROJECT SALES</span>
                <div class="stat-val" style="color:#db2777;">₹<?= number_format($proj_revenue, 2) ?></div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">FIELD DEALS (DSR)</span>
                <div class="stat-val" style="color:#6366f1;">₹<?= number_format($dsr_revenue, 2) ?></div>
            </div>
            <div class="stat-card">
                <span style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">TOTAL TRANSACTIONS</span>
                <div class="stat-val" style="color:var(--text-main);"><?= count($sales) ?></div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Sales Ledger Overview (<?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>)</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entry Type</th>
                            <th>Client Name</th>
                            <th>Item / Project</th>
                            <th>Amount</th>
                            <th>Staff Member</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><?= date('d M, Y', strtotime($s['sale_date'])) ?></td>
                            <td><span class="type-badge type-<?= $s['sale_type'] === 'DSR Deal' ? 'DSR' : 'Project' ?>"><?= $s['sale_type'] ?></span></td>
                            <td><strong><?= htmlspecialchars($s['client_name']) ?></strong></td>
                            <td style="font-size:0.9rem;"><?= htmlspecialchars($s['item_name']) ?></td>
                            <td style="font-weight:700; color:#10b981;">₹<?= number_format((float)$s['amount'], 2) ?></td>
                            <td style="font-size:0.9rem;"><?= htmlspecialchars($s['staff_name']) ?></td>
                            <td>
                                <?php if ($s['branch_id'] == $cid): ?>
                                    <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">🏠 Main Branch</span>
                                <?php else: ?>
                                    <span style="font-size:0.85rem; background:#eff6ff; color:#1e40af; padding:3px 8px; border-radius:6px; font-weight:600;">🏢 <?= htmlspecialchars($s['branch_name']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                                No sales found for the selected date range. Ensure deals are marked as "Closed Won" or Projects are "Verified" by HQ.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
