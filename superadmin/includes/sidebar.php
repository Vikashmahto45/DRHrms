<?php
// /superadmin/includes/sidebar.php
// Shared Super Admin Sidebar - include on every SA page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>
<button class="floating-mobile-toggle" onclick="document.body.classList.toggle('sidebar-open')">☰</button>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">DR<span>Hrms</span></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">SUPER ADMIN PORTAL</div>
    </div>
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
        <a href="main_branch.php" class="nav-item <?= $current_page === 'main_branch.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span> Main Branch
        </a>
        <a href="sub_branches.php" class="nav-item <?= $current_page === 'sub_branches.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏬</span> Sub-Branches
        </a>
        <a href="admins.php" class="nav-item <?= $current_page === 'admins.php' ? 'active' : '' ?>">
            <span class="nav-icon">👨‍💼</span> Manage Admins
        </a>
        <a href="expiry_alerts.php" class="nav-item <?= $current_page === 'expiry_alerts.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚠️</span> Expiry Alerts
        </a>
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span> System Settings
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">SA</div>
            <div>
                <div style="font-size:0.9rem;font-weight:600;"><?= htmlspecialchars($_SESSION['sa_user_name']) ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);">Super Admin</div>
            </div>
        </div>
        <a href="../logout.php?role=sa" class="btn btn-outline" style="width:100%;text-align:center;font-size:0.85rem;padding:0.5rem;">Logout</a>
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
