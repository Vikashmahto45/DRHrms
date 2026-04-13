<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(50) NOT NULL UNIQUE,
        can_add TINYINT(1) DEFAULT 0,
        can_edit TINYINT(1) DEFAULT 0,
        can_delete TINYINT(1) DEFAULT 0,
        can_update_progress TINYINT(1) DEFAULT 0,
        can_verify TINYINT(1) DEFAULT 0,
        can_instruction TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $roles = [
        ['role_key' => 'hq_admin', 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_update_progress' => 0, 'can_verify' => 1, 'can_instruction' => 1],
        ['role_key' => 'hq_manager', 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_update_progress' => 0, 'can_verify' => 1, 'can_instruction' => 1],
        ['role_key' => 'branch_admin', 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_update_progress' => 0, 'can_verify' => 0, 'can_instruction' => 1],
        ['role_key' => 'branch_manager', 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_update_progress' => 0, 'can_verify' => 0, 'can_instruction' => 1],
        ['role_key' => 'sales_person', 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0, 'can_update_progress' => 0, 'can_verify' => 0, 'can_instruction' => 1],
        ['role_key' => 'staff', 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_update_progress' => 1, 'can_verify' => 0, 'can_instruction' => 0]
    ];

    foreach ($roles as $r) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO project_permissions (role_key, can_add, can_edit, can_delete, can_update_progress, can_verify, can_instruction) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$r['role_key'], $r['can_add'], $r['can_edit'], $r['can_delete'], $r['can_update_progress'], $r['can_verify'], $r['can_instruction']]);
    }

    echo "Migration Successful: project_permissions table ready.\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
