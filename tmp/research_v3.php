<?php
require_once 'config/database.php';
$tables = ['products', 'settings_products'];
foreach ($tables as $t) {
    echo "\n--- TABLE: $t ---\n";
    try {
        $stmt = $pdo->query("SELECT * FROM $t");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode($row) . "\n";
        }
    } catch (Exception $e) { echo "Error on $t: " . $e->getMessage() . "\n"; }
}

echo "\n--- SHIVAM SEARCH ---\n";
$stmt = $pdo->query("SELECT id, name, company_id FROM users WHERE name LIKE '%iv%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>
