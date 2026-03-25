<?php
// /admin/expense_categories.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person']);

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

// Add / Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            if ($name) {
                $stmt = $pdo->prepare("INSERT INTO expense_categories (company_id, name) VALUES (?, ?)");
                $stmt->execute([$cid, $name]);
                logActivity('expense_category_added', "Added category: $name", $cid);
                $msg = "Category added successfully!";
                $msgType = "success";
            }
        } elseif ($_POST['action'] === 'delete' && $_SESSION['user_role'] !== 'sales_person') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $cid]);
            logActivity('expense_category_deleted', "Deleted category ID: $id", $cid);
            $msg = "Category deleted successfully!";
            $msgType = "warning";
        }
    }
}

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Categories - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .category-item { background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Expense Categories</h1>
            <p style="color:var(--text-muted)">Define how you group your business expenditures.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ New Category</button>
    </div>

    <div class="category-grid">
        <?php foreach ($categories as $cat): ?>
        <div class="category-item">
            <span style="font-weight:600;"><?= htmlspecialchars($cat['name']) ?></span>
            <?php if ($_SESSION['user_role'] !== 'sales_person'): ?>
            <form method="POST" onsubmit="return confirm('Are you sure? This will delete all expenses in this category.')" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline" style="color:#ef4444; border-color:#ef4444;">Delete</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!count($categories)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted); background: #fff; border-radius: 12px; border: 1px dashed #ccc;">
                No categories defined. Click "+ New Category" to start.
            </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3>Add New Category</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Office Rent, Marketing, Salary">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
