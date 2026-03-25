<?php
require_once 'C:/xampp/htdocs/DR Hrms/config/database.php';
try {
    $pdo->exec("ALTER TABLE expenses ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER description");
    echo "Added status column successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column status already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
