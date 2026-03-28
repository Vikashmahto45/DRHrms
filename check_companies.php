<?php
// check_companies.php
require_once 'config/database.php';
$stmt = $pdo->query('SELECT id, name, is_main_branch, parent_id FROM companies');
while($row = $stmt->fetch()) {
    print_r($row);
}
?>
