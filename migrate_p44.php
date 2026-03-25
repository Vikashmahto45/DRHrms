<?php
require_once 'config/database.php';
function runMig($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "SUCCESS: " . substr($sql, 0, 50) . "...\n";
    } catch(Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "SKIPPED (EXISTS): " . substr($sql, 0, 50) . "...\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

$queries = [
    "CREATE TABLE IF NOT EXISTS designations (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, title VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "ALTER TABLE users ADD COLUMN designation_id INT NULL DEFAULT NULL",
    "CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, price DECIMAL(15,2) DEFAULT '0.00', description TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "ALTER TABLE leads_crm ADD COLUMN product_id INT NULL DEFAULT NULL"
];

foreach ($queries as $q) {
    runMig($pdo, $q);
}
echo "\nDONE.\n";
