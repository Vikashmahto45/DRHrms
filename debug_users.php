<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT id, name, company_id, role FROM users WHERE name LIKE '%vikash%'");
echo "<h2>Users</h2><table border='1'>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>".implode("</td><td>", $row)."</td></tr>";
}
echo "</table>";
?>
