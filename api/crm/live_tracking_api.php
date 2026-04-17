<?php
// /api/crm/live_tracking_api.php
date_default_timezone_set('Asia/Kolkata');
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? $input['action'] ?? null;

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

$uid  = $_SESSION['user_id'] ?? $_SESSION['sa_user_id'] ?? null;
$cid  = $_SESSION['company_id'] ?? 0;

if (!$uid || !$cid) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 1. Auto-patch DB Schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_tracking_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_tracking_pings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            accuracy DECIMAL(8, 2) NULL,
            timestamp DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(session_id),
            INDEX(user_id, timestamp)
        )
    ");
} catch (Exception $e) {
    // Silently continue if tables exist
}

// 2. Handle Actions
switch ($action) {

    case 'start_session':
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        
        // Close any active sessions for today to prevent duplicates
        $closeStmt = $pdo->prepare("UPDATE live_tracking_sessions SET status = 'completed', end_time = ? WHERE user_id = ? AND date = ? AND status = 'active'");
        $closeStmt->execute([$time, $uid, $date]);

        // Start new
        $stmt = $pdo->prepare("INSERT INTO live_tracking_sessions (company_id, user_id, date, start_time, status) VALUES (?, ?, ?, ?, 'active')");
        if ($stmt->execute([$cid, $uid, $date, $time])) {
            $session_id = $pdo->lastInsertId();
            echo json_encode(['status' => 'success', 'session_id' => $session_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to start session']);
        }
        break;

    case 'stop_session':
        $session_id = $_POST['session_id'] ?? $input['session_id'] ?? null;
        $time = date('Y-m-d H:i:s');
        
        if ($session_id) {
            $stmt = $pdo->prepare("UPDATE live_tracking_sessions SET status = 'completed', end_time = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$time, $session_id, $uid]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing session ID']);
        }
        break;

    case 'ping':
        $session_id = $_POST['session_id'] ?? $input['session_id'] ?? null;
        $lat = $_POST['lat'] ?? $input['lat'] ?? null;
        $lng = $_POST['lng'] ?? $input['lng'] ?? null;
        $acc = $_POST['accuracy'] ?? $input['accuracy'] ?? null;
        $client_time = $_POST['client_time'] ?? $input['client_time'] ?? null;
        
        // If phone sent its time, use it. Otherwise use server.
        if ($client_time) {
            $time = date('Y-m-d H:i:s', strtotime($client_time));
        } else {
            $time = date('Y-m-d H:i:s');
        }

        if ($session_id && $lat && $lng) {
            $stmt = $pdo->prepare("INSERT INTO live_tracking_pings (session_id, user_id, latitude, longitude, accuracy, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$session_id, $uid, $lat, $lng, $acc, $time])) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ping failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing data']);
        }
        break;

    case 'get_route':
        // For Admin Map
        $target_user_id = $_POST['target_user_id'] ?? json_decode(file_get_contents('php://input'))->target_user_id ?? null;
        $target_date = $_POST['target_date'] ?? json_decode(file_get_contents('php://input'))->target_date ?? date('Y-m-d');
        
        if (!$target_user_id) {
            echo json_encode(['status' => 'error', 'message' => 'No user specified']);
            exit;
        }

        // Fetch route points
        $stmt = $pdo->prepare("
            SELECT p.latitude, p.longitude, p.timestamp, p.session_id 
            FROM live_tracking_pings p
            JOIN live_tracking_sessions s ON p.session_id = s.id
            WHERE p.user_id = ? AND s.date = ?
            ORDER BY p.timestamp ASC
        ");
        $stmt->execute([$target_user_id, $target_date]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Sessions
        $sessStmt = $pdo->prepare("SELECT * FROM live_tracking_sessions WHERE user_id = ? AND date = ? ORDER BY start_time ASC");
        $sessStmt->execute([$target_user_id, $target_date]);
        $sessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

        // Algorithm to calculate Stops
        // Distance Haversine function
        function getDistance($lat1, $lon1, $lat2, $lon2) {
            $earth_radius = 6371000; // in meters
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * asin(sqrt($a));
            return $earth_radius * $c;
        }

        $stops = [];
        $d_max = 50; // 50 meters
        $t_min = 300; // 5 minutes in seconds

        $n = count($points);
        $i = 0;

        while ($i < $n) {
            $j = $i + 1;
            $cluster_end = $i;
            
            while ($j < $n) {
                // If the next point is within 50 meters of the initial stop point
                $dist = getDistance($points[$i]['latitude'], $points[$i]['longitude'], $points[$j]['latitude'], $points[$j]['longitude']);
                if ($dist <= $d_max) {
                    $cluster_end = $j;
                    $j++;
                } else {
                    break;
                }
            }

            // Check if time duration at this stop is >= 5 minutes
            $time_start = strtotime($points[$i]['timestamp']);
            $time_end = strtotime($points[$cluster_end]['timestamp']);
            $duration = $time_end - $time_start;

            if ($duration >= $t_min) {
                $stops[] = [
                    'latitude' => $points[$i]['latitude'],
                    'longitude' => $points[$i]['longitude'],
                    'start_time' => $points[$i]['timestamp'],
                    'end_time' => $points[$cluster_end]['timestamp'],
                    'duration_minutes' => round($duration / 60)
                ];
            }

            // Move pointer forward
            if ($j > $i + 1) {
                $i = $j - 1; 
            } else {
                $i++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'points' => $points,
            'stops' => $stops,
            'sessions' => $sessions
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>
