<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('admins');
require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Org View';
$pageSubtitle = 'Organization structure, roles, and designation overview.';

$adminsRes = function_exists('getAllAdmins') ? getAllAdmins() : ['success' => false, 'data' => []];
$admins = ($adminsRes['success'] ?? false) ? ($adminsRes['data'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Org View</title>
    <link rel="stylesheet" href="assets/css/admin_layout.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="app-shell" id="appShell">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="app-main">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="page-content">
            <div class="panel-card">
                <h2>Organization Team</h2>
                <div class="list-block">
                    <?php foreach ($admins as $admin): ?>
                        <div class="list-item">
                            <span>
                                <?php echo htmlspecialchars($admin['name'] ?? '-'); ?>
                                <small class="muted"> · <?php echo htmlspecialchars($admin['designation'] ?? '-'); ?></small>
                            </span>
                            <span class="badge"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $admin['role'] ?? 'member'))); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>