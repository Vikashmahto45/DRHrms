<?php
// /admin/apply_leave.php
require_once '../includes/auth.php';
require_once '../config/database.php';
// Restrict to staff/manager (Admins use leave_requests.php to manage)
checkAccess(['staff', 'manager', 'sales_person', 'admin']);

$user_id = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['leave_type'] ?? 'Casual';
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $reason = trim($_POST['reason'] ?? '');

    if ($start && $end) {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, company_id, leave_type, start_date, end_date, reason) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$user_id, $cid, $type, $start, $end, $reason]);
        
        logActivity('leave_applied', "Applied for $type leave from $start to $end", $cid);
        $msg = "Leave application submitted successfully!";
        $msgType = "success";
    } else {
        $msg = "Please select both start and end dates.";
        $msgType = "error";
    }
}

// Fetch my history
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY applied_at DESC");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434221">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434221">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <h1>Apply for Leave</h1>
    </div>

    <div style="display:grid;grid-template-columns: 1fr 1.5fr; gap:2rem;">
        <!-- Application Form -->
        <div class="content-card">
            <div class="card-header"><h4>Leave Application</h4></div>
            <form method="POST">
                <div class="form-group">
                    <label>Leave Type</label>
                    <select name="leave_type" class="form-control">
                        <option value="Casual">Casual Leave</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Annual">Annual / Vacation</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason for Leave</label>
                    <textarea name="reason" class="form-control" rows="4" placeholder="Brief explanation..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Submit Application</button>
            </form>
        </div>

        <!-- History -->
        <div class="content-card">
            <div class="card-header"><h4>My Leave History</h4></div>
            <table class="table">
                <thead><tr><th>Type</th><th>Duration</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><strong><?= $h['leave_type'] ?></strong></td>
                        <td style="font-size:0.85rem">
                            <?= date('M d', strtotime($h['start_date'])) ?> - <?= date('M d', strtotime($h['end_date'])) ?>
                        </td>
                        <td><span class="badge badge-<?= $h['status'] ?>"><?= ucfirst($h['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!count($history)): ?>
                        <tr><td colspan="3" style="text-align:center;padding:2rem;color:var(--text-muted)">No applications yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
