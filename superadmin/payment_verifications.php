<?php
// /superadmin/payment_verifications.php
require_once '../includes/auth.php';
require_once '../config/database.php';
// Custom check for roles
$role = strtolower($_SESSION['sa_user_role'] ?? $_SESSION['user_role'] ?? '');
$cid = (int)($_SESSION['company_id'] ?? 0);

if ($role !== 'super_admin' && $role !== 'admin') {
    header("Location: ../login.php"); exit();
}

// If admin, double check they are a main branch
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
    $stmt->execute([$cid]);
    if ($stmt->fetchColumn() == 0) {
        die("Access Denied: You do not have financial verification authority.");
    }
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

            // 1. Get Payment & Company Commission
            $stmt = $pdo->prepare("
                SELECT p.*, c.commission_percentage 
                FROM franchise_payments p 
                JOIN companies c ON p.company_id = c.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$pid]);
            $pay = $stmt->fetch();

            if ($pay && $pay['status'] === 'pending') {
                $commission = $pay['commission_percentage'] ?: 20.00;
                $admin_cut = $pay['amount'] * ($commission / 100);
                $franchise_share = $pay['amount'] - $admin_cut;

                // 2. Update Payment Record
                $stmt = $pdo->prepare("
                    UPDATE franchise_payments 
                    SET status = 'approved', admin_cut = ?, franchise_share = ?, approved_by = ?, approved_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$admin_cut, $franchise_share, $admin_id, $pid]);

                $pdo->commit();
                $msg = "Payment approved and revenue split: Admin (₹" . number_format($admin_cut,2) . "), Franchise (₹" . number_format($franchise_share,2) . ")";
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
        $msg = "Payment rejected.";
        $msgType = "warning";
    }
}

// Fetch Pending Payments based on Role
if ($role === 'super_admin') {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name 
        FROM franchise_payments p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.status = 'pending' 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
} else {
    // Main Admin: Only see payments from SUB-BRANCHES assigned to them
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name 
        FROM franchise_payments p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.status = 'pending' AND c.parent_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$cid]);
}
$pendings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verifications - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434221">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434221">
    <style>
        .verify-card { display: flex; gap: 2rem; background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem; align-items: flex-start; }
        .proof-img { width: 300px; border-radius: 8px; cursor: zoom-in; border: 1px solid #eee; }
        .data-box { flex: 1; }
        .action-btns { display: flex; gap: 10px; margin-top: 1.5rem; }
    </style>
</head>
<body>
<?php
// Include the correct sidebar based on role
if ($role === 'super_admin') {
    include 'includes/sidebar.php';
} else {
    include '../admin/includes/sidebar.php';
}
?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <h1>Payment Verifications</h1>
        <p style="color:var(--text-muted)">Review franchise sale submissions and settle revenue shares.</p>
    </div>

    <?php if (!count($pendings)): ?>
        <div class="content-card" style="text-align:center; padding:4rem;">
            <div style="font-size:3rem; margin-bottom:1rem;">✅</div>
            <h3>All caught up!</h3>
            <p style="color:var(--text-muted)">No pending payments requiring verification.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($pendings as $p): ?>
    <div class="verify-card">
        <div class="proof-view">
            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Proof of Payment</p>
            <a href="../assets/uploads/payments/<?= $p['proof_file'] ?>" target="_blank">
                <img src="../assets/uploads/payments/<?= $p['proof_file'] ?>" class="proof-img" alt="Proof">
            </a>
        </div>
        <div class="data-box">
            <h3 style="margin-bottom:0.5rem;"><?= htmlspecialchars($p['company_name']) ?></h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted)">Client</label>
                    <div style="font-weight:600;"><?= htmlspecialchars($p['client_name']) ?></div>
                </div>
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted)">Amount</label>
                    <div style="font-weight:700; color:var(--primary-color); font-size:1.2rem;">₹<?= number_format($p['amount'], 2) ?></div>
                </div>
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted)">Category</label>
                    <div><?= $p['category'] ?></div>
                </div>
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted)">Payment Date</label>
                    <div><?= date('M d, Y', strtotime($p['payment_date'])) ?></div>
                </div>
            </div>

            <div class="action-btns">
                <form method="POST" style="flex:1;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-primary" style="width:100%; background:#10b981; border-color:#10b981;">Approve & Split Revenue</button>
                </form>
                <div style="flex:1;">
                    <button class="btn btn-danger" style="width:100%;" onclick="showReject('<?= $p['id'] ?>')">Reject</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</main>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Reject Payment</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="payment_id" id="reject_pid">
            <div class="form-group">
                <label>Reason for Rejection *</label>
                <textarea name="reason" class="form-control" required placeholder="e.g. Screenshot blurry, amount mismatch..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('rejectModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Reject</button>
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
