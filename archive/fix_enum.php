<?php
require 'config/database.php';

// Step 1: Add sales_person to the ENUM
$pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','manager','staff','sales_person') NOT NULL DEFAULT 'staff'");
echo "ENUM updated to include 'sales_person'.\n";

// Step 2: Update the blank role users
$r = $pdo->exec("UPDATE users SET role = 'sales_person' WHERE email = 'sale@drhrms.com'");
echo "Updated: $r row(s)\n";

// Step 3: Fix any remaining empty roles (fallback to 'staff')
$pdo->exec("UPDATE users SET role = 'staff' WHERE role = ''");

// Verify
$stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id");
echo "\n=== USERS ===\n";
foreach ($stmt->fetchAll() as $u) {
    echo "ID:{$u['id']} | {$u['name']} | {$u['email']} | role:{$u['role']}\n";
}
?>
