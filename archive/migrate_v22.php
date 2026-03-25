<?php
require_once __DIR__ . '/config/database.php';
try {
    // 1. Add payout columns to franchise_payments
    try {
        $pdo->exec("ALTER TABLE franchise_payments 
            ADD COLUMN payout_status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
            ADD COLUMN payout_date DATETIME NULL");
        echo "Franchise payments table updated with payout columns.<br>";
    } catch (Exception $e) {
        echo "Payout columns might already exist: " . $e->getMessage() . "<br>";
    }

    // 2. Create announcements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
        target ENUM('all', 'main_branch', 'sub_branch', 'staff') DEFAULT 'all',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        is_active TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Announcements table created.<br>";

    echo "<strong>Migration Complete.</strong>";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage();
}
?>
