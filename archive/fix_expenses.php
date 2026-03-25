<?php
require_once 'C:/xampp/htdocs/DR Hrms/config/database.php';
try {
    $pdo->exec("ALTER TABLE expenses ADD COLUMN user_id INT NULL AFTER company_id");
    echo "Added user_id column successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column user_id already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
