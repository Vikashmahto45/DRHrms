<?php
require 'config/database.php';

// Fix all users with empty/null roles
$stmt = $pdo->prepare("UPDATE users SET role = 'staff' WHERE (role = '' OR role IS NULL) AND role != 'super_admin'");
$stmt->execute();
$fixed = $stmt->rowCount();
echo "Fixed $fixed user(s) with missing roles (set to 'staff').\n";

// Remove duplicate users (keep the one with a proper role)
$stmt = $pdo->query("SELECT email, COUNT(*) as cnt FROM users GROUP BY email HAVING cnt > 1");
$dupes = $stmt->fetchAll();
foreach ($dupes as $d) {
    // Keep the record with a valid role/latest id, delete older blank ones
    $pdo->prepare("DELETE FROM users WHERE email = ? AND (role = 'staff' OR role = '') ORDER BY id ASC LIMIT 1")->execute([$d['email']]);
    echo "Removed duplicate entry for: {$d['email']}\n";
}

// Show final state
$stmt = $pdo->query("SELECT id, name, email, role, status, company_id FROM users ORDER BY id");
echo "\n=== CURRENT USERS ===\n";
foreach ($stmt->fetchAll() as $u) {
    echo "ID:{$u['id']} | {$u['name']} | {$u['email']} | role:{$u['role']} | status:{$u['status']}\n";
}
?>
