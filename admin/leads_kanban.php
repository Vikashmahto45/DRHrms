<?php
// /admin/leads_kanban.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person', 'staff']);

$cid = $_SESSION['company_id'];
$branch_ids = getAccessibleBranchIds($pdo, $cid);
$cids_in = implode(',', $branch_ids);

// Get all leads
$role = $_SESSION['user_role'];
$uid  = $_SESSION['user_id'];
$where_role = ($role === 'sales_person') ? "AND assigned_to = $uid" : "";
$stmt = $pdo->prepare("SELECT id, client_name, phone, status, source, product, note FROM leads_crm WHERE company_id IN ($cids_in) $where_role");
$stmt->execute();
$leads = $stmt->fetchAll();

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
    return "<span class='source-tag' style='background:".($color."22")."; color:$color; border:1px solid ".($color."44")."; font-style: normal; text-transform: uppercase; font-weight:700;'>$source</span>";
}

$columns = [
    'new' => 'New Leads',
    'contacted' => 'Contacted',
    'follow-up' => 'Follow-up',
    'closed' => 'Closed'
];

$leads_by_status = [
    'new' => [],
    'contacted' => [],
    'follow-up' => [],
    'closed' => []
];

foreach ($leads as $lead) {
    $status = strtolower($lead['status']);
    if (isset($leads_by_status[$status])) {
        $leads_by_status[$status][] = $lead;
    } else {
        $leads_by_status['new'][] = $lead; // Fallback
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Pipeline (Kanban) - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        .kanban-board {
            display: flex;
            gap: 1.5rem;
            padding: 1rem 0;
            overflow-x: auto;
            min-height: calc(100vh - 250px);
            align-items: flex-start;
        }
        
        .kanban-column {
            background: var(--bg-main);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            width: 320px;
            min-width: 320px;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
        }
        
        .column-header {
            padding: 1.2rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .column-header h3 {
            font-size: 1rem;
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lead-count {
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .lead-list {
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
            min-height: 100px;
        }
        
        .lead-card {
            background: #ffffff;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: grab;
            transition: all 0.3s;
            position: relative;
        }
        
        .lead-card:active {
            cursor: grabbing;
        }
        
        .lead-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .lead-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .lead-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .source-tag {
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(0,0,0,0.03);
            font-size: 0.65rem;
        }
        
        .drag-over {
            background: rgba(99, 102, 241, 0.05);
            border-style: dashed;
        }
        
        .card-ghost {
            opacity: 0.4;
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Lead Pipeline</h1>
                <p style="color:var(--text-muted)">Drag and drop leads to update their lifecycle status.</p>
            </div>
            <div style="display:flex;gap:1rem;">
                <a href="leads.php" class="btn btn-outline">Table View</a>
                <?php if ($role === 'admin'): ?>
                    <a href="leads.php?action=new" class="btn btn-primary">+ New Lead</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="kanban-board">
            <?php foreach ($columns as $status_id => $title): ?>
            <div class="kanban-column" id="col-<?= $status_id ?>" ondrop="drop(event, '<?= $status_id ?>')" ondragover="allowDrop(event)" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)">
                <div class="column-header">
                    <h3><?= $title ?></h3>
                    <span class="lead-count"><?= count($leads_by_status[$status_id]) ?></span>
                </div>
                <div class="lead-list" id="list-<?= $status_id ?>">
                    <?php foreach ($leads_by_status[$status_id] as $lead): ?>
                    <div class="lead-card" id="lead-<?= $lead['id'] ?>" draggable="true" ondragstart="drag(event)">
                        <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <span class="lead-name" style="margin:0;"><?= htmlspecialchars($lead['client_name']) ?></span>
                            <a href="lead_profile.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-outline" style="padding: 2px 6px; font-size: 0.65rem; border-radius: 4px;">Profile</a>
                        </div>
                        <div style="margin-bottom: 0.8rem; display:flex; flex-wrap:wrap; gap:0.4rem;">
                            <?php if ($lead['phone']): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lead['phone']) ?>" target="_blank" style="font-size: 0.7rem; color: #25d366; text-decoration: none; display: flex; align-items: center; gap: 4px; background: rgba(37,211,102,0.1); padding: 2px 6px; border-radius: 4px;">
                                    <span>💬</span> WhatsApp
                                </a>
                            <?php endif; ?>
                            <?php if ($lead['product']): ?>
                                <span style="font-size: 0.65rem; background: rgba(99,102,241,0.1); color: var(--primary-color); padding: 2px 6px; border-radius: 4px; font-weight: 700; text-transform: uppercase; border: 1px solid rgba(99,102,241,0.2);">
                                    📦 <?= htmlspecialchars($lead['product']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="lead-meta">
                            <?= getSourceBadge($lead['source'] ?? 'Manual') ?>
                            <span>#<?= $lead['id'] ?></span>
                        </div>
                        <?php if ($lead['note']): ?>
                            <div style="margin-top: 0.8rem; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--glass-border); padding-top: 0.5rem; line-height: 1.4;">
                                📝 <?= htmlspecialchars(strlen($lead['note']) > 60 ? substr($lead['note'], 0, 57).'...' : $lead['note']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<script>
function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("lead_id", ev.target.id.replace('lead-', ''));
    ev.target.classList.add('card-ghost');
}

function dragEnter(ev) {
    if (ev.target.classList.contains('kanban-column') || ev.target.closest('.kanban-column')) {
        const col = ev.target.closest('.kanban-column');
        col.classList.add('drag-over');
    }
}

function dragLeave(ev) {
    const col = ev.target.closest('.kanban-column');
    col.classList.remove('drag-over');
}

function drop(ev, newStatus) {
    ev.preventDefault();
    const col = ev.target.closest('.kanban-column');
    col.classList.remove('drag-over');
    
    const leadId = ev.dataTransfer.getData("lead_id");
    const leadElement = document.getElementById('lead-' + leadId);
    leadElement.classList.remove('card-ghost');
    
    // Move element in DOM
    const list = col.querySelector('.lead-list');
    list.appendChild(leadElement);
    
    // Update count badges
    updateBadges();
    
    // AJAX call to update database
    fetch('../api/leads/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `lead_id=${leadId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Failed to update status: ' + data.error);
            location.reload(); // Revert on failure
        }
    });
}

function updateBadges() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const count = col.querySelectorAll('.lead-card').length;
        col.querySelector('.lead-count').textContent = count;
    });
}
</script>
</body>
</html>
