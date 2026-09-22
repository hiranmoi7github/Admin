<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editItem = null;

$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteResult = deleteSupportFeedback($deleteId);

    if ($deleteResult['success']) {
        header('Location: support_feedback.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete entry.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT MODE
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $res = getSupportFeedbackById($editId);

    if (($res['success'] ?? false) && !empty($res['data'])) {
        $editMode = true;
        $editItem = $res['data'][0];
    } else {
        $error = 'Entry not found.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $success = 'Entry updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Entry deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entryId = (int)($_POST['entry_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'new');
    $adminNote = trim($_POST['admin_note'] ?? '');

    if ($entryId <= 0) {
        $error = 'Invalid entry selected.';
    } elseif (!in_array($status, ['new', 'in_progress', 'resolved', 'closed'], true)) {
        $error = 'Invalid status selected.';
    } else {
        $result = updateSupportFeedback($entryId, $status, $adminNote);

        if ($result['success']) {
            header('Location: support_feedback.php?msg=updated');
            exit;
        } else {
            $error = 'Failed to update entry.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL
|--------------------------------------------------------------------------
*/
$res = getAllSupportFeedback();
$allRows = ($res['success'] ?? false) ? ($res['data'] ?? []) : [];

$rows = array_filter($allRows, function ($row) use ($search, $typeFilter, $statusFilter) {
    $matchSearch = true;
    $matchType = true;
    $matchStatus = true;

    if ($search !== '') {
        $haystack = strtolower(
            ($row['full_name'] ?? '') . ' ' .
            ($row['email'] ?? '') . ' ' .
            ($row['phone'] ?? '') . ' ' .
            ($row['subject'] ?? '') . ' ' .
            ($row['message'] ?? '')
        );

        $matchSearch = str_contains($haystack, strtolower($search));
    }

    if ($typeFilter !== '') {
        $matchType = (($row['request_type'] ?? '') === $typeFilter);
    }

    if ($statusFilter !== '') {
        $matchStatus = (($row['status'] ?? '') === $statusFilter);
    }

    return $matchSearch && $matchType && $matchStatus;
});

$rows = array_values($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Feedback</title>
    <link rel="stylesheet" href="assets/css/support_feedback.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Support & Feedback</h1>
            <p>Manage support requests and feedback messages from users.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($editMode && $editItem): ?>
        <div class="card edit-card">
            <h3>Update Entry</h3>

            <div class="entry-preview">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($editItem['full_name'] ?? '-'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($editItem['email'] ?? '-'); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($editItem['phone'] ?? '-'); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($editItem['request_type'] ?? '-')); ?></p>
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($editItem['subject'] ?? '-'); ?></p>
                <p><strong>Message:</strong> <?php echo htmlspecialchars($editItem['message'] ?? '-'); ?></p>
            </div>

            <form method="POST" class="edit-form">
                <input type="hidden" name="entry_id" value="<?php echo (int)$editItem['id']; ?>">

                <div class="group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php $currentStatus = $editItem['status'] ?? 'new'; ?>
                        <option value="new" <?php echo $currentStatus === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="in_progress" <?php echo $currentStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $currentStatus === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $currentStatus === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div class="group">
                    <label for="admin_note">Admin Note</label>
                    <textarea id="admin_note" name="admin_note" placeholder="Write note here..."><?php echo htmlspecialchars($editItem['admin_note'] ?? ''); ?></textarea>
                </div>

                <div class="action-row">
                    <button type="submit" class="btn primary-btn">Save Update</button>
                    <a href="support_feedback.php" class="btn secondary-btn">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card filter-card">
        <form method="GET" class="filter-form">
            <div class="group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, phone, subject, message">
            </div>

            <div class="group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="">All Types</option>
                    <option value="support" <?php echo $typeFilter === 'support' ? 'selected' : ''; ?>>Support</option>
                    <option value="feedback" <?php echo $typeFilter === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                </select>
            </div>

            <div class="group">
                <label for="status_filter">Status</label>
                <select id="status_filter" name="status">
                    <option value="">All Status</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn primary-btn">Apply</button>
                <a href="support_feedback.php" class="btn secondary-btn">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>All Entries</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Admin Note</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php $statusValue = $row['status'] ?? 'new'; ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['email'] ?? '-'); ?></div>
                                <div class="muted-text"><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></div>
                            </td>
                            <td>
                                <span class="tag type-tag <?php echo htmlspecialchars(strtolower($row['request_type'] ?? 'support')); ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['request_type'] ?? 'Support')); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['subject'] ?? '-'); ?></td>
                            <td class="message-cell"><?php echo htmlspecialchars($row['message'] ?? '-'); ?></td>
                            <td>
                                <span class="tag status-tag <?php echo htmlspecialchars(strtolower($statusValue)); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusValue))); ?>
                                </span>
                            </td>
                            <td class="note-cell"><?php echo htmlspecialchars($row['admin_note'] ?? '-'); ?></td>
                            <td>
                                <?php
                                echo !empty($row['created_at'])
                                    ? htmlspecialchars(date('d M Y', strtotime($row['created_at'])))
                                    : '-';
                                ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="mini-btn edit-btn" href="support_feedback.php?edit_id=<?php echo (int)$row['id']; ?>">Open</a>
                                    <a class="mini-btn delete-btn" href="support_feedback.php?delete_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this entry?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="empty-state">No support or feedback entries found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>