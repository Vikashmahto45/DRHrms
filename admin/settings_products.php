<?php
// /admin/settings_products.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO products (company_id, name, price) VALUES (?, ?, ?)");
            if ($stmt->execute([$cid, $name, $price])) {
                $msg = "Service/Product created successfully."; $msgType = "success";
            }
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        if ($name) {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ? WHERE id = ? AND company_id = ?");
            if ($stmt->execute([$name, $price, $id, $cid])) {
                $msg = "Service/Product updated successfully."; $msgType = "success";
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        // Ensure no leads are tied to this exactly via ID (if we decide to enforce it, 
        // but for now soft-delete mapping isn't strict, we just delete the listing).
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND company_id = ?");
        if ($stmt->execute([$id, $cid])) {
            $msg = "Service/Product deleted from catalog."; $msgType = "success";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$cid]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services & Products</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774439732">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774439732">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?>
        <div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Services / Product Catalog</h1>
            <p style="color:var(--text-muted)">Define what your branch sells. Leads will drop-down into these options.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Service</button>
    </div>

    <div class="content-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Base Price (₹)</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td style="color:#10b981; font-weight:600;">₹<?= number_format($p['price'], 2) ?></td>
                    <td style="text-align:right">
                        <button class="btn btn-outline btn-sm" onclick="editP(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>)">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#ef4444;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="3" style="text-align:center; padding:2rem; color:var(--text-muted)">No services cataloged yet. Agents will use free-text.</td>
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
        <h3>Add Service</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group" style="margin-top:1.5rem;">
                <label class="form-label">Service Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Advanced SEO Package" required>
            </div>
            <div class="form-group">
                <label class="form-label">Base Price (₹)</label>
                <input type="number" step="0.01" name="price" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Save Service</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width: 400px;">
        <button type="button" class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">&times;</button>
        <h3>Edit Service</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group" style="margin-top:1.5rem;">
                <label class="form-label">Service Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Base Price (₹)</label>
                <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Update Service</button>
        </form>
    </div>
</div>

<script>
function editP(id, name, price) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('editModal').classList.add('open');
}
</script>
</body>
</html>
