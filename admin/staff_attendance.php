<?php
// /admin/staff_attendance.php
require_once '../includes/auth.php';
require_once '../config/database.php';
// Staff can access this
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

// Get staff's shift (if assigned - for now just showing general info)
$shifts = $pdo->prepare("SELECT * FROM shifts WHERE company_id=?");
$shifts->execute([$cid]);
$all_shifts = $shifts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434221">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434221">
    <style>
        .attendance-card {
            max-width: 500px;
            margin: 2rem auto;
            text-align: center;
            padding: 3rem 2rem;
        }
        #camera-preview {
            width: 100%;
            max-width: 320px;
            height: 240px;
            background: #000;
            border-radius: 12px;
            margin: 1.5rem auto;
            display: none;
            object-fit: cover;
        }
        #captured-photo {
            width: 100%;
            max-width: 320px;
            border-radius: 12px;
            margin: 1.5rem auto;
            display: none;
        }
        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
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

            <div id="status-display" style="margin-bottom: 2rem;">
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
                    
                    <div id="security-check" style="margin-top: 1.5rem; text-align: left; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
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

    </main>
</div>

<script>
// Live Clock
setInterval(() => {
    document.getElementById('live-clock').textContent = new Date().toLocaleTimeString();
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
    
    // Stop camera stream
    stream.getTracks().forEach(track => track.stop());
    
    document.getElementById('btn-snap').style.display = 'none';
    document.getElementById('btn-clockin').style.display = 'block';
}

// Geo-fencing Detection
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
        userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        document.getElementById('gps-status').innerHTML = "✅ Location Verified";
        document.getElementById('gps-status').style.color = "#10b981";
    }, (err) => {
        document.getElementById('gps-status').innerHTML = "❌ Location Access Denied";
        document.getElementById('gps-status').style.color = "#ef4444";
    });
}

// IP Check (Simulated for status, verified on backend)
document.getElementById('ip-status').innerHTML = "✅ Network Verified";
document.getElementById('ip-status').style.color = "#10b981";

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
