<?php
// /admin/attendance_settings.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];

$msg = ''; $msgType = '';
if ($_SESSION['flash_msg'] ?? null) {
    $msg = $_SESSION['flash_msg']; $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = $_POST['office_lat'] ?: null;
    $lng = $_POST['office_lng'] ?: null;
    $rad = (int)$_POST['radius_meters'] ?: 100;
    $ip  = trim($_POST['allowed_ip']) ?: null;
    
    $stmt = $pdo->prepare("UPDATE companies SET office_lat=?, office_lng=?, radius_meters=?, allowed_ip=? WHERE id=?");
    $stmt->execute([$lat, $lng, $rad, $ip, $cid]);
    
    logActivity('attendance_settings_updated', "Updated Geo-fencing/IP restrictions", $cid);
    $_SESSION['flash_msg'] = "Attendance settings updated successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: attendance_settings.php"); exit();
}

$company = $pdo->prepare("SELECT office_lat, office_lng, radius_meters, allowed_ip FROM companies WHERE id=?");
$company->execute([$cid]);
$config = $company->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Security - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439731">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439731">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Attendance Security</h1>
                <p style="color:var(--text-muted)">Restrict clock-ins by Location (Geo-fencing) and Network (IP Restriction).</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 2rem;"><?= $msg ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; align-items: start;">
            <div class="content-card">
                <form method="POST">
                    <div style="margin-bottom: 2rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">📍 Geo-Fencing Settings</h3>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin:0;">
                                Establish a virtual boundary around your office. Staff will only be able to clock-in if they are within the specified radius.
                            </p>
                            <button type="button" class="btn btn-sm btn-outline" id="autoLocBtn" onclick="fetchLocation()">📍 Get Current Location</button>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Office Latitude</label>
                                <input type="number" step="0.00000001" name="office_lat" id="offLat" class="form-control" value="<?= $config['office_lat'] ?>" placeholder="e.g. 28.6139">
                            </div>
                            <div class="form-group">
                                <label>Office Longitude</label>
                                <input type="number" step="0.00000001" name="office_lng" id="offLng" class="form-control" value="<?= $config['office_lng'] ?>" placeholder="e.g. 77.2090">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Allowed Radius (Meters)</label>
                            <input type="number" name="radius_meters" class="form-control" value="<?= $config['radius_meters'] ?: 100 ?>" placeholder="e.g. 100">
                            <small style="color:var(--text-muted)">Default is 100 meters.</small>
                        </div>
                    </div>

                    <div style="margin-bottom: 2rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 2rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">🌐 IP Restriction</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                            Only allow attendance from your office Wi-Fi network. Leave blank to allow clock-ins from any network.
                        </p>
                        <div class="form-group">
                            <label>Designated Office IP</label>
                            <input type="text" name="allowed_ip" class="form-control" value="<?= htmlspecialchars($config['allowed_ip'] ?? '') ?>" placeholder="e.g. 122.161.x.x">
                            <small style="color:var(--text-muted)">Current Your IP: <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong></small>
                        </div>
                    </div>

                    <div style="display:flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem;">Save Security Rules</button>
                    </div>
                </form>
            </div>

            <div class="content-card" style="background: rgba(99, 102, 241, 0.03); border-color: rgba(99, 102, 241, 0.1);">
                <h3 style="margin-bottom: 1rem;">How it works</h3>
                <ul style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.6; padding-left: 1.2rem;">
                    <li style="margin-bottom: 1rem;"><strong>Geo-Fencing:</strong> Uses the browser's Geolocation API. Staff must grant location permission.</li>
                    <li style="margin-bottom: 1rem;"><strong>IP Matching:</strong> Compares the incoming request IP with your saved Designated IP.</li>
                    <li style="margin-bottom: 1rem;"><strong>Enforcement:</strong> If either rule is breached, the "Clock-in" button will be disabled or show an error.</li>
                    <li><strong>Accuracy:</strong> GPS accuracy depends on the employee's device and mobile network.</li>
                </ul>
            </div>
        </div>

    </main>
</div>
<script>
function fetchLocation() {
    const btn = document.getElementById('autoLocBtn');
    btn.innerHTML = '⏳ Locating...';
    btn.disabled = true;
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('offLat').value = pos.coords.latitude.toFixed(8);
            document.getElementById('offLng').value = pos.coords.longitude.toFixed(8);
            btn.innerHTML = '✅ Location Saved';
            btn.style.borderColor = '#10b981';
            btn.style.color = '#10b981';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = '📍 Get Current Location'; btn.style = ''; }, 3000);
        }, err => {
            alert('Location access denied or unavailable. Please enable GPS.');
            btn.innerHTML = '📍 Get Current Location';
            btn.disabled = false;
        });
    } else {
        alert('Geolocation is not supported by this browser.');
        btn.innerHTML = '📍 Get Current Location';
        btn.disabled = false;
    }
}
</script>
</body>
</html>
