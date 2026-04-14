<?php
// /api/crm/get_client_history.php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

// Ensure only authorized staff can access
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$cid = $_SESSION['company_id'];
$client_name = trim($_GET['client_name'] ?? '');

if (empty($client_name)) {
    echo json_encode(['error' => 'Client name required']);
    exit();
}

try {
    // Get the most recent DSR for this client within the company
    $stmt = $pdo->prepare("
        SELECT id, project_details, notes, deal_status 
        FROM dsr 
        WHERE client_name = ? AND company_id = ? 
        ORDER BY visit_date DESC, created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$client_name, $cid]);
    $history = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($history) {
        // Fetch items (products and prices) for this DSR
        $item_stmt = $pdo->prepare("
            SELECT product_id, manual_product_name, custom_price 
            FROM dsr_items 
            WHERE dsr_id = ?
        ");
        $item_stmt->execute([$history['id']]);
        $history['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($history);
    } else {
        echo json_encode(['status' => 'no_history']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
