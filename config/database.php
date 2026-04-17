<?php
// /config/database.php
date_default_timezone_set('Asia/Kolkata');

// 1. Detect Environment Automatically
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($domain, ['localhost', '127.0.0.1', '::1']);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// 2. Define Global Base URL
if (!defined('BASE_URL')) {
    if ($is_local) {
        define('BASE_URL', $protocol . $domain . '/Loom/');
    } else {
        // Change this if your agency installs it in a subfolder like yourdomain.com/erp/
        define('BASE_URL', $protocol . $domain . '/');
    }
}

// 3. Database Credentials Setup
if ($is_local) {
    // 🖥️ LOCAL XAMPP CREDENTIALS
    $host = 'localhost';
    $dbname = 'drhrms_db';
    $username = 'root';
    $password = '';
} else {
    // 🌐 LIVE PRODUCTION CREDENTIALS
    // You will only ever need to change these once when moving to the live cPanel/server.
    $host = 'localhost';
    $dbname = 'u769307048_globalwebify';
    $username = 'u769307048_globalwebify';
    $password = 'Admin@12312332';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>