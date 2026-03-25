<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';
$tables = ['companies', 'users', 'leads'];
foreach($tables as $t) {
    echo "\n--- $t ---\n";
    try {
        $s = $pdo->query("DESCRIBE $t");
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $f) {
            echo "{$f['Field']} ({$f['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error describing $t: " . $e->getMessage() . "\n";
    }
}
