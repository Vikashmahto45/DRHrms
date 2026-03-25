<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Summary of tables in drhrms_db:\n";
    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            echo "[OK] $table\n";
        } catch (Exception $e) {
            echo "[ERROR] $table: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
