<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('admins');
require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Admins';
$pageSubtitle = 'Manage super admins, members, permissions, employee IDs, reporting structure, and profile image.';

$success = '';
$error = '';
$editMode = false;
$editAdmin = null;

$currentAdminId = getAdminId();
$currentAdminRole = getAdminRole();

$permissionOptions = [
    'dashboard' => 'Dashboard',
    'exams' => 'Exams',
    'subjects' => 'Subjects',
    'mock_tests' => 'Mock Tests',
    'question_bank' => 'Question Bank',
    'materials' => 'Upload Materials',
    'results' => 'Results',
    'subscription' => 'Subscriptions',
    'finance' => 'Finance',
    'announcements' => 'Announcements',
    'support_feedback' => 'Support & Feedback',
    'admins' => 'Admins',
    'users' => 'Users',
    'analytics' => 'Analytics',
    'settings' => 'Settings'
];

function normalizePermissions($permissions): array
{
    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($permissions) ? $permissions : [];
}

function canDeleteAdmin(array $targetAdmin, int $currentAdminId, string $currentAdminRole): bool
{
    $targetId = (int)($targetAdmin['id'] ?? 0);
    $targetRole = (string)($targetAdmin['role'] ?? 'member');

    if ($currentAdminRole !== 'super_admin') {
        return false;
    }

    if ($targetId === $currentAdminId) {
        return true;
    }

    if ($targetRole === 'super_admin') {
        return false;
    }

    return true;
}

function canEditAdmin(array $targetAdmin, int $currentAdminId, string $currentAdminRole): bool
{
    $targetId = (int)($targetAdmin['id'] ?? 0);

    if ($targetId === $currentAdminId) {
        return true;
    }

    return $currentAdminRole === 'super_admin';
}

/*
|--------------------------------------------------------------------------
| Upload dir
|--------------------------------------------------------------------------
*/
$avatarUploadDir = __DIR__ . '/assets/uploads/admins/';
if (!is_dir($avatarUploadDir)) {
    mkdir($avatarUploadDir, 0777, true);
}

/*
|--------------------------------------------------------------------------
| FETCH ADMINS
|--------------------------------------------------------------------------
*/
$adminsRes = getAllAdmins();
$admins = ($adminsRes['success'] ?? false) ? ($adminsRes['data'] ?? []) : [];

$reportingAdminsRes = function_exists('getAdminsForReporting')
    ? getAdminsForReporting()
    : ['success' => false, 'data' => []];
$reportingAdmins = ($reportingAdminsRes['success'] ?? false) ? ($reportingAdminsRes['data'] ?? []) : [];

$adminLookup = [];
foreach ($admins as $adminRow) {
    $adminLookup[(int)($adminRow['id'] ?? 0)] = $adminRow;
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $targetRes = getAdminById($deleteId);

    if (($targetRes['success'] ?? false) && !empty($targetRes['data'])) {
        $targetAdmin = $targetRes['data'][0];

        if (!canDeleteAdmin($targetAdmin, $currentAdminId, $currentAdminRole)) {
            $error = 'You are not allowed to delete this admin.';
        } else {
            $deleteResult = deleteAdminById($deleteId);

            if ($deleteResult['success']) {
                if ($deleteId === $currentAdminId) {
                    session_unset();
                    session_destroy();
                    header('Location: admin_login.php?msg=account_deleted');
                    exit;
                }

                header('Location: admins.php?msg=deleted');
                exit;
            } else {
                $error = 'Failed to delete admin.';
            }
        }
    } else {
        $error = 'Admin not found.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT LOAD
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $editRes = getAdminById($editId);

    if (($editRes['success'] ?? false) && !empty($editRes['data'])) {
        $candidate = $editRes['data'][0];

        if (!canEditAdmin($candidate, $currentAdminId, $currentAdminRole)) {
            $error = 'You are not allowed to edit this admin.';
        } else {
            $editMode = true;
            $editAdmin = $candidate;
        }
    } else {
        $error = 'Admin not found.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Admin added successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Admin updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Admin deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $role = trim($_POST['role'] ?? 'member');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $permissions = array_values(array_unique(array_filter(array_map('trim', (array)$permissions))));
    $reportsToAdminId = (int)($_POST['reports_to_admin_id'] ?? 0);

    if ($reportsToAdminId <= 0) {
        $reportsToAdminId = null;
    }

    if ($name === '') {
        $error = 'Name is required.';
    } elseif ($email === '') {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($designation === '') {
        $error = 'Designation is required.';
    } elseif (!in_array($role, ['super_admin', 'member'], true)) {
        $error = 'Invalid role.';
    } else {
        if ($role === 'super_admin') {
            $permissions = array_keys($permissionOptions);
        }

        if ($action === 'add') {
            if ($currentAdminRole !== 'super_admin') {
                $error = 'Only super admin can add admins.';
            } elseif ($password === '') {
                $error = 'Password is required for new admin.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $result = addAdminAdvanced(
                    $name,
                    $email,
                    $passwordHash,
                    $designation,
                    $role,
                    $permissions,
                    $status,
                    $reportsToAdminId
                );

                if ($result['success']) {
                    header('Location: admins.php?msg=added');
                    exit;
                } else {
                    $error = 'Failed to add admin.';
                }
            }
        }

        if ($action === 'update') {
            $adminId = (int)($_POST['admin_id'] ?? 0);

            $targetRes = getAdminById($adminId);
            if (!(($targetRes['success'] ?? false) && !empty($targetRes['data']))) {
                $error = 'Admin not found.';
            } else {
                $targetAdmin = $targetRes['data'][0];

                if (!canEditAdmin($targetAdmin, $currentAdminId, $currentAdminRole)) {
                    $error = 'You are not allowed to update this admin.';
                } else {
                    $targetRole = (string)($targetAdmin['role'] ?? 'member');

                    if (
                        $adminId !== $currentAdminId &&
                        $targetRole === 'super_admin' &&
                        $role !== 'super_admin'
                    ) {
                        $error = 'You cannot change another super admin role.';
                    } else {
                        $result = updateAdminAdvanced(
                            $adminId,
                            $name,
                            $email,
                            $designation,
                            $role,
                            $permissions,
                            $status,
                            $reportsToAdminId
                        );

                        if ($result['success']) {
                            if ($password !== '') {
                                updateAdminPassword($adminId, password_hash($password, PASSWORD_DEFAULT));
                            }

                            if (
                                isset($_FILES['avatar']) &&
                                $_FILES['avatar']['error'] === UPLOAD_ERR_OK
                            ) {
                                $tmpName = $_FILES['avatar']['tmp_name'];
                                $originalName = $_FILES['avatar']['name'];
                                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                    $error = 'Avatar must be jpg, jpeg, png, or webp.';
                                } else {
                                    $fileName = 'admin_' . $adminId . '_' . time() . '.' . $extension;
                                    $targetPath = $avatarUploadDir . $fileName;

                                    if (move_uploaded_file($tmpName, $targetPath)) {
                                        $avatarUrl = 'assets/uploads/admins/' . $fileName;
                                        updateAdminAvatar($adminId, $avatarUrl);

                                        if ($adminId === $currentAdminId) {
                                            $_SESSION['admin_avatar'] = $avatarUrl;
                                        }
                                    } else {
                                        $error = 'Failed to upload avatar.';
                                    }
                                }
                            }

                            if ($adminId === $currentAdminId) {
                                $_SESSION['admin_name'] = $name;
                                $_SESSION['admin_role'] = $role;
                                $_SESSION['admin_designation'] = $designation;
                                $_SESSION['admin_permissions'] = $permissions;
                            }

                            if ($error === '') {
                                header('Location: admins.php?msg=updated');
                                exit;
                            }
                        } else {
                            $error = 'Failed to update admin.';
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Refresh admin list after changes
|--------------------------------------------------------------------------
*/
$adminsRes = getAllAdmins();
$admins = ($adminsRes['success'] ?? false) ? ($adminsRes['data'] ?? []) : [];

$adminLookup = [];
foreach ($admins as $adminRow) {
    $adminLookup[(int)($adminRow['id'] ?? 0)] = $adminRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admins</title>
    <link rel="stylesheet" href="assets/css/admin_layout.css">
    <link rel="stylesheet" href="assets/css/admins.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="app-shell" id="appShell">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="app-main">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="page-content">
            <?php if ($success !== ''): ?>
                <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($currentAdminRole === 'super_admin' || $editMode): ?>
                <div class="admin-grid">
                    <div class="card">
                        <h3><?php echo $editMode ? 'Edit Admin' : 'Add New Admin'; ?></h3>

                        <form method="POST" enctype="multipart/form-data" class="admin-form">
                            <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                            <input type="hidden" name="admin_id" value="<?php echo $editMode ? (int)$editAdmin['id'] : ''; ?>">

                            <div class="group avatar-group">
                                <label>Profile Picture</label>
                                <div class="avatar-preview-wrap">
                                    <?php
                                    $currentAvatar = $editMode ? ($editAdmin['avatar_url'] ?? '') : '';
                                    ?>
                                    <div class="avatar-preview">
                                        <?php if (!empty($currentAvatar)): ?>
                                            <img src="<?php echo htmlspecialchars($currentAvatar); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <span><?php echo $editMode ? htmlspecialchars(strtoupper(substr($editAdmin['name'] ?? 'A', 0, 1))) : 'A'; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                            </div>

                            <div class="group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" value="<?php echo $editMode ? htmlspecialchars($editAdmin['name'] ?? '') : ''; ?>" required>
                            </div>

                            <div class="group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo $editMode ? htmlspecialchars($editAdmin['email'] ?? '') : ''; ?>" required>
                            </div>

                            <div class="group">
                                <label for="designation">Designation</label>
                                <input type="text" id="designation" name="designation" value="<?php echo $editMode ? htmlspecialchars($editAdmin['designation'] ?? '') : ''; ?>" placeholder="e.g. Founder / Co-Founder / Content Manager" required>
                            </div>

                            <div class="group">
                                <label for="reports_to_admin_id">Reports To</label>
                                <?php $currentReportsTo = $editMode ? (int)($editAdmin['reports_to_admin_id'] ?? 0) : 0; ?>
                                <select id="reports_to_admin_id" name="reports_to_admin_id">
                                    <option value="0">No Reporting Manager</option>
                                    <?php foreach ($reportingAdmins as $manager): ?>
                                        <?php
                                        $managerId = (int)($manager['id'] ?? 0);
                                        if ($editMode && $managerId === (int)$editAdmin['id']) continue;
                                        ?>
                                        <option value="<?php echo $managerId; ?>" <?php echo $currentReportsTo === $managerId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(($manager['employee_id'] ?? '-') . ' - ' . ($manager['name'] ?? '-') . ' (' . ($manager['designation'] ?? '-') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="group">
                                <label for="role">Role</label>
                                <?php $currentEditRole = $editMode ? ($editAdmin['role'] ?? 'member') : 'member'; ?>
                                <select id="role" name="role">
                                    <option value="member" <?php echo $currentEditRole === 'member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="super_admin" <?php echo $currentEditRole === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                </select>
                            </div>

                            <div class="group">
                                <label for="status">Status</label>
                                <?php $currentStatus = $editMode ? ($editAdmin['status'] ?? 'active') : 'active'; ?>
                                <select id="status" name="status">
                                    <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="group">
                                <label for="password"><?php echo $editMode ? 'New Password (optional)' : 'Password'; ?></label>
                                <input type="password" id="password" name="password" <?php echo $editMode ? '' : 'required'; ?>>
                            </div>

                            <div class="group">
                                <label>Permissions</label>
                                <?php $selectedPermissions = $editMode ? normalizePermissions($editAdmin['permissions'] ?? []) : []; ?>
                                <div class="permissions-grid">
                                    <?php foreach ($permissionOptions as $key => $label): ?>
                                        <label class="permission-item">
                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="<?php echo htmlspecialchars($key); ?>"
                                                <?php echo in_array($key, $selectedPermissions, true) ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo htmlspecialchars($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn primary-btn">
                                <?php echo $editMode ? 'Update Admin' : 'Add Admin'; ?>
                            </button>

                            <?php if ($editMode): ?>
                                <a href="admins.php" class="btn secondary-btn">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card">
                        <h3>Admin Rules</h3>
                        <div class="rules-box">
                            <p><strong>Super Admin:</strong> Full access to everything.</p>
                            <p><strong>Member:</strong> Only assigned modules are accessible.</p>
                            <p><strong>Protection:</strong> A super admin cannot delete another super admin.</p>
                            <p><strong>Self access:</strong> Any admin can update self. A super admin can also delete self.</p>
                            <p><strong>Profile image:</strong> Upload image from edit profile form.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>All Admins</h3>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Designation</th>
                                <th>Reports To</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($admins)): ?>
                            <?php foreach ($admins as $index => $admin): ?>
                                <?php
                                $adminId = (int)($admin['id'] ?? 0);
                                $role = (string)($admin['role'] ?? 'member');
                                $status = (string)($admin['status'] ?? 'active');
                                $permissions = normalizePermissions($admin['permissions'] ?? []);
                                $isSelf = $adminId === $currentAdminId;
                                $canEdit = canEditAdmin($admin, $currentAdminId, $currentAdminRole);
                                $canDelete = canDeleteAdmin($admin, $currentAdminId, $currentAdminRole);

                                $reportsToName = '-';
                                $reportsToId = (int)($admin['reports_to_admin_id'] ?? 0);
                                if ($reportsToId > 0 && isset($adminLookup[$reportsToId])) {
                                    $reportsToName = ($adminLookup[$reportsToId]['employee_id'] ?? '-') . ' - ' . ($adminLookup[$reportsToId]['name'] ?? '-');
                                }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="table-avatar">
                                            <?php if (!empty($admin['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($admin['avatar_url']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars(strtoupper(substr($admin['name'] ?? 'A', 0, 1))); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($admin['employee_id'] ?? '-')); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($admin['name'] ?? '-'); ?>
                                        <?php if ($isSelf): ?>
                                            <span class="self-badge">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($admin['designation'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($reportsToName); ?></td>
                                    <td><span class="tag role-<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $role))); ?></span></td>
                                    <td><span class="tag status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                                    <td class="permissions-cell">
                                        <?php if ($role === 'super_admin'): ?>
                                            <span class="permission-chip all-access">All Access</span>
                                        <?php else: ?>
                                            <?php if (!empty($permissions)): ?>
                                                <?php foreach ($permissions as $perm): ?>
                                                    <span class="permission-chip"><?php echo htmlspecialchars($permissionOptions[$perm] ?? $perm); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="muted-text">No permissions</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <?php if ($canEdit): ?>
                                                <a class="mini-btn edit-btn" href="admins.php?edit_id=<?php echo $adminId; ?>">Edit</a>
                                            <?php endif; ?>

                                            <?php if ($canDelete): ?>
                                                <a class="mini-btn delete-btn" href="admins.php?delete_id=<?php echo $adminId; ?>" onclick="return confirm('Delete this admin?');">
                                                    <?php echo $isSelf ? 'Delete Self' : 'Delete'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="mini-btn disabled-btn">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="empty-state">No admins found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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

const roleSelect = document.getElementById('role');
const permissionCheckboxes = document.querySelectorAll('input[name="permissions[]"]');

function togglePermissionsByRole() {
    if (!roleSelect) return;

    const isSuperAdmin = roleSelect.value === 'super_admin';

    permissionCheckboxes.forEach(cb => {
        if (isSuperAdmin) {
            cb.checked = true;
            cb.disabled = true;
        } else {
            cb.disabled = false;
        }
    });
}

if (roleSelect) {
    roleSelect.addEventListener('change', togglePermissionsByRole);
    togglePermissionsByRole();
}
</script>
</body>
</html>