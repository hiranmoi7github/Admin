<?php
require_once __DIR__ . '/includes/auth_check.php';

require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Dashboard';
$pageSubtitle = 'Cool admin control panel with overview, organization, and operations.';

$adminRole = $_SESSION['admin_role'] ?? 'member';
$isSuperAdmin = ($adminRole === 'super_admin');

$stats = function_exists('getDashboardStats') ? getDashboardStats() : [
    'users' => 0,
    'exams' => 0,
    'subjects' => 0,
    'mock_tests' => 0,
    'questions' => 0,
    'sales' => 0
];

if (!$isSuperAdmin) {
    $stats['sales'] = 0;
}

$quickLinks = [
    [
        'label' => 'Manage Exams',
        'href' => 'exam_categories.php',
        'permission' => 'exams'
    ],
    [
        'label' => 'Manage Mock Tests',
        'href' => 'mock_tests.php',
        'permission' => 'mock_tests'
    ],
    [
        'label' => 'Question Bank',
        'href' => 'question_bank.php',
        'permission' => 'question_bank'
    ],
    [
        'label' => 'Upload Materials',
        'href' => 'materials.php',
        'permission' => 'materials'
    ],
    [
        'label' => 'Subscriptions',
        'href' => 'subscription.php',
        'permission' => 'subscription'
    ],
    [
        'label' => 'Announcements',
        'href' => 'announcements.php',
        'permission' => 'announcements'
    ],
];

$orgBlocks = [
    ['label' => 'Admins', 'value' => hasPermission('admins') ? 'Manage team access' : 'Restricted'],
    ['label' => 'Users', 'value' => hasPermission('users') ? 'View student base' : 'Restricted'],
    ['label' => 'Org View', 'value' => hasPermission('admins') ? 'Hierarchy & roles' : 'Restricted'],
    ['label' => 'Payroll', 'value' => hasPermission('admins') ? 'Salary operations' : 'Restricted'],
];

$recentOps = [
    ['label' => 'Review latest mock tests', 'badge' => 'Academic'],
    ['label' => 'Check member permissions', 'badge' => 'Organization'],
    ['label' => 'Monitor support queue', 'badge' => 'Support'],
    ['label' => 'Verify subscription pricing', 'badge' => 'Business'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard</title>

    <link rel="stylesheet" href="assets/css/admin_layout.css">

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

<div class="app-shell" id="appShell">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="app-main">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="page-content">

            <section class="dashboard-grid">

                <div class="dashboard-card">
                    <h3>Total Users</h3>
                    <p><?php echo number_format((int)($stats['users'] ?? 0)); ?></p>
                    <span>Student accounts in platform</span>
                </div>

                <div class="dashboard-card">
                    <h3>Total Exams</h3>
                    <p><?php echo number_format((int)($stats['exams'] ?? 0)); ?></p>
                    <span>Exam structures created</span>
                </div>

                <div class="dashboard-card">
                    <h3>Total Mock Tests</h3>
                    <p><?php echo number_format((int)($stats['mock_tests'] ?? 0)); ?></p>
                    <span>Published and draft mocks</span>
                </div>

                <div class="dashboard-card">
                    <h3>Total Questions</h3>
                    <p><?php echo number_format((int)($stats['questions'] ?? 0)); ?></p>
                    <span>Question bank inventory</span>
                </div>

                <div class="dashboard-card">
                    <h3>Total Subjects</h3>
                    <p><?php echo number_format((int)($stats['subjects'] ?? 0)); ?></p>
                    <span>Academic subject structure</span>
                </div>

                <?php if ($isSuperAdmin): ?>

                    <div class="dashboard-card">

                        <h3>Total Sales</h3>

                        <p>
                            ₹<?php echo number_format((float)($stats['sales'] ?? 0), 2); ?>
                        </p>

                        <span>Business revenue snapshot</span>

                    </div>

                <?php endif; ?>

            </section>


            <section class="content-grid-2">

                <div class="panel-card">

                    <h2>Quick Access</h2>

                    <div class="quick-links">

                        <?php foreach ($quickLinks as $item): ?>

                            <?php if (hasPermission($item['permission'])): ?>

                                <a href="<?php echo htmlspecialchars($item['href']); ?>">
                                    <?php echo htmlspecialchars($item['label']); ?>
                                </a>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                </div>


                <div class="panel-card">

                    <h2>Organization & Operations</h2>

                    <div class="list-block">

                        <?php foreach ($orgBlocks as $block): ?>

                            <div class="list-item">

                                <span>
                                    <?php echo htmlspecialchars($block['label']); ?>
                                </span>

                                <span class="muted">
                                    <?php echo htmlspecialchars($block['value']); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </section>


            <section class="content-grid-2">

                <div class="panel-card">

                    <h2>Recent Focus Areas</h2>

                    <div class="list-block">

                        <?php foreach ($recentOps as $op): ?>

                            <div class="list-item">

                                <span>
                                    <?php echo htmlspecialchars($op['label']); ?>
                                </span>

                                <span class="badge">
                                    <?php echo htmlspecialchars($op['badge']); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>


                <div class="panel-card">

                    <h2>Premium Admin Feel</h2>

                    <div class="list-block">

                        <div class="list-item">
                            <span>Compact sidebar</span>
                            <span class="muted">Collapsible</span>
                        </div>

                        <div class="list-item">
                            <span>Profile actions</span>
                            <span class="muted">Top-right dropdown</span>
                        </div>

                        <div class="list-item">
                            <span>Role-based menu</span>
                            <span class="muted">Permission aware</span>
                        </div>

                        <div class="list-item">
                            <span>Org + Payroll blocks</span>
                            <span class="muted">Scalable layout</span>
                        </div>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>


<script>

lucide.createIcons();

const appShell = document.getElementById('appShell');

const sidebarToggle = document.getElementById('sidebarToggle');

const mobileSidebarBtn = document.getElementById('mobileSidebarBtn');

const appSidebar = document.getElementById('appSidebar');


if (sidebarToggle) {

    sidebarToggle.addEventListener('click', () => {

        appShell.classList.toggle('sidebar-collapsed');

    });

}


if (mobileSidebarBtn && appSidebar) {

    mobileSidebarBtn.addEventListener('click', () => {

        appSidebar.classList.toggle('mobile-open');

    });

}


const profileTrigger = document.getElementById('profileTrigger');

const profileMenu = document.getElementById('profileMenu');


if (profileTrigger && profileMenu) {

    profileTrigger.addEventListener('click', (e) => {

        e.stopPropagation();

        profileMenu.classList.toggle('show');

    });


    document.addEventListener('click', (e) => {

        if (!e.target.closest('#profileDropdown')) {

            profileMenu.classList.remove('show');

        }

    });

}

</script>

</body>
</html>