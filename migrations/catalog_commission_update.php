<?php
// /migrations/catalog_commission_update.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Robust Catalog & Commission Migration...<br>";
    
    // 1. Check if 'products' exists but 'settings_products' does not
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasProducts = in_array('products', $tables);
    $hasSettingsProducts = in_array('settings_products', $tables);

    if ($hasProducts && !$hasSettingsProducts) {
        echo "Found old 'products' table. Renaming it to 'settings_products'...<br>";
        $pdo->exec("RENAME TABLE products TO settings_products");
        echo "✓ Table renamed successfully.<br>";
    } elseif (!$hasProducts && !$hasSettingsProducts) {
        echo "Neither table found. Creating 'settings_products' from scratch...<br>";
        $pdo->exec("CREATE TABLE settings_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✓ settings_products table created.<br>";
    }

    // 2. Add commission_rate column
    echo "Checking for 'commission_rate' column...<br>";
    $pdo->exec("ALTER TABLE settings_products ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    echo "✓ settings_products table updated with commission_rate.<br>";

    echo "Migration Completed Successfully! Your Service Catalog should now be functional.<br>";
} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
