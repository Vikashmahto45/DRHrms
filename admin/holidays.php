<?php
// /admin/holidays.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $date = $_POST['holiday_date'];
        $name = trim($_POST['name']);
        if ($date && $name) {
            $stmt = $pdo->prepare("INSERT INTO holidays (company_id, holiday_date, name) VALUES (?,?,?)");
            $stmt->execute([$cid, $date, $name]);
            $msg = "Holiday added successfully!";
            $msgType = "success";
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM holidays WHERE id = ? AND company_id = ?")->execute([$id, $cid]);
        $msg = "Holiday removed.";
        $msgType = "success";
    }
}

$holidays = $pdo->prepare("SELECT * FROM holidays WHERE company_id = ? ORDER BY holiday_date ASC");
$holidays->execute([$cid]);
$holidays = $holidays->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management - Loom</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper" style="flex: 1; margin-left: 260px;">
    <?php include 'includes/topbar.php'; ?>
    <main class="main-content" style="margin-left: 0; width: 100%; padding: 2rem 3rem;">
        <div class="page-header">
            <div>
                <h1>Holiday Management</h1>
                <p style="color:var(--text-muted)">Mark company holidays and festivals.</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Holiday</button>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 2rem;"><?= $msg ?></div>
        <?php endif; ?>

        <div class="content-card">
            <table class="table">
                <thead><tr><th>Date</th><th>Holiday Name</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td style="font-weight:600"><?= date('M d, Y', strtotime($h['holiday_date'])) ?></td>
                        <td><?= htmlspecialchars($h['name']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this holiday?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($holidays)): ?>
                    <tr><td colspan="3" style="text-align:center; padding:3rem; color:var(--text-muted);">No holidays marked yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width:400px;">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">&times;</button>
        <h3>Mark New Holiday</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="holiday_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Holiday Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Diwali, Christmas, Sunday" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Save Holiday</button>
        </form>
    </div>
</div>
</body>
</html>
