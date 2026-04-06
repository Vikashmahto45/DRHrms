<?php
// /admin/attendance.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Fetch attendance for the specific date
$stmt = $pdo->prepare("
    SELECT a.*, u.name, u.role
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.company_id = ? AND a.date = ?
    ORDER BY u.name ASC
");
$stmt->execute([$cid, $date_filter]);
$attendance = $stmt->fetchAll();

// Get total staff count to calculate presence %
$total_staff = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id=? AND role IN ('staff','manager') AND status='active'");
$total_staff->execute([$cid]); $total_staff = $total_staff->fetchColumn();
$present = count($attendance);
$absent = max(0, $total_staff - $present);

// Handle Manual Attendance Correction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_manual') {
    $att_id = (int)$_POST['attendance_id'];
    $clock_in = $_POST['clock_in'];
    $clock_out = !empty($_POST['clock_out']) ? $_POST['clock_out'] : null;
    $status = $_POST['status'];
    $uid_admin = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE attendance SET clock_in = ?, clock_out = ?, status = ?, is_manual = 1, updated_by = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$clock_in, $clock_out, $status, $uid_admin, $att_id, $cid]);
    header("Location: attendance.php?date=$date_filter&msg=Record updated manually"); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Attendance - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Daily Attendance</h1>
            <p style="color:var(--text-muted)">Track employee clock-ins and clock-outs.</p>
        </div>
        <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>" onchange="this.form.submit()" style="width:200px;">
        </form>
    </div>

    <!-- Quick Stats -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:2rem;">
        <div class="content-card" style="margin-bottom:0;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:var(--text-main)"><?= $total_staff ?></div>
            <div style="color:var(--text-muted);font-size:.9rem">Active Staff</div>
        </div>
        <div class="content-card" style="margin-bottom:0;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#10b981"><?= $present ?></div>
            <div style="color:var(--text-muted);font-size:.9rem">Present</div>
        </div>
        <div class="content-card" style="margin-bottom:0;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#ef4444"><?= $absent ?></div>
            <div style="color:var(--text-muted);font-size:.9rem">Absent</div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h2>Attendance Record: <?= date('M d, Y', strtotime($date_filter)) ?></h2></div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Role</th><th>Clock In</th><th>Clock Out</th><th>Duration</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($attendance as $a): 
                        $in = strtotime($a['clock_in']);
                        $out = $a['clock_out'] ? strtotime($a['clock_out']) : null;
                        $hours = $out ? round(($out - $in)/3600, 1) . ' hrs' : '—';
                    ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
                        <td><?= ucfirst($a['role']) ?></td>
                        <td style="color:#10b981;font-weight:600"><?= $a['clock_in'] ? date('h:i A', strtotime($a['clock_in'])) : '---' ?></td>
                        <td style="color:#ef4444;font-weight:600"><?= $out ? date('h:i A', $out) : '---' ?></td>
                        <td><?= $hours ?></td>
                        <td>
                            <span class="badge" style="background:rgba(99,102,241,0.1); color:#6366f1;font-size:0.75rem;font-weight:700;">
                                <?= htmlspecialchars($a['status'] ?: 'Present') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= htmlspecialchars(json_encode($a)) ?>)">✎ Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($attendance)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem">No attendance entries found for this date.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:400px;">
        <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Manual Edit: <span id="staff-name"></span></h3>
        <p style="color:var(--text-muted); font-size:0.85rem;">Correct clock timings for this record.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_manual">
            <input type="hidden" name="attendance_id" id="edit-att-id">
            <div class="form-group">
                <label>Clock In Time</label>
                <input type="datetime-local" name="clock_in" id="edit-in" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Clock Out Time</label>
                <input type="datetime-local" name="clock_out" id="edit-out" class="form-control">
            </div>
            <div class="form-group">
                <label>Attendance Status</label>
                <select name="status" id="edit-status" class="form-control">
                    <option value="Present">Present</option>
                    <option value="Late">Late Arrival</option>
                    <option value="Half-Day">Half-Day</option>
                    <option value="On-Leave">On-Leave</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Save Correction</button>
        </form>
    </div>
</div>

<script>
function openEditModal(record) {
    document.getElementById('staff-name').innerText = record.name;
    document.getElementById('edit-att-id').value = record.id;
    
    // Format dates for datetime-local input
    if(record.clock_in) {
        document.getElementById('edit-in').value = record.clock_in.replace(' ', 'T').substring(0, 16);
    }
    if(record.clock_out) {
        document.getElementById('edit-out').value = record.clock_out.replace(' ', 'T').substring(0, 16);
    } else {
        document.getElementById('edit-out').value = '';
    }
    document.getElementById('edit-status').value = record.status || 'Present';
    document.getElementById('editModal').classList.add('open');
}
</script>
</body>
</html>
