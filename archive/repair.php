<?php
// Repair script
$files = ['admin/dashboard.php', 'admin/leads.php', 'admin/dsr.php'];

foreach($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Apply bypass
        $c = preg_replace('/\bleads\b/i', 'leads_crm', $c);
        $c = preg_replace('/\bdaily_sales_reports\b/i', 'dsr', $c);
        
        file_put_contents($f, $c);
        echo "Repaired: $f\n";
    }
}
?>
