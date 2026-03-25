<?php
require 'config/database.php';

// Show users table schema
$stmt = $pdo->query("DESCRIBE users");
echo "=== users TABLE SCHEMA ===\n";
foreach ($stmt->fetchAll() as $col) {
    echo "Field:{$col['Field']} | Type:{$col['Type']} | Null:{$col['Null']} | Default:{$col['Default']}\n";
}
echo "\n";

// Try direct fix with error reporting
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $r = $pdo->exec("UPDATE users SET role = 'sales_person' WHERE id = 18");
    echo "Rows affected: $r\n";

    $u = $pdo->query("SELECT role FROM users WHERE id = 18")->fetchColumn();
    echo "Role now: '$u'\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
