<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();

require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $error = 'Invalid user selected.';
    } else {
        if ($action === 'toggle_status') {
            $newStatus = trim($_POST['new_status'] ?? '');

            if (!in_array($newStatus, ['active', 'inactive'], true)) {
                $error = 'Invalid status selected.';
            } else {
                $result = updateProfileStatus($id, $newStatus);

                if ($result['success']) {
                    $success = 'User status updated successfully.';
                } else {
                    $error = 'Failed to update user status.';
                }
            }
        }

        if ($action === 'edit_user') {
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $dob = trim($_POST['dob'] ?? '');

            if ($fullName === '') {
                $error = 'Full name is required.';
            } else {
                $result = updateRows('profiles', 'id=eq.' . $id, [
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'dob' => $dob
                ], true);

                if ($result['success']) {
                    $success = 'User updated successfully.';
                } else {
                    $error = 'Failed to update user.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH USERS FROM PROFILES
|--------------------------------------------------------------------------
*/
$response = getAllProfiles(1000);
$allUsers = $response['success'] ? ($response['data'] ?? []) : [];

if (!$response['success']) {
    $error = 'Failed to fetch users from profiles table.';
}

/*
|--------------------------------------------------------------------------
| SEARCH + FILTER
|--------------------------------------------------------------------------
*/
$filteredUsers = array_filter($allUsers, function ($user) use ($search, $statusFilter) {
    $matchesSearch = true;
    $matchesStatus = true;

    if ($search !== '') {
        $haystack = strtolower(
            ($user['id'] ?? '') . ' ' .
            ($user['full_name'] ?? '') . ' ' .
            ($user['phone'] ?? '') . ' ' .
            ($user['dob'] ?? '')
        );

        $matchesSearch = str_contains($haystack, strtolower($search));
    }

    if ($statusFilter !== '') {
        $matchesStatus = (($user['status'] ?? 'active') === $statusFilter);
    }

    return $matchesSearch && $matchesStatus;
});

$filteredUsers = array_values($filteredUsers);

$totalUsers = count($filteredUsers);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$users = array_slice($filteredUsers, $offset, $perPage);

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editUser = null;

if ($editId > 0) {
    foreach ($allUsers as $u) {
        if ((int)$u['id'] === $editId) {
            $editUser = $u;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="assets/css/users.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="users-wrap">
    <div class="topbar">
        <div>
            <h1>Manage Users</h1>
            <p>Showing student data from profiles table.</p>
        </div>
        <div class="topbar-actions">
            <a href="export_users.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn success-export-btn">Export Excel</a>
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($editUser): ?>
        <div class="card edit-user-card">
            <h3>Edit User</h3>
            <form method="POST" class="edit-user-form">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>">

                <div class="group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>" required>
                </div>

                <div class="group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>">
                </div>

                <div class="group">
                    <label for="dob">DOB</label>
                    <input type="text" id="dob" name="dob" value="<?php echo htmlspecialchars($editUser['dob'] ?? ''); ?>" placeholder="YYYY-MM-DD">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn primary-btn">Save Changes</button>
                    <a href="users.php" class="btn secondary-btn">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card filter-card">
        <form method="GET" class="filter-form">
            <div class="group">
                <label for="search">Search</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by ID, name, phone, DOB"
                >
            </div>

            <div class="group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn primary-btn">Apply Filters</button>
                <a href="users.php" class="btn secondary-btn">Reset</a>
            </div>
        </form>
    </div>

    <div class="card summary-card">
        <h3>Total Users</h3>
        <p><?php echo number_format($totalUsers); ?></p>
    </div>

    <div class="card table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Profile ID</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>DOB</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $index => $user): ?>
                        <?php
                        $rowNumber = $offset + $index + 1;
                        $userStatus = $user['status'] ?? 'active';
                        $menuId = 'menu-' . (int)$user['id'];
                        ?>
                        <tr>
                            <td><?php echo $rowNumber; ?></td>
                            <td><?php echo htmlspecialchars((string)($user['id'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['dob'] ?? '-'); ?></td>
                            <td>
                                <span class="tag <?php echo htmlspecialchars($userStatus); ?>">
                                    <?php echo htmlspecialchars(ucfirst($userStatus)); ?>
                                </span>
                            </td>
                            <td>
                                <div class="menu-wrapper">
                                    <button type="button" class="three-dot-btn" onclick="toggleMenu('<?php echo $menuId; ?>')">⋮</button>

                                    <div class="action-menu" id="<?php echo $menuId; ?>">
                                        <a href="users.php?edit=<?php echo (int)$user['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page; ?>" class="menu-link">Edit</a>

                                        <?php if ($userStatus === 'active'): ?>
                                            <form method="POST" class="menu-form">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" class="menu-btn">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="menu-form">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="menu-btn">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">No users found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page - 1; ?>">← Prev</a>
            <?php endif; ?>

            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $page + 1; ?>">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleMenu(menuId) {
    document.querySelectorAll('.action-menu').forEach(menu => {
        if (menu.id !== menuId) {
            menu.classList.remove('show');
        }
    });

    const menu = document.getElementById(menuId);
    if (menu) {
        menu.classList.toggle('show');
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.menu-wrapper')) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});
</script>
</body>
</html>