<?php
require_once 'config/database.php';
$stmt = $pdo->query("DESCRIBE users");
echo "<h2>Users Table Structure</h2><table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>".implode("</td><td>", $row)."</td></tr>";
}
echo "</table>";

$stmt2 = $pdo->query("DESCRIBE attendance");
echo "<h2>Attendance Table Structure</h2><table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>".implode("</td><td>", $row)."</td></tr>";
}
echo "</table>";
?>
