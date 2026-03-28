<?php
require_once 'config/database.php';
echo "--- franchise_payments ---\n";
$stmt = $pdo->query("DESCRIBE franchise_payments");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: {$row['Field']} | Type: {$row['Type']}\n";
}
