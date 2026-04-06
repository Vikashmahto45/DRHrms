<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT id, company_id, branch_id, project_name, client_name, status FROM projects");
echo "<table border='1'><tr><th>ID</th><th>CompanyID</th><th>BranchID</th><th>Project</th><th>Client</th><th>Status</th></tr>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>".implode("</td><td>", $row)."</td></tr>";
}
echo "</table>";

$stmt2 = $pdo->query("SELECT id, name, is_main_branch FROM companies");
echo "<h3>Companies</h3><table border='1'><tr><th>ID</th><th>Name</th><th>IsMain</th></tr>";
while($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>".implode("</td><td>", $row)."</td></tr>";
}
echo "</table>";
?>
