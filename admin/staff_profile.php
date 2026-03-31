<?php
// /admin/staff_profile.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager']);

$cid = $_SESSION['company_id'];
$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) { header("Location: staff.php"); exit(); }

// 0. Auto-patch for Profile Image
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER password");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER password"); } catch(Exception $ex){}
}

// ── Handle Profile Updates ──────────────────────────────────────────
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $father = trim($_POST['father_name'] ?? '');
    $degree = trim($_POST['degree'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $acc = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc_code'] ?? '');
    $p_addr = trim($_POST['permanent_address'] ?? '');
    $c_addr = trim($_POST['current_address'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Handle Image Upload
        $img_path = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['profile_image']['name'])) {
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            $target = "../assets/uploads/profiles/";
            if (!is_dir($target)) mkdir($target, 0777, true);
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target . $filename)) {
                $img_path = "assets/uploads/profiles/" . $filename;
            }
        }

        // 2. Update Users Table
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_image = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$name, $email, $img_path, $user_id, $cid]);

        // 3. Update Employee Details (Upsert style)
        $stmt = $pdo->prepare("SELECT id FROM employee_details WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE employee_details SET father_name=?, degree=?, permanent_address=?, current_address=?, bank_name=?, account_number=?, ifsc_code=? WHERE user_id=?");
            $stmt->execute([$father, $degree, $p_addr, $c_addr, $bank, $acc, $ifsc, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO employee_details (user_id, company_id, father_name, degree, permanent_address, current_address, bank_name, account_number, ifsc_code) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $cid, $father, $degree, $p_addr, $c_addr, $bank, $acc, $ifsc]);
        }

        $pdo->commit();
        $msg = "Profile updated successfully!"; $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error: " . $e->getMessage(); $msgType = 'error';
    }
}

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
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 2rem; margin-top: 1rem; }
        .info-section { background: #fff; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem; }
        .info-row { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.9rem; }
        .info-value { font-weight: 600; color: var(--text-main); }
        .stats-mini { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
        .stat-mini-box { padding: 1rem; border-radius: 8px; text-align: center; background: #f8f9fa; }
        
        @media print {
            .sidebar, .top-bar, .page-header button, .modal-overlay, .badge, .btn { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .profile-grid { grid-template-columns: 1fr; }
            .info-section { border: none; border-bottom: 1px solid #eee; break-inside: avoid; }
            body { background: #fff; color: #000; }
        }
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
            <?php if ($msg): ?>
                <div class="badge badge-<?= $msgType ?>" style="padding:10px 15px;"><?= $msg ?></div>
            <?php endif; ?>
            <button class="btn btn-outline" onclick="window.print()">Print Details</button>
            <button class="btn btn-primary" onclick="document.getElementById('editModal').classList.add('open')">Edit Profile</button>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Sidebar: Summary -->
        <div>
            <div class="info-section" style="text-align:center;">
                <?php if ($staff['profile_image']): ?>
                    <img src="<?= BASE_URL . $staff['profile_image'] ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 1.5rem;border:3px solid var(--primary-color);display:block;">
                <?php else: ?>
                    <div class="avatar" style="width:80px;height:80px;font-size:2rem;margin:0 auto 1.5rem;"><?= strtoupper(substr($staff['name'],0,2)) ?></div>
                <?php endif; ?>
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

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:700px;">
        <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&times;</button>
        <h3>Update Employee Profile</h3>
        <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="existing_image" value="<?= $staff['profile_image'] ?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                <!-- Basic Info -->
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($staff['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($staff['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Father's Name</label>
                    <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($staff['father_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Degree / Qualification</label>
                    <input type="text" name="degree" class="form-control" value="<?= htmlspecialchars($staff['degree'] ?? '') ?>">
                </div>
                
                <!-- Bank Info -->
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($staff['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($staff['account_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-control" value="<?= htmlspecialchars($staff['ifsc_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem;">
                <label>Permanent Address</label>
                <textarea name="permanent_address" class="form-control" rows="2"><?= htmlspecialchars($staff['permanent_address'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Current Address</label>
                <textarea name="current_address" class="form-control" rows="2"><?= htmlspecialchars($staff['current_address'] ?? '') ?></textarea>
            </div>

            <div class="modal-footer" style="padding-top:2rem;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding-left:2rem; padding-right:2rem;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
