<?php
// /admin/settings_designations.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO designations (company_id, title) VALUES (?, ?)");
            if ($stmt->execute([$cid, $title])) {
                $msg = "Designation created successfully."; $msgType = "success";
            } else {
                $msg = "Failed to create designation."; $msgType = "error";
            }
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $stmt = $pdo->prepare("UPDATE designations SET title = ? WHERE id = ? AND company_id = ?");
            if ($stmt->execute([$title, $id, $cid])) {
                $msg = "Designation updated successfully."; $msgType = "success";
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Ensure no users are currently using this designation
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE designation_id = ? AND company_id = ?");
        $check->execute([$id, $cid]);
        if ($check->fetchColumn() > 0) {
            $msg = "Cannot delete: Staff members are currently assigned to this designation."; $msgType = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM designations WHERE id = ? AND company_id = ?");
            if ($stmt->execute([$id, $cid])) {
                $msg = "Designation deleted."; $msgType = "success";
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM designations WHERE company_id = ? ORDER BY title ASC");
$stmt->execute([$cid]);
$designations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Designations</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Manage Designations</h1>
            <p style="color:var(--text-muted)">Create custom job titles and tags for your staff members.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Designation</button>
    </div>

    <div class="content-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Job Title / Designation</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($designations as $d): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['title']) ?></strong></td>
                    <td style="text-align:right">
                        <button class="btn btn-outline btn-sm" onclick="editD(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['title'])) ?>')">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#ef4444;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($designations)): ?>
                <tr>
                    <td colspan="2" style="text-align:center; padding:2rem; color:var(--text-muted)">No custom designations created yet. Defaults will be generic.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width: 400px;">
        <button type="button" class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Add Designation</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group" style="margin-top:1.5rem;">
                <label class="form-label">Designation Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Senior iOS Developer" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Save Designation</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width: 400px;">
        <button type="button" class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Edit Designation</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group" style="margin-top:1.5rem;">
                <label class="form-label">Designation Title</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Update Designation</button>
        </form>
    </div>
</div>

<script>
function editD(id, title) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('editModal').classList.add('open');
}
</script>
</body>
</html>
