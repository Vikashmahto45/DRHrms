<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';
$stmt = $pdo->query("SELECT id, client_name, assigned_to FROM leads LIMIT 5");
while($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['client_name'] . " | AssignedTo: [" . $row['assigned_to'] . "]\n";
}
?>
