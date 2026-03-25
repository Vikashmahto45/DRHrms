<?php
// /api/leads/update_status.php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$lead_id = $_POST['lead_id'] ?? null;
$status = $_POST['status'] ?? null;
$cid = $_SESSION['company_id'];

if (!$lead_id || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

try {
    // 1. Fetch Lead and assigned info
    $stmt = $pdo->prepare("SELECT status, assigned_to FROM leads_crm WHERE id = ? AND company_id = ?");
    $stmt->execute([$lead_id, $cid]);
    $lead = $stmt->fetch();

    if (!$lead) {
        echo json_encode(['success' => false, 'error' => 'Lead not found']);
        exit();
    }

    $old_status = $lead['status'];
    $assigned_to = (int)$lead['assigned_to'];
    
    // 2. Security Check (Normalized Role)
    $role = strtolower($_SESSION['user_role'] ?? '');
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    $can_edit = ($role === 'admin' || $role === 'manager' || $uid === $assigned_to);

    if (!$can_edit) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: You do not own this lead']);
        exit();
    }

    // 3. Perform update if status changed
    if ($old_status !== $status) {
        $update_stmt = $pdo->prepare("UPDATE leads_crm SET status = ? WHERE id = ? AND company_id = ?");
        $result = $update_stmt->execute([$status, $lead_id, $cid]);
        
        if ($result) {
            // Log to history
            $h_stmt = $pdo->prepare("INSERT INTO lead_history (lead_id, user_id, event_type, details) VALUES (?, ?, 'status_change', ?)");
            $h_stmt->execute([$lead_id, $uid, "Moved lead from $old_status to $status (via Kanban API)"]);
            
            logActivity('lead_status_updated', "Lead ID #$lead_id status changed to $status", $cid);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => true, 'info' => 'No change needed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
