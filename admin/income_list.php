<?php
// /admin/income_list.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['income_id'] ?? 0);
    $pdo->prepare("DELETE FROM incomes WHERE id = ? AND company_id = ?")->execute([$id, $cid]);
    $msg = "Income entry deleted."; $msgType = 'warning';
}

// Totals
$total = $pdo->prepare("SELECT SUM(amount) FROM incomes WHERE company_id = ?");
$total->execute([$cid]); $total_income = $total->fetchColumn() ?? 0;

$this_month = $pdo->prepare("SELECT SUM(amount) FROM incomes WHERE company_id = ? AND MONTH(income_date) = MONTH(CURDATE()) AND YEAR(income_date) = YEAR(CURDATE())");
$this_month->execute([$cid]); $month_income = $this_month->fetchColumn() ?? 0;

// All income
$incomes = $pdo->prepare("
    SELECT i.*, ic.name AS category_name 
    FROM incomes i 
    LEFT JOIN income_categories ic ON i.category_id = ic.id 
    WHERE i.company_id = ? 
    ORDER BY i.income_date DESC
");
$incomes->execute([$cid]); $incomes = $incomes->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income List - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div><h1>Income List</h1><p style="color:var(--text-muted)">All recorded income entries for the HQ.</p></div>
        <a href="add_income.php" class="btn btn-primary">+ Add Income</a>
    </div>

    <!-- Summary Cards -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;margin-bottom:2rem;">
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">💰</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">Total Income</div>
                <div style="font-size:1.6rem;font-weight:800;color:#10b981;">₹<?= number_format($total_income, 2) ?></div>
            </div>
        </div>
        <div class="content-card" style="margin-bottom:0;display:flex;align-items:center;gap:1rem;">
            <div style="width:48px;height:48px;border-radius:10px;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">📅</div>
            <div>
                <div style="color:var(--text-muted);font-size:.85rem;">This Month</div>
                <div style="font-size:1.6rem;font-weight:800;color:#6366f1;">₹<?= number_format($month_income, 2) ?></div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header"><h2>All Entries (<?= count($incomes) ?>)</h2></div>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Date</th><th>Notes</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($incomes as $i): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($i['title']) ?></strong></td>
                        <td>
                            <?php if ($i['category_name']): ?>
                                <span class="badge" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;"><?= htmlspecialchars($i['category_name']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;color:#10b981;">₹<?= number_format($i['amount'], 2) ?></td>
                        <td style="color:var(--text-muted)"><?= date('d M Y', strtotime($i['income_date'])) ?></td>
                        <td style="color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($i['description'] ?: '—') ?></td>
                        <td>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="income_id" value="<?= $i['id'] ?>">
                                <button class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($incomes)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:3rem;">No income entries yet. <a href="add_income.php" style="color:var(--primary-color);">Add your first one →</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
