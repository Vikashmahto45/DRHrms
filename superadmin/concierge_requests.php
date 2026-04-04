<?php
// /superadmin/concierge_requests.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('super_admin');

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM demo_requests WHERE id = ?")->execute([$id]);
    header("Location: concierge_requests.php?msg=deleted");
    exit();
}

$stmt = $pdo->query("SELECT * FROM demo_requests ORDER BY status ASC, created_at DESC");
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Concierge Requests - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(\"../assets/css/style.css\") ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(\"../assets/css/admin.css\") ?>">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <h1>All Concierge Requests</h1>
            <p style="color:var(--text-muted)">Manage and process all incoming setup/demo requests.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="flash-success">Request deleted successfully.</div>
    <?php endif; ?>

    <div class="content-card">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Company/Agency</th>
                        <th>Contact Info</th>
                        <th>Date Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td>
                                    <?php if($r['status'] === 'pending'): ?>
                                        <span class="badge" style="background:#fef3c7;color:#d97706;">Pending</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#d1fae5;color:#059669;">Processed</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['company_name']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($r['email']) ?></div>
                                    <div style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($r['phone']) ?></div>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></td>
                                <td style="display:flex; gap:8px;">
                                    <?php if($r['status'] === 'pending'): ?>
                                        <a href="process_demo.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary" style="padding:0.3rem 0.6rem;">Process HQ</a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.85rem;">Completed</span>
                                    <?php endif; ?>
                                    <a href="concierge_requests.php?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this request permanently?')" class="btn btn-sm btn-outline" style="color:#ef4444;border-color:#ef4444;padding:0.3rem 0.6rem;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:3rem;">No requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
