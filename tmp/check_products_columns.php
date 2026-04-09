<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM products");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
?>
