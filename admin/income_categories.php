<?php
// /admin/income_categories.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO income_categories (company_id, name, description) VALUES (?,?,?)")->execute([$cid, $name, $desc]);
                $msg = "Category '{$name}' added!"; $msgType = 'success';
            } catch (Exception $e) { $msg = "Error: " . $e->getMessage(); $msgType = 'error'; }
        } else { $msg = "Category name is required."; $msgType = 'error'; }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['cat_id'] ?? 0);
        $pdo->prepare("DELETE FROM income_categories WHERE id = ? AND company_id = ?")->execute([$id, $cid]);
        $msg = "Category deleted."; $msgType = 'warning';
    }
}

$cats = $pdo->prepare("SELECT * FROM income_categories WHERE company_id = ? ORDER BY name");
$cats->execute([$cid]); $cats = $cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Income Categories - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div><h1>Income Categories</h1><p style="color:var(--text-muted)">Manage categories for your HQ income entries.</p></div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Category</button>
    </div>

    <div class="content-card">
        <table class="table">
            <thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($cats as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($c['description'] ?: '—') ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!count($cats)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:2rem;">No categories yet. Add your first one!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width:450px;">
        <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">&times;</button>
        <h3>Add Income Category</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Category Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Consulting Fees"></div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Optional description..."></textarea></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Save Category</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
