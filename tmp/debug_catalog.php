<?php
require_once 'config/database.php';

echo "--- USERS ---\n";
$stmt = $pdo->query("SELECT id, name, company_id, role FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n--- COMPANIES ---\n";
$stmt = $pdo->query("SELECT id, name, is_main_branch, parent_id FROM companies");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n--- CATALOG (settings_products) ---\n";
$stmt = $pdo->query("SELECT id, company_id, name, commission_rate FROM settings_products");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>
