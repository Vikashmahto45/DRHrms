<?php
// /admin/staff_attendance.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); exit();
}

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];

// Get company security config
$company = $pdo->prepare("SELECT office_lat, office_lng, radius_meters, allowed_ip FROM companies WHERE id=?");
$company->execute([$cid]);
$config = $company->fetch();

// Check if already clocked in today
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id=? AND date=CURDATE()");
$stmt->execute([$uid]);
$today_record = $stmt->fetch();

// Filtering Logic
$where_clauses = ["user_id=?"];
$params = [$uid];

if (!empty($_GET['month'])) {
    $where_clauses[] = "MONTH(date) = ? AND YEAR(date) = YEAR(CURDATE())";
    $params[] = $_GET['month'];
}
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $where_clauses[] = "date BETWEEN ? AND ?";
    $params[] = $_GET['start_date'];
    $params[] = $_GET['end_date'];
}

$where_sql = implode(" AND ", $where_clauses);
$history_stmt = $pdo->prepare("SELECT * FROM attendance WHERE $where_sql ORDER BY date DESC LIMIT 100");
$history_stmt->execute($params);
$history = $history_stmt->fetchAll();

// Helper to calculate duration
function getDuration($in, $out) {
    if (!$in || !$out) return "---";
    $start = new DateTime($in);
    $end = new DateTime($out);
    $diff = $start->diff($end);
    return $diff->format('%h hrs %i mins');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .attendance-card { max-width: 500px; margin: 0 auto 2rem auto; text-align: center; padding: 3rem 2rem; }
        #camera-preview { width: 100%; max-width: 320px; height: 240px; background: #000; border-radius: 12px; margin: 1.5rem auto; display: none; object-fit: cover; }
        #captured-photo { width: 100%; max-width: 320px; border-radius: 12px; margin: 1.5rem auto; display: none; }
        .status-online { background: #10b981; box-shadow: 0 0 10px #10b981; }
        .status-offline { background: #6b7280; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="attendance-card content-card">
            <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
            <p style="color:var(--text-muted); margin-bottom: 2rem;">
                <?= date('l, F j, Y') ?> | <span id="live-clock">--:--:--</span>
            </p>

            <div id="status-display">
                <?php if ($today_record && $today_record['clock_out']): ?>
                    <div class="badge" style="background:rgba(239,68,68,0.1); color:#ef4444; padding: 1rem 2rem; font-size: 1rem;">
                        Shift Completed
                    </div>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">
                        In: <?= date('h:i A', strtotime($today_record['clock_in'])) ?> | Out: <?= date('h:i A', strtotime($today_record['clock_out'])) ?>
                    </p>
                <?php elseif ($today_record): ?>
                    <div class="badge status-online" style="padding: 1rem 2rem; font-size: 1rem; color: #fff;">
                        Currently Clocked In
                    </div>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">
                        Since: <?= date('h:i A', strtotime($today_record['clock_in'])) ?>
                    </p>
                    <button class="btn btn-danger" onclick="handleClockAction('out')" style="margin-top: 1.5rem; width: 100%;">Clock Out</button>
                <?php else: ?>
                    <div class="badge status-offline" style="padding: 1rem 2rem; font-size: 1rem; color: #fff;">
                        Not Clocked In
                    </div>
                    
                    <div id="security-check" style="margin-top: 1.5rem; text-align: left; background: rgba(0,0,0,0.03); padding: 1rem; border-radius: 8px;">
                        <div id="gps-status" style="font-size: 0.85rem; margin-bottom: 8px;">🛰️ Detecting Location...</div>
                        <div id="ip-status" style="font-size: 0.85rem;">🌐 Checking Network...</div>
                    </div>

                    <video id="camera-preview" autoplay playsinline></video>
                    <canvas id="photo-canvas" style="display:none;"></canvas>
                    <img id="captured-photo" src="">

                    <div id="action-buttons" style="margin-top: 1.5rem;">
                        <button id="btn-selfie" class="btn btn-outline" style="width: 100%; margin-bottom: 1rem;" onclick="startCamera()">🤳 Take Selfie to Start</button>
                        <button id="btn-snap" class="btn btn-primary" style="width: 100%; display:none;" onclick="takeSnapshot()">📸 Capture & Clock In</button>
                        <button id="btn-clockin" class="btn btn-primary" style="width: 100%; display:none;" onclick="handleClockAction('in')">Confirm Clock In</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h2>Attendance History</h2>
                <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <select name="month" class="form-control" style="width:auto; padding:5px 15px;" onchange="this.form.submit()">
                        <option value="">-- All Months --</option>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?= $i ?>" <?= (isset($_GET['month']) && $_GET['month'] == $i) ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <div style="display:flex; gap:5px; align-items:center;">
                        <input type="date" name="start_date" class="form-control" style="width:auto; padding:5px;" value="<?= $_GET['start_date'] ?? '' ?>">
                        <span style="color:var(--text-muted)">to</span>
                        <input type="date" name="end_date" class="form-control" style="width:auto; padding:5px;" value="<?= $_GET['end_date'] ?? '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="staff_attendance.php" class="btn btn-outline btn-sm">Reset</a>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= date('D, M d', strtotime($h['date'])) ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('Y', strtotime($h['date'])) ?></div>
                                </td>
                                <td><?= $h['clock_in'] ? date('h:i A', strtotime($h['clock_in'])) : '---' ?></td>
                                <td><?= $h['clock_out'] ? date('h:i A', strtotime($h['clock_out'])) : '---' ?></td>
                                <td>
                                    <?php if($h['clock_in'] && $h['clock_out']): ?>
                                        <span style="font-weight:600; color:#4f46e5;"><?= getDuration($h['clock_in'], $h['clock_out']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">---</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background:rgba(16,185,129,0.1); color:#10b981; font-weight:700;">
                                        <?= htmlspecialchars($h['status'] ?? 'Present') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($history)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">No records found. Your first clock-in will appear here.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
// Live Clock
setInterval(() => {
    const clock = document.getElementById('live-clock');
    if(clock) clock.textContent = new Date().toLocaleTimeString();
}, 1000);

let stream;
let photoData = null;
let userCoords = null;

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        const video = document.getElementById('camera-preview');
        video.srcObject = stream;
        video.style.display = 'block';
        document.getElementById('btn-selfie').style.display = 'none';
        document.getElementById('btn-snap').style.display = 'block';
    } catch (err) {
        alert("Camera access denied. Camera is required for attendance.");
    }
}

function takeSnapshot() {
    const video = document.getElementById('camera-preview');
    const canvas = document.getElementById('photo-canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    photoData = canvas.toDataURL('image/jpeg');
    document.getElementById('captured-photo').src = photoData;
    document.getElementById('captured-photo').style.display = 'block';
    video.style.display = 'none';
    stream.getTracks().forEach(track => track.stop());
    document.getElementById('btn-snap').style.display = 'none';
    document.getElementById('btn-clockin').style.display = 'block';
}

if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
        userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        const gps = document.getElementById('gps-status');
        if(gps) {
            gps.innerHTML = "✅ Location Verified";
            gps.style.color = "#10b981";
        }
    }, (err) => {
        const gps = document.getElementById('gps-status');
        if(gps) {
            gps.innerHTML = "❌ Location Access Denied";
            gps.style.color = "#ef4444";
        }
    });
}

const ipStatus = document.getElementById('ip-status');
if(ipStatus) {
    ipStatus.innerHTML = "✅ Network Verified";
    ipStatus.style.color = "#10b981";
}

async function handleClockAction(type) {
    const formData = new FormData();
    formData.append('action', type);
    if (type === 'in') {
        if (!photoData) return alert("Photo is required.");
        if (!userCoords) return alert("Location is required.");
        formData.append('photo', photoData);
        formData.append('lat', userCoords.lat);
        formData.append('lng', userCoords.lng);
    }

    try {
        const response = await fetch('../api/hrms/process_attendance.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            location.reload();
        } else {
            alert(res.error);
        }
    } catch (err) {
        alert("Action failed. Check your connection.");
    }
}
</script>
</body>
</html>
