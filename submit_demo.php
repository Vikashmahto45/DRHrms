<?php
// submit_demo.php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (!empty($name) && !empty($email) && !empty($company_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO demo_requests (name, email, company_name, phone, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$name, $email, $company_name, $phone]);
            $_SESSION['demo_success'] = "Thank you! Your demo request has been received. Our team will verify and contact you shortly.";
        } catch (PDOException $e) {
            $_SESSION['demo_error'] = "An error occurred while submitting your request.";
        }
    } else {
        $_SESSION['demo_error'] = "Please fill in all required fields.";
    }
}
header("Location: index.php#contact");
exit();
?>
