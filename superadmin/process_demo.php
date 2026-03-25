<?php
// /superadmin/process_demo.php
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

checkAccess('super_admin');

$request_id = $_GET['id'] ?? null;

if (!$request_id) {
    header("Location: dashboard.php");
    exit();
}

try {
    // 1. Fetch Demo Request
    $stmt = $pdo->prepare("SELECT * FROM demo_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $demoRequest = $stmt->fetch();

    if (!$demoRequest) {
        die("Invalid request or already processed.");
    }

    $pdo->beginTransaction();

    // 2. Create Company
    $stmt = $pdo->prepare("INSERT INTO companies (name, status) VALUES (?, 'active')");
    $stmt->execute([$demoRequest['company_name']]);
    $company_id = $pdo->lastInsertId();

    // 3. Create Company Admin User (Generate random password)
    $raw_password = bin2hex(random_bytes(4)); // 8 character random password
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $stmt->execute([$company_id, $demoRequest['name'], $demoRequest['email'], $hashed_password]);

    // 4. Assign Default Permissions (HRMS and Leads enabled)
    $stmt = $pdo->prepare("INSERT INTO permissions_map (company_id, module_name, is_enabled) VALUES (?, 'hrms', 1), (?, 'leads', 1)");
    $stmt->execute([$company_id, $company_id]);

    // 5. Update Demo Request Status
    $stmt = $pdo->prepare("UPDATE demo_requests SET status = 'converted' WHERE id = ?");
    $stmt->execute([$request_id]);

    $pdo->commit();
    
    // In a real app we'd send an email here. We simulate it with a session flash message.
    $_SESSION['sa_flash_message'] = "Setup Complete! Company '{$demoRequest['company_name']}' created. <br><strong>Admin Email:</strong> {$demoRequest['email']}<br><strong>Password:</strong> {$raw_password}";

    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Setup failed: " . $e->getMessage());
}
?>
