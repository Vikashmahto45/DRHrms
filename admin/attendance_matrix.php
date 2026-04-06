<?php
// /admin/attendance_matrix.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager']);

$cid = $_SESSION['company_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Fetch all active staff
$staff_stmt = $pdo->prepare("SELECT id, name FROM users WHERE company_id = ? AND role IN ('staff', 'manager', 'sales_person') AND status = 'active' ORDER BY name ASC");
$staff_stmt->execute([$cid]);
$staff = $staff_stmt->fetchAll();

// Fetch attendance for this month
$att_stmt = $pdo->prepare("SELECT user_id, DAY(date) as day, status, clock_in, clock_out FROM attendance WHERE company_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$att_stmt->execute([$cid, $month, $year]);
$attendance_data = [];
while($row = $att_stmt->fetch()) {
    $attendance_data[$row['user_id']][$row['day']] = $row;
}

// Fetch holidays for this month
$hol_stmt = $pdo->prepare("SELECT DAY(holiday_date) as day, name FROM holidays WHERE company_id = ? AND MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?");
$hol_stmt->execute([$cid, $month, $year]);
$holidays = [];
while($row = $hol_stmt->fetch()) {
    $holidays[$row['day']] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Matrix - Loom</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <style>
        .matrix-table { border-collapse: collapse; font-size: 0.75rem; width: 100%; table-layout: fixed; }
        .matrix-table th, .matrix-table td { border: 1px solid #e2e8f0; padding: 4px; text-align: center; }
        .matrix-table th:first-child, .matrix-table td:first-child { position: sticky; left: 0; background: #fff; z-index: 10; width: 120px; text-align: left; font-size: 0.8rem; }
        .day-header { width: 30px; min-width: 30px; font-weight: 700; background: #f8fafc; }
        .status-P { background: #dcfce7; color: #166534; } /* Present */
        .status-L { background: #fef9c3; color: #854d0e; } /* Late */
        .status-A { background: #fee2e2; color: #991b1b; } /* Absent */
        .status-H { background: #e0e7ff; color: #3730a3; } /* Holiday */
        .weekend { background: #f1f5f9; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        <div class="page-header">
            <div>
                <h1>Monthly Attendance Matrix</h1>
                <p style="color:var(--text-muted)">Visual overview of staff performance for <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></p>
            </div>
            <form method="GET" style="display:flex; gap:10px;">
                <select name="month" class="form-control" onchange="this.form.submit()">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php for($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <div class="content-card" style="padding: 0; overflow: hidden;">
            <div style="overflow-x: auto;">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th>Staff Name</th>
                            <?php for($d=1; $d<=$days_in_month; $d++): 
                                $time = mktime(0,0,0,$month,$d,$year);
                                $is_weekend = (date('N', $time) >= 7); // 7 = Sunday
                            ?>
                            <th class="day-header <?= $is_weekend ? 'weekend' : '' ?>">
                                <?= $d ?><br><small><?= date('D', $time)[0] ?></small>
                            </th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff as $s): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                            <?php for($d=1; $d<=$days_in_month; $d++): 
                                $time = mktime(0,0,0,$month,$d,$year);
                                $is_weekend = (date('N', $time) >= 7);
                                $record = $attendance_data[$s['id']][$d] ?? null;
                                $is_holiday = isset($holidays[$d]);
                                $class = ''; $symbol = '';

                                if($is_holiday) {
                                    $class = 'status-H'; $symbol = 'H';
                                } elseif($record) {
                                    if(($record['status'] ?? '') === 'Late') { $class = 'status-L'; $symbol = 'L'; }
                                    else { $class = 'status-P'; $symbol = 'P'; }
                                } elseif($time < time()) {
                                    $class = $is_weekend ? 'weekend' : 'status-A';
                                    $symbol = $is_weekend ? 'W' : 'A';
                                }
                            ?>
                            <td class="<?= $class ?>" title="<?= $is_holiday ? $holidays[$d] : ($record ? 'Clock In: '.$record['clock_in'] : '') ?>">
                                <?= $symbol ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding: 1rem; display:flex; gap:20px; font-size:0.8rem; border-top: 1px solid #e2e8f0;">
                <span><span style="display:inline-block; width:12px; height:12px; vertical-align:middle; margin-right:5px;" class="status-P"></span> Present (P)</span>
                <span><span style="display:inline-block; width:12px; height:12px; vertical-align:middle; margin-right:5px;" class="status-L"></span> Late Arrival (L)</span>
                <span><span style="display:inline-block; width:12px; height:12px; vertical-align:middle; margin-right:5px;" class="status-A"></span> Absent (A)</span>
                <span><span style="display:inline-block; width:12px; height:12px; vertical-align:middle; margin-right:5px;" class="status-H"></span> Holiday (H)</span>
            </div>
        </div>
    </main>
</div>
</body>
</html>
