<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
echo "DB: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";
echo "TABLES:\n" . implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
?>
