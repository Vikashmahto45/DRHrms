<?php
// /api/hrms/process_attendance.php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit();
}

$uid = $_SESSION['user_id'];
$cid = $_SESSION['company_id'];
$action = $_POST['action'] ?? '';

// Get company security config
$company = $pdo->prepare("SELECT office_lat, office_lng, radius_meters, allowed_ip FROM companies WHERE id=?");
$company->execute([$cid]);
$config = $company->fetch();

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

try {
    if ($action === 'in') {
        // 1. IP Check
        if ($config['allowed_ip'] && $config['allowed_ip'] !== $_SERVER['REMOTE_ADDR']) {
            throw new Exception("Security breach: Attendance must be from office network.");
        }

        // 2. Location Check
        $uLat = $_POST['lat'] ?? 0;
        $uLng = $_POST['lng'] ?? 0;
        if ($config['office_lat'] && $config['office_lng']) {
            $dist = calculateDistance($uLat, $uLng, $config['office_lat'], $config['office_lng']);
            if ($dist > $config['radius_meters']) {
                $roundedDist = round($dist);
                throw new Exception("Out of bounds: You are {$roundedDist}m away.");
            }
        }

        // 3. Late Calculation
        $late_mins = 0;
        $status = 'Present';
        $user_shift = $pdo->prepare("SELECT s.start_time FROM users u JOIN shifts s ON u.shift_id = s.id WHERE u.id = ?");
        $user_shift->execute([$uid]);
        $shift = $user_shift->fetch();

        if ($shift) {
            $now_time = date('H:i:s');
            $shift_start = $shift['start_time'];
            $diff = (strtotime($now_time) - strtotime($shift_start)) / 60;
            if ($diff > 15) { // 15 mins grace
                $late_mins = round($diff);
                $status = 'Late';
            }
        }

        // 4. Save Record
        $stmt = $pdo->prepare("INSERT INTO attendance (company_id, user_id, date, clock_in, lat, lng, ip_address, late_minutes, status) VALUES (?,?,CURDATE(),CURTIME(),?,?,?,?,?)");
        $stmt->execute([$cid, $uid, $uLat, $uLng, $_SERVER['REMOTE_ADDR'], $late_mins, $status]);
        
        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'out') {
        // Calculate Overtime
        $overtime_mins = 0;
        $user_shift = $pdo->prepare("SELECT s.end_time FROM users u JOIN shifts s ON u.shift_id = s.id WHERE u.id = ?");
        $user_shift->execute([$uid]);
        $shift = $user_shift->fetch();

        if ($shift) {
            $now_time = date('H:i:s');
            $shift_end = $shift['end_time'];
            $diff = (strtotime($now_time) - strtotime($shift_end)) / 60;
            if ($diff > 0) {
                $overtime_mins = round($diff);
            }
        }

        $stmt = $pdo->prepare("UPDATE attendance SET clock_out = CURTIME(), overtime_minutes = ? WHERE user_id = ? AND date = CURDATE() AND clock_out IS NULL");
        $stmt->execute([$overtime_mins, $uid]);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
