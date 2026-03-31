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

// DB Schema Auto-patch for deal_status and sales fields
try {
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS deal_status VARCHAR(50) DEFAULT 'In Progress' AFTER visit_purpose");
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS product_id INT NULL DEFAULT NULL AFTER user_id");
    $pdo->exec("ALTER TABLE dsr ADD COLUMN IF NOT EXISTS sold_price DECIMAL(15,2) NULL DEFAULT NULL AFTER deal_status");
} catch (Exception $e) { /* Fallback for older MySQL without IF NOT EXISTS */
    try { $pdo->exec("ALTER TABLE dsr ADD COLUMN product_id INT NULL DEFAULT NULL AFTER user_id"); } catch(Exception $ex){}
    try { $pdo->exec("ALTER TABLE dsr ADD COLUMN sold_price DECIMAL(15,2) NULL DEFAULT NULL AFTER deal_status"); } catch(Exception $ex){}
}

// Fetch products for dropdown
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$all_products = $stmt->fetchAll();

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_dsr') {
    $client = trim($_POST['client_name'] ?? '');
    $purpose = trim($_POST['visit_purpose'] ?? '');
    $deal_status = trim($_POST['deal_status'] ?? 'In Progress');
    $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $sold_price = !empty($_POST['sold_price']) ? (float)$_POST['sold_price'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $lat = $_POST['latitude'] ?? '';
    $lng = $_POST['longitude'] ?? '';
    $date = date('Y-m-d');

    if ($client && $purpose && $lat && $lng) {
        $photo_path = '';
        $photo_b64 = $_POST['live_photo_b64'] ?? '';
        
        if ($photo_b64) {
            $photo_b64 = str_replace('data:image/png;base64,', '', $photo_b64);
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
            $stmt = $pdo->prepare("INSERT INTO dsr (user_id, company_id, client_name, visit_purpose, deal_status, product_id, sold_price, visit_photo, notes, latitude, longitude, visit_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$uid, $cid, $client, $purpose, $deal_status, $product_id, $sold_price, $photo_path, $notes, $lat, $lng, $date]);
            $msg = "DSR submitted and client timeline updated successfully!"; $msgType = 'success';
        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage(); $msgType = 'error';
        }
    } else {
        $msg = "Missing required fields or GPS location."; $msgType = 'error';
    }
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
    $stmt = $pdo->prepare("SELECT d.*, p.name as product_name FROM dsr d LEFT JOIN products p ON d.product_id = p.id WHERE d.user_id = ? ORDER BY d.visit_date DESC, d.created_at DESC");
    $stmt->execute([$uid]);
} else {
    // Admins see all reports for the company and sub-branches
    $stmt = $pdo->prepare("SELECT d.*, u.name as staff_name, c.name as company_name, p.name as product_name FROM dsr d JOIN users u ON d.user_id = u.id LEFT JOIN companies c ON d.company_id = c.id LEFT JOIN products p ON d.product_id = p.id WHERE d.company_id IN ($cids_in) ORDER BY d.visit_date DESC, d.created_at DESC");
    $stmt->execute();
}
$reports = $stmt->fetchAll();

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
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        .dsr-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        @media (max-width: 992px) { .dsr-grid { grid-template-columns: 1fr; } }
        
        .timeline-card { background: #fff; border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; }
        .timeline-header { padding: 1.2rem 1.5rem; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
        .timeline-header:hover { background: #f1f5f9; }
        .timeline-body { display: none; padding: 1.5rem; border-top: 1px solid var(--glass-border); background: #fafafa; }
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
                    <div style="padding:1.5rem; background:#f8fafc; border-radius:12px; text-align:center;">
                        <div style="font-size:2rem; font-weight:800; color:var(--primary-color)"><?= count($grouped_clients) ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Active Clients</div>
                    </div>
                    <div style="padding:1.5rem; background:#f8fafc; border-radius:12px; text-align:center;">
                        <div style="font-size:2rem; font-weight:800; color:#10b981;"><?= count($reports) ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Total DSR Visits</div>
                    </div>
                </div>
            </div>

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
                                    <div style="font-size:0.8rem; color:var(--text-muted);">Last Visit: <?= date('M d, Y', strtotime($latest['visit_date'])) ?> &bull; <?= count($visits) ?> Total Visits</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <span class="status-badge <?= $st_class ?>"><?= htmlspecialchars($latest['deal_status'] ?? 'In Progress') ?></span>
                                <span style="color:var(--text-muted);">&#9660;</span>
                            </div>
                        </div>
                        <div class="timeline-body">
                            <!-- Visits Sequence -->
                            <?php foreach($visits as $v): ?>
                                <div class="visit-event">
                                    <div style="background:#fff; border:1px solid var(--glass-border); padding:1rem; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                        <div style="display:flex; justify-content:space-between; margin-bottom: 8px;">
                                            <div>
                                                <strong style="color:var(--primary-color); font-size:1rem;"><?= htmlspecialchars($v['visit_purpose']) ?></strong>
                                                <?php if($role !== 'sales_person'): ?>
                                                    <div style="margin-top:4px;">
                                                        <span style="font-size:0.75rem; color:var(--text-muted);">👤 Agent: <?= htmlspecialchars($v['staff_name']) ?></span>
                                                        <span style="font-size:0.75rem; background:#f1f5f9; color:#6366f1; padding:2px 6px; border-radius:4px; margin-left:8px; font-weight:600;">🏢 Branch: <?= htmlspecialchars($v['company_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($v['product_name']): ?>
                                                    <div style="font-size:0.85rem; color:#6366f1; font-weight:600; margin-top:4px;">📦 Product: <?= htmlspecialchars($v['product_name']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($v['sold_price']): ?>
                                                    <div style="font-size:0.9rem; color:#10b981; font-weight:700; margin-top:2px;">💰 Sold for: ₹<?= number_format($v['sold_price'], 2) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;"><?= date('M d, Y', strtotime($v['visit_date'])) ?></span>
                                        </div>
                                        <p style="font-size:0.9rem; color:#475569; margin: 0 0 10px 0; line-height:1.5;"><?= nl2br(htmlspecialchars($v['notes'])) ?></p>
                                        
                                        <?php if ($v['visit_photo']): ?>
                                            <img src="<?= BASE_URL . $v['visit_photo'] ?>" class="report-pic" alt="Live Capture" onclick="window.open(this.src)">
                                        <?php endif; ?>
                                        
                                        <?php if ($v['latitude']): ?>
                                            <div class="geo-info">
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
    <div class="modal-box" style="max-width:550px;">
        <button class="modal-close" onclick="closeDsrModal()">&times;</button>
        <h3>Log Field Visit</h3>
        
        <form method="POST" id="dsrForm">
            <input type="hidden" name="action" value="submit_dsr">
            <input type="hidden" name="latitude" id="latInp">
            <input type="hidden" name="longitude" id="lngInp">
            <input type="hidden" name="live_photo_b64" id="photoB64">
            
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Client / Business Name *</label>
                    <input type="text" name="client_name" list="pastClients" class="form-control" required placeholder="Select existing or type new...">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Deal Status *</label>
                    <select name="deal_status" id="dealStatusSelect" class="form-control" required onchange="toggleSoldPrice()">
                        <option value="Initial Meeting">Initial Meeting</option>
                        <option value="Follow-up">Follow-up</option>
                        <option value="Negotiating">Negotiating</option>
                        <option value="Closed Won">Closed Won</option>
                        <option value="Closed Lost">Closed Lost</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Related Product/Service</label>
                    <select name="product_id" class="form-control">
                        <option value="">-- No specific product --</option>
                        <?php foreach($all_products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (₹<?= number_format($p['price'], 0) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex:1; display:none;" id="soldPriceGroup">
                    <label>Sold Price (₹) *</label>
                    <input type="number" step="0.01" name="sold_price" class="form-control" placeholder="0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label>Purpose of Visit *</label>
                <select name="visit_purpose" class="form-control" required>
                    <option value="Introduction">Introduction</option>
                    <option value="Product Demo">Product Demo</option>
                    <option value="Contract Signing">Contract Signing</option>
                    <option value="Payment Collection">Payment Collection</option>
                    <option value="Routine Check-in">Routine Check-in</option>
                </select>
            </div>

            <div class="form-group">
                <label>Secure Live Photo (Gallery Blocked) *</label>
                <div class="cam-wrapper">
                    <video id="videoFeed" autoplay playsinline></video>
                    <img id="photoPreview">
                    <canvas id="canvasFeed" style="display:none;"></canvas>
                    
                    <div class="cam-overlay" id="camControls">
                        <button type="button" class="btn btn-primary" onclick="snapPhoto()" style="padding:10px 20px; border-radius:30px; font-weight:bold; box-shadow:0 4px 15px rgba(0,0,0,0.3);">📸 Snap Photo</button>
                    </div>
                    <div class="cam-overlay" id="camRetake" style="display:none;">
                        <button type="button" class="btn btn-outline" onclick="retakePhoto()" style="background:#fff; padding:8px 15px; border-radius:30px; font-weight:bold;">🔄 Retake</button>
                    </div>
                </div>
                <small style="color:var(--text-muted); display:block; margin-top:5px;">This system explicitly prohibits file uploads. You must snap a photo live on location.</small>
            </div>

            <div class="form-group">
                <label>Visit Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Documentation of the meeting..."></textarea>
            </div>

            <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--glass-border); padding-top:15px;">
                <div id="locStatus" style="font-size:0.85rem; color:#f59e0b; display:flex; align-items:center; gap:5px;">
                    <span class="spinner" style="width:14px;height:14px;border:2px solid rgba(245,158,11,0.2);border-top-color:#f59e0b;border-radius:50%;animation:spin 1s linear infinite; display:inline-block;"></span>
                    Awaiting GPS Lock...
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeDsrModal()">Cancel</button>
                    <button type="button" id="submitDSRBtn" class="btn btn-primary" disabled onclick="validateAndSubmit()">Submit Secured Report</button>
                </div>
            </div>
        </form>
    </div>
</div>
<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>

<script>
let streamGlobal = null;

function openDsrModal() {
    document.getElementById('dsrModal').classList.add('open');
    startCamera();
    lockGPS();
}

function closeDsrModal() {
    document.getElementById('dsrModal').classList.remove('open');
    if (streamGlobal) {
        streamGlobal.getTracks().forEach(track => track.stop());
    }
}

function startCamera() {
    const video = document.getElementById('videoFeed');
    const preview = document.getElementById('photoPreview');
    video.style.display = 'block';
    preview.style.display = 'none';
    document.getElementById('camControls').style.display = 'block';
    document.getElementById('camRetake').style.display = 'none';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
    .then(stream => {
        streamGlobal = stream;
        video.srcObject = stream;
    })
    .catch(err => {
        alert("Camera access denied or unsupported! Please grant camera permissions to log your visit.");
    });
}

function snapPhoto() {
    const video = document.getElementById('videoFeed');
    const canvas = document.getElementById('canvasFeed');
    const preview = document.getElementById('photoPreview');
    
    // Draw frame to canvas
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Get Base64
    const dataUrl = canvas.toDataURL('image/png');
    document.getElementById('photoB64').value = dataUrl;
    
    // Show Preview
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

function lockGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('latInp').value = pos.coords.latitude;
            document.getElementById('lngInp').value = pos.coords.longitude;
            
            let stat = document.getElementById('locStatus');
            stat.innerHTML = '✅ GPS Locked';
            stat.style.color = '#10b981';
            document.getElementById('submitDSRBtn').disabled = false;
        }, err => {
            let stat = document.getElementById('locStatus');
            stat.innerHTML = '❌ Location Denied';
            stat.style.color = '#ef4444';
            alert("You must allow Location access to submit a Daily Sales Report.");
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    } else {
        document.getElementById('locStatus').innerHTML = '❌ Unsupported Browser';
    }
}

function toggleSoldPrice() {
    const status = document.getElementById('dealStatusSelect').value;
    const group = document.getElementById('soldPriceGroup');
    group.style.display = (status === 'Closed Won') ? 'block' : 'none';
}

function validateAndSubmit() {
    if (!document.getElementById('photoB64').value) {
        alert("You must snap a live photo of the visit!");
        return;
    }
    if (!document.getElementById('latInp').value) {
        alert("GPS Lock is required. Please wait for coordinates or check permissions.");
        return;
    }
    document.getElementById('dsrForm').submit();
}
</script>
</body>
</html>
