<?php
// /admin/includes/topbar.php
?>
<div class="sidebar-overlay" onclick="document.body.classList.remove('sidebar-open')"></div>

<header class="top-bar">
    <div style="display:flex; align-items:center; gap:15px;">
        <button class="mobile-toggle" onclick="document.body.classList.toggle('sidebar-open')">☰</button>
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="globalSearch" placeholder="Search leads, staff...">
        </div>
    </div>
    
    <div class="top-bar-actions">
        <div class="notification-wrapper">
            <button class="icon-btn" id="notifBell" title="Notifications">
                <span class="icon">🔔</span>
                <span class="badge" id="notifBadge" style="display:none;">0</span>
            </button>
            <div id="notifPanel" class="dropdown-menu" style="width: 280px; right: 0; top: 100%;">
                <div style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 600; font-size: 0.8rem;">Recent Alerts</div>
                <div id="notifList" style="max-height: 300px; overflow-y: auto;">
                    <!-- Notifications will appear here -->
                </div>
            </div>
        </div>
        
        <div class="user-profile-dropdown">
            <div class="profile-trigger">
                <div class="avatar-small"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
                <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <span class="chevron">▼</span>
            </div>
            <div class="dropdown-menu">
                <a href="<?= BASE_URL ?>admin/settings.php">Account Settings</a>
                <a href="<?= BASE_URL ?>logout.php?role=user" class="text-danger">Logout</a>
            </div>
        </div>
    </div>
</header>

<style>
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 3rem;
    background: #ffffff;
    border-bottom: 1px solid var(--glass-border);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.search-wrapper {
    position: relative;
    width: 350px;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-wrapper input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    background: #f1f5f9;
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    color: var(--text-main);
    transition: all 0.3s ease;
}

.search-wrapper input:focus {
    background: #ffffff;
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 15px rgba(99, 102, 241, 0.1);
}

.top-bar-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.icon-btn {
    background: none;
    border: none;
    color: var(--text-main);
    font-size: 1.2rem;
    cursor: pointer;
    position: relative;
    padding: 0.5rem;
    border-radius: 8px;
    transition: background 0.3s;
}

.icon-btn:hover {
    background: rgba(255, 255, 255, 0.05);
}

.icon-btn .badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #ef4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

.profile-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 0.4rem 0.8rem;
    border-radius: 30px;
    background: #f8fafc;
    border: 1px solid var(--glass-border);
    transition: all 0.3s;
}

.profile-trigger:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(99, 102, 241, 0.4);
}

.avatar-small {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: white;
}

.profile-name {
    font-size: 0.9rem;
    font-weight: 500;
}

.chevron {
    font-size: 0.7rem;
    opacity: 0.5;
}

.user-profile-dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 180px;
    background: #ffffff;
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 0.5rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    display: none;
    flex-direction: column;
}

.user-profile-dropdown:hover .dropdown-menu,
.notification-wrapper:hover #notifPanel {
    display: flex;
}

#notifList .notif-item {
    padding: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    font-size: 0.8rem;
    transition: background 0.2s;
}

#notifList .notif-item:hover {
    background: rgba(255,255,255,0.02);
}

#notifList .notif-title {
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 4px;
}

#notifList .notif-body {
    color: var(--text-muted);
}
</style>

<script>
// Notification Polling 
if (Notification.permission !== "granted") {
    Notification.requestPermission();
}

let lastNotifCount = 0;

async function checkNotifications() {
    try {
        const response = await fetch('../api/notifications/fetch.php');
        const data = await response.json();
        
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifList');
        
        if (data.length > 0) {
            badge.textContent = data.length;
            badge.style.display = 'flex';
            
            data.forEach(n => {
                // Browser Push
                if (Notification.permission === "granted") {
                    new Notification(n.title, { body: n.message });
                }
                
                // Add to list
                const div = document.createElement('div');
                div.className = 'notif-item';
                div.innerHTML = `<div class="notif-title">${n.title}</div><div class="notif-body">${n.message}</div>`;
                list.prepend(div);
            });
        }
    } catch (err) {
        console.error("Notif check failed", err);
    }
}

// Check every 10 seconds
setInterval(checkNotifications, 10000);
document.addEventListener('DOMContentLoaded', () => {
    checkNotifications();
    // Auto-wrap tables for mobile responsiveness
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

