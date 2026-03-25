<?php
// /admin/add_income.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date   = $_POST['income_date'] ?? date('Y-m-d');
    $cat_id = (int)($_POST['category_id'] ?? 0) ?: null;
    $desc   = trim($_POST['description'] ?? '');

    if ($title && $amount > 0) {
        try {
            $pdo->prepare("INSERT INTO incomes (company_id, category_id, title, amount, income_date, description, created_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$cid, $cat_id, $title, $amount, $date, $desc, $_SESSION['user_id']]);
            $msg = "Income entry '{$title}' added successfully!"; $msgType = 'success';
        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage(); $msgType = 'error';
        }
    } else {
        $msg = "Title and a valid amount are required."; $msgType = 'error';
    }
}

$cats = $pdo->prepare("SELECT * FROM income_categories WHERE company_id = ? ORDER BY name");
$cats->execute([$cid]); $cats = $cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Income - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439731">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439731">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div><h1>Add Income Entry</h1><p style="color:var(--text-muted)">Record a new income transaction for the HQ.</p></div>
        <a href="income_list.php" class="btn btn-outline">View All Income →</a>
    </div>

    <div class="content-card" style="max-width:600px;">
        <form method="POST">
            <div class="form-group">
                <label>Income Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Client Payment — January" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label>Amount (₹) *</label>
                    <input type="number" name="amount" class="form-control" required step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Income Date *</label>
                    <input type="date" name="income_date" class="form-control" required value="<?= htmlspecialchars($_POST['income_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">— No Category —</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_POST['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!count($cats)): ?>
                    <small style="color:var(--text-muted)">No categories yet. <a href="income_categories.php" style="color:var(--primary-color)">Create one →</a></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Description / Notes</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Optional notes about this income..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">💰 Save Income Entry</button>
        </form>
    </div>
</main>
</body>
</html>
