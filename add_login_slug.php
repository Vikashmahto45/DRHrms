<?php
require 'config/database.php';

try {
    // Check if login_slug exists
    $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'login_slug'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN login_slug VARCHAR(100) NULL");
        echo "Column 'login_slug' added successfully.\n";
        
        // Populate existing rows
        $pdo->exec("UPDATE companies SET login_slug = SUBSTRING(MD5(id), 1, 10) WHERE login_slug IS NULL");
        echo "Populated existing companies with fallback slugs.\n";
    } else {
        echo "Column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
