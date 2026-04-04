<?php
// /admin/staff_profile.php
require_once '../includes/auth.php';
require_once '../config/database.php';
header("Cache-Control: no-cache, no-store, must-revalidate"); // Cache Buster
header("Pragma: no-cache"); 
header("Expires: 0");
checkAccess(['admin', 'manager']);

$cid = $_SESSION['company_id'];
$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) { header("Location: staff.php"); exit(); }

// 0. Auto-patch for Profile Image & Phone
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER password");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER password"); } catch(Exception $ex){}
}
try {
    $pdo->exec("ALTER TABLE employee_details ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER company_id");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE employee_details ADD COLUMN phone VARCHAR(20) NULL AFTER company_id"); } catch(Exception $ex){}
}

// 0.1 Fetch Company Info
$company_stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
$company_stmt->execute([$cid]);
$company_name = $company_stmt->fetchColumn() ?: 'DRHrms';

// ── Handle Profile Updates ──────────────────────────────────────────
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
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
            $stmt = $pdo->prepare("UPDATE employee_details SET phone=?, father_name=?, degree=?, permanent_address=?, current_address=?, bank_name=?, account_number=?, ifsc_code=? WHERE user_id=?");
            $stmt->execute([$phone, $father, $degree, $p_addr, $c_addr, $bank, $acc, $ifsc, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO employee_details (user_id, company_id, phone, father_name, degree, permanent_address, current_address, bank_name, account_number, ifsc_code) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $cid, $phone, $father, $degree, $p_addr, $c_addr, $bank, $acc, $ifsc]);
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
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
            
            /* ID CARD PRINT MODE */
            body.print-id-card-only .main-content, 
            body.print-id-card-only .profile-grid,
            body.print-id-card-only .page-header { display: none !important; }
            body.print-id-card-only .id-card-printable { display: block !important; margin: 0 auto; }
        }

        /* ID Card Layout */
        .id-card-printable { 
            display: none; width: 330px; height: 500px; 
            border: 1px solid #ddd; border-radius: 15px; 
            overflow: hidden; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            font-family: 'Inter', sans-serif; position: relative;
        }
        .id-card-header { 
            background: linear-gradient(135deg, var(--primary-color), #4f46e5); 
            color: #fff; padding: 25px 15px; text-align: center; 
        }
        .id-card-header h4 { margin: 0; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; }
        .id-card-body { padding: 30px 20px; text-align: center; }
        .id-card-avatar { width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin: -60px auto 15px; background: #fff; object-fit: cover; }
        .id-card-initials { width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin: -60px auto 15px; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 700; }
        .id-card-name { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .id-card-role { color: var(--primary-color); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 25px; }
        .id-card-footer { background: #f8fafc; padding: 20px; border-top: 1px solid #e2e8f0; position: absolute; bottom: 0; left: 0; right: 0; }
        .id-card-info { display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; }
        .id-card-info strong { color: #1e293b; display: block; margin-top: 2px; }
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
            <script>
            function startIdPrint() {
                document.body.classList.add('print-id-card-only');
                window.print();
                setTimeout(() => { document.body.classList.remove('print-id-card-only'); }, 500);
            }
            </script>
            <button class="btn btn-outline" onclick="startIdPrint()" style="background:#4f46e5;color:#fff;border:none;">PRINT ID CARD</button>
            <button class="btn btn-outline" onclick="window.print()">PRINT REPORT</button>
            <button class="btn btn-primary" onclick="document.getElementById('editModal').classList.add('open')">EDIT PROFILE</button>
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
                    <div style="font-weight:600;"><?= htmlspecialchars($staff['phone'] ?: 'Not Provided') ?></div>
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
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
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

<!-- Printable ID Card Layout -->
<div class="id-card-printable" id="idCard">
    <div class="id-card-header">
        <h4><?= htmlspecialchars($company_name) ?></h4>
    </div>
    <div class="id-card-body">
        <?php if ($staff['profile_image']): ?>
            <img src="<?= BASE_URL . $staff['profile_image'] ?>" class="id-card-avatar">
        <?php else: ?>
            <div class="id-card-initials"><?= strtoupper(substr($staff['name'],0,2)) ?></div>
        <?php endif; ?>
        
        <div class="id-card-name"><?= htmlspecialchars($staff['name']) ?></div>
        <div class="id-card-role"><?= ucwords($staff['role']) ?></div>
        
        <div style="margin-top:20px; font-size:0.8rem; color:#64748b;">
            <p><strong>Email:</strong> <?= htmlspecialchars($staff['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($staff['phone'] ?: 'N/A') ?></p>
        </div>
    </div>
    <div class="id-card-footer">
        <div class="id-card-info">
            <div>
                Employee ID
                <strong>#<?= str_pad($staff['id'], 3, '0', STR_PAD_LEFT) ?></strong>
            </div>
            <div style="text-align:right;">
                Joining Date
                <strong><?= $staff['joining_date'] ? date('M d, Y', strtotime($staff['joining_date'])) : 'N/A' ?></strong>
            </div>
        </div>
    </div>
</div>

<script>
function printIdCard() {
    document.body.classList.add('print-id-card-only');
    window.print();
    setTimeout(() => {
        document.body.classList.remove('print-id-card-only');
    }, 500);
}
</script>
</body>
</html>
