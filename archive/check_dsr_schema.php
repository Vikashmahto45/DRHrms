<?php
require_once __DIR__ . '/config/database.php';
$stmt = $pdo->query("DESCRIBE daily_sales_reports");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
