<?php
// /admin/attendance_report.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Fetch all staff
$staff = $pdo->prepare("SELECT id, name FROM users WHERE company_id=? AND role IN ('staff','manager') AND status='active'");
$staff->execute([$cid]);
$staff_list = $staff->fetchAll();

// Fetch attendance for the month
$stmt = $pdo->prepare("SELECT user_id, date, clock_in, clock_out FROM attendance WHERE company_id=? AND MONTH(date)=? AND YEAR(date)=?");
$stmt->execute([$cid, $month, $year]);
$records = $stmt->fetchAll();

$attendance_matrix = [];
foreach ($records as $r) {
    $day = (int)date('d', strtotime($r['date']));
    $attendance_matrix[$r['user_id']][$day] = [
        'in' => $r['clock_in'],
        'out' => $r['clock_out']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
    <style>
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        .calendar-table th, .calendar-table td {
            border: 1px solid var(--glass-border);
            padding: 8px 4px;
            text-align: center;
            min-width: 30px;
        }
        .calendar-table th { background: rgba(255,255,255,0.03); color: var(--text-muted); }
        .attendance-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
                            <th style="min-width: 150px; text-align: left; padding-left: 1rem;">Staff Name</th>
                            <?php for($d=1; $d<=$days_in_month; $d++): ?>
                                <th><?= $d ?></th>
                            <?php endfor; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff_list as $s): 
                            $present_count = 0;
                        ?>
                        <tr>
                            <td style="text-align: left; padding-left: 1rem; font-weight: 600; color: var(--text-main);">
                                <?= htmlspecialchars($s['name']) ?>
                            </td>
                            <?php for($d=1; $d<=$days_in_month; $d++): 
                                $status = 'absent';
                                if (isset($attendance_matrix[$s['id']][$d])) {
                                    $status = 'present';
                                    $present_count++;
                                }
                                $is_weekend = in_array(date('N', strtotime("$year-$month-$d")), [6,7]);
                            ?>
                                <td style="<?= $is_weekend ? 'background: rgba(255,255,255,0.02);' : '' ?>">
                                    <?php if ($status === 'present'): ?>
                                        <div class="attendance-dot status-present" title="Present"></div>
                                    <?php elseif (!$is_weekend): ?>
                                        <span style="color: rgba(239, 68, 68, 0.2); font-size: 0.7rem;">A</span>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                            <td style="font-weight: 700; color: var(--primary-color);"><?= $present_count ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 2rem; padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="attendance-dot status-present"></div> Present
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="color: rgba(239, 68, 68, 0.4); font-size: 0.75rem; font-weight: 700;">A</span> Absent
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:20px; height:20px; background:rgba(255,255,255,0.02); border: 1px solid var(--glass-border);"></div> Weekend
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
