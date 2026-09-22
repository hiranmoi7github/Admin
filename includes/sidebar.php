<?php
require_once __DIR__ . '/auth_check.php';

if (!function_exists('sidebarHas')) {
    function sidebarHas(string $permission): bool
    {
        return hasPermission($permission);
    }
}

if (!function_exists('isActivePage')) {
    function isActivePage(array $files): bool
    {
        $current = basename($_SERVER['PHP_SELF']);
        return in_array($current, $files, true);
    }
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'member';
$adminDesignation = $_SESSION['admin_designation'] ?? '';
$adminAvatar = $_SESSION['admin_avatar'] ?? '';

$menu = [
    [
        'title' => 'Overview',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => 'dashboard.php', 'permission' => 'dashboard', 'files' => ['dashboard.php']],
            ['label' => 'Analytics', 'icon' => 'chart-column', 'href' => 'analytics.php', 'permission' => 'analytics', 'files' => ['analytics.php']],
            ['label' => 'Results', 'icon' => 'square-check-big', 'href' => 'results.php', 'permission' => 'results', 'files' => ['results.php']],
        ]
    ],
    [
        'title' => 'Academic Engine',
        'items' => [
            ['label' => 'Exams', 'icon' => 'book-open', 'href' => 'exams.php', 'permission' => 'exams', 'files' => ['exams.php']],
            ['label' => 'Subjects', 'icon' => 'library', 'href' => 'subjects.php', 'permission' => 'subjects', 'files' => ['subjects.php']],
			['label' => 'Test Series', 'icon' => 'books', 'href' => 'test_series.php', 'permission' => 'test_series', 'files' => ['test_series.php']],
            ['label' => 'Mock Tests', 'icon' => 'clipboard-list', 'href' => 'mock_tests.php', 'permission' => 'mock_tests', 'files' => ['mock_tests.php','mock_test_subjects.php','mock_test_questions.php']],
            ['label' => 'Question Bank', 'icon' => 'files', 'href' => 'question_bank.php', 'permission' => 'question_bank', 'files' => ['question_bank.php']],
            ['label' => 'Bulk Upload', 'icon' => 'upload', 'href' => 'bulk_upload.php', 'permission' => 'question_bank', 'files' => ['bulk_upload.php']],
        ]
    ],
    [
        'title' => 'Content',
        'items' => [
            ['label' => 'Upload Materials', 'icon' => 'file-text', 'href' => 'materials.php', 'permission' => 'materials', 'files' => ['materials.php']],
            ['label' => 'Banner & Content', 'icon' => 'image', 'href' => 'banner_content.php', 'permission' => 'announcements', 'files' => ['banner_content.php']],
            ['label' => 'Announcements', 'icon' => 'megaphone', 'href' => 'announcements.php', 'permission' => 'announcements', 'files' => ['announcements.php']],
        ]
    ],
    [
        'title' => 'Business',
        'items' => [
            ['label' => 'Subscriptions', 'icon' => 'badge-indian-rupee', 'href' => 'subscription.php', 'permission' => 'subscription', 'files' => ['subscription.php']],
            ['label' => 'Finance', 'icon' => 'wallet', 'href' => 'finance.php', 'permission' => 'finance', 'files' => ['finance.php']],
        ]
    ],
    [
        'title' => 'Organization',
        'items' => [
            ['label' => 'Admins', 'icon' => 'shield-check', 'href' => 'admins.php', 'permission' => 'admins', 'files' => ['admins.php']],
            ['label' => 'Users', 'icon' => 'users', 'href' => 'users.php', 'permission' => 'users', 'files' => ['users.php']],
            ['label' => 'Org View', 'icon' => 'network', 'href' => 'org_view.php', 'permission' => 'admins', 'files' => ['org_view.php']],
            ['label' => 'Payroll', 'icon' => 'credit-card', 'href' => 'payroll.php', 'permission' => 'admins', 'files' => ['payroll.php']],
        ]
    ],
    [
        'title' => 'Support',
        'items' => [
            ['label' => 'Support & Feedback', 'icon' => 'life-buoy', 'href' => 'support_feedback.php', 'permission' => 'support_feedback', 'files' => ['support_feedback.php']],
        ]
    ],
    [
        'title' => 'System',
        'items' => [
            ['label' => 'Settings', 'icon' => 'settings', 'href' => 'settings.php', 'permission' => 'settings', 'files' => ['settings.php']],
            ['label' => 'Logout', 'icon' => 'log-out', 'href' => 'logout.php', 'permission' => 'dashboard', 'files' => ['logout.php']],
        ]
    ]
];
?>

<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-top">
        <div class="brand-block">
            <div class="brand-logo">TYT</div>
            <div class="brand-text">
                <h2>TYT Admin</h2>
                <p>Control Center</p>
            </div>
        </div>

        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i data-lucide="panel-left-close"></i>
        </button>
    </div>

    <div class="sidebar-admin-card">
        <div class="sidebar-avatar">
            <?php if (!empty($adminAvatar)): ?>
                <img src="<?php echo htmlspecialchars($adminAvatar); ?>" alt="Profile">
            <?php else: ?>
                <span><?php echo htmlspecialchars(strtoupper(substr($adminName, 0, 1))); ?></span>
            <?php endif; ?>
        </div>
        <div class="sidebar-admin-info">
            <strong><?php echo htmlspecialchars($adminName); ?></strong>
            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $adminRole))); ?><?php echo $adminDesignation ? ' · ' . htmlspecialchars($adminDesignation) : ''; ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $section): ?>
            <?php
            $visibleItems = array_filter($section['items'], function ($item) {
                return sidebarHas($item['permission']);
            });
            if (empty($visibleItems)) continue;
            ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title"><?php echo htmlspecialchars($section['title']); ?></div>

                <?php foreach ($visibleItems as $item): ?>
                    <a
                        href="<?php echo htmlspecialchars($item['href']); ?>"
                        class="sidebar-link <?php echo isActivePage($item['files']) ? 'active' : ''; ?>"
                    >
                        <span class="sidebar-link-icon">
                            <i data-lucide="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                        </span>
                        <span class="sidebar-link-text"><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>