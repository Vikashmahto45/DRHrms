<?php
// /admin/submit_payment.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $client = trim($_POST['client_name']);
    $date = $_POST['payment_date'];
    $category = $_POST['category'];
    $proof = $_FILES['proof_file'] ?? null;

    if ($amount > 0 && $client && $proof && $proof['error'] === 0) {
        $ext = pathinfo($proof['name'], PATHINFO_EXTENSION);
        $filename = "pay_" . time() . "_" . $cid . "." . $ext;
        $target = "../assets/uploads/payments/" . $filename;

        if (move_uploaded_file($proof['tmp_name'], $target)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO franchise_payments (company_id, amount, client_name, category, payment_date, proof_file, status) VALUES (?,?,?,?,?,?,'pending')");
                $stmt->execute([$cid, $amount, $client, $category, $date, $filename]);
                
                logActivity('payment_submitted', "Submitted payment: $amount for $client", $cid);
                $msg = "Payment submitted successfully! Waiting for verification.";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "Database error: " . $e->getMessage();
                $msgType = "error";
            }
        } else {
            $msg = "Failed to upload proof of payment.";
            $msgType = "error";
        }
    } else {
        $msg = "Please fill all fields and upload a valid proof screenshot.";
        $msgType = "error";
    }
}

// Fetch my recent submissions
$stmt = $pdo->prepare("SELECT * FROM franchise_payments WHERE company_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$cid]);
$recent_payments = $stmt->fetchAll();

// Fetch HQ Payment details
$hq_bank_details = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_bank_details'")->fetchColumn() ?: 'Not provided yet. Contact HQ.';
$hq_upi_id = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_upi_id'")->fetchColumn() ?: 'Not provided yet.';
$hq_upi_qr = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_upi_qr'")->fetchColumn() ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Payment - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <h1>Submit Payment / Sale</h1>
        <p style="color:var(--text-muted)">Report new sales and upload proof for verification.</p>
    </div>

    <!-- HQ Payment Info -->
    <div class="content-card" style="background: linear-gradient(135deg, #f8fafc, #fff); border-left: 4px solid #3b82f6; margin-bottom: 2rem;">
        <div class="card-header"><h4 style="margin:0; display:flex; align-items:center; gap:8px;">🏦 HQ Payment Details</h4></div>
        <p style="color:var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Please remit your Franchise Share to the following accounts before submitting payment proof below.</p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
            <div>
                <strong style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Bank Account Details:</strong>
                <pre style="background: #f1f5f9; padding: 12px; border-radius: 8px; font-family: inherit; font-size: 0.9rem; color: #334155; white-space: pre-wrap; margin-top: 8px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($hq_bank_details) ?></pre>
            </div>
            <div>
                <strong style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">UPI & QR Code:</strong>
                <div style="background: #f1f5f9; padding: 12px; border-radius: 8px; margin-top: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 1rem;">
                    <?php if ($hq_upi_qr): ?>
                        <div style="background:#fff; padding:4px; border-radius:6px; border:1px solid #cbd5e1;">
                            <img src="<?= BASE_URL ?>assets/uploads/qr/<?= htmlspecialchars($hq_upi_qr) ?>" style="width: 80px; height: 80px; object-fit: contain; display:block;">
                        </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size: 1rem; font-weight: 700; color: #3b82f6; word-break: break-all; margin-bottom: 5px;">
                            <?= htmlspecialchars($hq_upi_id) ?>
                        </div>
                        <small style="color:var(--text-muted)">Scan the QR or copy the UPI ID to pay.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap: 2rem;">
        <!-- Submission Form -->
        <div class="content-card">
            <div class="card-header"><h4>Payment Details</h4></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="1000.00">
                </div>
                <div class="form-group">
                    <label>Client Name *</label>
                    <input type="text" name="client_name" class="form-control" required placeholder="John Smith">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="New Sale">New Sale</option>
                            <option value="Renewal">Renewal</option>
                            <option value="Upgrade">Upgrade</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Proof of Payment (Screenshot) *</label>
                    <input type="file" name="proof_file" class="form-control" required accept="image/*">
                    <small style="color:var(--text-muted)">Upload a clear screenshot of the transaction receipt.</small>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Submit for Verification</button>
            </form>
        </div>

        <!-- History -->
        <div class="content-card">
            <div class="card-header"><h4>Recent Submissions</h4></div>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr><th>Client</th><th>Amount</th><th>Status</th><th>Submitted</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_payments as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['client_name']) ?></strong><br><small><?= $p['category'] ?></small></td>
                            <td>₹<?= number_format($p['amount'], 2) ?></td>
                            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td style="font-size:0.8rem; color:var(--text-muted)"><?= date('M d, H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!count($recent_payments)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted)">No submissions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
