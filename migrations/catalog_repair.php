<?php
// /migrations/catalog_repair.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Catalog Data Repair...<br>";
    
    // 1. Ensure commission_rate exists in the 'products' table
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    echo "✓ 'products' table updated with commission_rate column.<br>";

    echo "Migration Completed Successfully! Your Service Catalog is now ready.<br>";
} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
