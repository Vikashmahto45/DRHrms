<?php
// /admin/leads_crm.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$cid = $_SESSION['company_id'];
$role = strtolower($_SESSION['user_role'] ?? '');
$uid  = $_SESSION['user_id'] ?? 0;

// Fetch accessible branch IDs
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Check permission
$perm = $pdo->prepare("SELECT is_enabled FROM permissions_map WHERE company_id=? AND module_name='leads_crm'");
$perm->execute([$cid]);
if (!$perm->fetchColumn()) {
    die("<div style='padding:3rem;text-align:center;color:var(--text-main);background:#fff;min-height:100vh;font-family:sans-serif;'><h2>Module Locked</h2><p>The leads_crm CRM is not enabled for your account.</p><a href='dashboard.php' style='color:var(--primary-color)'>Back to Dashboard</a></div>");
}

// Fetch Limits & Usage
$company_stmt = $pdo->prepare("SELECT lead_limit FROM companies WHERE id = ?");
$company_stmt->execute([$cid]);
$lead_limit = $company_stmt->fetchColumn() ?: 100;

$usage_stmt = $pdo->prepare("SELECT COUNT(*) FROM leads_crm WHERE company_id IN ($cids_in)");
$usage_stmt->execute();
$current_usage = $usage_stmt->fetchColumn();

// Fetch Dynamic Products Catalog
$prod_stmt = $pdo->prepare("SELECT * FROM products WHERE company_id IN ($cids_in) ORDER BY name ASC");
$prod_stmt->execute();
$products_catalog = $prod_stmt->fetchAll();
$products_map = [];
foreach($products_catalog as $pc) { $products_map[$pc['id']] = $pc['name']; }

$msg = ''; $msgType = '';
if ($_SESSION['flash_msg'] ?? null) {
    $msg = $_SESSION['flash_msg']; $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if ($role !== 'admin') {
            die("Unauthorized: Only admins can create leads_crm.");
        }
        $client  = trim($_POST['client_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $source  = $_POST['source'] ?? 'Manual';
        
        $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $product_name = $product_id && isset($products_map[$product_id]) ? $products_map[$product_id] : 'General';
        
        $assign  = $_POST['assigned_to'] ?: null;
        
        if ($client) {
            if ($current_usage >= $lead_limit) {
                $_SESSION['flash_msg'] = "Lead limit reached ({$lead_limit}). Please upgrade your plan.";
                $_SESSION['flash_type'] = "error";
            } else {
                $note = trim($_POST['note'] ?? '');
                $pdo->prepare("INSERT INTO leads_crm (company_id, client_name, phone, source, product, product_id, status, assigned_to, note) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$cid, $client, $phone, $source, $product_name, $product_id, 'New', $assign, $note]);
                logActivity('lead_captured', "New lead: $client for $product_name from $source", $cid);
                $_SESSION['flash_msg'] = "Lead captured successfully!";
                $_SESSION['flash_type'] = "success";
            }
            header("Location: leads_crm.php"); exit();
        }
    }

    if ($action === 'update_status') {
        $lead_id = (int)$_POST['lead_id'];
        $new_status  = trim($_POST['status']);
        
        
        // Fetch old status for history and permission check
        $stmt = $pdo->prepare("SELECT status, assigned_to FROM leads_crm WHERE id=? AND company_id IN ($cids_in)");
        $stmt->execute([$lead_id]);
        $row = $stmt->fetch();
        
        if ($row) {
            $old_status = $row['status'];
            $assigned_to = (int)$row['assigned_to'];
            
            // Security Check: Admin or Assigned staff only
            $can_edit = ($role === 'admin' || $role === 'manager' || (int)$uid === $assigned_to);
            
            
            if ($can_edit && $old_status !== $new_status) {
                // Perform Update
                // Note: The specific branch ID doesn't need to be checked explicitly since the lead ID determines the record, and we just ensure they have access to the lead's company.
                $pdo->prepare("UPDATE leads_crm SET status=? WHERE id=? AND company_id IN ($cids_in)")
                    ->execute([$new_status, $lead_id]);
                
                // History & Global Log
                $pdo->prepare("INSERT INTO lead_history (lead_id, user_id, event_type, details) VALUES (?, ?, 'status_change', ?)")
                    ->execute([$lead_id, $_SESSION['user_id'], "Changed status from $old_status to $new_status (via Table)"]);
                
                logActivity('lead_status_updated', "Lead ID: $lead_id status changed to $new_status", $cid);
                
                $_SESSION['flash_msg'] = "Status updated to $new_status!";
                $_SESSION['flash_type'] = "success";
            }
        }
        header("Location: leads_crm.php"); 
        exit();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['lead_id'];
        $pdo->prepare("DELETE FROM leads_crm WHERE id=? AND company_id IN ($cids_in)")->execute([$id]);
        logActivity('lead_deleted', "Deleted lead ID: $id", $cid);
        header("Location: leads_crm.php"); exit();
    }
}

// Filter by Source
$source_filter = $_GET['source_filter'] ?? '';
$where_source = $source_filter ? "AND l.source = ?" : "";

// Fetch leads_crm
$where_role = ($role === 'sales_person') ? "AND l.assigned_to = $uid" : "";

$leads_query = "
    SELECT l.*, u.name as assignee, c.name as company_name 
    FROM leads_crm l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    LEFT JOIN companies c ON l.company_id = c.id
    WHERE l.company_id IN ($cids_in) $where_source $where_role
    ORDER BY l.created_at DESC
";
$leads_crm = $pdo->prepare($leads_query);
$params = [];
if ($source_filter) $params[] = $source_filter;
$leads_crm->execute($params);
$leads_crm = $leads_crm->fetchAll();

// Helper for source badges
function getSourceBadge($source) {
    $colors = [
        'Meta Ads' => '#1877F2',
        'Google Ads' => '#4285F4',
        'Referral' => '#10b981',
        'Website' => '#f59e0b',
        'Walk-in' => '#8b5cf6',
        'Email' => '#ec4899',
        'Social Media' => '#06b6d4'
    ];
    $color = $colors[$source] ?? '#6b7280';
    return "<span class='source-tag' style='background:".($color."22")."; color:$color; border:1px solid ".($color."44")."; font-weight:600;'>$source</span>";
}

// Fetch staff for assignment dropdown
$staff = $pdo->prepare("SELECT id, name FROM users WHERE company_id IN ($cids_in) AND role IN ('staff','manager','sales_person') AND status='active'");
$staff->execute();
$staff = $staff->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead CRM - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439732">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439732">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Lead CRM</h1>
            <p style="color:var(--text-muted)">Usage: <strong><?= $current_usage ?> / <?= $lead_limit ?></strong> leads_crm</p>
        </div>
        <div style="display:flex; gap:1rem; align-items:center;">
            <form method="GET" style="display:flex; gap:.5rem;">
                <select name="source_filter" class="form-control" style="width:180px;" onchange="this.form.submit()">
                    <option value="">All Sources</option>
                    <option value="Manual" <?= $source_filter==='Manual'?'selected':'' ?>>Manual</option>
                    <option value="Referral" <?= $source_filter==='Referral'?'selected':'' ?>>Referral</option>
                    <option value="Walk-in" <?= $source_filter==='Walk-in'?'selected':'' ?>>Walk-in</option>
                    <option value="Meta Ads" <?= $source_filter==='Meta Ads'?'selected':'' ?>>Meta Ads</option>
                    <option value="Google Ads" <?= $source_filter==='Google Ads'?'selected':'' ?>>Google Ads</option>
                    <option value="Website" <?= $source_filter==='Website'?'selected':'' ?>>Website</option>
                </select>
            </form>
            <?php if ($role === 'admin'): ?>
                <?php if ($current_usage < $lead_limit): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">+ New Lead</button>
                <?php else: ?>
                    <button class="btn btn-outline" style="border-color:#ef4444;color:#ef4444;cursor:not-allowed">Limit Reached</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- leads_crm Kanban / Table representation view -->
    <div class="content-card">
        <div class="card-header"><h2>All leads_crm &amp; Walk-ins (<?= count($leads_crm) ?>)</h2></div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead><tr><th>Client/Lead Name</th><th>Contact</th><th>Source</th><th>Product</th><th>Assigned To</th><th>Status</th><th>Note</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($leads_crm as $l): ?>
                    <tr>
                        <td style="font-weight:600">
                            <?= htmlspecialchars($l['client_name']) ?>
                            <?php if($l['company_id'] != $cid): ?> <br><span style="font-size:0.7rem;color:var(--text-muted);">(Branch: <?= htmlspecialchars($l['company_name']) ?>)</span> <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['phone']): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $l['phone']) ?>" target="_blank" class="badge" style="background:#25d366;color:#fff;text-decoration:none">💬 WhatsApp</a>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($l['phone']) ?></div>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= getSourceBadge($l['source'] ?? 'Manual') ?></td>
                        <td>
                            <span class="badge" style="background:rgba(0,0,0,0.03); color:var(--text-main); border:1px solid var(--glass-border)">
                                <?= htmlspecialchars($l['product'] ?: '—') ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($l['assignee'] ?? 'Unassigned') ?></td>
                        <td>
                            <?php if ($role === 'admin' || $role === 'manager' || $uid == $l['assigned_to']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                    <select name="status" class="form-control" style="width:130px;padding:.4rem;font-size:.85rem;background:transparent;border-color:var(--glass-border)" onchange="this.form.submit()">
                                        <option value="New" <?= $l['status']==='New'?'selected':'' ?>>🔵 New</option>
                                        <option value="In Progress" <?= $l['status']==='In Progress'?'selected':'' ?>>🟡 In Progress</option>
                                        <option value="Converted" <?= $l['status']==='Converted'?'selected':'' ?>>🟢 Converted</option>
                                        <option value="Lost" <?= $l['status']==='Lost'?'selected':'' ?>>🔴 Lost</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <div class="badge badge-<?= strtolower(str_replace(' ', '-', $l['status'])) ?>"><?= $l['status'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.85rem;color:var(--text-muted); max-width: 200px;" title="<?= htmlspecialchars($l['note'] ?? '') ?>">
                            <?= $l['note'] ? (strlen($l['note']) > 40 ? htmlspecialchars(substr($l['note'], 0, 37)).'...' : htmlspecialchars($l['note'])) : '—' ?>
                        </td>
                        <td style="display:flex; gap:0.4rem;">
                            <a href="lead_profile.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline" title="View Profile">📁</a>
                            <?php if ($role === 'admin'): ?>
                                <form method="POST" onsubmit="return confirm('Delete this lead forever?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                    <button class="btn btn-sm btn-danger">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($leads_crm)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No leads_crm yet. Create one manually or connect website forms.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="max-width:550px">
        <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        <h3>Capture New Lead</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Client / Lead Name *</label>
                <input type="text" name="client_name" class="form-control" required placeholder="Full Name or Company">
            </div>
            <div class="form-group">
                <label>Phone Number (for WhatsApp)</label>
                <input type="text" name="phone" class="form-control" placeholder="e.g. +91 98765 43210">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Product / Service Interest</label>
                    <select name="product_id" class="form-control" required>
                        <option value="">-- Select Service --</option>
                        <?php foreach($products_catalog as $pc): ?>
                            <option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['name']) ?> (₹<?= number_format($pc['price'],0) ?>)</option>
                        <?php endforeach; ?>
                        <?php if(empty($products_catalog)): ?>
                            <option value="">(No services cataloged! Add in Settings first)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Source</label>
                    <select name="source" class="form-control">
                        <option value="Manual">Manual Entry</option>
                        <option value="Referral">Client Referral</option>
                        <option value="Walk-in">Walk-in / Visit</option>
                        <option value="Meta Ads">Meta Ads (Manual)</option>
                        <option value="Google Ads">Google Ads (Manual)</option>
                        <option value="Social Media">Social Media</option>
                        <option value="Website">Website</option>
                        <option value="Email">Email/Cold</option>
                    </select>
                </div>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div class="form-group">
                    <label>Assign To</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">-- Leave Unassigned --</option>
                        <?php foreach($staff as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="assigned_to" value="<?= $_SESSION['user_id'] ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Staff Note / Context</label>
                <textarea name="note" class="form-control" rows="3" placeholder="Additional details about the lead..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Create Lead Record</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
