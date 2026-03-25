<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=drhrms_db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Import tablespace
    echo "Attempting to import tablespace for users...\n";
    $pdo->exec("ALTER TABLE users IMPORT TABLESPACE");
    echo "SUCCESS: Tablespace imported for users!\n";
    
    // 4. Verify data
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Users found: $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, name, email, role FROM users LIMIT 1");
        print_r($stmt->fetch(PDO::FETCH_ASSOC));
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "If it's a schema mismatch, we need to match the columns exactly.\n";
}
