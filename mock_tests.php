<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('mock_tests');
require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Mock Tests';
$pageSubtitle = 'Create, edit, and manage mock tests.';

$success = '';
$error = '';
$editMode = false;
$editMock = null;

/*
|--------------------------------------------------------------------------
| Fetch exams
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
| Delete
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];

    $deleteResult = deleteMockTest($deleteId);

    if ($deleteResult['success'] ?? false) {
        header('Location: mock_tests.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete mock test.';
    }
}

/*
|--------------------------------------------------------------------------
| Edit load
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $mockRes = getMockTestById($editId);

    if (($mockRes['success'] ?? false) && !empty($mockRes['data'])) {
        $editMode = true;
        $editMock = $mockRes['data'][0];
    } else {
        $error = 'Mock test not found.';
    }
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Mock test added successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Mock test updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Mock test deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| Add / Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $departmentName = trim($_POST['department_name'] ?? '');
    $examId = (int)($_POST['exam_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $durationMinutes = (int)($_POST['duration_minutes'] ?? 0);
    $totalQuestions = (int)($_POST['total_questions'] ?? 0);
    $totalMarks = (int)($_POST['total_marks'] ?? 0);
    $negativeMarks = (float)($_POST['negative_marks'] ?? 0);
    $accessType = trim($_POST['access_type'] ?? 'free');
    $status = trim($_POST['status'] ?? 'draft');

    if ($departmentName === '') {
        $error = 'Please select department.';
    } elseif ($examId <= 0) {
        $error = 'Please select exam.';
    } elseif ($title === '') {
        $error = 'Mock test title is required.';
    } elseif (!isset($examMap[$examId])) {
        $error = 'Invalid exam selected.';
    } elseif (($examMap[$examId]['department_name'] ?? '') !== $departmentName) {
        $error = 'Selected exam does not belong to selected department.';
    } elseif (!in_array($accessType, ['free', 'premium'], true)) {
        $error = 'Invalid access type.';
    } elseif (!in_array($status, ['active', 'inactive', 'draft', 'coming soon'], true)) {
        $error = 'Invalid status.';
    } else {
        if ($action === 'add') {
            $result = addMockTest(
                $title,
                $examId,
                $durationMinutes,
                $totalQuestions,
                $totalMarks,
                $negativeMarks,
                $accessType,
                $status
            );

            if (($result['success'] ?? false) && !empty($result['data'][0]['id'])) {
                $newMockTestId = (int)$result['data'][0]['id'];
                header('Location: mock_test_subjects.php?mock_test_id=' . $newMockTestId);
                exit;
            } else {
                $error = 'Failed to add mock test.';
            }
        }

        if ($action === 'update') {
            $mockTestId = (int)($_POST['mock_test_id'] ?? 0);

            if ($mockTestId <= 0) {
                $error = 'Invalid mock test ID.';
            } else {
                $result = updateMockTest(
                    $mockTestId,
                    $title,
                    $examId,
                    $durationMinutes,
                    $totalQuestions,
                    $totalMarks,
                    $negativeMarks,
                    $accessType,
                    $status
                );

                if ($result['success'] ?? false) {
                    header('Location: mock_tests.php?msg=updated');
                    exit;
                } else {
                    $error = 'Failed to update mock test.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch all mock tests
|--------------------------------------------------------------------------
*/
$mockResponse = getAllMockTests();
$mockTests = ($mockResponse['success'] ?? false) ? ($mockResponse['data'] ?? []) : [];

$selectedDepartment = '';
$selectedExamId = 0;

if ($editMode && !empty($editMock)) {
    $selectedExamId = (int)($editMock['exam_id'] ?? 0);
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
    <title>Mock Tests</title>
    <link rel="stylesheet" href="assets/css/admin_layout.css">
    <link rel="stylesheet" href="assets/css/mock_tests.css">
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

            <div class="mock-grid">
                <div class="card">
                    <h3><?php echo $editMode ? 'Edit Mock Test' : 'Add New Mock Test'; ?></h3>

                    <form method="POST" class="mock-form">
                        <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                        <input type="hidden" name="mock_test_id" value="<?php echo $editMode ? (int)$editMock['id'] : ''; ?>">

                        <div class="group">
                            <label for="department_name">Department</label>
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
                            <label for="exam_id">Exam</label>
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
                            <label for="title">Mock Test Title</label>
                            <input
                                type="text"
                                id="title"
                                name="title"
                                value="<?php echo $editMode ? htmlspecialchars($editMock['title'] ?? '') : ''; ?>"
                                required
                            >
                        </div>

                        <div class="group two-col">
                            <div>
                                <label for="duration_minutes">Duration (Minutes)</label>
                                <input
                                    type="number"
                                    id="duration_minutes"
                                    name="duration_minutes"
                                    value="<?php echo $editMode ? (int)($editMock['duration_minutes'] ?? 0) : 0; ?>"
                                    min="0"
                                >
                            </div>
                            <div>
                                <label for="total_questions">Total Questions</label>
                                <input
                                    type="number"
                                    id="total_questions"
                                    name="total_questions"
                                    value="<?php echo $editMode ? (int)($editMock['total_questions'] ?? 0) : 0; ?>"
                                    min="0"
                                >
                            </div>
                        </div>

                        <div class="group two-col">
                            <div>
                                <label for="total_marks">Total Marks</label>
                                <input
                                    type="number"
                                    id="total_marks"
                                    name="total_marks"
                                    value="<?php echo $editMode ? (int)($editMock['total_marks'] ?? 0) : 0; ?>"
                                    min="0"
                                >
                            </div>
                            <div>
                                <label for="negative_marks">Negative Marks</label>
                                <input
                                    type="number"
                                    step="0.25"
                                    id="negative_marks"
                                    name="negative_marks"
                                    value="<?php echo $editMode ? htmlspecialchars((string)($editMock['negative_marks'] ?? 0)) : '0'; ?>"
                                    min="0"
                                >
                            </div>
                        </div>

                        <div class="group two-col">
                            <div>
                                <label for="access_type">Access Type</label>
                                <?php $currentAccess = $editMode ? ($editMock['access_type'] ?? 'free') : 'free'; ?>
                                <select id="access_type" name="access_type">
                                    <option value="free" <?php echo $currentAccess === 'free' ? 'selected' : ''; ?>>Free</option>
                                    <option value="premium" <?php echo $currentAccess === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                </select>
                            </div>
                            <div>
                                <label for="status">Status</label>
                                <?php $currentStatus = $editMode ? ($editMock['status'] ?? 'draft') : 'draft'; ?>
                                <select id="status" name="status">
                                    <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="draft" <?php echo $currentStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="coming soon" <?php echo $currentStatus === 'coming soon' ? 'selected' : ''; ?>>Coming Soon</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn primary-btn">
                            <?php echo $editMode ? 'Update Mock Test' : 'Add Mock Test'; ?>
                        </button>

                        <?php if ($editMode): ?>
                            <a href="mock_tests.php" class="btn secondary-btn">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card">
                    <h3>All Mock Tests</h3>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Department</th>
                                    <th>Exam</th>
                                    <th>Title</th>
                                    <th>Duration</th>
                                    <th>Questions</th>
                                    <th>Marks</th>
                                    <th>Negative</th>
                                    <th>Access</th>
                                    <th>Status</th>
                                    <th>Subjects</th>
                                    <th>Questions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($mockTests)): ?>
                                <?php foreach ($mockTests as $index => $mock): ?>
                                    <?php
                                    $examId = (int)($mock['exam_id'] ?? 0);
                                    $examName = $examMap[$examId]['exam_name'] ?? '-';
                                    $departmentName = $examMap[$examId]['department_name'] ?? '-';
                                    $statusValue = $mock['status'] ?? 'draft';
                                    $accessValue = $mock['access_type'] ?? 'free';
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($departmentName); ?></td>
                                        <td><?php echo htmlspecialchars($examName); ?></td>
                                        <td><?php echo htmlspecialchars($mock['title'] ?? '-'); ?></td>
                                        <td><?php echo (int)($mock['duration_minutes'] ?? 0); ?> min</td>
                                        <td><?php echo (int)($mock['total_questions'] ?? 0); ?></td>
                                        <td><?php echo (int)($mock['total_marks'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars((string)($mock['negative_marks'] ?? 0)); ?></td>
                                        <td>
                                            <span class="tag <?php echo htmlspecialchars(strtolower($accessValue)); ?>">
                                                <?php echo htmlspecialchars(ucfirst($accessValue)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="tag <?php echo htmlspecialchars(str_replace(' ', '-', strtolower($statusValue))); ?>">
                                                <?php echo htmlspecialchars(ucwords($statusValue)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="mini-btn edit-btn" href="mock_test_subjects.php?mock_test_id=<?php echo (int)$mock['id']; ?>">
                                                Manage Subjects
                                            </a>
                                        </td>
                                        <td>
                                            <a class="mini-btn edit-btn" href="mock_test_questions.php?mock_test_id=<?php echo (int)$mock['id']; ?>">
                                                Manage Questions
                                            </a>
                                        </td>
                                        <td>
                                            <div class="row-actions">
                                                <a class="mini-btn edit-btn" href="mock_tests.php?edit_id=<?php echo (int)$mock['id']; ?>">Edit</a>
                                                <a class="mini-btn delete-btn" href="mock_tests.php?delete_id=<?php echo (int)$mock['id']; ?>" onclick="return confirm('Delete this mock test?');">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" class="empty-state">No mock tests found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

const departmentSelect = document.getElementById('department_name');
const examSelect = document.getElementById('exam_id');

function filterExams() {
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

if (departmentSelect && examSelect) {
    departmentSelect.addEventListener('change', filterExams);
    filterExams();
}
</script>
</body>
</html>