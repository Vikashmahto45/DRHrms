<?php
require_once 'config/database.php';
$tables = ['dsr', 'products', 'companies'];
foreach($tables as $t) {
    echo "--- Table: $t ---\n";
    $stmt = $pdo->query("DESCRIBE $t");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
}
?>
