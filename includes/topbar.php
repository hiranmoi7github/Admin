<?php
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'member';
$adminDesignation = $_SESSION['admin_designation'] ?? '';
$adminAvatar = $_SESSION['admin_avatar'] ?? '';
?>
<header class="app-topbar">
    <div class="topbar-left">
        <button class="mobile-sidebar-btn" id="mobileSidebarBtn" type="button" aria-label="Open sidebar">
            <i data-lucide="menu"></i>
        </button>
        <div>
            <h1 class="topbar-title"><?php echo htmlspecialchars($pageTitle ?? 'Admin Panel'); ?></h1>
            <p class="topbar-subtitle"><?php echo htmlspecialchars($pageSubtitle ?? 'Manage your platform efficiently.'); ?></p>
        </div>
    </div>

    <div class="topbar-right">
        <div class="profile-dropdown" id="profileDropdown">
            <button class="profile-trigger" id="profileTrigger" type="button">
                <span class="profile-avatar">
                    <?php if (!empty($adminAvatar)): ?>
                        <img src="<?php echo htmlspecialchars($adminAvatar); ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars(strtoupper(substr($adminName, 0, 1))); ?></span>
                    <?php endif; ?>
                </span>
                <span class="profile-meta">
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <small><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $adminRole))); ?><?php echo $adminDesignation ? ' · ' . htmlspecialchars($adminDesignation) : ''; ?></small>
                </span>
                <i data-lucide="chevron-down"></i>
            </button>

            <div class="profile-menu" id="profileMenu">
                <a href="admins.php?edit_id=<?php echo (int)($_SESSION['admin_id'] ?? 0); ?>">
                    <i data-lucide="user-round-cog"></i>
                    <span>Edit Profile</span>
                </a>
                <a href="settings.php">
                    <i data-lucide="settings"></i>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="danger-link">
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>