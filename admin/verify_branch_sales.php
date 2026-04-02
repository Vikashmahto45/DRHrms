<?php
// /admin/verify_branch_sales.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Strictly for Main Branch Admin (HQ)
checkAccess(['admin']);
$cid = (int)$_SESSION['company_id'];

$stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
$stmt->execute([$cid]);
if ($stmt->fetchColumn() == 0) {
    die("<div style='padding:3rem; text-align:center; font-family:sans-serif;'><h2>Access Denied</h2><p>Only the Main Branch (HQ) can verify financial submissions.</p><a href='dashboard.php'>Back to Dashboard</a></div>");
}

$msg = ''; $msgType = '';

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)$_POST['payment_id'];
    $admin_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT p.*, c.commission_percentage 
                FROM franchise_payments p 
                JOIN companies c ON p.company_id = c.id 
                WHERE p.id = ? AND p.status = 'pending'
            ");
            $stmt->execute([$pid]);
            $pay = $stmt->fetch();

            if ($pay) {
                $commission = $pay['commission_percentage'] ?: 20.00;
                $admin_cut = $pay['amount'] * ($commission / 100);
                $franchise_share = $pay['amount'] - $admin_cut;

                $stmt = $pdo->prepare("
                    UPDATE franchise_payments 
                    SET status = 'approved', admin_cut = ?, franchise_share = ?, approved_by = ?, approved_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$admin_cut, $franchise_share, $admin_id, $pid]);

                $pdo->commit();
                $msg = "Sale Verified: ₹" . number_format($pay['amount'],2) . " approved.";
                $msgType = "success";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    }

    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'Invalid proof');
        $stmt = $pdo->prepare("UPDATE franchise_payments SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$reason, $admin_id, $pid]);
        $msg = "Submission rejected.";
        $msgType = "warning";
    }
}

// Fetch Pending Sub-Branch Payments
$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, pr.name as product_name 
    FROM franchise_payments p 
    JOIN companies c ON p.company_id = c.id 
    LEFT JOIN products pr ON p.product_id = pr.id
    WHERE p.status = 'pending' AND c.is_main_branch = 0
    ORDER BY p.created_at DESC
");
$stmt->execute();
$pendings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Branch Sales (HQ) - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .verify-card { display: flex; gap: 2rem; background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem; align-items: flex-start; }
        .proof-img { width: 320px; height: 200px; object-fit: cover; border-radius: 8px; cursor: zoom-in; border: 1px solid #eee; }
        .data-box { flex: 1; }
        .action-btns { display: flex; gap: 10px; margin-top: 1.5rem; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="page-header">
            <div>
                <h1>Verify Branch Sales (HQ Only)</h1>
                <p style="color:var(--text-muted)">Authorize sub-branch income submissions and settle revenue shares.</p>
            </div>
        </div>

        <?php if (!count($pendings)): ?>
            <div class="content-card" style="text-align:center; padding:4rem;">
                <div style="font-size:3rem; margin-bottom:1rem;">✅</div>
                <h3>All caught up!</h3>
                <p style="color:var(--text-muted)">No branch sales are currently awaiting verification.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($pendings as $p): ?>
        <div class="verify-card">
            <div class="proof-view">
                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Proof Attachment</p>
                <a href="../assets/uploads/payments/<?= $p['proof_file'] ?>" target="_blank">
                    <img src="../assets/uploads/payments/<?= $p['proof_file'] ?>" class="proof-img" alt="Proof">
                </a>
            </div>
            <div class="data-box">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:0.8rem;">
                    <h3 style="margin:0;"><?= htmlspecialchars($p['company_name']) ?></h3>
                    <span style="font-size:0.75rem; background:#eef2ff; color:#4f46e5; padding:2px 8px; border-radius:4px; font-weight:600;">🏢 Sub-Branch Sale</span>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted)">Client Name</label>
                        <div style="font-weight:600;"><?= htmlspecialchars($p['client_name']) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted)">Amount</label>
                        <div style="font-weight:700; color:#10b981; font-size:1.2rem;">₹<?= number_format($p['amount'], 2) ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted)">Service/Product</label>
                        <div style="font-weight:600; color:#6366f1;"><?= htmlspecialchars($p['product_name'] ?? $p['category'] ?? 'General') ?></div>
                    </div>
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted)">Transaction Date</label>
                        <div><?= date('M d, Y', strtotime($p['payment_date'])) ?></div>
                    </div>
                </div>

                <div class="action-btns">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                        <button class="btn btn-primary" style="width:100%; background:#10b981; border-color:#10b981;">✅ Approve Sale</button>
                    </form>
                    <div style="flex:1;">
                        <button class="btn btn-danger" style="width:100%;" onclick="showReject('<?= $p['id'] ?>')">❌ Reject</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')">&times;</button>
        <h3>Reject Submission</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="payment_id" id="reject_pid">
            <div class="form-group">
                <label>Reason for Rejection *</label>
                <textarea name="reason" class="form-control" required placeholder="e.g. Inconsistent amount or blurry proof..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('rejectModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-danger" style="flex:1">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function showReject(id) {
    document.getElementById('reject_pid').value = id;
    document.getElementById('rejectModal').classList.add('open');
}
</script>
</body>
</html>
