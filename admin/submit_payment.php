<?php
// /admin/submit_payment.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = '';
$msgType = '';

// Auto-patch for franchise_payments table
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM franchise_payments LIKE 'project_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE franchise_payments ADD COLUMN project_id INT NULL AFTER company_id");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM franchise_payments LIKE 'commission_percent'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE franchise_payments ADD COLUMN commission_percent DECIMAL(5,2) NULL AFTER project_id");
    }

    $pdo->exec("ALTER TABLE franchise_payments ADD COLUMN IF NOT EXISTS product_id INT NULL DEFAULT NULL AFTER client_name");
    
    // Patch for products table
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'commission_rate'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    }
} catch (Exception $e) {
}

// Fetch HQ ID dynamically
$hq_check = $pdo->prepare("SELECT id FROM companies WHERE is_main_branch = 1 LIMIT 1");
$hq_check->execute();
$hq_id = $hq_check->fetchColumn() ?: 1;

// Fetch products for dropdown using HQ catalog
$stmt = $pdo->prepare("SELECT id, name, price, commission_rate FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$hq_id]);
$products = $stmt->fetchAll();

// Fetch active projects for dropdown
$stmt = $pdo->prepare("SELECT id, client_name, project_name, commission_percent FROM projects WHERE branch_id = ? ORDER BY created_at DESC");
$stmt->execute([$cid]);
$branch_projects = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) $_POST['amount'];
    $category = $_POST['category'] ?? '';
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $proof = $_FILES['proof_file'] ?? null;
    $product_id = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : null;

    $project_id = $_POST['project_id'] ?? '';
    $client = '';
    $commission = null;

    try {
        $pdo->beginTransaction();

        if ($project_id === 'NEW') {
            $client = trim($_POST['new_client_name'] ?? '');
            $pname = trim($_POST['new_project_name'] ?? '');
            $commission = isset($_POST['commission_percent']) ? (float) $_POST['commission_percent'] : null;

            // Create Project
            $stmt = $pdo->prepare("INSERT INTO projects (company_id, branch_id, client_name, project_name, commission_percent, status) VALUES (?, ?, ?, ?, ?, 'Pending HQ Review')");
            $stmt->execute([$cid, $cid, $client, $pname, $commission]);
            $project_id = $pdo->lastInsertId();
        } else {
            $project_id = (int) $project_id;
            // Fetch client name & commision from existing project
            $stmt = $pdo->prepare("SELECT client_name, commission_percent FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $proj = $stmt->fetch();
            if ($proj) {
                $client = $proj['client_name'];
                $commission = $proj['commission_percent'];
            }
        }

        if ($amount > 0 && $client && $proof && $proof['error'] === 0) {
            $ext = pathinfo($proof['name'], PATHINFO_EXTENSION);
            $filename = "pay_" . time() . "_" . $cid . "." . $ext;
            $uploadDir = "../assets/uploads/payments/";
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $target = $uploadDir . $filename;

            if (move_uploaded_file($proof['tmp_name'], $target)) {
                $stmt = $pdo->prepare("INSERT INTO franchise_payments (company_id, project_id, commission_percent, amount, client_name, product_id, category, payment_date, proof_file, status) VALUES (?,?,?,?,?,?,?,?,?,'pending')");
                $stmt->execute([$cid, $project_id, $commission, $amount, $client, $product_id, $category, $date, $filename]);

                logActivity('payment_submitted', "Submitted payment: ₹$amount for $client", $cid);
                $msg = "Payment & Project Linked Successfully! Waiting for HQ verification.";
                $msgType = "success";
                $pdo->commit();
            } else {
                $pdo->rollBack();
                $msg = "Failed to upload proof image. Please check file permissions.";
                $msgType = "error";
            }
        } else {
            $pdo->rollBack();
            $msg = "Please fill all fields and upload a valid proof screenshot.";
            $msgType = "error";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = "Database error: " . $e->getMessage();
        $msgType = "error";
    }
}

// Fetch my recent submissions
$stmt = $pdo->prepare("SELECT f.*, p.name as product_name FROM franchise_payments f LEFT JOIN products p ON f.product_id = p.id WHERE f.company_id = ? ORDER BY f.created_at DESC LIMIT 10");
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <?php if ($msg): ?>
            <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="page-header">
            <h1>Submit Payment / Sale</h1>
            <p style="color:var(--text-muted)">Report new sales and upload proof for verification.</p>
        </div>

        <!-- HQ Payment Info -->
        <div class="content-card"
            style="background: linear-gradient(135deg, var(--bg-main), #fff); border-left: 4px solid #3b82f6; margin-bottom: 2rem;">
            <div class="card-header">
                <h4 style="margin:0; display:flex; align-items:center; gap:8px;">🏦 HQ Payment Details</h4>
            </div>
            <p style="color:var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Please remit your Franchise Share to
                the following accounts before submitting payment proof below.</p>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                <div>
                    <strong style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">Bank
                        Account Details:</strong>
                    <pre
                        style="background: var(--bg-main); padding: 12px; border-radius: 8px; font-family: inherit; font-size: 0.9rem; color: #334155; white-space: pre-wrap; margin-top: 8px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($hq_bank_details) ?></pre>
                </div>
                <div>
                    <strong style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">UPI & QR
                        Code:</strong>
                    <div
                        style="background: var(--bg-main); padding: 12px; border-radius: 8px; margin-top: 8px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 1rem;">
                        <?php if ($hq_upi_qr): ?>
                            <div style="background:#fff; padding:4px; border-radius:6px; border:1px solid #cbd5e1;">
                                <img src="<?= BASE_URL ?>assets/uploads/qr/<?= htmlspecialchars($hq_upi_qr) ?>"
                                    style="width: 80px; height: 80px; object-fit: contain; display:block;">
                            </div>
                        <?php endif; ?>
                        <div>
                            <div
                                style="font-size: 1rem; font-weight: 700; color: #3b82f6; word-break: break-all; margin-bottom: 5px;">
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
                <div class="card-header">
                    <h4>Payment Details</h4>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Amount (₹) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required
                            placeholder="1000.00">
                    </div>
                    <div class="form-group">
                        <label>Select Project / Client *</label>
                        <select name="project_id" id="projectSelector" class="form-control" required
                            onchange="toggleNewProjectFields()">
                            <option value="">-- Select an Existing Project --</option>
                            <?php foreach ($branch_projects as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['client_name']) ?> - <?= htmlspecialchars($p['project_name']) ?>
                                    (Comm:
                                    <?= $p['commission_percent'] !== null ? $p['commission_percent'] . '%' : 'Not Set' ?>)
                                </option>
                            <?php endforeach; ?>
                            <option value="NEW" style="font-weight:bold; color:var(--primary-color);">➕ CREATE NEW
                                PROJECT...</option>
                        </select>
                    </div>

                    <!-- Hidden Fields for New Project -->
                    <div id="newProjectFields"
                        style="display:none; background:var(--bg-main); padding:15px; border-radius:8px; border:1px dashed #cbd5e1; margin-bottom:1.5rem;">
                        <h5 style="margin-top:0; font-size:0.9rem; color:var(--text-main);">New Project Details</h5>
                        <div class="form-group">
                            <label>Client Name *</label>
                            <input type="text" name="new_client_name" id="new_client_name" class="form-control"
                                placeholder="John Smith">
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>Project Name *</label>
                                <input type="text" name="new_project_name" id="new_project_name" class="form-control"
                                    placeholder="e.g. Website Dev">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Commission Percentage (%)</label>
                                <input type="number" step="0.01" name="commission_percent" id="commission_percent"
                                    class="form-control" style="background-color: var(--bg-main); cursor: not-allowed;" readonly placeholder="Fixed by HQ">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                            <label style="margin-bottom:0;">Related Product/Service</label>
                            <a href="settings_products.php"
                                style="font-size:0.75rem; color:var(--primary-color); text-decoration:none; font-weight:600;">+
                                Manage Catalog</a>
                        </div>
                        <select name="product_id" id="service_catalog_select" class="form-control" onchange="updatePaymentCommission(this)">
                            <option value="" data-rate="0">-- No specific product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-rate="<?= $p['commission_rate'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['commission_rate'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                            <input type="date" name="payment_date" class="form-control" required
                                value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Proof of Payment (Screenshot) *</label>
                        <input type="file" name="proof_file" class="form-control" required accept="image/*">
                        <small style="color:var(--text-muted)">Upload a clear screenshot of the transaction
                            receipt.</small>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Submit for
                        Verification</button>
                </form>
            </div>

            <!-- History -->
            <div class="content-card">
                <div class="card-header">
                    <h4>Recent Submissions</h4>
                </div>
                <div style="overflow-x:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['client_name']) ?></strong><br>
                                        <small><?= $p['category'] ?></small>
                                        <?php if ($p['product_name']): ?><br><small style="color:var(--primary-color)">📦
                                                <?= htmlspecialchars($p['product_name']) ?></small><?php endif; ?>
                                    </td>
                                    <td>₹<?= number_format($p['amount'], 2) ?></td>
                                    <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted)">
                                        <?= date('M d, H:i', strtotime($p['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!count($recent_payments)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted)">No
                                        submissions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script>
        // Image Preview
        document.getElementById('proofInput').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('previewImg');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Toggle New Project Fields
        function toggleNewProjectFields() {
            const sel = document.getElementById('projectSelector').value;
            const panel = document.getElementById('newProjectFields');
            const cName = document.getElementById('new_client_name');
            const pName = document.getElementById('new_project_name');
            const comm = document.getElementById('commission_percent');

            if (sel === 'NEW') {
                panel.style.display = 'block';
                cName.required = true;
                pName.required = true;
                comm.required = true;
            } else {
                panel.style.display = 'none';
                cName.required = false;
                pName.required = false;
                comm.required = false;
            }
        }
        
        // Update Commission Rate from HQ Catalog
        function updatePaymentCommission(select) {
            const selectedOption = select.options[select.selectedIndex];
            const rate = selectedOption.getAttribute('data-rate');
            document.getElementById('commission_percent').value = rate ? rate : '';
        }
    </script>
</body>

</html>