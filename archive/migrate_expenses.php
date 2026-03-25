<?php
require_once 'c:/xampp/htdocs/DR Hrms/config/database.php';

try {
    $pdo->beginTransaction();

    // 1. Create expense_categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "Table 'expense_categories' created successfully.\n";

    // 2. Create expenses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        category_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        expense_date DATE NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "Table 'expenses' created successfully.\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Database Error: " . $e->getMessage());
}
