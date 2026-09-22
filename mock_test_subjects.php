<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('mock_tests');
require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Mock Test Subjects';
$pageSubtitle = 'Assign subject rules for this mock test.';

$success = '';
$error = '';

$mockTestId = (int)($_GET['mock_test_id'] ?? $_POST['mock_test_id'] ?? 0);

if ($mockTestId <= 0) {
    exit('Invalid mock test ID');
}

/*
|--------------------------------------------------------------------------
| FETCH MOCK TEST
|--------------------------------------------------------------------------
*/
$mockRes = getMockTestById($mockTestId);

if (!(($mockRes['success'] ?? false) && !empty($mockRes['data']))) {
    exit('Mock test not found');
}

$mockTest = $mockRes['data'][0];
$examId = (int)$mockTest['exam_id'];

/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS FOR THIS EXAM
|--------------------------------------------------------------------------
*/
$subjectsRes = getAllSubjects();
$allSubjects = ($subjectsRes['success'] ?? false) ? ($subjectsRes['data'] ?? []) : [];

$subjects = array_values(array_filter($allSubjects, function ($s) use ($examId) {
    return (int)($s['exam_id'] ?? 0) === $examId;
}));

$subjectMap = [];
foreach ($subjects as $s) {
    $subjectMap[(int)$s['id']] = $s['subject_name'] ?? '-';
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];

    $deleteResult = deleteMockTestSubject($deleteId);

    if ($deleteResult['success'] ?? false) {
        header("Location: mock_test_subjects.php?mock_test_id=$mockTestId&msg=deleted");
        exit;
    } else {
        $error = 'Failed to delete subject rule.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Subject rule added successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Subject rule deleted successfully.';
}

/*
|--------------------------------------------------------------------------
| ADD SUBJECT RULE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $count = (int)($_POST['question_count'] ?? 0);

    if ($subjectId <= 0) {
        $error = 'Please select subject.';
    } elseif ($count <= 0) {
        $error = 'Enter valid question count.';
    } else {
        $res = addMockTestSubject($mockTestId, $subjectId, $count);

        if ($res['success'] ?? false) {
            header("Location: mock_test_subjects.php?mock_test_id=$mockTestId&msg=added");
            exit;
        } else {
            $error = json_encode($res);
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RULES
|--------------------------------------------------------------------------
*/
$rulesRes = getMockTestSubjects($mockTestId);
$rules = ($rulesRes['success'] ?? false) ? ($rulesRes['data'] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock Test Subjects</title>
    <link rel="stylesheet" href="assets/css/admin_layout.css">
    <link rel="stylesheet" href="assets/css/mock_test_subjects.css">
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
                    <h3>Add Subject Rule</h3>

                    <form method="POST" class="mock-form">
                        <input type="hidden" name="mock_test_id" value="<?php echo $mockTestId; ?>">

                        <div class="group">
                            <label for="subject_id">Subject</label>
                            <select id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>">
                                        <?php echo htmlspecialchars($s['subject_name'] ?? '-'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="group">
                            <label for="question_count">Question Count</label>
                            <input type="number" id="question_count" name="question_count" min="1" placeholder="Enter count" required>
                        </div>

                        <button type="submit" class="btn primary-btn">Add Subject Rule</button>
                        <a href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>" class="btn secondary-btn">Go to Questions</a>
                    </form>
                </div>

                <div class="card">
                    <h3><?php echo htmlspecialchars($mockTest['title'] ?? 'Mock Test'); ?> - Assigned Subjects</h3>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject</th>
                                    <th>Question Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rules)): ?>
                                    <?php foreach ($rules as $i => $r): ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($subjectMap[(int)$r['subject_id']] ?? '-'); ?></td>
                                            <td><?php echo (int)($r['question_count'] ?? 0); ?></td>
                                            <td>
                                                <div class="row-actions">
                                                    <a
                                                        class="mini-btn delete-btn"
                                                        href="mock_test_subjects.php?mock_test_id=<?php echo $mockTestId; ?>&delete_id=<?php echo (int)$r['id']; ?>"
                                                        onclick="return confirm('Delete this subject rule?');"
                                                    >
                                                        Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">No subject rules added yet.</td>
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
</script>
</body>
</html>