<?php
$files = [
    'superadmin/staff_performance.php',
    'superadmin/sales.php',
    'superadmin/dashboard.php',
    'api/webhooks/meta.php',
    'api/webhooks/google.php',
    'api/leads/update_status.php',
    'admin/leads_kanban.php',
    'admin/lead_profile.php'
];

echo "--- Starting Bulk Redirect ---\n";
foreach($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $c = file_get_contents($path);
        
        $c = preg_replace('/\bFROM leads\b/i', 'FROM leads_crm', $c);
        $c = preg_replace('/\bUPDATE leads\b/i', 'UPDATE leads_crm', $c);
        $c = preg_replace('/\bINTO leads\b/i', 'INTO leads_crm', $c);
        $c = preg_replace('/\bJOIN leads\b/i', 'JOIN leads_crm', $c);
        
        file_put_contents($path, $c);
        echo "Successfully redirected queries in: $f\n";
    } else {
        echo "File not found: $f\n";
    }
}
echo "--- Bulk Redirect Complete ---\n";
?>
