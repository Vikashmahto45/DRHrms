<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';

echo "--- TABLES ---\n";
$s = $pdo->query('SHOW TABLES');
$tables = $s->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo "\n--- EXPENSE RELATED TABLES ---\n";
foreach($tables as $t) {
    if(stripos($t, 'expense') !== false) {
        echo "Table: $t\n";
        $s2 = $pdo->query("DESCRIBE `$t` ");
        print_r($s2->fetchAll(PDO::FETCH_ASSOC));
    }
}
