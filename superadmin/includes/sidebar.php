<?php
// /superadmin/includes/sidebar.php
// Shared Super Admin Sidebar - include on every SA page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<button class="floating-mobile-toggle" onclick="document.body.classList.toggle('sidebar-open')">☰</button>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo"><span>Loom</span></div>
        <div style="font-size:.7rem; color:var(--text-muted); margin-top:4px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Super Admin Portal</div>
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
        .sidebar-footer {
            background: transparent !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
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
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="finance_stats.php" class="nav-item <?= $current_page === 'finance_stats.php' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Financial Overview
        </a>
        <a href="franchise_revenue.php" class="nav-item <?= $current_page === 'franchise_revenue.php' ? 'active' : '' ?>">
            <span class="nav-icon">💰</span> Franchise Revenue
        </a>
        <a href="payment_verifications.php" class="nav-item <?= $current_page === 'payment_verifications.php' ? 'active' : '' ?>">
            <span class="nav-icon">💳</span> Verify Branch Sales
        </a>
        <a href="commission_payouts.php" class="nav-item <?= $current_page === 'commission_payouts.php' ? 'active' : '' ?>">
            <span class="nav-icon">💸</span> Franchise Payouts
        </a>
        <a href="staff_performance.php" class="nav-item <?= $current_page === 'staff_performance.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏆</span> Staff Leaderboard
        </a>
        <a href="projects.php" class="nav-item <?= $current_page === 'projects.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏗️</span> Project Tracking
        </a>
        <a href="attendance.php" class="nav-item <?= $current_page === 'attendance.php' ? 'active' : '' ?>">
            <span class="nav-icon">⏰</span> Attendance
        </a>
        <a href="main_branch.php" class="nav-item <?= $current_page === 'main_branch.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span> Main Branch
        </a>
        <a href="sub_branches.php" class="nav-item <?= $current_page === 'sub_branches.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏬</span> Sub-Branches
        </a>
        <a href="admins.php" class="nav-item <?= $current_page === 'admins.php' ? 'active' : '' ?>">
            <span class="nav-icon">👨‍💼</span> Manage Admins
        </a>
        <a href="project_permissions.php" class="nav-item <?= $current_page === 'project_permissions.php' ? 'active' : '' ?>">
            <span class="nav-icon">👮</span> Action Permissions
        </a>
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span> System Settings
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important; color: #fff !important; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">SA</div>
            <div>
                <div style="font-size:0.9rem;font-weight:600;"><?= htmlspecialchars($_SESSION['sa_user_name'] ?? 'Admin') ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);">Super Admin</div>
            </div>
        </div>
        <a href="../logout.php?role=sa" class="btn btn-outline" style="width:100%; text-align:center; font-size:0.85rem; padding:0.6rem 0.5rem; border: 1px solid rgba(255, 255, 255, 0.4) !important; border-radius: 30px !important; text-decoration: none; color: #ffffff !important;">Logout</a>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('table').forEach(table => {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });
});
</script>
