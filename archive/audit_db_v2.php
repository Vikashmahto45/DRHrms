<?php
require_once __DIR__ . '/config/database.php';
$tables = ['users', 'companies', 'leads', 'dsr', 'daily_sales_reports', 'franchise_payments', 'announcements', 'attendance', 'expenses', 'system_settings', 'employee_details'];
echo "<h2>Database Audit</h2>";
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ $table: $count records<br>";
    }
    catch (Exception $e) {
        echo "❌ $table: ERROR - " . $e->getMessage() . "<br>";
    }
}
?>
