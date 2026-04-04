<?php
// /admin/add_expense.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person']);

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

$uid = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'staff';

// Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id = (int)$_POST['category_id'];
    $amount = (float)$_POST['amount'];
    $date = $_POST['expense_date'];
    $desc = trim($_POST['description'] ?? '');
    
    // Auto-approve expenses added by Admins or Managers
    $status = ($role === 'sales_person') ? 'pending' : 'approved';

    if ($cat_id > 0 && $amount > 0 && $date) {
        $stmt = $pdo->prepare("INSERT INTO expenses (company_id, user_id, category_id, amount, expense_date, description, status) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$cid, $uid, $cat_id, $amount, $date, $desc, $status]);
        
        $msg = ($status === 'approved') ? "Expense recorded and approved successfully!" : "Expense recorded and is pending admin approval.";
        logActivity('expense_added', $msg, $cid);
        $msgType = "success";
    } else {
        $msg = "Please fill all required fields correctly.";
        $msgType = "error";
    }
}

// Fetch Categories for Dropdown
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime('../assets/css/admin.css') ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <h1>Record New Expense</h1>
    </div>

    <div class="content-card" style="max-width:600px; margin: 0 auto;">
        <div class="card-header"><h4>Expenditure Details</h4></div>
        <form method="POST">
            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if(!count($categories)): ?>
                    <small style="color:#ef4444">No categories found. <a href="expense_categories.php" style="color:var(--primary-color)">Create one first.</a></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Amount (₹) *</label>
                <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Expense Date *</label>
                <input type="date" name="expense_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Description / Note</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Additional details..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:1rem;">Save Expense Record</button>
        </form>
    </div>
</main>
</body>
</html>
