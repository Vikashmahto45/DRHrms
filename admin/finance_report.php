<?php
// /admin/finance_report.php
require_once '../includes/auth.php';
require_once '../config/database.php';
ini_set('display_errors', 1); error_reporting(E_ALL);
checkAccess('admin');

$cid = $_SESSION['company_id'];

// 1. Stats
$stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as total_generated,
        SUM(CASE WHEN status = 'approved' THEN franchise_share ELSE 0 END) as settled_earnings,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_verification
    FROM franchise_payments 
    WHERE company_id = ?
");
$stmt->execute([$cid]);
$stats = $stmt->fetch();

// 2. Recent Ledger
$stmt = $pdo->prepare("
    SELECT f.*, p.name as product_name 
    FROM franchise_payments f 
    LEFT JOIN products p ON f.product_id = p.id 
    WHERE f.company_id = ? 
    ORDER BY f.created_at DESC
");
$stmt->execute([$cid]);
$ledger = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Report - Franchise</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); }
        .stat-val { font-size: 1.8rem; font-weight: 700; color: var(--text-main); margin: 0.5rem 0; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Finance Report</h1>
            <p style="color:var(--text-muted)">Transparency of your earnings and transaction status.</p>
        </div>
        <button onclick="downloadPDF()" class="btn btn-primary" style="padding:0.6rem 1.2rem;">📄 Download PDF</button>
    </div>

    <div id="pdf-content" style="padding: 10px; background: #f8fafc;">
        <div style="text-align:center; margin-bottom:20px; display:none;" id="pdf-header">
            <h2 style="margin-bottom:5px;">Branch Finance Report</h2>
            <p style="color:#64748b;">Generated on <?= date('d M, Y') ?></p>
        </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span style="color:var(--text-muted); font-size:0.9rem;">Total Sales Generated</span>
            <div class="stat-val">₹<?= number_format((float)($stats['total_generated'] ?? 0), 2) ?></div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #10b981;">
            <span style="color:var(--text-muted); font-size:0.9rem;">Settled Earnings (Your Cut)</span>
            <div class="stat-val" style="color:#10b981;">₹<?= number_format((float)($stats['settled_earnings'] ?? 0), 2) ?></div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
            <span style="color:var(--text-muted); font-size:0.9rem;">Pending Verification</span>
            <div class="stat-val" style="color:#f59e0b;">₹<?= number_format((float)($stats['pending_verification'] ?? 0), 2) ?></div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h4>Transaction Ledger</h4></div>
        <table class="table">
            <thead>
                <tr><th>Client</th><th>Total Amount</th><th>Your Share</th><th>Platform Fee</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach($ledger as $l): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($l['client_name']) ?></strong>
                        <?php if ($l['product_name']): ?><br><small style="color:var(--primary-color)">📦 <?= htmlspecialchars($l['product_name']) ?></small><?php endif; ?>
                    </td>
                    <td>₹<?= number_format($l['amount'], 2) ?></td>
                    <td style="font-weight:600; color:#10b981;">₹<?= $l['status']==='approved' ? number_format($l['franchise_share'], 2) : '-' ?></td>
                    <td style="color:var(--text-muted);">₹<?= $l['status']==='approved' ? number_format($l['admin_cut'], 2) : '-' ?></td>
                    <td>
                        <span class="badge badge-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span>
                        <?php if($l['status']==='rejected'): ?>
                            <br><small style="color:#ef4444;"><?= htmlspecialchars($l['rejection_reason']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(!count($ledger)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted)">No transactions recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div> <!-- end content card -->
    </div> <!-- end pdf content -->
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    document.getElementById('pdf-header').style.display = 'block';
    
    var element = document.getElementById('pdf-content');
    var opt = {
      margin:       [0.5, 0.5],
      filename:     'Branch_Finance_Report.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        document.getElementById('pdf-header').style.display = 'none';
    });
}
</script>
</body>
</html>
