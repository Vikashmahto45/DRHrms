<?php
require_once __DIR__ . '/config/database.php';

try {
    echo "--- Database Rescue Operation Started ---\n";

    // 1. Fix 'dsr' issue FIRST (it's independent)
    echo "Renaming 'daily_sales_reports' to 'dsr'...\n";
    $pdo->exec("DROP TABLE IF EXISTS dsr"); // Ensure no ghost exists
    try {
        $pdo->exec("RENAME TABLE daily_sales_reports TO dsr");
        echo "SUCCESS: 'daily_sales_reports' renamed to 'dsr'.\n";
    }
    catch (Exception $e) {
        echo "Note: rename failed (likely already renamed): " . $e->getMessage() . "\n";
    }

    // 2. Fix 'leads' table (Ghost Tablespace Cleanup Trick)
    echo "Attempting ghost tablespace cleanup for 'leads'...\n";
    try {
        $pdo->exec("DROP TABLE IF EXISTS leads");
        // Create as MyISAM first (doesn't care about InnoDB .ibd files)
        $pdo->exec("CREATE TABLE leads (id INT) ENGINE=MyISAM");
        $pdo->exec("DROP TABLE leads");
        echo "MyISAM cleanup successful.\n";
    }
    catch (Exception $e) {
        echo "MyISAM cleanup failed (expected if MyISAM also fails): " . $e->getMessage() . "\n";
    }

    echo "Recreating 'leads' table (InnoDB)...\n";
    $pdo->exec("CREATE TABLE leads (
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
    echo "SUCCESS: 'leads' table recreated.\n";

    echo "--- Rescue Operation Completed Successfully ---\n";

}
catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>
