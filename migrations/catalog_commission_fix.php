<?php
// /migrations/catalog_commission_fix.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Universal Catalog Repair...<br>";
    
    // 1. Detect all available tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found Tables: " . implode(", ", $tables) . "<br>";

    // 2. Identify the active catalog table
    $activeTable = '';
    if (in_array('products', $tables)) {
        $activeTable = 'products';
    } elseif (in_array('settings_products', $tables)) {
        $activeTable = 'settings_products';
        echo "Renaming 'settings_products' back to 'products' for consistency...<br>";
        $pdo->exec("RENAME TABLE settings_products TO products");
        $activeTable = 'products';
    } else {
        echo "Creating missing 'products' table...<br>";
        $pdo->exec("CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $activeTable = 'products';
    }

    // 3. Add the commission column
    echo "Ensuring 'commission_rate' column exists in 'products'...<br>";
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    
    echo "✓ Final Setup: Table 'products' is ready with 'commission_rate'.<br>";
    echo "Migration Completed Successfully! ✅<br>";

} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
