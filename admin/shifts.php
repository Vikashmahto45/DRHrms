<?php
// /admin/shifts.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];

$msg = ''; $msgType = '';
if ($_SESSION['flash_msg'] ?? null) {
    $msg = $_SESSION['flash_msg']; $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name']);
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        
        if ($name && $start && $end) {
            $stmt = $pdo->prepare("INSERT INTO shifts (company_id, name, start_time, end_time) VALUES (?,?,?,?)");
            $stmt->execute([$cid, $name, $start, $end]);
            logActivity('shift_created', "Created shift: $name ($start - $end)", $cid);
            $_SESSION['flash_msg'] = "Shift created successfully!";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: shifts.php"); exit();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['shift_id'];
        $pdo->prepare("DELETE FROM shifts WHERE id=? AND company_id=?")->execute([$id, $cid]);
        logActivity('shift_deleted', "Deleted shift ID: $id", $cid);
        $_SESSION['flash_msg'] = "Shift deleted.";
        $_SESSION['flash_type'] = "success";
        header("Location: shifts.php"); exit();
    }
}

$shifts = $pdo->prepare("SELECT * FROM shifts WHERE company_id=? ORDER BY created_at DESC");
$shifts->execute([$cid]);
$shifts = $shifts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift Management - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        
        <div class="page-header">
            <div>
                <h1>Shift Management</h1>
                <p style="color:var(--text-muted)">Define work timings for your staff.</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">+ Create Shift</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 2rem;"><?= $msg ?></div>
        <?php endif; ?>

        <div class="content-card">
            <table class="table">
                <thead><tr><th>Shift Name</th><th>Timings</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($s['name']) ?></td>
                        <td>
                            <span class="badge" style="background:rgba(99,102,241,0.1); color:#6366f1;">
                                <?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size: 0.85rem;"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this shift?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                <button class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($shifts)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:3rem; color:var(--text-muted);">No shifts defined yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="max-width:450px">
        <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        <h3>Create New Shift</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Shift Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Morning Shift" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Save Shift</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
