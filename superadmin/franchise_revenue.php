<?php
// /superadmin/franchise_revenue.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

// Fetch all branches with their sales and commission rates
$stmt = $pdo->query("
    SELECT 
        c.id, c.name, c.is_main_branch, c.commission_rate, c.status,
        (SELECT COALESCE(SUM(amount), 0) FROM franchise_payments WHERE company_id = c.id AND status = 'approved') as total_gross_sales,
        (SELECT COUNT(*) FROM franchise_payments WHERE company_id = c.id AND status = 'approved') as total_transactions
    FROM companies c
    WHERE c.is_main_branch = 0
    ORDER BY total_gross_sales DESC
");
$branches = $stmt->fetchAll();

$system_gross = 0;
$system_hq_cut = 0;

foreach ($branches as $b) {
    // commission_rate is typically what the franchise KEEPS (e.g. 80%)
    // so HQ gets (100 - commission_rate) %
    $hq_percent = 100 - (float)$b['commission_rate'];
    
    $system_gross += $b['total_gross_sales'];
    $system_hq_cut += ($b['total_gross_sales'] * ($hq_percent / 100));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Revenue - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        .rev-stats { display: grid; grid-template-columns: repeat(2,1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
        @media (max-width: 768px) { .rev-stats { grid-template-columns: 1fr !important; } }
        .page-header { flex-wrap: wrap; gap: 1rem; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Franchise Revenue Share</h1>
            <p style="color:var(--text-muted)">Track sub-branch sales performance and HQ royalty earnings.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="downloadPDF()" class="btn btn-primary">📄 Download PDF</button>
            <a href="settings.php" class="btn btn-outline">Update HQ Bank Details</a>
        </div>
    </div>

    <div id="pdf-content" style="padding: 10px; background: #f8fafc;">
        <div style="text-align:center; margin-bottom:20px; display:none;" id="pdf-header">
            <h2 style="margin-bottom:5px;">DRHrms Headquarters</h2>
            <p style="color:#64748b;">Franchise Revenue Report — Generated on <?= date('d M, Y') ?></p>
        </div>

    <div class="rev-stats">
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1.5rem;background:linear-gradient(135deg,#f8faff,#fff);border-left:4px solid #3b82f6;">
            <div style="width:60px;height:60px;border-radius:12px;background:rgba(59,130,246,.1);display:flex;align-items:center;justify-content:center;font-size:1.8rem;">📈</div>
            <div>
                <div style="color:var(--text-muted);font-size:.9rem;font-weight:600;text-transform:uppercase;">Total Franchise Gross Sales</div>
                <div style="font-size:2rem;font-weight:800;color:#0f172a;">₹<?= number_format($system_gross, 2) ?></div>
            </div>
        </div>
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1.5rem;background:linear-gradient(135deg,#f0fdf4,#fff);border-left:4px solid #10b981;">
            <div style="width:60px;height:60px;border-radius:12px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.8rem;">💰</div>
            <div>
                <div style="color:var(--text-muted);font-size:.9rem;font-weight:600;text-transform:uppercase;">Total HQ Share (Earned)</div>
                <div style="font-size:2rem;font-weight:800;color:#10b981;">₹<?= number_format($system_hq_cut, 2) ?></div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Sub-Branch Performance</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Transactions</th>
                        <th>Deal (Branch : HQ)</th>
                        <th>Total Gross Sales</th>
                        <th>Branch Net Earning</th>
                        <th style="background:#f0fdf4;">HQ Share (Actionable)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): 
                        $branch_pct = (float)$b['commission_rate'];
                        $hq_pct = 100 - $branch_pct;
                        
                        $gross = (float)$b['total_gross_sales'];
                        $branch_cut = $gross * ($branch_pct / 100);
                        $hq_cut = $gross * ($hq_pct / 100);
                    ?>
                    <tr>
                        <td>
                            <strong style="color: #0f172a; font-size: 1.05rem;"><?= htmlspecialchars($b['name']) ?></strong><br>
                            <?php if ($b['status'] !== 'active'): ?>
                                <span class="badge badge-inactive" style="font-size: 0.65rem; margin-top: 4px; display: inline-block;">Inactive</span>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Active Franchise</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600; color: #64748b;"><?= $b['total_transactions'] ?></td>
                        <td>
                            <div style="display:inline-flex; align-items:center; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; font-size:0.8rem; font-weight:700;">
                                <span style="padding:4px 8px; background:#f1f5f9; color:#475569; border-right:1px solid #e2e8f0;" title="Branch retains <?= $branch_pct ?>%"><?= $branch_pct ?>%</span>
                                <span style="padding:4px 8px; background:#ecfdf5; color:#059669;" title="HQ takes <?= $hq_pct ?>%"><?= $hq_pct ?>%</span>
                            </div>
                        </td>
                        <td style="font-weight: 700; color: #3b82f6;">₹<?= number_format($gross, 2) ?></td>
                        <td style="font-weight: 600; color: #64748b;">₹<?= number_format($branch_cut, 2) ?></td>
                        <td style="background:#f0fdf4; font-weight: 800; color: #10b981; font-size: 1.1rem;">₹<?= number_format($hq_cut, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($branches)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No sub-branches found yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- end content-card -->
    </div> <!-- end pdf-content -->
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    // Show hidden PDF header for the print
    document.getElementById('pdf-header').style.display = 'block';
    
    var element = document.getElementById('pdf-content');
    var opt = {
      margin:       0.5,
      filename:     'HQ_Franchise_Revenue_Report.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        // Hide header again
        document.getElementById('pdf-header').style.display = 'none';
    });
}
</script>
</body>
</html>
