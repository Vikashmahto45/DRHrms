<?php
// /login.php — Unified login portal
// HQ (no slug or ?company=hq): shows Super Admin tab + HQ Staff tab
// Other branches (?company=SLUG): shows only their own company login
session_start();
require_once 'config/database.php';

$error = '';
$sa_error = '';
$company_info = null;
$company_slug = trim($_GET['company'] ?? '');

// Determine if this is an HQ visit (no slug, or ?company=hq)
$is_hq_page = ($company_slug === '' || $company_slug === 'hq');

// Set active tab
$active_tab = $_GET['tab'] ?? 'user';
if (isset($_POST['sa_submit'])) $active_tab = 'superadmin';
if (isset($_POST['user_submit'])) $active_tab = 'user';

// Load company info when a non-HQ slug is given
if ($company_slug && $company_slug !== 'hq') {
    $stmt = $pdo->prepare("SELECT id, name, status FROM companies WHERE login_slug = ?");
    $stmt->execute([$company_slug]);
    $company_info = $stmt->fetch();

    if (!$company_info) {
        die("<div style='padding:5rem;text-align:center;font-family:sans-serif;'><h2>❌ Invalid Login Link</h2><p>This link is invalid or has been removed.</p><a href='<?= BASE_URL ?>login.php'>Back to main login</a></div>");
    }
    if ($company_info['status'] !== 'active') {
        die("<div style='padding:5rem;text-align:center;font-family:sans-serif;'><h2>🚫 Account Suspended</h2><p>This account has been deactivated.</p></div>");
    }
}

// For HQ page, load HQ company info for context
if ($is_hq_page) {
    $stmt = $pdo->prepare("SELECT name FROM companies WHERE login_slug = 'hq' OR is_main_branch = 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $hq_name = $stmt->fetchColumn() ?: 'Headquarters';
}

// ============================================================
// HANDLE: Super Admin Login
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sa_submit'])) {
    $email    = $_POST['sa_email'] ?? '';
    $password = $_POST['sa_password'] ?? '';

    if (!$email || !$password) {
        $sa_error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password, role, status FROM users WHERE email = ? AND role = 'super_admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $sa_error = "Your account has been deactivated.";
            } else {
                $_SESSION['sa_user_id']   = $user['id'];
                $_SESSION['sa_user_name'] = $user['name'];
                $_SESSION['sa_user_role'] = 'super_admin';
                header("Location: " . BASE_URL . "superadmin/dashboard.php");
                exit();
            }
        } else {
            $sa_error = "Invalid Super Admin credentials.";
        }
    }
}

// ============================================================
// HANDLE: Company / HQ User Login
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_submit'])) {
    $email      = $_POST['email'] ?? '';
    $password   = $_POST['password'] ?? '';
    $post_slug  = trim($_POST['company_slug'] ?? ''); // '' means HQ (any company)

    if (!$email || !$password) {
        $error = "Please enter both email and password.";
    } else {
        if ($post_slug) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.company_id, u.name, u.password, u.role, u.status
                FROM users u JOIN companies c ON u.company_id = c.id
                WHERE u.email = ? AND c.login_slug = ?
            ");
            $stmt->execute([$email, $post_slug]);
        } else {
            // HQ page with no slug — match any HQ/main branch user
            $stmt = $pdo->prepare("
                SELECT u.id, u.company_id, u.name, u.password, u.role, u.status
                FROM users u JOIN companies c ON u.company_id = c.id
                WHERE u.email = ? AND (c.login_slug = 'hq' OR c.is_main_branch = 1)
                ORDER BY c.id ASC LIMIT 1
            ");
            $stmt->execute([$email]);
        }
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account has been deactivated.";
            } elseif ($user['role'] === 'super_admin') {
                $error = "Super Admins must use the Super Admin tab above.";
            } else {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = strtolower($user['role']);
                
                if ($user['role'] === 'sales_person') {
                    header("Location: " . BASE_URL . "admin/sales_dashboard.php");
                } elseif ($user['role'] === 'staff') {
                    header("Location: " . BASE_URL . "admin/staff_dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "admin/dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $company_info ? htmlspecialchars($company_info['name']) . ' — Login' : 'DRHrms Login' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;
     background:linear-gradient(135deg,#f0f4ff 0%,#faf5ff 50%,#f0fff4 100%);}
.wrap{width:100%;max-width:430px}
/* Brand */
.brand{text-align:center;margin-bottom:1.5rem}
.logo{font-size:2rem;font-weight:800;color:#6366f1;letter-spacing:-1px}
.logo span{color:#0f172a}
.badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(139,92,246,.1));
       border:1px solid rgba(99,102,241,.2);border-radius:30px;padding:5px 14px;font-size:.82rem;font-weight:600;color:#6366f1;margin-top:.6rem}
/* Card */
.card{background:#fff;border-radius:20px;padding:2rem 2.5rem;
      box-shadow:0 20px 60px rgba(0,0,0,.08),0 1px 3px rgba(0,0,0,.04);border:1px solid rgba(255,255,255,.8)}
/* Tabs */
.tabs{display:flex;background:#f1f5f9;border-radius:12px;padding:4px;margin-bottom:1.75rem;gap:4px}
.tab-btn{flex:1;border:none;background:none;padding:.6rem .75rem;border-radius:9px;
         font-size:.83rem;font-weight:600;cursor:pointer;transition:all .25s;color:#64748b}
.tab-btn.active{background:#fff;color:#6366f1;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.tab-btn.sa.active{color:#ec4899}
.tab-pane{display:none}.tab-pane.active{display:block}
/* Form */
.title{font-size:1.35rem;font-weight:800;color:#0f172a;margin-bottom:.25rem}
.sub{color:#64748b;font-size:.83rem;margin-bottom:1.25rem}
.fg{margin-bottom:.9rem}
label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.35rem}
input[type=email],input[type=password]{width:100%;padding:.72rem 1rem;border:1.5px solid #e5e7eb;border-radius:10px;
  font-size:.9rem;font-family:inherit;transition:border-color .2s,box-shadow .2s;outline:none;background:#fafafa}
input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.12);background:#fff}
.pw{position:relative}.pw input{padding-right:40px}
.eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;
     cursor:pointer;font-size:.95rem;color:#9ca3af;padding:0}
.btn{width:100%;padding:.82rem;border:none;border-radius:10px;font-size:.92rem;font-weight:700;
     cursor:pointer;transition:all .2s;margin-top:.5rem}
.btn-u{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-sa{background:linear-gradient(135deg,#ec4899,#db2777);color:#fff}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(99,102,241,.3)}
.btn-sa:hover{box-shadow:0 8px 20px rgba(236,72,153,.3)}
.err{background:#fef2f2;border:1px solid #fecaca;color:#ef4444;border-radius:10px;
     padding:.65rem 1rem;font-size:.83rem;margin-bottom:.9rem}
.hint{text-align:center;font-size:.76rem;color:#94a3b8;margin-top:1rem}
.hint a{color:#6366f1;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="logo">DR<span>Hrms</span></div>
    <?php if ($company_info): ?>
      <div class="badge">🏢 <?= htmlspecialchars($company_info['name']) ?></div>
    <?php elseif ($is_hq_page): ?>
      <div class="badge">🏛️ <?= htmlspecialchars($hq_name) ?> — Main Branch</div>
    <?php endif; ?>
  </div>

  <div class="card">

    <?php if ($is_hq_page): ?>
    <!-- ====== HQ PAGE: 2 tabs (HQ Staff + Super Admin) ====== -->
    <div class="tabs">
      <button type="button" class="tab-btn <?= $active_tab==='user'?'active':'' ?>" onclick="sw('user')">🏬 HQ Staff Login</button>
      <button type="button" class="tab-btn sa <?= $active_tab==='superadmin'?'active':'' ?>" onclick="sw('superadmin')">🛡️ Super Admin</button>
    </div>

    <!-- HQ Tab -->
    <div class="tab-pane <?= $active_tab==='user'?'active':'' ?>" id="tab-user">
      <div class="title">HQ Staff Login</div>
      <div class="sub">Sign in to the Main Branch dashboard</div>
      <?php if ($error): ?><div class="err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="user_submit" value="1">
        <!-- No company_slug hidden field — auto-detects HQ -->
        <div class="fg"><label>Email</label><input type="email" name="email" required placeholder="you@hq.com" value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
        <div class="fg"><label>Password</label><div class="pw"><input type="password" name="password" id="up" required placeholder="••••••••"><button type="button" class="eye" onclick="tp('up',this)">👁️</button></div></div>
        <button type="submit" class="btn btn-u">Sign In →</button>
      </form>
    </div>

    <!-- Super Admin Tab -->
    <div class="tab-pane <?= $active_tab==='superadmin'?'active':'' ?>" id="tab-superadmin">
      <div class="title" style="color:#ec4899">🛡️ Command Center</div>
      <div class="sub">Restricted — Super Admins only</div>
      <?php if ($sa_error): ?><div class="err">⚠️ <?= htmlspecialchars($sa_error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="sa_submit" value="1">
        <div class="fg"><label>Email</label><input type="email" name="sa_email" required placeholder="superadmin@drhrms.com" value="<?= htmlspecialchars($_POST['sa_email']??'') ?>"></div>
        <div class="fg"><label>Password</label><div class="pw"><input type="password" name="sa_password" id="sp" required placeholder="••••••••"><button type="button" class="eye" style="color:#ec4899" onclick="tp('sp',this)">👁️</button></div></div>
        <button type="submit" class="btn btn-sa">Enter Command Center →</button>
      </form>
    </div>

    <?php else: ?>
    <!-- ====== SUB-BRANCH PAGE: Single company login only ====== -->
    <div class="title">Welcome back</div>
    <div class="sub">Sign in to your <strong><?= htmlspecialchars($company_info['name']) ?></strong> dashboard</div>
    <?php if ($error): ?><div class="err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="user_submit" value="1">
      <input type="hidden" name="company_slug" value="<?= htmlspecialchars($company_slug) ?>">
      <div class="fg"><label>Email</label><input type="email" name="email" required placeholder="you@company.com" value="<?= htmlspecialchars($_POST['email']??'') ?>"></div>
      <div class="fg"><label>Password</label><div class="pw"><input type="password" name="password" id="bp" required placeholder="••••••••"><button type="button" class="eye" onclick="tp('bp',this)">👁️</button></div></div>
      <button type="submit" class="btn btn-u">Sign In →</button>
    </form>
    <?php endif; ?>

  </div><!-- /.card -->

  <div class="hint">DRHrms Platform — All access is monitored and logged.</div>
</div>

<script>
function sw(tab){
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+tab).classList.add('active');
  document.querySelector('[onclick*="'+tab+'"]').classList.add('active');
}
function tp(id,btn){
  const f=document.getElementById(id);
  f.type=f.type==='password'?'text':'password';
  btn.textContent=f.type==='password'?'👁️':'🔒';
}
</script>
</body>
</html>
