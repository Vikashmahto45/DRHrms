<?php
// /migrations/catalog_repair.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Universal Catalog Discovery & Repair...<br>";
    
    // 1. Get ALL tables on the live server
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available Tables: " . implode(", ", $tables) . "<br>";

    // 2. Identify which table is the catalog
    $catalogTable = '';
    if (in_array('settings_products', $tables)) {
        $catalogTable = 'settings_products';
    } elseif (in_array('products', $tables)) {
        $catalogTable = 'products';
    } else {
        echo "Catalog table not found. Creating 'products' table from scratch...<br>";
        $pdo->exec("CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $catalogTable = 'products';
        echo "✓ 'products' table created successfully.<br>";
    }

    // 3. Add the commission_rate column if it's missing
    echo "Updating table '$catalogTable' with commission_rate column...<br>";
    $pdo->exec("ALTER TABLE $catalogTable ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(5,2) DEFAULT 0.00 AFTER price");
    echo "✓ Column added successfully.<br>";

    echo "Migration Completed Successfully! Your Service Catalog is now ready.<br>";
} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
