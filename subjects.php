<?php
require_once __DIR__ . '/includes/auth_check.php';
requireContentAccess();

require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editSubject = null;

/*
|--------------------------------------------------------------------------
| DELETE SUBJECT
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $deleteResult = deleteSubject($deleteId);

    if ($deleteResult['success']) {
        header('Location: subjects.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete subject.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT MODE LOAD
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $subjectResult = getSubjectById($editId);

    if (($subjectResult['success'] ?? false) && !empty($subjectResult['data'])) {
        $editMode = true;
        $editSubject = $subjectResult['data'][0];
    } else {
        $error = 'Subject not found.';
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = 'Subject added successfully.';
    } elseif ($_GET['msg'] === 'updated') {
        $success = 'Subject updated successfully.';
    } elseif ($_GET['msg'] === 'deleted') {
        $success = 'Subject deleted successfully.';
    }
}

/*
|--------------------------------------------------------------------------
| FETCH EXAMS FIRST
|--------------------------------------------------------------------------
*/
$examResponse = getAllExams();
$exams = ($examResponse['success'] ?? false) ? ($examResponse['data'] ?? []) : [];

$departmentOptions = [];
$examMap = [];

foreach ($exams as $exam) {
    $departmentName = trim((string)($exam['department_name'] ?? ''));
    if ($departmentName !== '' && !in_array($departmentName, $departmentOptions, true)) {
        $departmentOptions[] = $departmentName;
    }

    $examMap[(int)$exam['id']] = [
        'exam_name' => $exam['exam_name'] ?? '',
        'department_name' => $departmentName
    ];
}

sort($departmentOptions);

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');
    $departmentName = trim($_POST['department_name'] ?? '');
    $examId = (int) ($_POST['exam_id'] ?? 0);
    $subjectName = trim($_POST['subject_name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($departmentName === '') {
        $error = 'Please select a department.';
    } elseif ($examId <= 0) {
        $error = 'Please select an exam.';
    } elseif ($subjectName === '') {
        $error = 'Subject name is required.';
    } elseif (!isset($examMap[$examId])) {
        $error = 'Selected exam is invalid.';
    } elseif (($examMap[$examId]['department_name'] ?? '') !== $departmentName) {
        $error = 'Selected exam does not belong to selected department.';
    } else {
        if ($action === 'add') {
            $result = addSubject($examId, $subjectName, $status);

            if ($result['success']) {
                header('Location: subjects.php?msg=added');
                exit;
            } else {
                $error = 'Failed to add subject.';
            }
        }

        if ($action === 'update') {
            $subjectId = (int) ($_POST['subject_id'] ?? 0);

            if ($subjectId <= 0) {
                $error = 'Invalid subject ID.';
            } else {
                $result = updateSubject($subjectId, $examId, $subjectName, $status);

                if ($result['success']) {
                    header('Location: subjects.php?msg=updated');
                    exit;
                } else {
                    $error = 'Failed to update subject.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS
|--------------------------------------------------------------------------
*/
$subjectResponse = getAllSubjects();
$subjects = ($subjectResponse['success'] ?? false) ? ($subjectResponse['data'] ?? []) : [];

/*
|--------------------------------------------------------------------------
| EDIT DEFAULTS
|--------------------------------------------------------------------------
*/
$selectedDepartment = '';
$selectedExamId = 0;

if ($editMode && !empty($editSubject)) {
    $selectedExamId = (int)($editSubject['exam_id'] ?? 0);
    if (isset($examMap[$selectedExamId])) {
        $selectedDepartment = $examMap[$selectedExamId]['department_name'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects</title>
    <link rel="stylesheet" href="assets/css/subjects.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Manage Subjects</h1>
            <p>Select department, select exam, then manage subjects.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="subject-grid">
        <div class="card">
            <h3><?php echo $editMode ? 'Edit Subject' : 'Add New Subject'; ?></h3>

            <form method="POST" class="subject-form">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $editMode ? (int)$editSubject['id'] : ''; ?>">

                <div class="group">
                    <label for="department_name">Select Department</label>
                    <select id="department_name" name="department_name" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departmentOptions as $department): ?>
                            <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $selectedDepartment === $department ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="exam_id">Select Exam</label>
                    <select id="exam_id" name="exam_id" required>
                        <option value="">Select Exam</option>
                        <?php foreach ($exams as $exam): ?>
                            <option
                                value="<?php echo (int)$exam['id']; ?>"
                                data-department="<?php echo htmlspecialchars($exam['department_name'] ?? ''); ?>"
                                <?php echo $selectedExamId === (int)$exam['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($exam['exam_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="subject_name">Subject Name</label>
                    <input
                        type="text"
                        id="subject_name"
                        name="subject_name"
                        value="<?php echo $editMode ? htmlspecialchars($editSubject['subject_name'] ?? '') : ''; ?>"
                        placeholder="e.g. General Knowledge"
                        required
                    >
                </div>

                <div class="group">
                    <label for="status">Status</label>
                    <?php $currentStatus = $editMode ? ($editSubject['status'] ?? 'active') : 'active'; ?>
                    <select id="status" name="status">
                        <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="draft" <?php echo $currentStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="coming soon" <?php echo $currentStatus === 'coming soon' ? 'selected' : ''; ?>>Coming Soon</option>
                    </select>
                </div>

                <button type="submit" class="btn primary-btn">
                    <?php echo $editMode ? 'Update Subject' : 'Add Subject'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="subjects.php" class="btn secondary-btn">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>All Subjects</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department</th>
                            <th>Exam</th>
                            <th>Subject Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $index => $subject): ?>
                            <?php
                            $examId = (int)($subject['exam_id'] ?? 0);
                            $examName = $examMap[$examId]['exam_name'] ?? '-';
                            $departmentName = $examMap[$examId]['department_name'] ?? '-';
                            $statusValue = $subject['status'] ?? 'draft';
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($departmentName); ?></td>
                                <td><?php echo htmlspecialchars($examName); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="tag <?php echo htmlspecialchars(str_replace(' ', '-', strtolower($statusValue))); ?>">
                                        <?php echo htmlspecialchars(ucwords($statusValue)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="mini-btn edit-btn" href="subjects.php?edit_id=<?php echo (int)$subject['id']; ?>">Edit</a>
                                        <a class="mini-btn delete-btn" href="subjects.php?delete_id=<?php echo (int)$subject['id']; ?>" onclick="return confirm('Delete this subject?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">No subjects found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const departmentSelect = document.getElementById('department_name');
const examSelect = document.getElementById('exam_id');

function filterExamsByDepartment() {
    const selectedDepartment = departmentSelect.value;

    Array.from(examSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }

        const optionDepartment = option.getAttribute('data-department') || '';
        option.style.display = (selectedDepartment === '' || optionDepartment === selectedDepartment) ? 'block' : 'none';
    });

    const selectedOption = examSelect.options[examSelect.selectedIndex];
    if (selectedOption && selectedOption.value !== '' && selectedOption.style.display === 'none') {
        examSelect.value = '';
    }
}

departmentSelect.addEventListener('change', filterExamsByDepartment);
filterExamsByDepartment();
</script>
</body>
</html>