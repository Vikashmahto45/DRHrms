<?php
// /api/webhooks/google.php
require_once '../../config/database.php';

// Google Ads Lead Form Webhook (Simpler JSON POST)
$input = file_get_contents('php://input');
if ($input) {
    $data = json_decode($input, true);
    
    // Log raw data
    $stmt = $pdo->prepare("INSERT INTO webhooks_inbound (provider, raw_data, status) VALUES ('Google Ads', ?, 'pending')");
    $stmt->execute([$input]);
    $webhook_id = $pdo->lastInsertId();

    // Verify Google Key (Google adds a 'google_key' in lead form settings)
    $google_key = $data['google_key'] ?? '';
    if ($google_key === 'drhrms_google_secret_2024') {
        
        $company_id = 1; // Mapped company
        
        // Auto-Assignment (Round-robin)
        $assigned_to = roundRobinAssign($pdo, $company_id);
        
        // Create Lead Record
        $client_name = $data['user_column_data'][0]['string_value'] ?? "Google Lead";
        $phone = $data['user_column_data'][1]['string_value'] ?? "";
        
        $stmt = $pdo->prepare("INSERT INTO leads_crm (company_id, client_name, phone, source, status, assigned_to, assigned_at) VALUES (?,?,?,?, 'new', ?, NOW())");
        $stmt->execute([$company_id, $client_name, $phone, 'Google Ads', $assigned_to]);
        
        // Notification
        if ($assigned_to) {
            $notif_stmt = $pdo->prepare("INSERT INTO system_notifications (company_id, user_id, title, message) VALUES (?,?,?,?)");
            $notif_stmt->execute([$company_id, $assigned_to, '⚡ New Google Lead!', "New inquiry from Google Search: $client_name"]);
        }

        $pdo->prepare("UPDATE webhooks_inbound SET status='processed' WHERE id=?")->execute([$webhook_id]);
    }
}

function roundRobinAssign($pdo, $company_id) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE company_id=? AND role IN ('staff','manager','sales_person') AND status='active' ORDER BY id ASC");
    $stmt->execute([$company_id]);
    $staff = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$staff) return null;
    $stmt = $pdo->prepare("SELECT assigned_to FROM leads_crm WHERE company_id=? AND source='Google Ads' AND assigned_to IS NOT NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$company_id]);
    $last_id = $stmt->fetchColumn();
    if (!$last_id) return $staff[0];
    $currentIndex = array_search($last_id, $staff);
    if ($currentIndex === false || $currentIndex === count($staff) - 1) return $staff[0];
    return $staff[$currentIndex + 1];
}
