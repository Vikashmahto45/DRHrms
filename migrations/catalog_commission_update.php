<?php
// /migrations/catalog_commission_update.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Catalog & Commission Migration...<br>";
    
    // Add commission_rate to settings_products
    $pdo->exec("ALTER TABLE settings_products ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    echo "✓ settings_products table updated with commission_rate.<br>";

    echo "Migration Completed Successfully!<br>";
} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
