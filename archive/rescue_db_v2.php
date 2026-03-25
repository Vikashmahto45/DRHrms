<?php
require_once __DIR__ . '/config/database.php';

try {
    echo "--- Database Rescue: Phase 2 (New Table Strategy) ---\n";

    // 1. Create leads_crm (since 'leads' is blocked by ghost tablespace)
    echo "Creating 'leads_crm' table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads_crm (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        client_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        source VARCHAR(50) DEFAULT 'Manual',
        product VARCHAR(100),
        status VARCHAR(20) DEFAULT 'New',
        assigned_to INT DEFAULT NULL,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (company_id),
        INDEX (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "SUCCESS: 'leads_crm' table created.\n";

    // 2. Double check 'dsr'
    $stmt = $pdo->query("SHOW TABLES LIKE 'dsr'");
    if ($stmt->fetch()) {
        echo "SUCCESS: 'dsr' table exists.\n";
    }
    else {
        echo "WARNING: 'dsr' table missing. Attempting rename again...\n";
        $pdo->exec("RENAME TABLE daily_sales_reports TO dsr");
    }

    echo "--- Rescue Operation Phase 2 Completed ---\n";

}
catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>
