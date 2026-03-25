<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=drhrms_db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Drop the table we just created
    $pdo->exec("DROP TABLE IF EXISTS users");
    echo "Table users dropped.\n";
    
    // 2. Create users table with NO secondary indexes and NO foreign keys
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'admin', 'manager', 'staff') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        admin_type ENUM('full', 'limited') DEFAULT 'full',
        two_factor_enabled TINYINT(1) DEFAULT 0,
        last_login DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "Table users recreated without indexes.\n";
    
    // 3. Discard tablespace
    $pdo->exec("ALTER TABLE users DISCARD TABLESPACE");
    echo "Tablespace discarded.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
