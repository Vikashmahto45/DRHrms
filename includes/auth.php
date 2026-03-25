<?php
// /includes/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Fetch accessible branch IDs for the current company
 * Main Branches see their own ID + Sub-Branch IDs. Sub-Branches see only their own.
 */
function getAccessibleBranchIds($pdo, $company_id) {
    if (!$company_id) return [0];
    
    $ids = [(int)$company_id];
    try {
        // Check if main branch
        $stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        if ($stmt->fetchColumn() == 1) {
            // Fetch children
            $childStmt = $pdo->prepare("SELECT id FROM companies WHERE parent_id = ?");
            $childStmt->execute([$company_id]);
            foreach($childStmt->fetchAll() as $row) {
                $ids[] = (int)$row['id'];
            }
        }
    } catch (Exception $e) {
        // Fallback to just own ID
    }
    return $ids;
}

/**
 * checkAccess
 * Validates user role and checks for subscription/system status.
 */
function checkAccess($requiredRole) {
    global $pdo;

    // 1. Determine Session Namespace
    $isSA = ($requiredRole === 'super_admin');
    $uid  = $isSA ? ($_SESSION['sa_user_id'] ?? null) : ($_SESSION['user_id'] ?? null);
    $role = $isSA ? ($_SESSION['sa_user_role'] ?? null) : ($_SESSION['user_role'] ?? null);

    // 2. Basic Login Check
    if (!$uid || !$role) {
        $redir = $isSA ? BASE_URL . "superadmin_login.php" : BASE_URL . "login.php";
        header("Location: $redir");
        exit();
    }

    // 3. Platform Governance (Skip for Super Admins)
    if ($role !== 'super_admin') {
        // Maintenance Mode Check
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $maintenance = $stmt->fetchColumn();
        if ($maintenance === '1') {
            die("<div style='padding:5rem;text-align:center;font-family:sans-serif;background:#0f111a;color:#fff;min-height:100vh;'>
                <h1 style='color:#ec4899'>🛠️ Maintenance Mode</h1>
                <p>We are currently updating the system. Please try again later.</p>
                </div>");
        }

        // Subscription & Status Check
        if (isset($_SESSION['company_id'])) {
            $stmt = $pdo->prepare("SELECT status, subscription_end_date FROM companies WHERE id = ?");
            $stmt->execute([$_SESSION['company_id']]);
            $company = $stmt->fetch();

            if ($company) {
                if ($company['status'] !== 'active') {
                    die("<div style='padding:5rem;text-align:center;font-family:sans-serif;background:#0f111a;color:#fff;min-height:100vh;'>
                        <h1 style='color:#ef4444'>🚫 Account Suspended</h1>
                        <p>Your company account has been deactivated. Contact support.</p>
                        </div>");
                }
                if ($company['subscription_end_date'] && strtotime($company['subscription_end_date']) < time()) {
                    die("<div style='padding:5rem;text-align:center;font-family:sans-serif;background:#0f111a;color:#fff;min-height:100vh;'>
                        <h1 style='color:#f59e0b'>⌛ Subscription Expired</h1>
                        <p>Your access has expired. Please contact your administrator to renew.</p>
                        <a href='" . BASE_URL . "logout.php' style='color:#ec4899'>Logout</a>
                        </div>");
                }
            }
        }
    }

    // 4. Role Authorization
    $currentRole = strtolower($role);
    if (is_array($requiredRole)) {
        $requiredRole = array_map('strtolower', $requiredRole);
        $allowed = in_array($currentRole, $requiredRole);
    } else {
        $allowed = ($currentRole === strtolower($requiredRole));
    }
    
    if (!$allowed) {
        // Determine logical landing page to prevent loop
        if ($role === 'super_admin') {
            $dest = BASE_URL . "superadmin/dashboard.php";
        } elseif ($role === 'sales_person') {
            $dest = BASE_URL . "admin/sales_dashboard.php";
        } elseif ($role === 'staff') {
            $dest = BASE_URL . "admin/staff_dashboard.php";
        } else {
            $dest = BASE_URL . "admin/dashboard.php";
        }

        // Only redirect if we AREN'T already on that page (prevent loop)
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== basename($dest)) {
            header("Location: $dest");
            exit();
        } else {
            // If they are on their ONLY allowed page but still failing check, something is wrong
            // Fallback to a hard stop to prevent loop
            die("Access Denied: You do not have permission to view this page.");
        }
    }
}

/**
 * isModuleEnabled
 */
function isModuleEnabled($moduleName) {
    global $pdo;
    if (($_SESSION['sa_user_role'] ?? '') === 'super_admin') return true;
    if (!isset($_SESSION['company_id'])) return false;

    $stmt = $pdo->prepare("SELECT is_enabled FROM permissions_map WHERE company_id = ? AND module_name = ?");
    $stmt->execute([$_SESSION['company_id'], $moduleName]);
    return (bool)$stmt->fetchColumn();
}

/**
 * logActivity
 */
function logActivity($action, $details, $company_id = null, $user_id = null) {
    global $pdo;
    $company_id = $company_id ?? ($_SESSION['company_id'] ?? null);
    $user_id    = $user_id    ?? ($_SESSION['sa_user_id'] ?? $_SESSION['user_id'] ?? 0);
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (company_id, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$company_id, $user_id, $action, $details, $ip]);
    } catch (Exception $e) {}
}
?>
