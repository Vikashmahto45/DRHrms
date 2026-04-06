<?php
// /migrations/attendance_overhaul.php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting Attendance Overhaul Migration...<br>";
    
    // 1. Update Attendance table
    $pdo->exec("ALTER TABLE attendance 
        ADD COLUMN IF NOT EXISTS is_manual TINYINT(1) DEFAULT 0, 
        ADD COLUMN IF NOT EXISTS updated_by INT DEFAULT NULL, 
        ADD COLUMN IF NOT EXISTS late_minutes INT DEFAULT 0, 
        ADD COLUMN IF NOT EXISTS overtime_minutes INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Present'");
    echo "✓ Attendance table updated.<br>";

    // 2. Update Users table
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS shift_id INT DEFAULT NULL");
    echo "✓ Users table updated with shift_id.<br>";

    // 3. Create Holidays table
    $pdo->exec("CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        holiday_date DATE NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ Holidays table created.<br>";

    echo "Migration Completed Successfully!<br>";
} catch (Exception $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>
