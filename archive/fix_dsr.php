<?php
$files = [
    'superadmin/sales.php',
    'superadmin/dashboard.php'
];

echo "--- Starting DSR Bulk Redirect ---\n";
foreach($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $c = file_get_contents($path);
        
        $c = preg_replace('/\bdaily_sales_reports\b/i', 'dsr', $c);
        
        file_put_contents($path, $c);
        echo "Successfully redirected queries in: $f\n";
    } else {
        echo "File not found: $f\n";
    }
}
echo "--- Bulk Redirect Complete ---\n";
?>
