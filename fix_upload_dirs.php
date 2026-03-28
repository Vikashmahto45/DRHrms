<?php
// fix_upload_dirs.php
require_once 'config/database.php';

$dirs = [
    'assets/uploads/payments/',
    'assets/uploads/qr/',
    'assets/uploads/profiles/',
    'assets/uploads/dsr/'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "[SUCCESS] Created directory: $dir\n";
        } else {
            echo "[ERROR] Failed to create directory: $dir. Check parent permissions.\n";
        }
    } else {
        echo "[INFO] Directory already exists: $dir\n";
        @chmod($dir, 0755); 
    }
    
    if (is_writable($dir)) {
        echo "[OK] Directory is WRITABLE: $dir\n";
    } else {
        echo "[CRITICAL] Directory is NOT WRITABLE: $dir. Please set permissions to 755 or 777 manually in File Manager.\n";
    }
    echo "---------------------------\n";
}
echo "Manual Fix: Use Hostinger File Manager to set 'assets/uploads/' and its children to 755 permissions.";
?>
