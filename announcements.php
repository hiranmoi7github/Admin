<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editAnnouncement = null;

/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/
$uploadDir = __DIR__ . '/assets/uploads/announcements/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function getSectionLabel(string $type): string
{
    if ($type === 'scrolling_top') return 'Scrolling Top Announcement';
    if ($type === 'bottom_big') return 'Bottom Big Announcement';
    if ($type === 'bottom_small') return 'Bottom Small Announcement';
    return ucfirst(str_replace('_', ' ', $type));
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteResult = deleteAnnouncement($deleteId);

    if ($deleteResult['success']) {
        header('Location: announcements.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete announcement.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT LOAD
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $res = getAnnouncementById($editId);

    if (($res['success'] ?? false) && !empty($res['data'])) {
        $editMode = true;
        $editAnnouncement = $res['data'][0];
    } else {
        $error = 'Announcement not found.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Announcement added successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Announcement updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Announcement deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $sectionType = trim($_POST['section_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $showButton = isset($_POST['show_button']);
    $buttonText = trim($_POST['button_text'] ?? '');
    $buttonLink = trim($_POST['button_link'] ?? '');
    $buttonColor = trim($_POST['button_color'] ?? '#111827');
    $backgroundColor = trim($_POST['background_color'] ?? '#6d28d9');
    $textColor = trim($_POST['text_color'] ?? '#ffffff');
    $isEnabled = isset($_POST['is_enabled']);
    $status = trim($_POST['status'] ?? 'active');

    if ($sectionType === '') {
        $error = 'Section type is required.';
    } elseif (!in_array($sectionType, ['scrolling_top', 'bottom_big', 'bottom_small'], true)) {
        $error = 'Invalid announcement section.';
    } elseif ($title === '') {
        $error = 'Title is required.';
    } elseif ($message === '') {
        $error = 'Message is required.';
    } else {
        $imageUrl = '';

        if ($action === 'update' && !empty($editAnnouncement['image_url'])) {
            $imageUrl = $editAnnouncement['image_url'];
        }

        if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['announcement_image']['tmp_name'];
            $originalName = $_FILES['announcement_image']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $error = 'Only jpg, jpeg, png, webp, gif files are allowed.';
            } else {
                $newFileName = 'announcement_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $imageUrl = 'assets/uploads/announcements/' . $newFileName;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if ($error === '') {
            if (!$showButton) {
                $buttonText = '';
                $buttonLink = '';
            }

            if ($action === 'add') {
                $result = addAnnouncement(
                    $sectionType,
                    $title,
                    $message,
                    $imageUrl,
                    $showButton,
                    $buttonText,
                    $buttonLink,
                    $buttonColor,
                    $backgroundColor,
                    $textColor,
                    $isEnabled,
                    $status
                );

                if ($result['success']) {
                    header('Location: announcements.php?msg=added');
                    exit;
                } else {
                    $error = 'Failed to add announcement.';
                }
            }

            if ($action === 'update') {
                $announcementId = (int)($_POST['announcement_id'] ?? 0);

                if ($announcementId <= 0) {
                    $error = 'Invalid announcement ID.';
                } else {
                    $result = updateAnnouncement(
                        $announcementId,
                        $sectionType,
                        $title,
                        $message,
                        $imageUrl,
                        $showButton,
                        $buttonText,
                        $buttonLink,
                        $buttonColor,
                        $backgroundColor,
                        $textColor,
                        $isEnabled,
                        $status
                    );

                    if ($result['success']) {
                        header('Location: announcements.php?msg=updated');
                        exit;
                    } else {
                        $error = 'Failed to update announcement.';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL
|--------------------------------------------------------------------------
*/
$res = getAllAnnouncements();
$announcements = ($res['success'] ?? false) ? ($res['data'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <link rel="stylesheet" href="assets/css/announcements.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Manage Announcements</h1>
            <p>Manage scrolling top, bottom big, and bottom small announcements with colors and button control.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="announce-grid">
        <div class="card">
            <h3><?php echo $editMode ? 'Edit Announcement' : 'Add New Announcement'; ?></h3>

            <form method="POST" enctype="multipart/form-data" class="announce-form">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                <input type="hidden" name="announcement_id" value="<?php echo $editMode ? (int)$editAnnouncement['id'] : ''; ?>">

                <div class="group">
                    <label for="section_type">Announcement Section</label>
                    <?php $currentSection = $editMode ? ($editAnnouncement['section_type'] ?? 'scrolling_top') : 'scrolling_top'; ?>
                    <select id="section_type" name="section_type">
                        <option value="scrolling_top" <?php echo $currentSection === 'scrolling_top' ? 'selected' : ''; ?>>Scrolling Top Announcement</option>
                        <option value="bottom_big" <?php echo $currentSection === 'bottom_big' ? 'selected' : ''; ?>>Bottom Big Announcement</option>
                        <option value="bottom_small" <?php echo $currentSection === 'bottom_small' ? 'selected' : ''; ?>>Bottom Small Announcement</option>
                    </select>
                </div>

                <div class="group">
                    <label for="title">Title / Heading</label>
                    <input type="text" id="title" name="title" value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['title'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required><?php echo $editMode ? htmlspecialchars($editAnnouncement['message'] ?? '') : ''; ?></textarea>
                </div>

                <div class="group">
                    <label for="announcement_image">Optional Image / PNG Upload</label>
                    <input type="file" id="announcement_image" name="announcement_image" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <?php if ($editMode && !empty($editAnnouncement['image_url'])): ?>
                        <div class="image-preview-wrap">
                            <img src="<?php echo htmlspecialchars($editAnnouncement['image_url']); ?>" alt="Announcement Image" class="image-preview">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="group two-col">
                    <div>
                        <label for="background_color">Announcement Colour</label>
                        <input type="color" id="background_color" name="background_color" value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['background_color'] ?? '#6d28d9') : '#6d28d9'; ?>">
                    </div>
                    <div>
                        <label for="text_color">Text Colour</label>
                        <input type="color" id="text_color" name="text_color" value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['text_color'] ?? '#ffffff') : '#ffffff'; ?>">
                    </div>
                </div>

                <div class="group checkbox-group">
                    <?php $enabledChecked = $editMode ? !empty($editAnnouncement['is_enabled']) : true; ?>
                    <label class="checkbox-label">
                        <input type="checkbox" id="is_enabled" name="is_enabled" <?php echo $enabledChecked ? 'checked' : ''; ?>>
                        Announcement On / Off
                    </label>
                </div>

                <div class="group checkbox-group">
                    <?php $showBtnChecked = $editMode ? !empty($editAnnouncement['show_button']) : false; ?>
                    <label class="checkbox-label">
                        <input type="checkbox" id="show_button" name="show_button" <?php echo $showBtnChecked ? 'checked' : ''; ?>>
                        Show Button
                    </label>
                </div>

                <div id="button-fields" class="<?php echo $showBtnChecked ? '' : 'hidden'; ?>">
                    <div class="group">
                        <label for="button_text">Button Name</label>
                        <input
                            type="text"
                            id="button_text"
                            name="button_text"
                            value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['button_text'] ?? '') : ''; ?>"
                            placeholder="e.g. Explore Now"
                        >
                    </div>

                    <div class="group">
                        <label for="button_link">Button Link</label>
                        <input
                            type="text"
                            id="button_link"
                            name="button_link"
                            value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['button_link'] ?? '') : ''; ?>"
                            placeholder="e.g. /pass or https://..."
                        >
                    </div>

                    <div class="group">
                        <label for="button_color">Button Colour</label>
                        <input type="color" id="button_color" name="button_color" value="<?php echo $editMode ? htmlspecialchars($editAnnouncement['button_color'] ?? '#111827') : '#111827'; ?>">
                    </div>
                </div>

                <div class="group">
                    <label for="status">Status</label>
                    <?php $currentStatus = $editMode ? ($editAnnouncement['status'] ?? 'active') : 'active'; ?>
                    <select id="status" name="status">
                        <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $currentStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <button type="submit" class="btn primary-btn">
                    <?php echo $editMode ? 'Update Announcement' : 'Add Announcement'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="announcements.php" class="btn secondary-btn">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>All Announcements</h3>

            <div class="announcements-list">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <?php
                        $statusValue = $announcement['status'] ?? 'draft';
                        $sectionType = $announcement['section_type'] ?? '';
                        $showButton = !empty($announcement['show_button']);
                        $isEnabled = !empty($announcement['is_enabled']);
                        $backgroundColor = $announcement['background_color'] ?? '#6d28d9';
                        $textColor = $announcement['text_color'] ?? '#ffffff';
                        $buttonColor = $announcement['button_color'] ?? '#111827';
                        ?>
                        <div class="announcement-card" style="background: <?php echo htmlspecialchars($backgroundColor); ?>; color: <?php echo htmlspecialchars($textColor); ?>;">
                            <div class="announcement-head">
                                <div>
                                    <h4><?php echo htmlspecialchars($announcement['title'] ?? '-'); ?></h4>
                                    <p class="announcement-type" style="color: <?php echo htmlspecialchars($textColor); ?>;"><?php echo htmlspecialchars(getSectionLabel($sectionType)); ?></p>
                                </div>
                                <div class="tag-group">
                                    <span class="tag <?php echo htmlspecialchars(strtolower($statusValue)); ?>">
                                        <?php echo htmlspecialchars(ucfirst($statusValue)); ?>
                                    </span>
                                    <span class="tag toggle-tag <?php echo $isEnabled ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $isEnabled ? 'On' : 'Off'; ?>
                                    </span>
                                </div>
                            </div>

                            <p class="announcement-msg" style="color: <?php echo htmlspecialchars($textColor); ?>;"><?php echo htmlspecialchars($announcement['message'] ?? '-'); ?></p>

                            <?php if (!empty($announcement['image_url'])): ?>
                                <div class="card-image-wrap">
                                    <img src="<?php echo htmlspecialchars($announcement['image_url']); ?>" alt="Announcement Image" class="card-image">
                                </div>
                            <?php endif; ?>

                            <?php if ($showButton): ?>
                                <div class="button-preview-wrap">
                                    <button type="button" class="preview-btn" style="background: <?php echo htmlspecialchars($buttonColor); ?>;">
                                        <?php echo htmlspecialchars($announcement['button_text'] ?? 'Button'); ?>
                                    </button>
                                    <?php if (!empty($announcement['button_link'])): ?>
                                        <div class="button-link-preview"><?php echo htmlspecialchars($announcement['button_link']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="row-actions">
                                <a class="mini-btn edit-btn" href="announcements.php?edit_id=<?php echo (int)$announcement['id']; ?>">Edit</a>
                                <a class="mini-btn delete-btn" href="announcements.php?delete_id=<?php echo (int)$announcement['id']; ?>" onclick="return confirm('Delete this announcement?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No announcements found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const showButtonCheckbox = document.getElementById('show_button');
const buttonFields = document.getElementById('button-fields');

function toggleButtonFields() {
    if (!showButtonCheckbox || !buttonFields) return;
    if (showButtonCheckbox.checked) {
        buttonFields.classList.remove('hidden');
    } else {
        buttonFields.classList.add('hidden');
    }
}

if (showButtonCheckbox) {
    showButtonCheckbox.addEventListener('change', toggleButtonFields);
    toggleButtonFields();
}
</script>
</body>
</html>