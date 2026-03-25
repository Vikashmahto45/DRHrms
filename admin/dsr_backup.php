<?php
// /admin/dsr.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['sales_person', 'admin', 'manager', 'staff']);

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$role = $_SESSION['user_role'] ?? '';
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

$msg = ''; $msgType = '';

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_dsr') {
    $client = trim($_POST['client_name'] ?? '');
    $purpose = trim($_POST['visit_purpose'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $lat = $_POST['latitude'] ?? '';
    $lng = $_POST['longitude'] ?? '';
    $date = date('Y-m-d');

    if ($client && $purpose) {
        $photo_path = '';
        if (isset($_FILES['visit_photo']) && $_FILES['visit_photo']['error'] === 0) {
            $ext = pathinfo($_FILES['visit_photo']['name'], PATHINFO_EXTENSION);
            $filename = "dsr_" . time() . "_" . uniqid() . "." . $ext;
            $target = "../uploads/dsr/" . $filename;
            if (move_uploaded_file($_FILES['visit_photo']['tmp_name'], $target)) {
                $photo_path = "uploads/dsr/" . $filename;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO daily_sales_reports (user_id, company_id, client_name, visit_purpose, visit_photo, notes, latitude, longitude, visit_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$uid, $cid, $client, $purpose, $photo_path, $notes, $lat, $lng, $date]);
            $msg = "DSR submitted successfully!"; $msgType = 'success';
        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage(); $msgType = 'error';
        }
    } else {
        $msg = "Client Name and Purpose are required."; $msgType = 'error';
    }
}

// Fetch Reports
if ($role === 'sales_person') {
    $stmt = $pdo->prepare("SELECT * FROM daily_sales_reports WHERE user_id = ? ORDER BY visit_date DESC, created_at DESC");
    $stmt->execute([$uid]);
} else {
    // Admins see all reports for the company and sub-branches
    $stmt = $pdo->prepare("SELECT d.*, u.name as staff_name, c.name as company_name FROM daily_sales_reports d JOIN users u ON d.user_id = u.id LEFT JOIN companies c ON d.company_id = c.id WHERE d.company_id IN ($cids_in) ORDER BY d.visit_date DESC, d.created_at DESC");
    $stmt->execute();
}
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales Report (DSR) - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434221">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434221">
    <style>
        .dsr-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; }
        @media (max-width: 992px) { .dsr-grid { grid-template-columns: 1fr; } }
        .report-pic { width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-top: 10px; border: 1px solid var(--glass-border); }
        .geo-info { font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Daily Sales Report (DSR)</h1>
                <p style="color:var(--text-muted)">Track field visits, client meetings, and sales progress.</p>
            </div>
            <?php if ($role === 'sales_person'): ?>
                <button class="btn btn-primary" onclick="document.getElementById('dsrModal').classList.add('open')">+ Submit New Report</button>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?>
            <div class="flash-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="dsr-grid">
            <!-- Left: Stats or Recently Logged -->
            <div class="content-card">
                <div class="card-header"><h2>📊 Summary</h2></div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div style="padding:1rem; background:#f8fafc; border-radius:12px; text-align:center;">
                        <div style="font-size:1.5rem; font-weight:800; color:var(--primary-color)"><?= count($reports) ?></div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Total Reports</div>
                    </div>
                    <div style="padding:1rem; background:#f8fafc; border-radius:12px; text-align:center;">
                        <div style="font-size:1.5rem; font-weight:800; color:#10b981;"><?= count(array_filter($reports, fn($r) => $r['visit_date'] === date('Y-m-d'))) ?></div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Today's Visits</div>
                    </div>
                </div>
            </div>

            <!-- Right: Reports History -->
            <div class="content-card">
                <div class="card-header"><h2>📜 Visit History</h2></div>
                <?php if (empty($reports)): ?>
                    <div style="padding:3rem; text-align:center; color:var(--text-muted);">No reports found. Submit your first DSR to get started!</div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:1.5rem;">
                        <?php foreach($reports as $r): ?>
                        <div style="padding:1.5rem; border:1px solid var(--glass-border); border-radius:12px; background:#fff;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <h3 style="margin:0; font-size:1.1rem;"><?= htmlspecialchars($r['client_name']) ?></h3>
                                    <?php if(isset($r['company_name']) && $r['company_id'] != $cid): ?>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:2px;">(Branch: <?= htmlspecialchars($r['company_name']) ?>)</div>
                                    <?php endif; ?>
                                    <div style="font-size:0.85rem; color:var(--primary-color); font-weight:600;"><?= htmlspecialchars($r['visit_purpose']) ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:0.85rem; font-weight:600;"><?= date('M d, Y', strtotime($r['visit_date'])) ?></div>
                                    <?php if(isset($r['staff_name'])): ?>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">By: <?= htmlspecialchars($r['staff_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p style="font-size:0.9rem; color:var(--text-muted); margin:0;"><?= nl2br(htmlspecialchars($r['notes'])) ?></p>
                            <?php if ($r['visit_photo']): ?>
                                <img src="../<?= $r['visit_photo'] ?>" class="report-pic" alt="Visit Photo" onclick="window.open(this.src)">
                            <?php endif; ?>
                            <?php if ($r['latitude']): ?>
                                <div class="geo-info">📍 <?= $r['latitude'] ?>, <?= $r['longitude'] ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<!-- DSR Submission Modal -->
<div class="modal-overlay" id="dsrModal">
    <div class="modal-box" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('dsrModal').classList.remove('open')">&times;</button>
        <h3>Submit Daily Sales Report</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_dsr">
            <input type="hidden" name="latitude" id="latInp">
            <input type="hidden" name="longitude" id="lngInp">
            
            <div class="form-group">
                <label>Client Name / Business Name *</label>
                <input type="text" name="client_name" class="form-control" required placeholder="e.g. Acme Solutions">
            </div>
            
            <div class="form-group">
                <label>Purpose of Visit *</label>
                <select name="visit_purpose" class="form-control" required>
                    <option value="Lead Generation">Lead Generation</option>
                    <option value="Follow-up">Follow-up</option>
                    <option value="Product Demo">Product Demo</option>
                    <option value="Closing / Payment">Closing / Payment</option>
                    <option value="Relationship Building">Relationship Building</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Visit Photo (Take live photo) *</label>
                <input type="file" name="visit_photo" class="form-control" accept="image/*" capture="environment" required>
                <small style="color:var(--text-muted)">Take a photo of the client location or meeting.</small>
            </div>

            <div class="form-group">
                <label>Visit Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="What happened during the visit?"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('dsrModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Submit Report</button>
            </div>
        </form>
    </div>
</div>

<script>
// Get Location
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('latInp').value = pos.coords.latitude;
        document.getElementById('lngInp').value = pos.coords.longitude;
    }, err => console.log("Location denied"));
}
</script>
</body>
</html>
