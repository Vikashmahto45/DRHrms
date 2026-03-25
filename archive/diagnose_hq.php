<?php
require 'config/database.php';

echo "<pre>";

// Check what the HQ company looks like
$stmt = $pdo->query("SELECT id, name, login_slug, is_main_branch, status FROM companies ORDER BY id");
$companies = $stmt->fetchAll();

echo "=== ALL COMPANIES ===\n";
foreach ($companies as $c) {
    echo "ID:{$c['id']} | Name:{$c['name']} | login_slug:{$c['login_slug']} | is_main_branch:{$c['is_main_branch']} | status:{$c['status']}\n";
}

echo "\n=== HQ USERS ===\n";
// Show users linked to main branch
$stmt = $pdo->query("SELECT u.id, u.company_id, u.name, u.email, u.role, u.status FROM users u JOIN companies c ON u.company_id = c.id WHERE c.is_main_branch = 1");
$users = $stmt->fetchAll();
foreach ($users as $u) {
    echo "User:{$u['name']} | email:{$u['email']} | role:{$u['role']} | status:{$u['status']} | company_id:{$u['company_id']}\n";
}

echo "</pre>";
?>
