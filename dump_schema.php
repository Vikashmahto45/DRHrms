<?php
require_once 'config/database.php';
echo "--- products ---\n";
$stmt = $pdo->query("DESCRIBE products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: {$row['Field']} | Type: {$row['Type']}\n";
}
