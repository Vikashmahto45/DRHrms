<?php
require 'config/database.php';
$stmt = $pdo->query("DESCRIBE companies");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) {
    echo $col['Field'] . "\n";
}
?>
