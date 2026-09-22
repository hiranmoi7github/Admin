<?php
require_once __DIR__ . '/includes/auth_check.php';
requireContentAccess();
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editMaterial = null;

/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/
$uploadDir = __DIR__ . '/assets/uploads/materials/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/*
|--------------------------------------------------------------------------
| FETCH EXAMS
|--------------------------------------------------------------------------
*/
$examResponse = getAllExams();
$exams = ($examResponse['success'] ?? false) ? ($examResponse['data'] ?? []) : [];

$examMap = [];
foreach ($exams as $exam) {
    $examMap[(int)$exam['id']] = $exam['exam_name'] ?? '-';
}

/*
|--------------------------------------------------------------------------
| DELETE MATERIAL
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteResult = deleteMaterial($deleteId);

    if ($deleteResult['success']) {
        header('Location: materials.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete material.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT MODE
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $materialRes = getMaterialById($editId);

    if (($materialRes['success'] ?? false) && !empty($materialRes['data'])) {
        $editMode = true;
        $editMaterial = $materialRes['data'][0];
    } else {
        $error = 'Material not found.';
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MSG
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Material added successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Material updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Material deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $examId = (int)($_POST['exam_id'] ?? 0);
    $materialTitle = trim($_POST['material_title'] ?? '');
    $materialCategory = trim($_POST['material_category'] ?? '');
    $accessType = trim($_POST['access_type'] ?? 'free');
    $status = trim($_POST['status'] ?? 'active');

    if ($examId <= 0) {
        $error = 'Please select an exam.';
    } elseif ($materialTitle === '') {
        $error = 'Material title is required.';
    } elseif (!in_array($materialCategory, ['notes', 'pyq', 'important_notes'], true)) {
        $error = 'Invalid material category.';
    } elseif (!in_array($accessType, ['free', 'paid'], true)) {
        $error = 'Invalid access type.';
    } else {
        $pdfUrl = '';

        if ($action === 'update' && !empty($editMaterial['pdf_url'])) {
            $pdfUrl = $editMaterial['pdf_url'];
        }

        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['pdf_file']['tmp_name'];
            $originalName = $_FILES['pdf_file']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($extension !== 'pdf') {
                $error = 'Only PDF files are allowed.';
            } else {
                $newFileName = 'material_' . time() . '_' . rand(1000, 9999) . '.pdf';
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $pdfUrl = 'assets/uploads/materials/' . $newFileName;
                } else {
                    $error = 'Failed to upload PDF file.';
                }
            }
        } elseif ($action === 'add') {
            $error = 'Please upload a PDF file.';
        }

        if ($error === '') {
            if ($action === 'add') {
                $result = addMaterial(
                    $examId,
                    $materialTitle,
                    $materialCategory,
                    $accessType,
                    $pdfUrl,
                    $status
                );

                if ($result['success']) {
                    header('Location: materials.php?msg=added');
                    exit;
                } else {
                    $error = 'Failed to add material.';
                }
            }

            if ($action === 'update') {
                $materialId = (int)($_POST['material_id'] ?? 0);

                if ($materialId <= 0) {
                    $error = 'Invalid material ID.';
                } else {
                    $result = updateMaterial(
                        $materialId,
                        $examId,
                        $materialTitle,
                        $materialCategory,
                        $accessType,
                        $pdfUrl,
                        $status
                    );

                    if ($result['success']) {
                        header('Location: materials.php?msg=updated');
                        exit;
                    } else {
                        $error = 'Failed to update material.';
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH ALL MATERIALS
|--------------------------------------------------------------------------
*/
$materialsRes = getAllMaterials();
$materials = ($materialsRes['success'] ?? false) ? ($materialsRes['data'] ?? []) : [];

function formatCategoryLabel(string $category): string
{
    if ($category === 'notes') return 'Notes';
    if ($category === 'pyq') return 'Previous Year Papers';
    if ($category === 'important_notes') return 'Important Notes';
    return ucfirst($category);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Materials</title>
    <link rel="stylesheet" href="assets/css/materials.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Manage Materials</h1>
            <p>Upload PDF materials like notes, previous year papers, and important notes.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="materials-grid">
        <div class="card">
            <h3><?php echo $editMode ? 'Edit Material' : 'Add New Material'; ?></h3>

            <form method="POST" enctype="multipart/form-data" class="material-form">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                <input type="hidden" name="material_id" value="<?php echo $editMode ? (int)$editMaterial['id'] : ''; ?>">

                <div class="group">
                    <label for="exam_id">Exam Title</label>
                    <select id="exam_id" name="exam_id" required>
                        <option value="">Select Exam</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo (int)$exam['id']; ?>" <?php echo ($editMode && (int)$editMaterial['exam_id'] === (int)$exam['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name'] ?? '-'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="material_title">Material Title</label>
                    <input
                        type="text"
                        id="material_title"
                        name="material_title"
                        value="<?php echo $editMode ? htmlspecialchars($editMaterial['material_title'] ?? '') : ''; ?>"
                        placeholder="e.g. ADRE Grade 3 Maths Notes"
                        required
                    >
                </div>

                <div class="group">
                    <label for="material_category">Material Category</label>
                    <?php $currentCategory = $editMode ? ($editMaterial['material_category'] ?? 'notes') : 'notes'; ?>
                    <select id="material_category" name="material_category">
                        <option value="notes" <?php echo $currentCategory === 'notes' ? 'selected' : ''; ?>>Notes</option>
                        <option value="pyq" <?php echo $currentCategory === 'pyq' ? 'selected' : ''; ?>>Previous Year Papers</option>
                        <option value="important_notes" <?php echo $currentCategory === 'important_notes' ? 'selected' : ''; ?>>Important Notes</option>
                    </select>
                </div>

                <div class="group">
                    <label for="access_type">Access Type</label>
                    <?php $currentAccess = $editMode ? ($editMaterial['access_type'] ?? 'free') : 'free'; ?>
                    <select id="access_type" name="access_type">
                        <option value="free" <?php echo $currentAccess === 'free' ? 'selected' : ''; ?>>Free</option>
                        <option value="paid" <?php echo $currentAccess === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>

                <div class="group">
                    <label for="pdf_file">PDF Upload</label>
                    <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" <?php echo $editMode ? '' : 'required'; ?>>
                    <?php if ($editMode && !empty($editMaterial['pdf_url'])): ?>
                        <a class="pdf-link" href="<?php echo htmlspecialchars($editMaterial['pdf_url']); ?>" target="_blank">View Current PDF</a>
                    <?php endif; ?>
                </div>

                <div class="group">
                    <label for="status">Status</label>
                    <?php $currentStatus = $editMode ? ($editMaterial['status'] ?? 'active') : 'active'; ?>
                    <select id="status" name="status">
                        <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $currentStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>

                <button type="submit" class="btn primary-btn">
                    <?php echo $editMode ? 'Update Material' : 'Add Material'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="materials.php" class="btn secondary-btn">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>All Materials</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Exam</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Access</th>
                            <th>PDF</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($materials)): ?>
                        <?php foreach ($materials as $index => $material): ?>
                            <?php
                            $statusValue = $material['status'] ?? 'draft';
                            $accessValue = $material['access_type'] ?? 'free';
                            $examTitle = $examMap[(int)($material['exam_id'] ?? 0)] ?? '-';
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($examTitle); ?></td>
                                <td><?php echo htmlspecialchars($material['material_title'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(formatCategoryLabel($material['material_category'] ?? '')); ?></td>
                                <td>
                                    <span class="tag <?php echo htmlspecialchars(strtolower($accessValue)); ?>">
                                        <?php echo htmlspecialchars(ucfirst($accessValue)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($material['pdf_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($material['pdf_url']); ?>" target="_blank" class="pdf-link">Open PDF</a>
                                    <?php else: ?>
                                        <span class="no-file">No PDF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tag <?php echo htmlspecialchars(strtolower($statusValue)); ?>">
                                        <?php echo htmlspecialchars(ucfirst($statusValue)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="mini-btn edit-btn" href="materials.php?edit_id=<?php echo (int)$material['id']; ?>">Edit</a>
                                        <a class="mini-btn delete-btn" href="materials.php?delete_id=<?php echo (int)$material['id']; ?>" onclick="return confirm('Delete this material?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">No materials found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>