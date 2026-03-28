<?php
require_once 'config/database.php';
try {
    $pdo->exec("ALTER TABLE dsr ADD COLUMN product_id INT NULL DEFAULT NULL AFTER user_id");
    echo "Added product_id to dsr table.\n";
} catch (Exception $e) { echo "product_id likely exists.\n"; }

try {
    $pdo->exec("ALTER TABLE dsr ADD COLUMN sold_price DECIMAL(15,2) NULL DEFAULT NULL AFTER deal_status");
    echo "Added sold_price to dsr table.\n";
} catch (Exception $e) { echo "sold_price likely exists.\n"; }
