<?php
// /admin/expense_list.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess(['admin', 'manager', 'sales_person']);

$cid = $_SESSION['company_id'];

$role = $_SESSION['user_role'];
$uid = $_SESSION['user_id'];

// Handle Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $role !== 'sales_person') {
    $expense_id = (int)$_POST['expense_id'];
    $action = $_POST['action'];
    if ($action === 'approve') {
        $pdo->prepare("UPDATE expenses SET status='approved' WHERE id=? AND company_id=?")->execute([$expense_id, $cid]);
        logActivity('expense_approved', "Approved expense ID: $expense_id", $cid);
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE expenses SET status='rejected' WHERE id=? AND company_id=?")->execute([$expense_id, $cid]);
        logActivity('expense_rejected', "Rejected expense ID: $expense_id", $cid);
    }
    header("Location: expense_list.php"); exit();
}

// Filters
$cat_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$query = "
    SELECT e.*, c.name as category_name, u.name as user_name 
    FROM expenses e 
    JOIN expense_categories c ON e.category_id = c.id 
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.company_id = ?
";
$params = [$cid];

if ($role === 'sales_person') {
    $query .= " AND e.user_id = ?";
    $params[] = $uid;
}

if ($cat_filter > 0) {
    $query .= " AND e.category_id = ?";
    $params[] = $cat_filter;
}

$query .= " ORDER BY e.expense_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Categories for filter
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense List - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>Expense List</h1>
            <p style="color:var(--text-muted)">Track and audit all your business expenditures.</p>
        </div>
        <a href="add_expense.php" class="btn btn-primary">+ Record Expense</a>
    </div>

    <!-- Filters -->
    <div class="content-card" style="margin-bottom:1.5rem; padding: 1rem;">
        <form method="GET" style="display:flex; gap: 1rem; align-items:flex-end;">
            <div style="flex:1;">
                <label style="font-size:0.85rem; color:var(--text-muted)">Filter by Category</label>
                <select name="category" class="form-control" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat_filter===$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="expense_list.php" class="btn btn-outline">Clear</a>
        </form>
    </div>

    <div class="content-card">
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Category</th><th>Description</th><th>Added By</th><th>Status</th><th>Amount</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php $total = 0; foreach($expenses as $e): 
                        if (($e['status'] ?? 'approved') === 'approved') $total += $e['amount']; 
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= date('M d, Y', strtotime($e['expense_date'])) ?></td>
                        <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?= htmlspecialchars($e['category_name']) ?></span></td>
                        <td style="color:var(--text-muted); font-size:0.9rem; max-width:250px;"><?= htmlspecialchars($e['description']) ?></td>
                        <td><?= htmlspecialchars($e['user_name'] ?? 'System/Unknown') ?></td>
                        <td>
                            <?php $st = $e['status'] ?? 'approved'; ?>
                            <?php if($st==='approved'): ?>
                                <span style="background:rgba(16,185,129,0.1);color:#10b981;padding:2px 6px;border-radius:4px;font-size:0.75rem;font-weight:600;">Approved</span>
                            <?php elseif($st==='rejected'): ?>
                                <span style="background:rgba(239,68,68,0.1);color:#ef4444;padding:2px 6px;border-radius:4px;font-size:0.75rem;font-weight:600;">Rejected</span>
                            <?php else: ?>
                                <span style="background:rgba(245,158,11,0.1);color:#f59e0b;padding:2px 6px;border-radius:4px;font-size:0.75rem;font-weight:600;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700; color:#ef4444;">₹<?= number_format($e['amount'], 2) ?></td>
                        <td>
                            <?php if($role !== 'sales_person' && ($e['status'] ?? 'pending') === 'pending'): ?>
                            <form method="POST" style="display:inline;margin:0;">
                                <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-primary" style="padding:0.2rem 0.5rem;font-size:0.75rem;">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline" style="padding:0.2rem 0.5rem;font-size:0.75rem;color:#ef4444;border-color:#ef4444;margin-left:4px;">Reject</button>
                            </form>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.85rem">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!count($expenses)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted)">No expense records found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if(count($expenses)): ?>
                <tfoot>
                    <tr style="background:#f8fafc;">
                        <td colspan="5" style="text-align:right; font-weight:700; font-size:0.9rem; color:var(--text-muted)">Total Approved:</td>
                        <td style="font-weight:800; color:#ef4444; font-size:1.1rem;">₹<?= number_format($total, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>
</body>
</html>
