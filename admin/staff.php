<?php
// /admin/staff.php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$cid = $_SESSION['company_id'];
$msg = ''; $msgType = '';

// Fetch Limits & Usage
$company = $pdo->prepare("SELECT user_limit FROM companies WHERE id = ?");
$company->execute([$cid]);
$c_data = $company->fetch();
$user_limit = $c_data['user_limit'] ?? 10;

$usage_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ? AND role IN ('staff','manager','sales_person')");
$usage_stmt->execute([$cid]);
$current_usage = $usage_stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'staff';
        $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
        $pass  = $_POST['password'] ?? '';

        if ($name && $email && $pass) {
            if ($current_usage >= $user_limit) {
                $msg = "User limit reached ({$user_limit}). Upgrade your plan to add more staff."; $msgType = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // 1. Create Core User
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (company_id,name,email,password,role,designation_id,status) VALUES (?,?,?,?,?,?,'active')");
                    $stmt->execute([$cid, $name, $email, $hash, $role, $designation_id]);
                    $new_user_id = $pdo->lastInsertId();

                    // 2. Create Employee Details
                    $father = trim($_POST['father_name'] ?? '');
                    $joinedAt = $_POST['joining_date'] ?? date('Y-m-d');
                    $degree = trim($_POST['degree'] ?? '');
                    $p_addr = trim($_POST['permanent_address'] ?? '');
                    $c_addr = trim($_POST['current_address'] ?? '');
                    $bank   = trim($_POST['bank_name'] ?? '');
                    $acc_no = trim($_POST['account_number'] ?? '');
                    $ifsc   = trim($_POST['ifsc_code'] ?? '');

                    $stmt = $pdo->prepare("INSERT INTO employee_details 
                        (user_id, company_id, father_name, joining_date, degree, permanent_address, current_address, bank_name, account_number, ifsc_code) 
                        VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$new_user_id, $cid, $father, $joinedAt, $degree, $p_addr, $c_addr, $bank, $acc_no, $ifsc]);

                    $pdo->commit();
                    logActivity('staff_created_with_profile', "Added staff & profile: $name ($email)", $cid);
                    $msg = "Staff '{$name}' & Profile created successfully!"; $msgType = 'success';
                    $current_usage++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error: " . $e->getMessage(); $msgType = 'error';
                }
            }
        } else {
            $msg = "All fields are required."; $msgType = 'error';
        }
    }

    if ($action === 'update') {
        $id    = (int)$_POST['user_id'];
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'staff';
        $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
        $status = $_POST['status'] ?? 'active';

        if ($name && $email) {
            $pdo->prepare("UPDATE users SET name=?, email=?, role=?, designation_id=?, status=? WHERE id=? AND company_id=?")
                ->execute([$name, $email, $role, $designation_id, $status, $id, $cid]);
            logActivity('staff_updated', "Updated staff: $name ($email)", $cid);
            $msg = "Staff details updated!"; $msgType = 'success';
        }
    }

    if ($action === 'change_password') {
        $id = (int)$_POST['user_id'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (strlen($new_pass) < 6) {
            $msg = "Password must be at least 6 characters."; $msgType = 'error';
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "Passwords do not match."; $msgType = 'error';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND company_id = ?")->execute([$hash, $id, $cid]);
            logActivity('staff_password_changed', "Changed password for user ID: $id", $cid);
            $msg = "Password updated successfully."; $msgType = 'success';
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $new = $_POST['new_status'];
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND company_id=?")->execute([$new, $id, $cid]);
        logActivity('staff_status_toggle', "Staff ID: $id status changed to $new", $cid);
        header("Location: staff.php"); exit();
    }

    if ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM users WHERE id=? AND company_id=? AND role!='admin'")->execute([$id, $cid]);
        logActivity('staff_deleted', "Deleted staff member ID: $id", $cid);
        header("Location: staff.php"); exit();
    }
}

// Fetch custom designations
$desig_stmt = $pdo->prepare("SELECT id, title FROM designations WHERE company_id = ? ORDER BY title ASC");
$desig_stmt->execute([$cid]);
$designations_list = $desig_stmt->fetchAll();

$staff_list = $pdo->prepare("
    SELECT u.*, d.title as designation_name 
    FROM users u 
    LEFT JOIN designations d ON u.designation_id = d.id 
    WHERE u.company_id=? AND u.role IN ('staff','manager','sales_person') 
    ORDER BY u.created_at DESC
");
$staff_list->execute([$cid]);
$staff_list = $staff_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - DRHrms</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=1774440084">
    <link rel="stylesheet" href="../assets/css/admin.css?v=1774440084">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-content">
    <?php if ($msg): ?><div class="flash-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Manage Staff</h1>
            <p style="color:var(--text-muted)">Usage: <strong><?= $current_usage ?> / <?= $user_limit ?></strong> Users</p>
        </div>
        <?php if ($current_usage < $user_limit): ?>
            <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('open')">+ Add Staff</button>
        <?php else: ?>
            <button class="btn btn-outline" style="border-color:#ef4444;color:#ef4444;cursor:not-allowed">Limit Reached</button>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <div class="card-header"><h2>All Staff (<?= count($staff_list) ?>)</h2></div>
        <div style="overflow-x:auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($staff_list as $s): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['email']) ?></td>
                        <td style="color:var(--text-muted);">
                            <?php if ($s['designation_name']): ?>
                                <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;"><?= htmlspecialchars($s['designation_name']) ?></span>
                                <div style="font-size:0.65rem; color:var(--text-muted); margin-top:4px;">Access: <?= ucfirst(str_replace('_',' ',$s['role'])) ?></div>
                            <?php else: ?>
                                <span class="badge badge-<?= $s['role'] ?>"><?= ucfirst(str_replace('_', ' ', $s['role'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td style="color:var(--text-muted);font-size:.9rem"><?= date('M d, Y',strtotime($s['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;gap:.5rem">
                                <a href="staff_profile.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline" style="color:var(--primary-color);border-color:var(--primary-color)">📁 Profile</a>
                                <button class="btn btn-outline btn-sm" onclick="editS(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= htmlspecialchars(addslashes($s['email'])) ?>', '<?= $s['role'] ?>', '<?= $s['status'] ?>', <?= $s['designation_id'] ?: 'null' ?>)">✏️ Edit</button>
                                <button class="btn btn-sm btn-outline" style="color:#6366f1;border-color:#6366f1;" onclick="openPasswordModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')">🔑 Pass</button>
                            </div>
                            <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $s['status']==='active'?'inactive':'active' ?>">
                                    <button class="btn btn-sm btn-outline" style="<?= $s['status']==='active'?'color:#f59e0b;border-color:#f59e0b':'' ?>"><?= $s['status']==='active'?'Suspend':'Activate' ?></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this staff member?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!count($staff_list)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem">No staff members yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="createModal">
    <div class="modal-box" style="max-width:800px;">
        <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        <h3>Add Staff / Manager</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <!-- Basic Info -->
                <div>
                    <h4 style="margin-bottom:1rem;color:var(--primary-color)">Basic Information</h4>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label>Father's Name</label>
                        <input type="text" name="father_name" class="form-control" placeholder="Mr. Smith">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="staff@example.com">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Job Title / Designation</label>
                            <select name="designation_id" class="form-control">
                                <option value="">-- Optional Custom Title --</option>
                                <?php foreach($designations_list as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">System Access Level *</label>
                            <select name="role" class="form-control" required>
                                <option value="staff">Standard Staff</option>
                                <option value="sales_person">Sales Person</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Joining Date</label>
                            <input type="date" name="joining_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Degree / Qualification</label>
                            <input type="text" name="degree" class="form-control" placeholder="MBA, B.Tech etc.">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <div style="position:relative">
                            <input type="password" id="spw" name="password" class="form-control" required minlength="6" style="padding-right:42px">
                            <span onclick="var i=document.getElementById('spw');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'👁️':'🔒';" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:1.1rem;color:var(--text-muted)">👁️</span>
                        </div>
                    </div>
                </div>

                <!-- Detailed Info -->
                <div>
                    <h4 style="margin-bottom:1rem;color:var(--primary-color)">Additional Details</h4>
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. HDFC Bank">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="form-control" placeholder="1234567890">
                        </div>
                        <div class="form-group">
                            <label>IFSC Code</label>
                            <input type="text" name="ifsc_code" class="form-control" placeholder="HDFC0001234">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Permanent Address</label>
                        <textarea name="permanent_address" class="form-control" rows="2" placeholder="Full home address"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Current Address</label>
                        <textarea name="current_address" class="form-control" rows="2" placeholder="Where they stay now"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Create Account & Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:480px;">
        <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&times;</button>
        <h3>Edit Staff Member</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" id="edit_name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" id="edit_email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Job Title / Designation</label>
                <select name="designation_id" id="edit_designation_id" class="form-control">
                    <option value="">-- Optional Custom Title --</option>
                    <?php foreach($designations_list as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">System Access Level *</label>
                <select name="role" id="edit_role" class="form-control" required>
                    <option value="staff">Standard Staff (Lowest Access)</option>
                    <option value="sales_person">Sales Person (CRM Only)</option>
                    <option value="manager">Manager (High Access)</option>
                </select>
                <small style="color:var(--text-muted);font-size:0.75rem;">Determines what pages they can view.</small>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="edit_status" name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Suspended</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box" style="max-width:400px;">
        <button class="modal-close" onclick="document.getElementById('passwordModal').classList.remove('open')">&times;</button>
        <h3>Force Change Password</h3>
        <p style="color:var(--text-muted);font-size:0.9rem;">Change password for: <strong id="pw_user_name" style="color:#0f172a;"></strong></p>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" id="pw_user_id">
            
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" style="flex:1" onclick="document.getElementById('passwordModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1">Update Password</button>
            </div>
        </form>
    </div>
</div>

<style>
.badge-sales { background: rgba(99, 102, 241, 0.1) !important; color: #818cf8 !important; border: 1px solid rgba(99, 102, 241, 0.2) !important; }
.badge-manager { background: rgba(16, 185, 129, 0.1) !important; color: #34d399 !important; border: 1px solid rgba(16, 185, 129, 0.2) !important; }
</style>

<script>
function editS(id, name, email, role, status, desig_id) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_designation_id').value = desig_id || '';
    document.getElementById('editModal').classList.add('open');
}

function openPasswordModal(id, name) {
    document.getElementById('pw_user_id').value = id;
    document.getElementById('pw_user_name').textContent = name;
    document.getElementById('passwordModal').classList.add('open');
}
</script>
</body>
</html>
