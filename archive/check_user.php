<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';
$stmt = $pdo->query("SELECT id, name, role FROM users WHERE name LIKE '%vikash%'");
while($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Role: " . $row['role'] . "\n";
}
?>
