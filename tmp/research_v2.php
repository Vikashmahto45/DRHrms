<?php
require_once 'config/database.php';

echo "--- PRODUCTS DATA ---\n";
$stmt = $pdo->query("SELECT * FROM products LIMIT 5");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n--- COMPANIES DATA ---\n";
$stmt = $pdo->query("SELECT id, name, is_main_branch, parent_id FROM companies");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n--- USERS (shivam) ---\n";
$stmt = $pdo->query("SELECT id, name, company_id, role FROM users WHERE name LIKE '%shivam%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>
