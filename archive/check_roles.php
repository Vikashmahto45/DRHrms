<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';
$stmt = $pdo->query("SELECT DISTINCT role FROM users");
while($row = $stmt->fetch()) {
    echo $row['role'] . "\n";
}
?>
