<?php
// /admin/leave_requests.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager']);

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $leave_id = (int)$_POST['leave_id'];
    $admin_id = $_SESSION['user_id'];
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, action_by = ?, action_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->execute([$status, $admin_id, $leave_id, $cid]);
        
        logActivity('leave_'.$action, "Leave request ID: $leave_id was $status", $cid);
        $msg = "Leave request successfully " . $status . "!";
        $msgType = "success";
    }
}

// Fetch Pending Requests
$stmt = $pdo->prepare("
    SELECT lr.*, u.name as employee_name, u.email as employee_email 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    WHERE lr.company_id = ? 
    ORDER BY lr.status = 'pending' DESC, lr.applied_at DESC
");
$stmt->execute([$cid]);
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Leave Management</h1>
            <p style="color:var(--text-muted)">Review and process staff leave applications.</p>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>All Leave Applications (<?= count($requests) ?>)</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td style="font-weight:600">
                            <?= htmlspecialchars($r['employee_name']) ?><br>
                            <span style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['employee_email']) ?></span>
                        </td>
                        <td><span style="padding:2px 6px;background:#f1f5f9;border-radius:4px;font-size:0.85rem;"><?= $r['leave_type'] ?></span></td>
                        <td style="font-size:0.9rem;">
                            <?= date('M d, Y', strtotime($r['start_date'])) ?><br>
                            <span style="color:var(--text-muted);font-size:0.8rem">to</span> <?= date('M d, Y', strtotime($r['end_date'])) ?>
                        </td>
                        <td style="max-width:200px;font-size:0.85rem;color:var(--text-muted)"><?= htmlspecialchars($r['reason']) ?></td>
                        <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <div style="display:flex;gap:5px;">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="leave_id" value="<?= $r['id'] ?>">
                                        <button class="btn btn-sm btn-primary" style="background:#10b981;border-color:#10b981;padding:0.3rem 0.6rem">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="leave_id" value="<?= $r['id'] ?>">
                                        <button class="btn btn-sm btn-danger" style="padding:0.3rem 0.6rem">Reject</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="font-size:0.8rem;color:var(--text-muted)">Processed at <?= date('M d, H:i', strtotime($r['action_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($requests)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">No leave requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
