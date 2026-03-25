<?php
// /api/webhooks/meta.php
require_once '../../config/database.php';

// 1. Facebook Webhook Verification (Hub Challenge)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe') {
    $verify_token = 'drhrms_meta_webhook_secret_2024'; // This would be configured in Meta App Dashboard
    if ($_GET['hub_verify_token'] === $verify_token) {
        echo $_GET['hub_challenge'];
        exit;
    }
}

// 2. Handle Inbound Lead Data
$input = file_get_contents('php://input');
if ($input) {
    $data = json_decode($input, true);
    
    // Log raw data for debugging/audit
    $stmt = $pdo->prepare("INSERT INTO webhooks_inbound (provider, raw_data, status) VALUES ('Meta Ads', ?, 'pending')");
    $stmt->execute([$input]);
    $webhook_id = $pdo->lastInsertId();

    if (isset($data['entry'])) {
        foreach ($data['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'leadgen') {
                    $lead_id = $change['value']['leadgen_id'];
                    $page_id = $change['value']['page_id'];
                    
                    // In a real scenario, we'd use these IDs to call Meta Graph API and get details.
                    // For this demo/setup, we'll simulate the lead creation process.
                    processMetaLead($pdo, $lead_id, $page_id, $webhook_id);
                }
            }
        }
    }
}

function processMetaLead($pdo, $meta_lead_id, $page_id, $webhook_id) {
    // 1. Find the company associated with this page_id (Mapping would be in a settings table)
    // For demo, we'll use company_id = 1 or look for a company with meta_page_id
    $company_id = 1; // Default or mapped
    
    // 2. Auto-Assignment (Round-robin)
    $assigned_to = roundRobinAssign($pdo, $company_id);
    
    // 3. Create Lead Record
    $client_name = "Meta Lead #" . substr($meta_lead_id, -6);
    $stmt = $pdo->prepare("INSERT INTO leads_crm (company_id, client_name, source, status, assigned_to, assigned_at) VALUES (?,?,?, 'new', ?, NOW())");
    $stmt->execute([$company_id, $client_name, 'Meta Ads', $assigned_to]);
    $new_lead_id = $pdo->lastInsertId();
    
    // 4. Create Notification for assigned staff
    if ($assigned_to) {
        $notif_stmt = $pdo->prepare("INSERT INTO system_notifications (company_id, user_id, title, message) VALUES (?,?,?,?)");
        $notif_stmt->execute([$company_id, $assigned_to, '🔥 New Meta Lead!', "You have been assigned a new lead from Meta Ads: $client_name"]);
    }
    
    // 5. Update webhook status
    $pdo->prepare("UPDATE webhooks_inbound SET status='processed' WHERE id=?")->execute([$webhook_id]);
}

function roundRobinAssign($pdo, $company_id) {
    // Get active staff for this company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE company_id=? AND role IN ('staff','manager','sales_person') AND status='active' ORDER BY id ASC");
    $stmt->execute([$company_id]);
    $staff = $staff_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!$staff) return null;
    
    // Get last assigned staff member FROM leads_crm table
    $stmt = $pdo->prepare("SELECT assigned_to FROM leads_crm WHERE company_id=? AND source='Meta Ads' AND assigned_to IS NOT NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$company_id]);
    $last_id = $stmt->fetchColumn();
    
    if (!$last_id) return $staff[0];
    
    $currentIndex = array_search($last_id, $staff);
    if ($currentIndex === false || $currentIndex === count($staff) - 1) {
        return $staff[0];
    } else {
        return $staff[$currentIndex + 1];
    }
}
