<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("USE drhrms_db");
    echo "Using drhrms_db.\n";
    
    // 1. Try to drop the ghost table
    try {
        $pdo->exec("DROP TABLE IF EXISTS users");
        echo "Table users dropped (if it existed).\n";
    } catch (Exception $e) {
        echo "Error dropping users: " . $e->getMessage() . "\n";
    }
    
    // 2. Try to create it again
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'admin', 'manager', 'staff') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        admin_type ENUM('full', 'limited') DEFAULT 'full',
        two_factor_enabled TINYINT(1) DEFAULT 0,
        last_login DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "Table users created successfully!\n";

} catch (Exception $e) {
    echo "Global Error: " . $e->getMessage() . "\n";
}
