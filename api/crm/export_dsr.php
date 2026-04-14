<?php
// /api/crm/export_dsr.php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Check basic access
checkAccess(['sales_person', 'admin', 'manager', 'staff']);

$uid  = $_SESSION['sa_user_id'] ?? $_SESSION['user_id'] ?? null;
$cid  = $_SESSION['company_id'] ?? 0;
$role = strtolower($_SESSION['sa_user_role'] ?? $_SESSION['user_role'] ?? '');

// Fetch Accessible Branch IDs
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Handle Staff Filter
$staff_filter = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;

// Fetch Reports (Same logic as dsr.php)
if ($role === 'sales_person') {
    $stmt = $pdo->prepare("SELECT * FROM dsr WHERE user_id = ? ORDER BY visit_date DESC, created_at DESC");
    $stmt->execute([$uid]);
} else {
    $sql = "SELECT d.*, u.name as staff_name, c.name as company_name 
            FROM dsr d 
            JOIN users u ON d.user_id = u.id 
            LEFT JOIN companies c ON d.company_id = c.id 
            WHERE d.company_id IN ($cids_in)";
    
    $params = [];
    if ($staff_filter) {
        $sql .= " AND d.user_id = ?";
        $params[] = $staff_filter;
    }
    
    $sql .= " ORDER BY d.visit_date DESC, d.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
$reports = $stmt->fetchAll();

// Fetch Products & Items for mapping
$prod_stmt = $pdo->prepare("SELECT id, name FROM products WHERE company_id = ?");
$prod_stmt->execute([$cid]);
$product_map = $prod_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Prepare CSV Headers
$filename = "DSR_Export_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'Date', 
    'Time',
    'Client Name', 
    'Staff Name', 
    'Activity Type', 
    'Deal Status', 
    'Total Deal Value (₹)',
    'Items/Services',
    'Notes',
    'Location Name',
    'Latitude',
    'Longitude'
]);

foreach ($reports as $r) {
    // Fetch Items for this report
    $it_stmt = $pdo->prepare("SELECT di.*, p.name as product_name 
                              FROM dsr_items di 
                              LEFT JOIN products p ON di.product_id = p.id 
                              WHERE di.dsr_id = ?");
    $it_stmt->execute([$r['id']]);
    $items = $it_stmt->fetchAll();
    
    $item_details = [];
    $total_val = 0;
    foreach ($items as $item) {
        $name = $item['manual_product_name'] ?: ($product_map[$item['product_id']] ?? 'Unknown');
        $item_details[] = $name . " (₹" . number_format($item['custom_price'], 2) . ")";
        $total_val += $item['custom_price'];
    }
    
    fputcsv($output, [
        $r['visit_date'],
        $r['visit_time'] ?: '-',
        $r['client_name'],
        $r['staff_name'] ?? 'N/A',
        $r['activity_type'],
        $r['deal_status'],
        number_format($total_val, 2, '.', ''),
        implode(", ", $item_details),
        strip_tags($r['notes']),
        $r['location_name'],
        $r['latitude'],
        $r['longitude']
    ]);
}

fclose($output);
exit();
