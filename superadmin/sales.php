<?php
// /superadmin/sales.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

// ── All branches for the filter dropdown
$all_companies = $pdo->query("
    SELECT c.id, c.name, c.is_main_branch, p.name as parent_name
    FROM companies c
    LEFT JOIN companies p ON c.parent_id = p.id
    ORDER BY c.is_main_branch DESC, c.name ASC
")->fetchAll();

// ── Selected branch filter
$selected_bid = isset($_GET['branch']) && (int)$_GET['branch'] > 0 ? (int)$_GET['branch'] : null;
$where_cid    = $selected_bid ? "AND company_id = $selected_bid" : "";
$where_cid_bare = $selected_bid ? "WHERE company_id = $selected_bid" : "";
$selected_name = 'All Branches';
if ($selected_bid) {
    foreach ($all_companies as $co) {
        if ($co['id'] == $selected_bid) { $selected_name = $co['name']; break; }
    }
}

// ── KPI Stats (filtered)
$total_leads    = $pdo->query("SELECT COUNT(*) FROM leads_crm $where_cid_bare")->fetchColumn();
$total_converted= $pdo->query("SELECT COUNT(*) FROM leads_crm WHERE status='Converted' $where_cid")->fetchColumn();
$total_revenue  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM franchise_payments WHERE status='approved' $where_cid")->fetchColumn();
$total_dsrs     = $pdo->query("SELECT COUNT(*) FROM dsr $where_cid_bare")->fetchColumn();

// ── Per-Branch table (always show all; highlighted if selected)
$branch_leads = $pdo->query("
    SELECT
        c.id, c.name as branch_name, c.is_main_branch,
        parent.name as parent_name,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.status='Converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN l.status='New' THEN 1 ELSE 0 END) as new_leads,
        SUM(CASE WHEN l.status='In Progress' OR l.status='Follow Up' THEN 1 ELSE 0 END) as in_progress,
        COALESCE(SUM(fp.amount),0) as approved_revenue,
        COUNT(DISTINCT dsr.id) as dsr_count
    FROM companies c
    LEFT JOIN companies parent ON c.parent_id = parent.id
    LEFT JOIN leads_crm l ON l.company_id = c.id
    LEFT JOIN franchise_payments fp ON fp.company_id = c.id AND fp.status='approved'
    LEFT JOIN dsr dsr ON dsr.company_id = c.id
    GROUP BY c.id
    ORDER BY total_leads DESC
")->fetchAll();

// ── Top Products (filtered)
$prod_stmt = $pdo->prepare("
    SELECT product,
        COUNT(*) as total_enquiries,
        SUM(CASE WHEN status='Converted' THEN 1 ELSE 0 END) as conversions,
        ROUND(SUM(CASE WHEN status='Converted' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as conv_rate
    FROM leads_crm
    WHERE product IS NOT NULL AND product != '' " . ($selected_bid ? "AND company_id = ?" : "") . "
    GROUP BY product ORDER BY total_enquiries DESC LIMIT 10
");
$selected_bid ? $prod_stmt->execute([$selected_bid]) : $prod_stmt->execute();
$top_products = $prod_stmt->fetchAll();

// ── Lead Sources (filtered)
$src_stmt = $pdo->prepare("
    SELECT source, COUNT(*) as total,
           SUM(CASE WHEN status='Converted' THEN 1 ELSE 0 END) as converted
    FROM leads_crm " . ($selected_bid ? "WHERE company_id = ?" : "") . "
    GROUP BY source ORDER BY total DESC
");
$selected_bid ? $src_stmt->execute([$selected_bid]) : $src_stmt->execute();
$lead_sources = $src_stmt->fetchAll();

// ── Monthly Lead Trend (filtered)
$trend_stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%b %Y') as label,
           MONTH(created_at) as m, YEAR(created_at) as y,
           COUNT(*) as total,
           SUM(CASE WHEN status='Converted' THEN 1 ELSE 0 END) as converted
    FROM leads_crm
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) " . ($selected_bid ? "AND company_id = ?" : "") . "
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY y ASC, m ASC
");
$selected_bid ? $trend_stmt->execute([$selected_bid]) : $trend_stmt->execute();
$monthly_leads = $trend_stmt->fetchAll();

$chart_labels    = array_column($monthly_leads, 'label');
$chart_total     = array_column($monthly_leads, 'total');
$chart_converted = array_column($monthly_leads, 'converted');

// ── Recent Approved Payments (filtered)
$pay_stmt = $pdo->prepare("
    SELECT fp.*, c.name as branch_name
    FROM franchise_payments fp
    JOIN companies c ON fp.company_id = c.id
    WHERE fp.status = 'approved' " . ($selected_bid ? "AND fp.company_id = ?" : "") . "
    ORDER BY fp.approved_at DESC LIMIT 8
");
$selected_bid ? $pay_stmt->execute([$selected_bid]) : $pay_stmt->execute();
$recent_payments = $pay_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        body { background: #f1f5f9; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .kpi-card { background: #fff; border-radius: 14px; border: 1px solid #e8edf3; padding: 1.4rem 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
        .kpi-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .kpi-val { font-size: 1.9rem; font-weight: 800; line-height: 1.1; }
        .kpi-lbl { font-size: 0.82rem; color: #64748b; margin-top: 2px; font-weight: 500; }

        .two-col { display: grid; grid-template-columns: 1.7fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        @media(max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
        @media(max-width: 768px) { 
            .two-col { grid-template-columns: 1fr !important; }
            .kpi-grid { grid-template-columns: repeat(2, 1fr) !important; }
        }

        /* Branch table rows */
        .conv-bar-wrap { background: #f1f5f9; border-radius: 20px; height: 6px; width: 80px; display: inline-block; vertical-align: middle; margin-left: 6px; }
        .conv-bar { background: linear-gradient(90deg, #10b981, #6366f1); height: 6px; border-radius: 20px; }

        .product-row { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9; gap: 1rem; }
        .product-row:last-child { border-bottom: none; }
        .product-bar-wrap { flex: 1; background: #f1f5f9; border-radius: 20px; height: 8px; max-width: 120px; }
        .product-bar { background: linear-gradient(90deg, #6366f1, #ec4899); height: 8px; border-radius: 20px; }

        .source-chip { display: inline-flex; align-items: center; gap: 5px; background: #f8fafc; border: 1px solid #e8edf3; border-radius: 8px; padding: 0.5rem 0.9rem; font-size: 0.82rem; font-weight: 600; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header" style="flex-wrap:wrap; gap:1rem; align-items:flex-start;">
        <div>
            <h1>Sales Analytics
                <?php if ($selected_bid): ?>
                    <span style="font-size:1rem;font-weight:500;color:#6366f1;background:rgba(99,102,241,.1);padding:4px 12px;border-radius:20px;margin-left:8px">📌 <?= htmlspecialchars($selected_name) ?></span>
                <?php endif; ?>
            </h1>
            <p style="color:var(--text-muted)">Branch-wise leads, product performance and revenue.</p>
        </div>

        <!-- Branch Filter -->
        <form method="GET" id="branchFilterForm" style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            <select name="branch" id="branchSelect" class="form-control" style="min-width:200px; height:40px; font-size:0.88rem;" onchange="this.form.submit()">
                <option value="0" <?= !$selected_bid ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($all_companies as $co): ?>
                    <option value="<?= $co['id'] ?>" <?= $selected_bid == $co['id'] ? 'selected' : '' ?>>
                        <?= $co['is_main_branch'] ? '🏢' : '🏬' ?> <?= htmlspecialchars($co['name']) ?>
                        <?= $co['parent_name'] ? '(sub of ' . htmlspecialchars($co['parent_name']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selected_bid): ?>
                <a href="sales.php" class="btn btn-outline" style="height:40px; padding:0 14px; display:flex; align-items:center; font-size:0.85rem;">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Quick Branch Buttons -->
    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1.5rem;">
        <a href="sales.php" class="btn <?= !$selected_bid ? 'btn-primary' : 'btn-outline' ?>" style="font-size:0.8rem; padding:5px 14px; border-radius:20px;">All</a>
        <?php foreach ($all_companies as $co): ?>
        <a href="?branch=<?= $co['id'] ?>"
           class="btn <?= $selected_bid == $co['id'] ? 'btn-primary' : 'btn-outline' ?>"
           style="font-size:0.8rem; padding:5px 14px; border-radius:20px;">
            <?= $co['is_main_branch'] ? '🏢' : '🏬' ?> <?= htmlspecialchars($co['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(99,102,241,.1)">📋</div>
            <div><div class="kpi-val" style="color:#6366f1"><?= number_format($total_leads) ?></div><div class="kpi-lbl">Total Leads</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(16,185,129,.1)">✅</div>
            <div><div class="kpi-val" style="color:#10b981"><?= number_format($total_converted) ?></div><div class="kpi-lbl">Conversions</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(245,158,11,.1)">📈</div>
            <div>
                <div class="kpi-val" style="color:#f59e0b"><?= $total_leads > 0 ? round($total_converted / $total_leads * 100, 1) : 0 ?>%</div>
                <div class="kpi-lbl">Overall Conv. Rate</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(236,72,153,.1)">💰</div>
            <div><div class="kpi-val" style="color:#ec4899">₹<?= number_format($total_revenue, 0) ?></div><div class="kpi-lbl">Approved Revenue</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(139,92,246,.1)">📝</div>
            <div><div class="kpi-val" style="color:#8b5cf6"><?= number_format($total_dsrs) ?></div><div class="kpi-lbl">DSR Field Visits</div></div>
        </div>
    </div>

    <!-- Monthly Trend Chart + Lead Sources -->
    <div class="two-col">
        <div class="content-card" style="margin-bottom:0">
            <div class="card-header"><h2>📊 Monthly Lead Trend (Last 6 Months)</h2></div>
            <div style="height:280px"><canvas id="leadChart"></canvas></div>
        </div>
        <div class="content-card" style="margin-bottom:0">
            <div class="card-header"><h2>🔗 Lead Sources</h2></div>
            <div style="display:flex; flex-wrap:wrap; gap:0.6rem; padding-top:0.5rem;">
                <?php foreach ($lead_sources as $src): ?>
                <div class="source-chip">
                    <?= htmlspecialchars($src['source'] ?: 'Direct') ?>
                    <span style="background:#6366f1;color:#fff;border-radius:20px;padding:1px 6px;font-size:0.75rem;"><?= $src['total'] ?></span>
                    <?php if ($src['converted'] > 0): ?>
                        <span style="color:#10b981;font-size:0.75rem;">✓<?= $src['converted'] ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($lead_sources)): ?>
                    <div style="color:var(--text-muted);padding:2rem;text-align:center;width:100%">No lead data yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Branch-wise Leads Table -->
    <div class="content-card">
        <div class="card-header">
            <h2>🏢 Branch-wise Sales Performance</h2>
        </div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Total Leads</th>
                        <th>New</th>
                        <th>In Progress</th>
                        <th>Converted</th>
                        <th>Conv. Rate</th>
                        <th>DSR Reports</th>
                        <th>Revenue Approved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($branch_leads)): ?>
                        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No branch data found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($branch_leads as $b):
                        $conv_rate = $b['total_leads'] > 0 ? round($b['converted'] / $b['total_leads'] * 100, 1) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:600">
                            <?= htmlspecialchars($b['branch_name']) ?>
                            <?php if ($b['parent_name']): ?>
                                <div style="font-size:0.72rem;color:#94a3b8">under <?= htmlspecialchars($b['parent_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['is_main_branch'] == 1): ?>
                                <span style="font-size:0.72rem;background:rgba(16,185,129,.12);color:#059669;padding:2px 7px;border-radius:20px;font-weight:600">Main</span>
                            <?php else: ?>
                                <span style="font-size:0.72rem;background:rgba(99,102,241,.1);color:#6366f1;padding:2px 7px;border-radius:20px;font-weight:600">Sub</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700"><?= number_format($b['total_leads']) ?></td>
                        <td style="color:#3b82f6"><?= $b['new_leads'] ?></td>
                        <td style="color:#f59e0b"><?= $b['in_progress'] ?></td>
                        <td style="color:#10b981;font-weight:700"><?= $b['converted'] ?></td>
                        <td>
                            <span style="font-weight:600"><?= $conv_rate ?>%</span>
                            <span class="conv-bar-wrap"><span class="conv-bar" style="width:<?= min($conv_rate, 100) ?>%"></span></span>
                        </td>
                        <td style="color:#8b5cf6"><?= number_format($b['dsr_count']) ?></td>
                        <td style="font-weight:700;color:#10b981">₹<?= number_format($b['approved_revenue'], 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products + Recent Payments -->
    <div class="two-col">
        <!-- Top Products -->
        <div class="content-card" style="margin-bottom:0">
            <div class="card-header"><h2>📦 Top Products / Services</h2></div>
            <?php if (empty($top_products)): ?>
                <div style="text-align:center;color:var(--text-muted);padding:2rem">No product data yet. Add product info when creating leads.</div>
            <?php else: ?>
                <?php
                $max_leads = max(array_column($top_products, 'total_enquiries')) ?: 1;
                foreach ($top_products as $prod): ?>
                <div class="product-row">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;font-size:0.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($prod['product']) ?></div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px"><?= $prod['total_enquiries'] ?> enquiries · <?= $prod['conversions'] ?> converted</div>
                    </div>
                    <div class="product-bar-wrap">
                        <div class="product-bar" style="width:<?= round($prod['total_enquiries'] / $max_leads * 100) ?>%"></div>
                    </div>
                    <div style="text-align:right;min-width:50px">
                        <div style="font-weight:700;color:#6366f1;font-size:0.9rem"><?= $prod['total_enquiries'] ?></div>
                        <div style="font-size:0.72rem;color:<?= $prod['conv_rate'] >= 50 ? '#10b981' : '#f59e0b' ?>"><?= $prod['conv_rate'] ?>% conv.</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Approved Payments -->
        <div class="content-card" style="margin-bottom:0">
            <div class="card-header"><h2>💳 Recent Approved Payments</h2></div>
            <?php if (empty($recent_payments)): ?>
                <div style="text-align:center;color:var(--text-muted);padding:2rem">No approved payments yet.</div>
            <?php else: ?>
                <?php foreach ($recent_payments as $pay): ?>
                <div style="padding:0.75rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-weight:600;font-size:0.85rem"><?= htmlspecialchars($pay['client_name'] ?? '—') ?></div>
                        <div style="font-size:0.75rem;color:#94a3b8"><?= htmlspecialchars($pay['branch_name']) ?> · <?= $pay['category'] ?? '' ?></div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-weight:800;font-size:0.95rem;color:#10b981">₹<?= number_format($pay['amount'], 0) ?></div>
                        <div style="font-size:0.72rem;color:#94a3b8"><?= date('M d', strtotime($pay['approved_at'] ?? $pay['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels   = <?= json_encode($chart_labels) ?>;
const totals   = <?= json_encode(array_map('intval', $chart_total)) ?>;
const convData = <?= json_encode(array_map('intval', $chart_converted)) ?>;

new Chart(document.getElementById('leadChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Total Leads',
                data: totals,
                backgroundColor: 'rgba(99,102,241,0.7)',
                borderRadius: 6, borderColor: '#6366f1', borderWidth: 1
            },
            {
                label: 'Converted',
                data: convData,
                backgroundColor: 'rgba(16,185,129,0.8)',
                borderRadius: 6, borderColor: '#10b981', borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { color: '#9ca3af' }, grid: { color: 'rgba(0,0,0,0.04)' } },
            x: { ticks: { color: '#9ca3af' }, grid: { display: false } }
        },
        plugins: { legend: { labels: { color: '#374151', padding: 15 } } }
    }
});
</script>
</body>
</html>
