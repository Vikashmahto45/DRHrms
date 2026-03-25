<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';

try {
    // 1. Create daily_sales_reports table
    $sql = "CREATE TABLE IF NOT EXISTS daily_sales_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        client_name VARCHAR(255) NOT NULL,
        visit_purpose TEXT NOT NULL,
        visit_photo VARCHAR(255),
        notes TEXT,
        longitude VARCHAR(50),
        latitude VARCHAR(50),
        visit_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (company_id),
        INDEX (user_id),
        INDEX (visit_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "DSR table created successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
