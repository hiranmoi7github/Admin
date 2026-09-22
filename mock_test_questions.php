<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('mock_tests');
require_once __DIR__ . '/includes/supabase_api.php';

$pageTitle = 'Mock Test Questions';
$pageSubtitle = 'Map final questions into this mock test.';

$success = '';
$error = '';
$editMode = false;
$editRow = null;

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
$examId = (int)($mockTest['exam_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| FETCH QUESTIONS FOR THIS EXAM
|--------------------------------------------------------------------------
*/
$questionsRes = getRows(
    'questions',
    'select=*&exam_id=eq.' . $examId . '&order=id.desc&limit=10000',
    true
);

$allQuestions = ($questionsRes['success'] ?? false) ? ($questionsRes['data'] ?? []) : [];

/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS FOR LABELS
|--------------------------------------------------------------------------
*/
$subjectsRes = getAllSubjects();
$subjects = ($subjectsRes['success'] ?? false) ? ($subjectsRes['data'] ?? []) : [];

$subjectMap = [];
foreach ($subjects as $subject) {
    $subjectMap[(int)($subject['id'] ?? 0)] = $subject['subject_name'] ?? '-';
}

/*
|--------------------------------------------------------------------------
| QUESTION LOOKUP
|--------------------------------------------------------------------------
*/
$questionMap = [];
foreach ($allQuestions as $question) {
    $questionMap[(int)($question['id'] ?? 0)] = $question;
}

/*
|--------------------------------------------------------------------------
| DELETE SINGLE QUESTION MAPPING
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];

    $deleteResult = deleteMockTestQuestion($deleteId);

    if ($deleteResult['success'] ?? false) {
        header('Location: mock_test_questions.php?mock_test_id=' . $mockTestId . '&msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete mapped question.';
    }
}

/*
|--------------------------------------------------------------------------
| CLEAR ALL QUESTION MAPPINGS
|--------------------------------------------------------------------------
*/
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    $clearResult = deleteMockTestQuestionsByMockTest($mockTestId);

    if ($clearResult['success'] ?? false) {
        header('Location: mock_test_questions.php?mock_test_id=' . $mockTestId . '&msg=cleared');
        exit;
    } else {
        $error = 'Failed to clear question mappings.';
    }
}

/*
|--------------------------------------------------------------------------
| LOAD EDIT MAPPING
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];

    $rowsRes = getMockTestQuestions($mockTestId);
    $rows = ($rowsRes['success'] ?? false) ? ($rowsRes['data'] ?? []) : [];

    foreach ($rows as $row) {
        if ((int)$row['id'] === $editId) {
            $editMode = true;
            $editRow = $row;
            break;
        }
    }

    if (!$editMode) {
        $error = 'Mapped question not found.';
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Question mapped successfully.';
    if ($_GET['msg'] === 'updated') $success = 'Question mapping updated successfully.';
    if ($_GET['msg'] === 'deleted') $success = 'Question mapping deleted successfully.';
    if ($_GET['msg'] === 'generated') $success = 'Questions auto-generated successfully.';
    if ($_GET['msg'] === 'cleared') $success = 'All mapped questions cleared successfully.';
}

/*
|--------------------------------------------------------------------------
| AUTO GENERATE FROM SUBJECT RULES
|--------------------------------------------------------------------------
*/
if (isset($_GET['generate']) && $_GET['generate'] === '1') {
    $rulesRes = getMockTestSubjects($mockTestId);
    $rules = ($rulesRes['success'] ?? false) ? ($rulesRes['data'] ?? []) : [];

    if (empty($rules)) {
        $error = 'No subject rules found. Add subjects first.';
    } else {
        $clearResult = deleteMockTestQuestionsByMockTest($mockTestId);

        if (!($clearResult['success'] ?? false)) {
            $error = 'Failed to clear old mapped questions: ' . json_encode($clearResult);
        } else {
            $finalQuestionIds = [];
            $debugLines = [];

            foreach ($rules as $rule) {
                $subjectId = (int)($rule['subject_id'] ?? 0);
                $count = (int)($rule['question_count'] ?? 0);

                $pool = array_values(array_filter($allQuestions, function ($question) use ($subjectId, $examId) {
                    $questionExamId = (int)($question['exam_id'] ?? 0);
                    $questionSubjectId = (int)($question['subject_id'] ?? 0);
                    $status = strtolower(trim((string)($question['status'] ?? 'active')));

                    return $questionExamId === $examId
                        && $questionSubjectId === $subjectId
                        && $status === 'active';
                }));

                $debugLines[] = 'Subject ID ' . $subjectId . ' needs ' . $count . ', found ' . count($pool);

                if (count($pool) < $count) {
                    $error = 'Not enough active questions for subject ID ' . $subjectId . '. Needed ' . $count . ', found ' . count($pool) . '.';
                    break;
                }

                shuffle($pool);
                $selected = array_slice($pool, 0, $count);

                foreach ($selected as $selectedQuestion) {
                    $finalQuestionIds[] = (int)$selectedQuestion['id'];
                }
            }

            if ($error === '') {
                $order = 1;

                foreach ($finalQuestionIds as $questionId) {
                    $insertResult = addMockTestQuestion($mockTestId, $questionId, $order);

                    if (!($insertResult['success'] ?? false)) {
                        $error = 'Insert failed at question ID ' . $questionId . ': ' . json_encode($insertResult);
                        break;
                    }

                    $order++;
                }
            }

            if ($error === '') {
                header('Location: mock_test_questions.php?mock_test_id=' . $mockTestId . '&msg=generated');
                exit;
            } else {
                $error .= '<br><br>Debug:<br>' . implode('<br>', $debugLines);
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE MANUAL MAPPING
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');
    $questionId = (int)($_POST['question_id'] ?? 0);
    $questionOrder = (int)($_POST['question_order'] ?? 1);

    if ($questionId <= 0) {
        $error = 'Please select a question.';
    } elseif (!isset($questionMap[$questionId])) {
        $error = 'Invalid question selected.';
    } elseif ($questionOrder <= 0) {
        $error = 'Question order must be greater than zero.';
    } else {
        if ($action === 'add') {
            $result = addMockTestQuestion($mockTestId, $questionId, $questionOrder);

            if ($result['success'] ?? false) {
                header('Location: mock_test_questions.php?mock_test_id=' . $mockTestId . '&msg=added');
                exit;
            } else {
                $error = json_encode($result);
            }
        }

        if ($action === 'update') {
            $rowId = (int)($_POST['row_id'] ?? 0);

            if ($rowId <= 0) {
                $error = 'Invalid mapped row ID.';
            } else {
                $result = updateMockTestQuestion($rowId, $questionId, $questionOrder);

                if ($result['success'] ?? false) {
                    header('Location: mock_test_questions.php?mock_test_id=' . $mockTestId . '&msg=updated');
                    exit;
                } else {
                    $error = json_encode($result);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH MAPPED QUESTIONS
|--------------------------------------------------------------------------
*/
$rowsRes = getMockTestQuestions($mockTestId);
$rows = ($rowsRes['success'] ?? false) ? ($rowsRes['data'] ?? []) : [];

$currentDefaultOrder = count($rows) + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mock Test Questions</title>
    <link rel="stylesheet" href="assets/css/admin_layout.css">
    <link rel="stylesheet" href="assets/css/mock_test_questions.css">
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
            <div class="msg error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="mock-grid">
                <div class="card">
                    <h3>Add Question Mapping</h3>

                    <form method="POST" class="mock-form">
                        <input type="hidden" name="mock_test_id" value="<?php echo $mockTestId; ?>">
                        <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                        <input type="hidden" name="row_id" value="<?php echo $editMode ? (int)$editRow['id'] : ''; ?>">

                        <div class="group">
                            <label for="question_id">Question</label>
                            <?php $selectedQuestionId = $editMode ? (int)($editRow['question_id'] ?? 0) : 0; ?>
                            <select id="question_id" name="question_id" required>
                                <option value="">Select Question</option>
                                <?php foreach ($allQuestions as $question): ?>
                                    <option value="<?php echo (int)$question['id']; ?>" <?php echo $selectedQuestionId === (int)$question['id'] ? 'selected' : ''; ?>>
                                        <?php
                                        $preview = mb_substr((string)($question['question_text'] ?? ''), 0, 70);
                                        $subjectName = $subjectMap[(int)($question['subject_id'] ?? 0)] ?? '-';
                                        echo htmlspecialchars('#' . ($question['id'] ?? '-') . ' | ' . $subjectName . ' | ' . $preview);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="group">
                            <label for="question_order">Question Order</label>
                            <input
                                type="number"
                                id="question_order"
                                name="question_order"
                                min="1"
                                value="<?php echo $editMode ? (int)($editRow['question_order'] ?? 1) : $currentDefaultOrder; ?>"
                                required
                            >
                        </div>

                        <button type="submit" class="btn primary-btn">
                            <?php echo $editMode ? 'Update Mapping' : 'Add Mapping'; ?>
                        </button>

                        <?php if ($editMode): ?>
                            <a href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>" class="btn secondary-btn">Cancel</a>
                        <?php endif; ?>

                        <a
                            href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>&generate=1"
                            class="btn info-btn"
                            onclick="return confirm('Generate questions from subject rules? Existing mapped questions will be replaced.');"
                        >
                            Auto Generate
                        </a>

                        <a
                            href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>&clear=1"
                            class="btn danger-btn"
                            onclick="return confirm('Clear all mapped questions?');"
                        >
                            Clear All
                        </a>

                        <a href="mock_test_subjects.php?mock_test_id=<?php echo $mockTestId; ?>" class="btn secondary-btn">
                            Back to Subjects
                        </a>
                    </form>
                </div>

                <div class="card">
                    <h3><?php echo htmlspecialchars($mockTest['title'] ?? 'Mock Test'); ?> - Mapped Questions</h3>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Order</th>
                                    <th>Subject</th>
                                    <th>Question</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $index => $row): ?>
                                        <?php
                                        $questionId = (int)($row['question_id'] ?? 0);
                                        $question = $questionMap[$questionId] ?? null;
                                        $questionText = $question['question_text'] ?? '-';
                                        $subjectName = $subjectMap[(int)($question['subject_id'] ?? 0)] ?? '-';
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo (int)($row['question_order'] ?? 1); ?></td>
                                            <td><?php echo htmlspecialchars($subjectName); ?></td>
                                            <td class="question-text-cell"><?php echo htmlspecialchars($questionText); ?></td>
                                            <td>
                                                <div class="row-actions">
                                                    <a
                                                        class="mini-btn edit-btn"
                                                        href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>&edit_id=<?php echo (int)$row['id']; ?>"
                                                    >
                                                        Edit
                                                    </a>

                                                    <a
                                                        class="mini-btn delete-btn"
                                                        href="mock_test_questions.php?mock_test_id=<?php echo $mockTestId; ?>&delete_id=<?php echo (int)$row['id']; ?>"
                                                        onclick="return confirm('Delete this question mapping?');"
                                                    >
                                                        Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">No questions mapped yet.</td>
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