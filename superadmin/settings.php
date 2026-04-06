<?php
// /superadmin/settings.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$flash = ''; $flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Maintenance Mode Toggle
    if ($action === 'toggle_maintenance') {
        $new_val = $_POST['maintenance_mode'] === '1' ? '1' : '0';
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$new_val, $new_val]);
        $flash = "Maintenance mode " . ($new_val === '1' ? 'ENABLED' : 'DISABLED'); $flashType = 'success';
    }

    // Update Bank Details
    if ($action === 'update_bank') {
        $bank_details = $_POST['bank_details'] ?? '';
        $upi_id = $_POST['upi_id'] ?? '';
        
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('hq_bank_details', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$bank_details, $bank_details]);
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('hq_upi_id', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$upi_id, $upi_id]);
        
        // Handle QR Code Upload
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = "hq_qr_" . time() . "." . $ext;
                $target = "../assets/uploads/qr/" . $filename;
                if (!is_dir("../assets/uploads/qr/")) @mkdir("../assets/uploads/qr/", 0777, true);
                if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target)) {
                    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('hq_upi_qr', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$filename, $filename]);
                }
            }
        }
        
        $flash = "Payment methods updated successfully!"; $flashType = 'success';
    }

    // Remove QR Code
    if ($action === 'remove_qr') {
        $old_qr = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_upi_qr'")->fetchColumn();
        if ($old_qr && file_exists("../assets/uploads/qr/" . $old_qr)) {
            unlink("../assets/uploads/qr/" . $old_qr);
        }
        $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'hq_upi_qr'")->execute();
        $flash = "QR Code removed successfully."; $flashType = 'success';
    }

    // Change Super Admin Password
    if ($action === 'change_password') {
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $flash = "New passwords do not match."; $flashType = 'error';
        } elseif (strlen($new) < 6) {
            $flash = "Password must be at least 6 characters."; $flashType = 'error';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['sa_user_id']]);
            $flash = "Password updated successfully!"; $flashType = 'success';
        }
    }
}

// Fetch current maintenance status
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$maintenance_mode = $stmt->fetchColumn() === '1';

// Fetch bank details
$hq_bank_details = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_bank_details'")->fetchColumn() ?: '';
$hq_upi_id = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_upi_id'")->fetchColumn() ?: '';
$hq_upi_qr = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'hq_upi_qr'")->fetchColumn() ?: '';

// Stats for the settings page context
$total_companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$total_users     = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin'")->fetchColumn();
$total_requests  = $pdo->query("SELECT COUNT(*) FROM demo_requests")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Loom</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>System Settings</h1>
            <p style="color:var(--text-muted)">Platform-wide configuration and account security.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="content-card">
        <div class="card-header"><h2>📊 System Overview</h2></div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem">
            <div style="text-align:center;padding:1.5rem;background:rgba(99,102,241,.06);border-radius:10px;border:1px solid rgba(99,102,241,.2)">
                <div style="font-size:2rem;font-weight:800;color:var(--primary-color)"><?= $total_companies ?></div>
                <div style="color:var(--text-muted);font-size:.9rem;margin-top:.3rem">Total Companies</div>
            </div>
            <div style="text-align:center;padding:1.5rem;background:rgba(16,185,129,.06);border-radius:10px;border:1px solid rgba(16,185,129,.2)">
                <div style="font-size:2rem;font-weight:800;color:#10b981"><?= $total_users ?></div>
                <div style="color:var(--text-muted);font-size:.9rem;margin-top:.3rem">Total Users</div>
            </div>
            <div style="text-align:center;padding:1.5rem;background:rgba(236,72,153,.06);border-radius:10px;border:1px solid rgba(236,72,153,.2)">
                <div style="font-size:2rem;font-weight:800;color:#ec4899"><?= $total_requests ?></div>
                <div style="color:var(--text-muted);font-size:.9rem;margin-top:.3rem">Total Requests</div>
            </div>
        </div>
    </div>

    <!-- Maintenance Mode -->
    <div class="content-card">
        <div class="card-header"><h2>🛠️ Platform Maintenance</h2></div>
        <div style="display:flex;align-items:center;gap:1.5rem;padding:0.5rem">
            <div style="flex:1">
                <p style="margin:0;font-weight:600">Maintenance Mode</p>
                <p style="margin:4px 0 0;font-size:0.85rem;color:var(--text-muted)">When enabled, all non-superadmin users will be blocked from accessing the platform.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="toggle_maintenance">
                <input type="hidden" name="maintenance_mode" value="<?= $maintenance_mode ? '0' : '1' ?>">
                <button type="submit" class="btn <?= $maintenance_mode ? 'btn-danger' : 'btn-primary' ?>" style="min-width:140px">
                    <?= $maintenance_mode ? 'Disable Maintenance' : 'Enable Maintenance' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Bank Payment Methods -->
    <div class="content-card">
        <div class="card-header"><h2>🏦 HQ Bank & Payment Details</h2></div>
        <div style="padding: 0 0.5rem 1rem;">
            <p style="color:var(--text-muted);font-size:0.9rem;margin-top:0;">These details will be displayed to Sub-Branches so they know where to remit your Franchise Revenue Share.</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_bank">
                <div class="form-group">
                    <label>Bank Account Details</label>
                    <textarea name="bank_details" class="form-control" rows="4" placeholder="Bank Name: HDFC Bank&#10;Account Name: Loom Pvt Ltd&#10;Account No: 502000...&#10;IFSC: HDFC000123..."><?= htmlspecialchars($hq_bank_details) ?></textarea>
                </div>
                <div class="form-group">
                    <label>UPI ID (Google Pay / PhonePe / Paytm)</label>
                    <input type="text" name="upi_id" class="form-control" value="<?= htmlspecialchars($hq_upi_id) ?>" placeholder="e.g. loom@upi">
                </div>
                <div class="form-group">
                    <label>Payment QR Code Image</label>
                    <?php if ($hq_upi_qr): ?>
                        <div style="margin-bottom:10px; display:flex; gap:1rem; align-items:flex-end;">
                            <img src="<?= BASE_URL ?>assets/uploads/qr/<?= htmlspecialchars($hq_upi_qr) ?>" style="max-height: 120px; border-radius: 8px; border: 1px solid #e2e8f0; padding: 4px; background: #fff;">
                            <button type="submit" name="action" value="remove_qr" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" formnovalidate>🗑️ Remove QR</button>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="qr_code" class="form-control" accept="image/jpeg, image/png, image/webp">
                    <small style="color:var(--text-muted)">Upload your business QR code. JPEG/PNG only.</small>
                </div>
                <button type="submit" class="btn btn-primary">Save Payment Details</button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="content-card">
        <div class="card-header"><h2>🔐 Change Super Admin Password</h2></div>
        <form method="POST" style="max-width:500px">
            <input type="hidden" name="action" value="change_password">
            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>

    <!-- Quick Links -->
    <div class="content-card">
        <div class="card-header"><h2>⚡ Quick Actions</h2></div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap">
            <a href="companies.php" class="btn btn-outline">Manage Companies</a>
            <a href="admins.php" class="btn btn-outline">Manage Admins</a>
            <a href="../logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</main>
</body>
</html>
