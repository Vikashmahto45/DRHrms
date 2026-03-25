<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';
$stmt = $pdo->query("DESCRIBE companies");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("DESCRIBE leads");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
