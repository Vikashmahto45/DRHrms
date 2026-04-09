<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
echo "AVAILABLE_TABLES:\n" . implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

echo "\n--- COMPANIES ---\n";
$stmt = $pdo->query("SELECT id, name, is_main_branch, parent_id FROM companies");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n--- CATALOG (products) ---\n";
try {
    $stmt = $pdo->query("DESCRIBE products");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row) . "\n";
    }
} catch (Exception $e) { echo "products table error: " . $e->getMessage() . "\n"; }

echo "\n--- CATALOG (settings_products) ---\n";
try {
    $stmt = $pdo->query("DESCRIBE settings_products");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row) . "\n";
    }
} catch (Exception $e) { echo "settings_products table error: " . $e->getMessage() . "\n"; }
?>
