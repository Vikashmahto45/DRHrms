<?php
// /admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = strtolower($_SESSION['user_role'] ?? '');
$cid = $_SESSION['company_id'] ?? 0;

// Fetch company branch type
$is_main_branch = 1; // Default fallback
if ($cid > 0) {
    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT is_main_branch FROM companies WHERE id = ?");
        $stmt->execute([$cid]);
        $is_main_branch = (int)$stmt->fetchColumn();
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo"><span>Loom</span></div>
        <div style="font-size:.7rem; color:var(--text-muted); margin-top:4px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Company Panel</div>
    </div>
    <style>
        .sidebar {
            background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 100%) !important;
            border-right: none !important;
            color: #ffffff !important;
        }
        .sidebar .logo span, 
        .sidebar .nav-item, 
        .sidebar .nav-icon,
        .sidebar div[style*="color:var(--text-muted)"] {
            color: #ffffff !important;
            opacity: 1 !important;
        }
        .nav-item {
            margin: 0.2rem 1rem !important;
            border-radius: 10px !important;
        }
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
        }
        .sidebar-footer .user-info div {
            color: #ffffff !important;
        }
        .sidebar-footer .btn-outline {
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
        }
        .sidebar-footer .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: #ffffff !important;
        }
    </style>
    <nav class="nav-menu">
        <?php 
        if ($role === 'sales_person') {
            $dash_link = BASE_URL . 'admin/sales_dashboard.php';
        } elseif ($role === 'staff') {
            $dash_link = BASE_URL . 'admin/staff_dashboard.php';
        } else {
            $dash_link = BASE_URL . 'admin/dashboard.php';
        }
        ?>
        <a href="<?= $dash_link ?>" class="nav-item <?= ($current_page==='dashboard.php' || $current_page==='sales_dashboard.php' || $current_page==='staff_dashboard.php')?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <?php if ($role === 'sales_person' || $role === 'admin'): ?>
            <a href="projects.php" class="nav-item <?= $current_page==='projects.php'?'active':'' ?>">
                <span class="nav-icon">🏗️</span> Project Tracking
            </a>
            <a href="<?= BASE_URL ?>admin/dsr.php" class="nav-item <?= $current_page==='dsr.php'?'active':'' ?>">
                <span class="nav-icon">📝</span> <?= $role === 'admin' ? 'DSR Reports' : 'Daily Report (DSR)' ?>
            </a>
            <?php if ($role === 'admin' || $role === 'manager'): ?>
                <a href="<?= BASE_URL ?>admin/sales_report.php" class="nav-item <?= $current_page==='sales_report.php'?'active':'' ?>">
                    <span class="nav-icon">📈</span> Sales Report
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($role === 'super_admin'): ?>
            <a href="payment_verifications.php" class="nav-item <?= $current_page==='payment_verifications.php'?'active':'' ?>">
                <span class="nav-icon">💳</span> Verify Branch Sales
            </a>
            <a href="../admin/projects.php" class="nav-item <?= $current_page==='projects.php'?'active':'' ?>">
                <span class="nav-icon">🏗️</span> Global Projects
            </a>
            <a href="finance_stats.php" class="nav-item <?= $current_page==='finance_stats.php'?'active':'' ?>">
                <span class="nav-icon">📉</span> Global Revenue
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <?php if ($is_main_branch === 1): ?>
                <a href="<?= BASE_URL ?>admin/manage_branches.php" class="nav-item <?= $current_page==='manage_branches.php'?'active':'' ?>">
                    <span class="nav-icon">🏢</span> Manage Branches
                </a>
                <a href="<?= BASE_URL ?>superadmin/finance_stats.php" class="nav-item <?= $current_page==='finance_stats.php'?'active':'' ?>">
                    <span class="nav-icon">📊</span> Global Revenue
                </a>
                <a href="<?= BASE_URL ?>admin/verify_branch_sales.php" class="nav-item <?= $current_page==='verify_branch_sales.php'?'active':'' ?>">
                    <span class="nav-icon">🛡️</span> Verify Branch Sales
                </a>
                <a href="<?= BASE_URL ?>admin/sales_report.php" class="nav-item <?= $current_page==='sales_report.php'?'active':'' ?>">
                    <span class="nav-icon">📈</span> Global Sales Report
                </a>
            <?php endif; ?>
            <?php if (isModuleEnabled('hrms')): ?>
                <!-- HRMS Dropdown -->
                <div class="nav-dropdown <?= in_array($current_page, ['staff.php', 'attendance.php', 'attendance_report.php', 'shifts.php', 'attendance_settings.php']) ? 'open' : '' ?>">
                    <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                        <span><span class="nav-icon">📁</span> HRMS</span>
                        <span class="chevron">▶</span>
                    </div>
                    <div class="nav-sub-menu">
                        <a href="<?= BASE_URL ?>admin/staff.php" class="nav-sub-item <?= $current_page==='staff.php'?'active':'' ?>">Manage Staff</a>
                        <a href="<?= BASE_URL ?>admin/leave_requests.php" class="nav-sub-item <?= $current_page==='leave_requests.php'?'active':'' ?>">Leave Requests</a>
                        <a href="<?= BASE_URL ?>admin/attendance.php" class="nav-sub-item <?= $current_page==='attendance.php'?'active':'' ?>">Daily Attendance</a>
                        <a href="<?= BASE_URL ?>admin/attendance_matrix.php" class="nav-sub-item <?= $current_page==='attendance_matrix.php'?'active':'' ?>">Attendance Matrix</a>
                        <a href="<?= BASE_URL ?>admin/attendance_report.php" class="nav-sub-item <?= $current_page==='attendance_report.php'?'active':'' ?>">Monthly Report</a>
                        <a href="<?= BASE_URL ?>admin/holidays.php" class="nav-sub-item <?= $current_page==='holidays.php'?'active':'' ?>">Holidays</a>
                        <a href="<?= BASE_URL ?>admin/shifts.php" class="nav-sub-item <?= $current_page==='shifts.php'?'active':'' ?>">Shifts</a>
                        <a href="<?= BASE_URL ?>admin/attendance_settings.php" class="nav-sub-item <?= $current_page==='attendance_settings.php'?'active':'' ?>">Security</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Branch Configuration (Phase 44) -->
            <div class="nav-dropdown <?= in_array($current_page, ['settings_designations.php', 'settings_products.php']) ? 'open' : '' ?>">
                <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                    <span><span class="nav-icon">⚙️</span> Configuration</span>
                    <span class="chevron">▶</span>
                </div>
                <div class="nav-sub-menu">
                    <a href="<?= BASE_URL ?>admin/settings_designations.php" class="nav-sub-item <?= $current_page==='settings_designations.php'?'active':'' ?>">Job Designations</a>
                    <a href="<?= BASE_URL ?>admin/settings_products.php" class="nav-sub-item <?= $current_page==='settings_products.php'?'active':'' ?>">Service Catalog</a>
                </div>
            </div>

        <?php else: ?>
            <div class="nav-dropdown <?= in_array($current_page, ['staff_attendance.php', 'apply_leave.php']) ? 'open' : '' ?>">
                <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                    <span><span class="nav-icon">📁</span> My HRMS</span>
                    <span class="chevron">▶</span>
                </div>
                <div class="nav-sub-menu">
                    <a href="<?= BASE_URL ?>admin/staff_attendance.php" class="nav-sub-item <?= $current_page==='staff_attendance.php'?'active':'' ?>">My Attendance</a>
                    <a href="<?= BASE_URL ?>admin/apply_leave.php" class="nav-sub-item <?= $current_page==='apply_leave.php'?'active':'' ?>">Apply Leave</a>
                    <a href="<?= BASE_URL ?>admin/staff_profile.php" class="nav-sub-item <?= $current_page==='staff_profile.php'?'active':'' ?>">My Profile</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Income Section: HQ Main Branch only -->
        <?php if ($role === 'admin' && $is_main_branch === 1): ?>
        <div class="nav-dropdown <?= in_array($current_page, ['income_categories.php', 'income_list.php', 'add_income.php']) ? 'open' : '' ?>">
            <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                <span><span class="nav-icon">🚀</span> Income</span>
                <span class="chevron">▶</span>
            </div>
            <div class="nav-sub-menu">
                <a href="<?= BASE_URL ?>admin/income_categories.php" class="nav-sub-item <?= $current_page==='income_categories.php'?'active':'' ?>">Income Category</a>
                <a href="<?= BASE_URL ?>admin/income_list.php" class="nav-sub-item <?= $current_page==='income_list.php'?'active':'' ?>">Income List</a>
                <a href="<?= BASE_URL ?>admin/add_income.php" class="nav-sub-item <?= $current_page==='add_income.php'?'active':'' ?>">Add Income</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Finance Section -->
        <?php if ($role === 'admin' && $is_main_branch !== 1): ?>
        <div class="nav-dropdown <?= in_array($current_page, ['submit_payment.php', 'finance_report.php']) ? 'open' : '' ?>">
            <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                <span><span class="nav-icon">💰</span> Finance</span>
                <span class="chevron">▶</span>
            </div>
            <div class="nav-sub-menu">
                <a href="<?= BASE_URL ?>admin/submit_payment.php" class="nav-sub-item <?= $current_page==='submit_payment.php'?'active':'' ?>">Submit Payment</a>
                <a href="<?= BASE_URL ?>admin/finance_report.php" class="nav-sub-item <?= $current_page==='finance_report.php'?'active':'' ?>">Finance Report</a>
                <?php if (isModuleEnabled('payroll')): ?>
                    <a href="#" class="nav-sub-item">Payroll (Soon)</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; /* end finance */ ?>

        <!-- Expense Section: shown for all admins including HQ AND sales_person -->
        <?php if ($role === 'admin' || $role === 'sales_person'): ?>
        <div class="nav-dropdown <?= in_array($current_page, ['expense_categories.php', 'expense_list.php', 'add_expense.php']) ? 'open' : '' ?>">
            <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                <span><span class="nav-icon">💸</span> Expense</span>
                <span class="chevron">▶</span>
            </div>
            <div class="nav-sub-menu">
                <a href="<?= BASE_URL ?>admin/expense_categories.php" class="nav-sub-item <?= $current_page==='expense_categories.php'?'active':'' ?>">Expense Category</a>
                <a href="<?= BASE_URL ?>admin/expense_list.php" class="nav-sub-item <?= $current_page==='expense_list.php'?'active':'' ?>">Expense List</a>
                <a href="<?= BASE_URL ?>admin/add_expense.php" class="nav-sub-item <?= $current_page==='add_expense.php'?'active':'' ?>">Add Expense</a>
            </div>
        </div>
        <?php endif; ?>



        <?php if ($role === 'admin'): ?>
        <div class="nav-dropdown <?= in_array($current_page, ['settings.php', 'settings_products.php']) ? 'open' : '' ?>">
            <div class="nav-dropdown-toggle" onclick="this.parentElement.classList.toggle('open')">
                <span><span class="nav-icon">⚙️</span> Settings</span>
                <span class="chevron">▶</span>
            </div>
            <div class="nav-sub-menu">
                <a href="<?= BASE_URL ?>admin/settings_products.php" class="nav-sub-item <?= $current_page==='settings_products.php'?'active':'' ?>">Product Catalog</a>
                <a href="<?= BASE_URL ?>admin/settings.php" class="nav-sub-item <?= $current_page==='settings.php'?'active':'' ?>">General Settings</a>
            </div>
        </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['impersonator_id'])): ?>
            <a href="<?= BASE_URL ?>superadmin/exit_impersonation.php" class="nav-item" style="background:rgba(236,72,153,0.1);color:#ec4899;margin-top:2rem;border:1px dashed #ec4899;border-radius:8px;">
                <span class="nav-icon">🔙</span> Exit Impersonation
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($_SESSION['user_name'],0,2)) ?></div>
            <div>
                <div style="font-size:.9rem;font-weight:600;"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div style="font-size:.8rem;color:var(--text-muted);"><?= ucfirst($_SESSION['user_role']) ?> Panel</div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php?role=user" class="btn btn-outline" style="width:100%; text-align:center; font-size:0.85rem; padding:0.6rem 0.5rem; border: 1px solid rgba(255, 255, 255, 0.4) !important; border-radius: 30px !important; text-decoration: none; color: #ffffff !important;">Logout</a>
    </div>
</aside>
