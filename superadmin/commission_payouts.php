<?php
// /superadmin/commission_payouts.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_paid') {
        $id = (int)$_POST['payment_id'];
        $pdo->prepare("UPDATE franchise_payments SET payout_status = 'paid', payout_date = NOW() WHERE id = ? AND status = 'approved'")->execute([$id]);
        $msg = "Commission payment marked as PAID."; $msgType = "success";
    }
}

// Fetch all approved payments
$query = "
    SELECT p.*, c.name as company_name, c.is_main_branch, parent.name as parent_name
    FROM franchise_payments p
    JOIN companies c ON p.company_id = c.id
    LEFT JOIN companies parent ON c.parent_id = parent.id
    WHERE p.status = 'approved'
    ORDER BY p.payout_status ASC, p.approved_at DESC
";
$payments = $pdo->query($query)->fetchAll();

// Grouping by payout status for stats
$pending_total = 0; $paid_total = 0;
foreach($payments as $p) {
    if ($p['payout_status'] === 'pending') $pending_total += $p['admin_cut'];
    else $paid_total += $p['admin_cut'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Payouts - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439732">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439732">
    <style>
        .payout-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background:#fff; padding:1.5rem; border-radius:12px; border:1px solid var(--glass-border); display:flex; justify-content:space-between; align-items:center; }
        .stat-val { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); }
        .payout-paid { color: #10b981; }
        .payout-pending { color: #f59e0b; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Franchise Payout Manager</h1>
        <p style="color:var(--text-muted)">Track and settle commissions with your branch partners.</p>
    </div>

    <div class="payout-stats">
        <div class="stat-card">
            <div>
                <div style="font-size:0.85rem; color:var(--text-muted);">UNSETTLED COMMISSIONS</div>
                <div class="stat-val payout-pending">₹<?= number_format($pending_total, 2) ?></div>
            </div>
            <span style="font-size:2rem;">⏳</span>
        </div>
        <div class="stat-card">
            <div>
                <div style="font-size:0.85rem; color:var(--text-muted);">TOTAL PAID OUT</div>
                <div class="stat-val payout-paid">₹<?= number_format($paid_total, 2) ?></div>
            </div>
            <span style="font-size:2rem;">✅</span>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Commission History</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Order Amount</th>
                        <th>Your Commission</th>
                        <th>Payout Status</th>
                        <th>Settlement Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['company_name']) ?></strong><br>
                            <small style="color:var(--text-muted)"><?= $p['is_main_branch'] ? 'Main Branch' : 'Sub-Branch of ' . htmlspecialchars($p['parent_name']) ?></small>
                        </td>
                        <td style="font-weight:600;">₹<?= number_format($p['amount'], 2) ?></td>
                        <td style="color:var(--primary-color); font-weight:800;">₹<?= number_format($p['admin_cut'], 2) ?></td>
                        <td>
                            <span class="badge badge-<?= $p['payout_status'] === 'paid' ? 'active' : 'inactive' ?>" style="background:<?= $p['payout_status'] === 'paid' ? '#ecfdf5;color:#10b981' : '#fff7ed;color:#f59e0b' ?>">
                                <?= strtoupper($p['payout_status']) ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size:0.85rem;">
                            <?= $p['payout_date'] ? date('M d, H:i', strtotime($p['payout_date'])) : '—' ?>
                        </td>
                        <td>
                            <?php if ($p['payout_status'] === 'pending'): ?>
                                <form method="POST" onsubmit="return confirm('Mark this as paid? This means you have sent the commission to the branch.')">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Mark Paid</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#10b981; font-weight:600;">Settled ✓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($payments)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No approved sales found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
