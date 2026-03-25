<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';

$dbs = ['drhrms_db', 'hrms_saas', 'saas_hrms', 'performance_schema'];

foreach ($dbs as $db) {
    echo "--- Checking Database: $db ---\n";
    try {
        $pdo->exec("USE `$db`");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo "No tables found.\n";
            continue;
        }
        $count = count($tables);
        echo "Found $count tables.\n";
        
        $first = $tables[0];
        try {
            $pdo->query("SELECT 1 FROM `$first` LIMIT 1");
            echo "Table `$first`: OK\n";
        } catch (Exception $e) {
            echo "Table `$first`: ERROR - " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        echo "Failed to use database $db: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
