<?php
require 'config/database.php';

// Set the correct role for the sales person
$pdo->prepare("UPDATE users SET role = 'sales_person' WHERE email = 'sale@drhrms.com'")->execute();
echo "Updated sale@drhrms.com to role = 'sales_person'\n";

// Verify
$stmt = $pdo->query("SELECT id, name, email, role, status FROM users WHERE email = 'sale@drhrms.com'");
$u = $stmt->fetch();
echo "Verified: ID:{$u['id']} | {$u['name']} | {$u['email']} | role:{$u['role']} | status:{$u['status']}\n";
?>
