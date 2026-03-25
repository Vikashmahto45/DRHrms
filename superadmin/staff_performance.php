<?php
// /superadmin/staff_performance.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

$month = $_GET['month'] ?? date('Y-m');

// Main Query: Cross-company staff ranking
$query = "
    SELECT 
        u.id, 
        u.name, 
        c.name as company_name,
        (SELECT COUNT(*) FROM dsr WHERE user_id = u.id AND DATE_FORMAT(visit_date, '%Y-%m') = :month) as dsr_count,
        (SELECT COUNT(*) FROM leads_crm WHERE assigned_to = u.id AND DATE_FORMAT(created_at, '%Y-%m') = :month) as leads_count,
        (SELECT COUNT(*) FROM leads_crm WHERE assigned_to = u.id AND status = 'converted' AND DATE_FORMAT(updated_at, '%Y-%m') = :month) as conversion_count
    FROM users u
    JOIN companies c ON u.company_id = c.id
    WHERE u.role = 'sales_person' OR u.role = 'staff'
    ORDER BY conversion_count DESC, dsr_count DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute(['month' => $month]);
$rankings = $stmt->fetchAll();

// Top 3 Overall for badges
$top_performers = array_slice($rankings, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774434222">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774434222">
    <style>
        .leaderboard-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .performance-card { background: #fff; padding: 1.5rem; border-radius: 16px; border: 1px solid var(--glass-border); position: relative; overflow: hidden; }
        .rank-badge { position: absolute; top: 10px; right: 10px; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; }
        .rank-1 { background: #fef3c7; color: #d97706; border: 2px solid #fbbf24; }
        .rank-2 { background: #f1f5f9; color: #475569; border: 2px solid #cbd5e1; }
        .rank-3 { background: #fff7ed; color: #c2410c; border: 2px solid #fdba74; }
        .top-3-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .per-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; }
        .per-val { font-size: 1.2rem; font-weight: 700; color: var(--text-main); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="leaderboard-header">
        <div>
            <h1>Global Performance Leaderboard</h1>
            <p style="color:var(--text-muted)">Tracking top performers across the entire network.</p>
        </div>
        <form method="GET" class="filter-box">
            <input type="month" name="month" value="<?= $month ?>" onchange="this.form.submit()" style="padding:0.6rem; border-radius:10px; border:1px solid #e2e8f0; font-family:inherit;">
        </form>
    </div>

    <?php if (count($top_performers) > 0): ?>
    <div class="top-3-grid">
        <?php foreach ($top_performers as $idx => $p): ?>
        <div class="performance-card">
            <div class="rank-badge rank-<?= $idx+1 ?>"><?= $idx+1 ?></div>
            <div style="font-size:0.8rem; color:var(--primary-color); font-weight:700; margin-bottom:5px;"><?= strtoupper($p['company_name']) ?></div>
            <h3 style="margin-bottom:1.5rem;"><?= htmlspecialchars($p['name']) ?></h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div>
                    <div class="per-label">Visits</div>
                    <div class="per-val"><?= $p['dsr_count'] ?></div>
                </div>
                <div>
                    <div class="per-label">Conversions</div>
                    <div class="per-val" style="color:#10b981;"><?= $p['conversion_count'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="card-header">
            <h2>Full Network Ranking</h2>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Staff Name</th>
                    <th>Branch / Franchise</th>
                    <th>DSR Visits</th>
                    <th>Leads Created</th>
                    <th>Sales Converted</th>
                    <th>Conv. Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankings as $idx => $p): ?>
                <tr>
                    <td><span style="font-weight:700; color:<?= $idx < 3 ? 'var(--primary-color)' : 'var(--text-muted)' ?>">#<?= $idx+1 ?></span></td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['company_name']) ?></td>
                    <td><?= $p['dsr_count'] ?></td>
                    <td><?= $p['leads_count'] ?></td>
                    <td style="font-weight:700; color:#10b981;"><?= $p['conversion_count'] ?></td>
                    <td>
                        <?php 
                            $rate = $p['leads_count'] > 0 ? ($p['conversion_count'] / $p['leads_count']) * 100 : 0;
                            echo number_format($rate, 1) . '%';
                        ?>
                        <div style="width:50px; height:4px; background:#f1f5f9; border-radius:2px; margin-top:4px;">
                            <div style="width:<?= min(100, $rate) ?>%; height:100%; background:#10b981; border-radius:2px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!count($rankings)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No activity data found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
