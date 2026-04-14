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

// DB Schema Auto-patch for CRM & Multi-Product Support
try {
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS activity_type VARCHAR(50) DEFAULT 'Regular Visit' AFTER visit_purpose");
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS project_details TEXT NULL AFTER notes");
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS custom_project_name VARCHAR(255) NULL AFTER client_name");
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS location_name TEXT NULL AFTER latitude");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dsr_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dsr_id INT NOT NULL,
        product_id INT NOT NULL,
        custom_price DECIMAL(15,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Migration: Move existing single product_id/sold_price to dsr_items if not already done
    $checkMigrated = $pdo->query("SELECT COUNT(*) FROM dsr_items")->fetchColumn();
    if ($checkMigrated == 0) {
        $pdo->exec("INSERT INTO dsr_items (dsr_id, product_id, custom_price) 
                    SELECT id, product_id, sold_price FROM dsr WHERE product_id IS NOT NULL");
    }
} catch (Exception $e) { /* Fallback for MySQL compatibility */ }

// Fetch products for dropdown
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$all_products = $stmt->fetchAll();

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_dsr') {
    $client = trim($_POST['client_name'] ?? '');
    $custom_project_name = trim($_POST['custom_project_name'] ?? '');
    $activity_type = trim($_POST['activity_type'] ?? 'Regular Visit');
    $purpose = trim($_POST['visit_purpose'] ?? '');
    if (empty($purpose)) { $purpose = $activity_type; } // Default purpose if blank
    $deal_status = trim($_POST['deal_status'] ?? 'In Progress');
    $notes = trim($_POST['notes'] ?? '');
    $project_details = trim($_POST['project_details'] ?? '');
    $lat = $_POST['latitude'] ?? '';
    $lng = $_POST['longitude'] ?? '';
    $location_name = $_POST['location_name'] ?? '';
    $date = date('Y-m-d');

    // Products Array
    $product_ids = $_POST['product_ids'] ?? [];
    $custom_prices = $_POST['custom_prices'] ?? [];

    // Validation: Only Visits require Camera/GPS
    $is_visit = ($activity_type === 'Regular Visit');
    $gps_locked = $lat && $lng;
    $photo_b64 = $_POST['live_photo_b64'] ?? '';

    if ($client && $purpose && (!$is_visit || ($gps_locked && $photo_b64))) {
        $photo_path = '';
        
        if ($photo_b64 && $is_visit) {
            $photo_b64 = str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,'], '', $photo_b64);
            $photo_b64 = str_replace(' ', '+', $photo_b64);
            $data = base64_decode($photo_b64);
            $filename = "dsr_" . time() . "_" . uniqid() . ".png";
            $target_dir = "../assets/uploads/dsr";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $target = $target_dir . "/" . $filename;
            file_put_contents($target, $data);
            $photo_path = "assets/uploads/dsr/" . $filename;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO dsr (user_id, company_id, client_name, custom_project_name, activity_type, visit_purpose, deal_status, visit_photo, notes, project_details, latitude, longitude, location_name, visit_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$uid, $cid, $client, $custom_project_name, $activity_type, $purpose, $deal_status, $photo_path, $notes, $project_details, $lat, $lng, $location_name, $date]);
            $new_dsr_id = $pdo->lastInsertId();

            // Insert Multi-Products
            if (!empty($product_ids)) {
                $item_stmt = $pdo->prepare("INSERT INTO dsr_items (dsr_id, product_id, custom_price) VALUES (?,?,?)");
                for ($i=0; $i<count($product_ids); $i++) {
                    if (!empty($product_ids[$i])) {
                        $item_stmt->execute([$new_dsr_id, (int)$product_ids[$i], (float)$custom_prices[$i]]);
                    }
                }
            }

            $pdo->commit();
            $msg = "CRM Entry logged successfully!"; $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage(); $msgType = 'error';
        }
    } else {
        $msg = "Missing required fields. Regular Visits require GPS and Live Photo."; $msgType = 'error';
    }
}

// Convert DSR to Project Logic
if (isset($_GET['convert_project'])) {
    $dsr_id = (int)$_GET['convert_project'];
    try {
        // Fetch DSR & its latest total value
        $stmt = $pdo->prepare("SELECT d.*, (SELECT SUM(custom_price) FROM dsr_items WHERE dsr_id = d.id) as total_val FROM dsr d WHERE d.id = ? AND d.user_id = ?");
        $stmt->execute([$dsr_id, $uid]);
        $dsr_data = $stmt->fetch();
        
        if ($dsr_data && $dsr_data['deal_status'] === 'Closed Won') {
            // Check if already converted
            $check = $pdo->prepare("SELECT id FROM projects WHERE client_name = ? AND project_name LIKE ? AND company_id = ?");
            $check->execute([$dsr_data['client_name'], "%" . $dsr_data['project_details'] . "%", $cid]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO projects (company_id, branch_id, sales_person_id, client_name, project_name, project_description, total_value, status, progress_pct) VALUES (?,?,?,?,?,?,?, 'Pending Approval', 0)");
                $ins->execute([$cid, $branch_ids[0], $uid, $dsr_data['client_name'], "Project: " . ($dsr_data['project_details'] ?: 'New Deal'), $dsr_data['notes'], $dsr_data['total_val']]);
                header("Location: projects.php?msg=Converted to Project. Awaiting Admin Verification."); exit();
            } else { $msg = "Already converted."; $msgType = "warning"; }
        }
    } catch (Exception $e) { $msg = $e->getMessage(); $msgType = "error"; }
}

// Fetch Past Clients for Datalist (Salesman Only)
$past_clients = [];
if ($role === 'sales_person') {
    $stmt = $pdo->prepare("SELECT DISTINCT client_name FROM dsr WHERE user_id = ? ORDER BY client_name ASC");
    $stmt->execute([$uid]);
    $past_clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch Reports
if ($role === 'sales_person') {
    $stmt = $pdo->prepare("SELECT * FROM dsr WHERE user_id = ? ORDER BY visit_date DESC, created_at DESC");
    $stmt->execute([$uid]);
} else {
    $stmt = $pdo->prepare("SELECT d.*, u.name as staff_name, c.name as company_name FROM dsr d JOIN users u ON d.user_id = u.id LEFT JOIN companies c ON d.company_id = c.id WHERE d.company_id IN ($cids_in) ORDER BY d.visit_date DESC, d.created_at DESC");
    $stmt->execute();
}
$reports = $stmt->fetchAll();

// Fetch Items for all reports
foreach ($reports as &$r) {
    $it_stmt = $pdo->prepare("SELECT di.*, p.name as product_name FROM dsr_items di JOIN products p ON di.product_id = p.id WHERE di.dsr_id = ?");
    $it_stmt->execute([$r['id']]);
    $r['items'] = $it_stmt->fetchAll();
    $r['total_deal_value'] = array_sum(array_column($r['items'], 'custom_price'));
}

// Group reports by Client Name
$grouped_clients = [];
foreach ($reports as $r) {
    $cls = trim($r['client_name']);
    if (!isset($grouped_clients[$cls])) {
        $grouped_clients[$cls] = [];
    }
    $grouped_clients[$cls][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced DSR Timelines - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        .dsr-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        @media (max-width: 992px) { .dsr-grid { grid-template-columns: 1fr; } }
        
        .timeline-card { background: #fff; border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; }
        .timeline-header { padding: 1.2rem 1.5rem; background: var(--bg-main); display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
        .timeline-header:hover { background: rgba(79, 70, 229, 0.05); }
        .timeline-body { display: none; padding: 1.5rem; border-top: 1px solid var(--glass-border); background: var(--card-bg); }
        .timeline-body.open { display: block; }
        
        .visit-event { position: relative; padding-left: 30px; margin-bottom: 25px; }
        .visit-event::before { content: ''; position: absolute; left: 0; top: 5px; width: 14px; height: 14px; background: var(--primary-color); border-radius: 50%; box-shadow: 0 0 0 4px rgba(99,102,241,0.2); }
        .visit-event::after { content: ''; position: absolute; left: 6px; top: 25px; bottom: -25px; width: 2px; background: var(--glass-border); }
        .visit-event:last-child { margin-bottom: 0; }
        .visit-event:last-child::after { display: none; }
        
        .report-pic { width: 100%; max-width: 250px; height: 150px; object-fit: cover; border-radius: 8px; margin-top: 10px; border: 1px solid var(--glass-border); cursor:pointer; }
        .geo-info { font-size: 0.75rem; color: var(--text-muted); margin-top: 5px; display: flex; align-items: center; gap: 5px; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .st-In-Progress { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .st-Negotiating { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .st-Closed-Won { background: rgba(16,185,129,0.1); color: #10b981; }
        .st-Closed-Lost { background: rgba(239,68,68,0.1); color: #ef4444; }

        .cam-wrapper { position: relative; width: 100%; border-radius: 12px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center; min-height: 250px; }
        #videoFeed, #photoPreview { width: 100%; max-height: 300px; object-fit: cover; display: none; }
        .cam-overlay { position: absolute; bottom: 15px; left:0; right:0; text-align: center; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Advanced CRM DSR Tracker</h1>
                <p style="color:var(--text-muted)">Track progressive client timelines and secure Live-Camera field visits.</p>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="window.print()" class="btn btn-outline">Print DSR Timeline</button>
                <?php if ($role === 'sales_person'): ?>
                    <button class="btn btn-primary" onclick="openDsrModal()">+ Log Visit / Submit DSR</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="flash-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="dsr-grid">
            <!-- Left: Stats -->
            <div class="content-card">
                <div class="card-header"><h2>📊 Portfolio Summary</h2></div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom: 2rem;">
                    <div class="stat-box-clickable" onclick="document.querySelector('.dsr-grid > div:last-child').scrollIntoView({behavior:'smooth'})" style="padding:1.5rem; background:var(--bg-main); border-radius:12px; text-align:center; border: 1px solid var(--glass-border); cursor:pointer; transition:transform 0.2s;">
                        <div style="font-size:2rem; font-weight:800; color:var(--primary-color)"><?= count($grouped_clients) ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Active Clients</div>
                    </div>
                    <div class="stat-box-clickable" onclick="document.querySelector('.dsr-grid > div:last-child').scrollIntoView({behavior:'smooth'})" style="padding:1.5rem; background:var(--bg-main); border-radius:12px; text-align:center; border: 1px solid var(--glass-border); cursor:pointer; transition:transform 0.2s;">
                        <div style="font-size:2rem; font-weight:800; color:#10b981;"><?= count($reports) ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Total DSR Visits</div>
                    </div>
                </div>
            </div>
            <style>
                .stat-box-clickable:hover { transform: translateY(-5px); border-color: var(--primary-color) !important; background: #fff !important; box-shadow: var(--glass-shadow); }
            </style>

            <!-- Right: Client Timelines -->
            <div class="content-card" style="padding:0; background:transparent; border:none; box-shadow:none;">
                <?php if (empty($grouped_clients)): ?>
                    <div class="content-card" style="padding:3rem; text-align:center; color:var(--text-muted);">No clients registered. Log your first visit!</div>
                <?php else: ?>
                    <?php foreach($grouped_clients as $client_name => $visits): 
                        $latest = $visits[0]; // Ordered by DESC in SQL
                        $st_class = "st-" . str_replace([' ', '(', ')'], ['-', '', ''], $latest['deal_status'] ?? 'In-Progress');
                    ?>
                    <div class="timeline-card">
                        <div class="timeline-header" onclick="this.nextElementSibling.classList.toggle('open')">
                            <div style="display:flex; align-items:center; gap:15px;">
                                <div style="width:40px;height:40px;background:var(--primary-color);color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;">
                                    <?= strtoupper(substr($client_name,0,1)) ?>
                                </div>
                                <div>
                                    <h3 style="margin:0; font-size:1.1rem; color:#1e293b;"><?= htmlspecialchars($client_name) ?></h3>
                                    <div style="font-size:0.8rem; color:var(--text-muted);">Status: <strong><?= htmlspecialchars($latest['deal_status']) ?></strong> &bull; <?= count($visits) ?> Activities</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <?php if ($latest['total_deal_value'] > 0): ?>
                                    <span style="font-weight:700; color:#10b981;">₹<?= number_format($latest['total_deal_value'], 0) ?></span>
                                <?php endif; ?>
                                <?php if ($latest['deal_status'] === 'Closed Won'): ?>
                                    <a href="?convert_project=<?= $latest['id'] ?>" class="btn btn-sm btn-primary" style="background:#4f46e5; border:none;" onclick="event.stopPropagation()">🏗️ Convert to Project</a>
                                <?php endif; ?>
                                <span style="color:var(--text-muted);">&#9660;</span>
                            </div>
                        </div>
                        <div class="timeline-body">
                            <!-- Visits Sequence -->
                            <?php foreach($visits as $v): 
                                $act_icon = match($v['activity_type']) {
                                    'Phone Call' => '📞',
                                    'Email' => '📧',
                                    'Meeting' => '🤝',
                                    'Follow-up' => '🔄',
                                    default => '📍'
                                };
                            ?>
                                <div class="visit-event">
                                    <div style="background:#fff; border:1px solid var(--glass-border); padding:1.2rem; border-radius:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <div style="display:flex; justify-content:space-between; margin-bottom: 12px; align-items:flex-start;">
                                            <div>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <span style="font-size:1.2rem;"><?= $act_icon ?></span>
                                                    <strong style="color:var(--primary-color); font-size:1.1rem;"><?= htmlspecialchars($v['visit_purpose']) ?></strong>
                                                    <span class="status-badge st-<?= str_replace(' ', '-', $v['deal_status']) ?>" style="font-size:0.65rem;"><?= $v['deal_status'] ?></span>
                                                </div>
                                                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">
                                                    Type: <strong><?= $v['activity_type'] ?></strong>
                                                    <?php if($role !== 'sales_person'): ?>
                                                        &bull; By: <strong><?= htmlspecialchars($v['staff_name']) ?></strong>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;"><?= date('M d, Y', strtotime($v['visit_date'])) ?></span>
                                        </div>

                                        <!-- Project / Note Details -->
                                        <div style="margin-bottom:15px;">
                                            <p style="font-size:0.95rem; color:#1e293b; margin: 0 0 8px 0; line-height:1.6;"><?= nl2br(htmlspecialchars($v['notes'])) ?></p>
                                            <?php if ($v['project_details']): ?>
                                                <div style="background:var(--bg-main); padding:10px; border-left:3px solid var(--primary-color); border-radius:4px; font-size:0.85rem; color:#475569;">
                                                    <strong>Project Details:</strong><br>
                                                    <?= nl2br(htmlspecialchars($v['project_details'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Multi-Product List -->
                                        <?php if (!empty($v['items'])): ?>
                                            <div style="border-top:1px dashed #e2e8f0; padding-top:10px; margin-top:10px;">
                                                <table style="width:100%; font-size:0.85rem;">
                                                    <?php foreach($v['items'] as $item): ?>
                                                        <tr>
                                                            <td style="color:var(--text-muted); padding:2px 0;">📦 <?= htmlspecialchars($item['product_name']) ?></td>
                                                            <td style="text-align:right; font-weight:600; color:#10b981;">₹<?= number_format($item['custom_price'], 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr style="border-top:1px solid #f1f5f9; font-weight:700;">
                                                        <td style="padding-top:5px;">Total Deal Value</td>
                                                        <td style="text-align:right; padding-top:5px; color:var(--primary-color);">₹<?= number_format($v['total_deal_value'], 2) ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($v['visit_photo']): ?>
                                            <img src="<?= BASE_URL . $v['visit_photo'] ?>" class="report-pic" alt="Live Capture" onclick="window.open(this.src)">
                                        <?php endif; ?>
                                        
                                        <?php if ($v['latitude']): ?>
                                            <div class="geo-info" style="margin-top:12px;">
                                                <a href="https://maps.google.com/?q=<?= $v['latitude'] ?>,<?= $v['longitude'] ?>" target="_blank" style="color:var(--primary-color); text-decoration:none;">
                                                    📍 View GPS Location (<?= htmlspecialchars($v['latitude']) ?>, <?= htmlspecialchars($v['longitude']) ?>)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<!-- DSR Submission Modal with Live Camera Booth -->
<datalist id="pastClients">
    <?php foreach($past_clients as $pc): ?>
        <option value="<?= htmlspecialchars($pc) ?>">
    <?php endforeach; ?>
</datalist>

<div class="modal-overlay" id="dsrModal">
    <div class="modal-box" style="max-width:650px;">
        <button class="modal-close" onclick="closeDsrModal()">&times;</button>
        <h3>Log Sales Activity (CRM)</h3>
        
        <form method="POST" id="dsrForm">
            <input type="hidden" name="action" value="submit_dsr">
            <input type="hidden" name="latitude" id="latInp">
            <input type="hidden" name="longitude" id="lngInp">
            <input type="hidden" name="location_name" id="locNameInp">
            <input type="hidden" name="live_photo_b64" id="photoB64">
            
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Client / Business Name *</label>
                    <input type="text" name="client_name" id="clientNameInp" list="pastClients" class="form-control" required placeholder="Select existing or type new..." autocomplete="off" onchange="lookupClientHistory()">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Activity Type *</label>
                    <select name="activity_type" id="activityTypeSelect" class="form-control" required onchange="toggleRequirements()">
                        <option value="Regular Visit">Regular Visit (On-Field)</option>
                        <option value="Phone Call">Phone Call / Discussion</option>
                        <option value="Email">Email Communication</option>
                        <option value="Follow-up">Regular Follow-up</option>
                        <option value="Meeting">Formal Meeting / Demo</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Deal Status *</label>
                    <select name="deal_status" id="dealStatusSelect" class="form-control" required>
                        <option value="Initial Meeting">Initial Meeting</option>
                        <option value="Negotiating">Negotiating</option>
                        <option value="Proposal Sent">Proposal Sent</option>
                        <option value="Closed Won">Closed Won (Sold)</option>
                        <option value="Closed Lost">Closed Lost</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Purpose / Short Subject</label>
                    <input type="text" name="visit_purpose" id="purposeInp" class="form-control" placeholder="e.g. Sales pitch, follow up (Optional)">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Custom Project Name (Optional)</label>
                    <input type="text" name="custom_project_name" id="customProjectInp" class="form-control" placeholder="Specific project identifier...">
                </div>
            </div>

            <!-- Multi-Product Interface -->
            <div style="background:var(--bg-main); padding:15px; border-radius:8px; margin-bottom:15px; border: 1px solid var(--glass-border);">
                <label style="font-weight:600; color:#475569; display:block; margin-bottom:10px;">Select Products & Services</label>
                <div id="productRows">
                    <div class="form-row product-item-row" style="margin-bottom:10px;">
                        <div class="form-group" style="flex:2; margin-bottom:0;">
                            <select name="product_ids[]" class="form-control" onchange="updateDefaultPrice(this)">
                                <option value="">-- Select Product --</option>
                                <?php foreach($all_products as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name']) ?> (₹<?= number_format($p['price'], 0) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1; margin-bottom:0;">
                            <input type="number" step="0.01" name="custom_prices[]" class="form-control price-input" placeholder="Price ₹" oninput="calculateTotal()">
                        </div>
                        <button type="button" class="btn btn-outline" style="border:none; color:#ef4444;" onclick="this.parentElement.remove(); calculateTotal()">✕</button>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; border-top:1px solid #cbd5e1; padding-top:10px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addProductRow()">+ Add Another Item</button>
                    <div style="font-weight:700; color:var(--primary-color);">Total: ₹<span id="liveTotal">0.00</span></div>
                </div>
            </div>
            
            <div class="form-group" id="cameraGroup">
                <label>Secure Live Photo (Gallery Blocked) *</label>
                <div class="cam-wrapper">
                    <video id="videoFeed" autoplay playsinline></video>
                    <img id="photoPreview">
                    <canvas id="canvasFeed" style="display:none;"></canvas>
                    <div class="cam-overlay" id="camControls">
                        <div style="display:flex; justify-content:center; gap:10px;">
                            <button type="button" class="btn btn-primary" onclick="snapPhoto()" style="padding:10px 20px; border-radius:30px; box-shadow:0 4px 15px rgba(0,0,0,0.3);">📸 Snap Photo</button>
                            <button type="button" class="btn btn-outline" onclick="toggleCamera()" title="Switch Camera" style="background:#fff; border-radius:50%; width:44px; height:44px; display:flex; align-items:center; justify-content:center; padding:0;">🔄</button>
                        </div>
                    </div>
                    <div class="cam-overlay" id="camRetake" style="display:none;">
                        <button type="button" class="btn btn-outline" onclick="retakePhoto()" style="background:#fff; padding:8px 15px; border-radius:30px; font-weight:bold;">🔄 Retake</button>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Activity Summary / Notes</label>
                    <textarea name="notes" id="notesInp" class="form-control" rows="2" placeholder="What happened in this interaction?"></textarea>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Project Details / Spec</label>
                    <textarea name="project_details" id="projectDetailsInp" class="form-control" rows="2" placeholder="Technical specs or requirements..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--glass-border); padding-top:15px;">
                <div id="locStatus" style="font-size:0.85rem; color:#f59e0b; display:flex; align-items:center; gap:5px;">
                    <span class="spinner" style="width:14px;height:14px;border:2px solid rgba(245,158,11,0.2);border-top-color:#f59e0b;border-radius:50%;animation:spin 1s linear infinite; display:inline-block;"></span>
                    Awaiting GPS Lock...
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeDsrModal()">Cancel</button>
                    <button type="button" id="submitDSRBtn" class="btn btn-primary" onclick="validateAndSubmit()">Submit Secured Report</button>
                </div>
            </div>
        </form>
    </div>
</div>
<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>

<script>
let streamGlobal = null;
let currentFacingMode = "environment";

async function lookupClientHistory() {
    const clientName = document.getElementById('clientNameInp').value;
    if (!clientName) return;

    try {
        const response = await fetch(`../api/crm/get_client_history.php?client_name=${encodeURIComponent(clientName)}`);
        const data = await response.json();

        if (data && data.status !== 'no_history') {
            // Auto-fill fields
            document.getElementById('customProjectInp').value = data.custom_project_name || '';
            document.getElementById('notesInp').value = data.notes || '';
            document.getElementById('projectDetailsInp').value = data.project_details || '';
            document.getElementById('dealStatusSelect').value = data.deal_status || 'Negotiating';

            // Auto-fill products if any
            if (data.items && data.items.length > 0) {
                const container = document.getElementById('productRows');
                container.innerHTML = ''; // Fresh start
                data.items.forEach(item => {
                    addProductRow();
                    const lastRow = container.lastElementChild;
                    const select = lastRow.querySelector('select');
                    const priceInp = lastRow.querySelector('input');
                    select.value = item.product_id;
                    priceInp.value = item.custom_price;
                });
                calculateTotal();
            }
        }
    } catch (err) { console.error("History lookup failed", err); }
}

function addProductRow() {
    const container = document.getElementById('productRows');
    const row = document.createElement('div');
    row.className = 'form-row product-item-row';
    row.style.marginBottom = '10px';
    row.innerHTML = `
        <div class="form-group" style="flex:2; margin-bottom:0;">
            <select name="product_ids[]" class="form-control" onchange="updateDefaultPrice(this)">
                <option value="">-- Select Product --</option>
                <?php foreach($all_products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1; margin-bottom:0;">
            <input type="number" step="0.01" name="custom_prices[]" class="form-control price-input" placeholder="Price ₹" oninput="calculateTotal()">
        </div>
        <button type="button" class="btn btn-outline" style="border:none; color:#ef4444;" onclick="this.parentElement.remove(); calculateTotal()">✕</button>
    `;
    container.appendChild(row);
}

function updateDefaultPrice(select) {
    const price = select.options[select.selectedIndex].dataset.price;
    const input = select.parentElement.nextElementSibling.querySelector('input');
    if(price) input.value = price;
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.price-input').forEach(input => {
        let val = parseFloat(input.value) || 0;
        total += val;
    });
    document.getElementById('liveTotal').innerText = total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function toggleRequirements() {
    const type = document.getElementById('activityTypeSelect').value;
    const camGroup = document.getElementById('cameraGroup');
    const locStatus = document.getElementById('locStatus');
    const btn = document.getElementById('submitDSRBtn');

    if (type === 'Regular Visit') {
        camGroup.style.display = 'block';
        locStatus.style.display = 'flex';
        btn.disabled = !document.getElementById('latInp').value;
        startCamera();
        lockGPS();
    } else {
        camGroup.style.display = 'none';
        locStatus.style.display = 'none';
        btn.disabled = false;
        if (streamGlobal) streamGlobal.getTracks().forEach(t => t.stop());
    }
}

function openDsrModal() {
    document.getElementById('dsrModal').classList.add('open');
    toggleRequirements();
}

function closeDsrModal() {
    document.getElementById('dsrModal').classList.remove('open');
    if (streamGlobal) streamGlobal.getTracks().forEach(t => t.stop());
}

function startCamera() {
    if (streamGlobal) streamGlobal.getTracks().forEach(t => t.stop());
    
    const video = document.getElementById('videoFeed');
    const preview = document.getElementById('photoPreview');
    video.style.display = 'block';
    preview.style.display = 'none';
    document.getElementById('camControls').style.display = 'block';
    document.getElementById('camRetake').style.display = 'none';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode } })
    .then(stream => {
        streamGlobal = stream;
        video.srcObject = stream;
    })
    .catch(err => {
        console.log("Camera access blocked", err);
        alert("Please enable camera permissions.");
    });
}

function toggleCamera() {
    currentFacingMode = (currentFacingMode === "environment") ? "user" : "environment";
    startCamera();
}

function snapPhoto() {
    const video = document.getElementById('videoFeed');
    const canvas = document.getElementById('canvasFeed');
    const preview = document.getElementById('photoPreview');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');
    document.getElementById('photoB64').value = dataUrl;
    preview.src = dataUrl;
    preview.style.display = 'block';
    video.style.display = 'none';
    document.getElementById('camControls').style.display = 'none';
    document.getElementById('camRetake').style.display = 'block';
}

function retakePhoto() {
    document.getElementById('photoB64').value = '';
    document.getElementById('photoPreview').style.display = 'none';
    document.getElementById('videoFeed').style.display = 'block';
    document.getElementById('camControls').style.display = 'block';
    document.getElementById('camRetake').style.display = 'none';
}

async function reverseGeocode(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        if (data && data.display_name) {
            document.getElementById('locNameInp').value = data.display_name;
            const stat = document.getElementById('locStatus');
            stat.innerHTML = `✅ ${data.display_name.substring(0, 30)}...`;
            stat.title = data.display_name;
        }
    } catch (err) { console.error("Geocoding failed", err); }
}

function lockGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('latInp').value = lat;
            document.getElementById('lngInp').value = lng;
            
            let stat = document.getElementById('locStatus');
            stat.innerHTML = '🕒 Fetching Address...';
            stat.style.color = '#10b981';
            
            reverseGeocode(lat, lng);
            document.getElementById('submitDSRBtn').disabled = false;
        }, null, { enableHighAccuracy: true });
    }
}

function validateAndSubmit() {
    const type = document.getElementById('activityTypeSelect').value;
    if (type === 'Regular Visit') {
        if (!document.getElementById('photoB64').value) return alert("Photo required for visits!");
        if (!document.getElementById('latInp').value) return alert("GPS required for visits!");
    }
    document.getElementById('dsrForm').submit();
}
</script>
</body>
</html>
