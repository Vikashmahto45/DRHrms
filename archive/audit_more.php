<?php
require_once __DIR__ . '/config/database.php';
$tables = ['lead_history', 'lead_tasks', 'permissions_map', 'activity_logs', 'system_settings'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ $table: OK\n";
    }
    catch (Exception $e) {
        echo "❌ $table: " . $e->getMessage() . "\n";
    }
}
?>
