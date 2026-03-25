<?php
// /admin/staff_profile.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager']);

$cid = $_SESSION['company_id'];
$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) { header("Location: staff.php"); exit(); }

// 1. Fetch User & Details
$stmt = $pdo->prepare("
    SELECT u.*, ed.* 
    FROM users u 
    LEFT JOIN employee_details ed ON u.id = ed.user_id 
    WHERE u.id = ? AND u.company_id = ?
");
$stmt->execute([$user_id, $cid]);
$staff = $stmt->fetch();

if (!$staff) { die("Staff member not found or access denied."); }

// 2. Fetch Leave History
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY applied_at DESC");
$stmt->execute([$user_id]);
$leaves = $stmt->fetchAll();

// 3. Stats
$stats = [
    'total_leaves' => count($leaves),
    'pending' => 0,
    'approved' => 0
];
foreach($leaves as $l) {
    if($l['status'] === 'pending') $stats['pending']++;
    if($l['status'] === 'approved') $stats['approved']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?= htmlspecialchars($staff['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439732">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439732">
    <style>
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 2rem; margin-top: 1rem; }
        .info-section { background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem; }
        .info-row { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.9rem; }
        .info-value { font-weight: 600; color: var(--text-main); }
        .stats-mini { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
        .stat-mini-box { padding: 1rem; border-radius: 8px; text-align: center; background: #f8f9fa; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <a href="staff.php" style="color:var(--primary-color);text-decoration:none;font-size:0.9rem;">← Back to Staff List</a>
            <h1>Employee Profile</h1>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-outline">Print Details</button>
            <button class="btn btn-primary">Edit Profile</button>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Sidebar: Summary -->
        <div>
            <div class="info-section" style="text-align:center;">
                <div class="avatar" style="width:80px;height:80px;font-size:2rem;margin:0 auto 1.5rem;"><?= strtoupper(substr($staff['name'],0,2)) ?></div>
                <h3><?= htmlspecialchars($staff['name']) ?></h3>
                <p style="color:var(--text-muted)"><?= ucwords($staff['role']) ?></p>
                <span class="badge badge-<?= $staff['status'] ?>" style="margin-top:0.5rem;"><?= ucfirst($staff['status']) ?></span>
                
                <div class="stats-mini">
                    <div class="stat-mini-box">
                        <div style="font-size:1.2rem;font-weight:700;"><?= $stats['approved'] ?></div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">Approved Leaves</div>
                    </div>
                    <div class="stat-mini-box">
                        <div style="font-size:1.2rem;font-weight:700;"><?= $stats['pending'] ?></div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">Pending</div>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h4>Contact Info</h4>
                <div style="margin-top:1rem;">
                    <div style="font-size:0.85rem;color:var(--text-muted);">Email</div>
                    <div style="font-weight:600;margin-bottom:0.8rem;"><?= htmlspecialchars($staff['email']) ?></div>
                    
                    <div style="font-size:0.85rem;color:var(--text-muted);">Phone</div>
                    <div style="font-weight:600;">Not Provided</div>
                </div>
            </div>
        </div>

        <!-- Main Body: Details -->
        <div>
            <div class="info-section">
                <h4 style="color:var(--primary-color);border-bottom:2px solid #f1f5f9;padding-bottom:0.5rem;margin-bottom:1rem;">Professional & Personal</h4>
                <div class="info-row">
                    <span class="info-label">Father's Name</span>
                    <span class="info-value"><?= htmlspecialchars($staff['father_name'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Joining Date</span>
                    <span class="info-value"><?= $staff['joining_date'] ? date('M d, Y', strtotime($staff['joining_date'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Degree / Qualification</span>
                    <span class="info-value"><?= htmlspecialchars($staff['degree'] ?: 'N/A') ?></span>
                </div>
            </div>

            <div class="info-section">
                <h4 style="color:var(--primary-color);border-bottom:2px solid #f1f5f9;padding-bottom:0.5rem;margin-bottom:1rem;">Addresses</h4>
                <div class="info-row" style="flex-direction:column;align-items:flex-start;">
                    <span class="info-label">Permanent Address</span>
                    <span class="info-value" style="margin-top:0.3rem;"><?= nl2br(htmlspecialchars($staff['permanent_address'] ?: 'N/A')) ?></span>
                </div>
                <div class="info-row" style="flex-direction:column;align-items:flex-start;border-bottom:none;">
                    <span class="info-label">Current Address</span>
                    <span class="info-value" style="margin-top:0.3rem;"><?= nl2br(htmlspecialchars($staff['current_address'] ?: 'N/A')) ?></span>
                </div>
            </div>

            <div class="info-section">
                <h4 style="color:var(--primary-color);border-bottom:2px solid #f1f5f9;padding-bottom:0.5rem;margin-bottom:1rem;">Bank Account Details</h4>
                <div class="info-row">
                    <span class="info-label">Bank Name</span>
                    <span class="info-value"><?= htmlspecialchars($staff['bank_name'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Account Number</span>
                    <span class="info-value"><?= htmlspecialchars($staff['account_number'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">IFSC Code</span>
                    <span class="info-value"><?= htmlspecialchars($staff['ifsc_code'] ?: 'N/A') ?></span>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h4>Recent Leave History</h4>
                    <span style="font-size:0.8rem;color:var(--text-muted)"><?= $stats['total_leaves'] ?> Total</span>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th><th>Duration</th><th>Status</th><th>Applied At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach(array_slice($leaves, 0, 5) as $l): ?>
                        <tr>
                            <td><?= $l['leave_type'] ?></td>
                            <td style="font-size:0.85rem;">
                                <?= date('M d', strtotime($l['start_date'])) ?> - <?= date('M d', strtotime($l['end_date'])) ?>
                            </td>
                            <td><span class="badge badge-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
                            <td style="color:var(--text-muted);font-size:0.8rem;"><?= date('M d, Y', strtotime($l['applied_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!count($leaves)): ?>
                            <tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--text-muted)">No leave records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
