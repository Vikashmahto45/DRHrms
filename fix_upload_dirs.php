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
        if (mkdir($dir, 0777, true)) {
            echo "Created directory: $dir\n";
        } else {
            echo "Failed to create directory: $dir\n";
        }
    } else {
        echo "Directory already exists: $dir\n";
        chmod($dir, 0777); 
        echo "Updated permissions for: $dir\n";
    }
}
?>
