<?php
// /superadmin/attendance.php
require_once '../includes/auth.php';
require_once '../config/database.php';

// Ensure user is super_admin
checkAccess('super_admin');

$msg = '';
$msgType = '';

// Filtering Logic
$where_clauses = ["c.is_main_branch = 1"];
$params = [];

if (!empty($_GET['company_id'])) {
    $where_clauses[] = "c.id = ?";
    $params[] = (int)$_GET['company_id'];
}

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $where_clauses[] = "a.date BETWEEN ? AND ?";
    $params[] = $_GET['start_date'];
    $params[] = $_GET['end_date'];
} elseif (!empty($_GET['month'])) {
    $where_clauses[] = "MONTH(a.date) = ? AND YEAR(a.date) = YEAR(CURDATE())";
    $params[] = $_GET['month'];
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Attendance Records
$query = "
    SELECT a.*, u.name as employee_name, u.role, c.name as company_name 
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    JOIN companies c ON u.company_id = c.id
    WHERE $where_sql
    ORDER BY a.date DESC, a.clock_in DESC
    LIMIT 200
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Fetch Main Branches for the filter
$main_branches = $pdo->query("SELECT id, name FROM companies WHERE is_main_branch = 1 ORDER BY name ASC")->fetchAll();

// Helper to calculate duration
function getDuration($in, $out) {
    if (!$in || !$out) return "---";
    $start = new DateTime($in);
    $end = new DateTime($out);
    $diff = $start->diff($end);
    return $diff->format('%h hrs %i mins');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance (Main Branch) - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
    <style>
        .filter-section {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e8edf3;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Attendance Monitoring</h1>
            <p style="color:var(--text-muted)">Viewing activity for <strong>Main Branch</strong> staff members.</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-section">
        <div class="filter-group">
            <label>Sub-Branch / Main Branch</label>
            <select name="company_id" class="form-control" style="width:200px;">
                <option value="">All Main Branches</option>
                <?php foreach($main_branches as $mb): ?>
                    <option value="<?= $mb['id'] ?>" <?= (isset($_GET['company_id']) && $_GET['company_id'] == $mb['id']) ? 'selected' : '' ?>><?= htmlspecialchars($mb['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Month Filter</label>
            <select name="month" class="form-control" style="width:150px;">
                <option value="">-- All Months --</option>
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?= $i ?>" <?= (isset($_GET['month']) && $_GET['month'] == $i) ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Date Range</label>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="date" name="start_date" class="form-control" value="<?= $_GET['start_date'] ?? '' ?>">
                <span style="color:var(--text-muted)">to</span>
                <input type="date" name="end_date" class="form-control" value="<?= $_GET['end_date'] ?? '' ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.5rem;">Apply Filter</button>
        <a href="attendance.php" class="btn btn-outline" style="padding:0.6rem 1.5rem;">Reset</a>
    </form>

    <div class="content-card">
        <div class="card-header">
            <h2>Activity Log (<?= count($records) ?>)</h2>
        </div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Branch</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                        <th>Location/IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($r['employee_name']) ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;"><?= htmlspecialchars($r['role']) ?></div>
                        </td>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($r['company_name']) ?></span></td>
                        <td><strong><?= date('D, M d', strtotime($r['date'])) ?></strong></td>
                        <td style="color:#10b981; font-weight:600;"><?= $r['clock_in'] ? date('h:i A', strtotime($r['clock_in'])) : '---' ?></td>
                        <td style="color:#ef4444; font-weight:600;"><?= $r['clock_out'] ? date('h:i A', strtotime($r['clock_out'])) : '---' ?></td>
                        <td style="font-weight:600; color:var(--primary-color);">
                            <?= getDuration($r['clock_in'], $r['clock_out']) ?>
                        </td>
                        <td>
                            <div style="font-size:0.8rem;"><?= htmlspecialchars($r['ip_address'] ?? 'No IP') ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">
                                <?= $r['lat'] ? "GPS: {$r['lat']}, {$r['lng']}" : 'No GPS' ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No attendance records found for the selected criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
