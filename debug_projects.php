<?php
// /debug_projects.php
require_once 'includes/auth.php';
require_once 'config/database.php';

// Simulate a Main Admin session (Assuming ID 1 is Main)
$cid = 1; 

echo "<h1>Debug Project Visibility</h1>";
echo "<p>Simulating Main Admin for Company ID: $cid</p>";

$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);
echo "<p>Accessible Branch IDs: $cids_in</p>";

echo "<h2>Projects in DB</h2>";
$stmt = $pdo->query("SELECT id, company_id, branch_id, project_name, client_name FROM projects");
echo "<table border='1'><thead><tr><th>ID</th><th>Company ID</th><th>Branch ID</th><th>Project Name</th><th>Client</th><th>Is in Accessible List?</th></tr></thead><tbody>";
while($p = $stmt->fetch()) {
    $is_in = in_array($p['company_id'], $branch_ids) ? 'YES' : 'NO';
    echo "<tr><td>{$p['id']}</td><td>{$p['company_id']}</td><td>{$p['branch_id']}</td><td>{$p['project_name']}</td><td>{$p['client_name']}</td><td>$is_in</td></tr>";
}
echo "</tbody></table>";

echo "<h2>Companies</h2>";
$stmt2 = $pdo->query("SELECT id, name, is_main_branch FROM companies");
echo "<table border='1'><thead><tr><th>ID</th><th>Name</th><th>Is Main?</th></tr></thead><tbody>";
while($c = $stmt2->fetch()) {
    echo "<tr><td>{$c['id']}</td><td>{$c['name']}</td><td>{$c['is_main_branch']}</td></tr>";
}
echo "</tbody></table>";
?>
