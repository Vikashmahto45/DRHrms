<?php
include 'config/database.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'dsr_items'");
    echo $stmt->fetch() ? "dsr_items EXISTS\n" : "dsr_items MISSING\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'branch_id'");
    echo $stmt->fetch() ? "projects.branch_id EXISTS\n" : "projects.branch_id MISSING\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM dsr");
    echo "dsr count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
