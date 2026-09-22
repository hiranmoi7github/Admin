<?php
require_once __DIR__ . '/includes/auth_check.php';
requirePermission('question_bank');
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$editMode = false;
$editQuestion = null;

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $deleteResult = deleteQuestion($deleteId);

    if ($deleteResult['success']) {
        header('Location: question_bank.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete question.';
    }
}

/*
|--------------------------------------------------------------------------
| EDIT LOAD
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $questionResult = getQuestionById($editId);

    if ($questionResult['success'] && !empty($questionResult['data'])) {
        $editMode = true;
        $editQuestion = $questionResult['data'][0];
    } else {
        $error = 'Question not found.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = 'Question added successfully.';
    } elseif ($_GET['msg'] === 'updated') {
        $success = 'Question updated successfully.';
    } elseif ($_GET['msg'] === 'deleted') {
        $success = 'Question deleted successfully.';
    }
}

/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'add');

    $examId = (int) ($_POST['exam_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $chapterId = (int) ($_POST['chapter_id'] ?? 0);
    $mockTestId = (int) ($_POST['mock_test_id'] ?? 0);

    $questionText = trim($_POST['question_text'] ?? '');
    $optionA = trim($_POST['option_a'] ?? '');
    $optionB = trim($_POST['option_b'] ?? '');
    $optionC = trim($_POST['option_c'] ?? '');
    $optionD = trim($_POST['option_d'] ?? '');
    $correctOption = trim($_POST['correct_option'] ?? '');
    $explanation = trim($_POST['explanation'] ?? '');
    $difficultyLevel = trim($_POST['difficulty_level'] ?? 'medium');
    $marks = (int) ($_POST['marks'] ?? 1);
    $negativeMarks = (float) ($_POST['negative_marks'] ?? 0);
    $status = trim($_POST['status'] ?? 'draft');

    if ($examId <= 0) {
        $error = 'Please select an exam.';
    } elseif ($questionText === '') {
        $error = 'Question text is required.';
    } elseif ($optionA === '' || $optionB === '' || $optionC === '' || $optionD === '') {
        $error = 'All 4 options are required.';
    } elseif (!in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
        $error = 'Please select a valid correct option.';
    } else {
        $payload = [
            'exam_id' => $examId,
            'subject_id' => $subjectId > 0 ? $subjectId : null,
            'chapter_id' => $chapterId > 0 ? $chapterId : null,
            'mock_test_id' => $mockTestId > 0 ? $mockTestId : null,
            'question_text' => $questionText,
            'option_a' => $optionA,
            'option_b' => $optionB,
            'option_c' => $optionC,
            'option_d' => $optionD,
            'correct_option' => $correctOption,
            'explanation' => $explanation,
            'difficulty_level' => $difficultyLevel,
            'marks' => $marks,
            'negative_marks' => $negativeMarks,
            'status' => $status
        ];

        if ($action === 'add') {
            $result = addQuestion($payload);

            if ($result['success']) {
                header('Location: question_bank.php?msg=added');
                exit;
            } else {
                $error = 'Failed to add question.';
            }
        }

        if ($action === 'update') {
            $questionId = (int) ($_POST['question_id'] ?? 0);

            if ($questionId <= 0) {
                $error = 'Invalid question ID.';
            } else {
                $result = updateQuestion($questionId, $payload);

                if ($result['success']) {
                    header('Location: question_bank.php?msg=updated');
                    exit;
                } else {
                    $error = 'Failed to update question.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/
$examResponse = getAllExams();
$exams = $examResponse['success'] ? ($examResponse['data'] ?? []) : [];

$subjectResponse = getAllSubjects();
$subjects = $subjectResponse['success'] ? ($subjectResponse['data'] ?? []) : [];

$mockResponse = getAllMockTests();
$mockTests = $mockResponse['success'] ? ($mockResponse['data'] ?? []) : [];

$questionResponse = getAllQuestions(200);
$questions = $questionResponse['success'] ? ($questionResponse['data'] ?? []) : [];

$examMap = [];
foreach ($exams as $exam) {
    $examMap[$exam['id']] = $exam['exam_name'];
}

$subjectMap = [];
foreach ($subjects as $subject) {
    $subjectMap[$subject['id']] = $subject['subject_name'];
}

$mockMap = [];
foreach ($mockTests as $mock) {
    $mockMap[$mock['id']] = $mock['title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Poppins',sans-serif;background:#f6f7fb;color:#1f2937;padding:24px}
        .wrap{max-width:1450px;margin:0 auto}
        .top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}
        .back{text-decoration:none;color:#6d28d9;font-weight:600}
        .grid{display:grid;grid-template-columns:1.1fr 1.9fr;gap:20px}
        .card{background:#fff;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(17,24,39,.08);border:1px solid rgba(229,231,235,.7)}
        .card h3{margin-bottom:16px}
        .msg{padding:12px 14px;border-radius:14px;font-size:13px;margin-bottom:16px}
        .success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .error{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
        .group{margin-bottom:16px}
        .group label{display:block;font-size:13px;font-weight:600;margin-bottom:8px}
        .group input,.group select,.group textarea{width:100%;border:1px solid #e5e7eb;border-radius:14px;padding:12px 14px;font-family:inherit;background:#fafafa;outline:none}
        .group input,.group select{height:50px}
        .group textarea{min-height:110px;resize:vertical}
        .btn{width:100%;height:52px;border:none;border-radius:14px;background:linear-gradient(90deg,#6d28d9,#8b5cf6);color:#fff;font-weight:600;font-family:inherit;cursor:pointer}
        .btn-secondary{display:inline-block;text-decoration:none;text-align:center;width:100%;margin-top:10px;padding:14px;border-radius:14px;background:#f3f0ff;color:#6d28d9;font-weight:600}
        .table-wrap{overflow:auto}
        table{width:100%;border-collapse:collapse;min-width:1200px}
        th,td{text-align:left;padding:14px;border-bottom:1px solid #e5e7eb;font-size:14px;vertical-align:top}
        th{font-size:12px;text-transform:uppercase;color:#6b7280}
        .small{font-size:12px;color:#6b7280;line-height:1.5}
        .tag{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:600}
        .easy{background:#dcfce7;color:#166534}
        .medium{background:#fef3c7;color:#92400e}
        .hard{background:#fee2e2;color:#b91c1c}
        .active{background:#dcfce7;color:#166534}
        .inactive{background:#fee2e2;color:#b91c1c}
        .draft{background:#fef3c7;color:#92400e}
        .coming-soon{background:#ede9fe;color:#6d28d9}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .action-btn{text-decoration:none;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:600}
        .edit-btn{background:#ede9fe;color:#6d28d9}
        .delete-btn{background:#fee2e2;color:#dc2626}
        .empty{text-align:center;color:#6b7280;padding:20px 0}
        @media(max-width:1100px){.grid{grid-template-columns:1fr}.top{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Question Bank</h1>
            <p style="color:#6b7280;font-size:14px;">Add, edit, and manage questions.</p>
        </div>
        <a href="dashboard.php" class="back">← Back to Dashboard</a>
    </div>

    <div class="grid">
        <div class="card">
            <h3><?php echo $editMode ? 'Edit Question' : 'Add Question'; ?></h3>

            <?php if ($success !== ''): ?>
                <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'add'; ?>">
                <input type="hidden" name="question_id" value="<?php echo $editMode ? (int)$editQuestion['id'] : ''; ?>">

                <div class="group">
                    <label for="exam_id">Exam</label>
                    <select id="exam_id" name="exam_id" required>
                        <option value="">Select Exam</option>
                        <?php
                        $selectedExamId = $editMode ? (int)($editQuestion['exam_id'] ?? 0) : 0;
                        foreach ($exams as $exam):
                        ?>
                            <option value="<?php echo (int)$exam['id']; ?>" <?php echo $selectedExamId === (int)$exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">Select Subject</option>
                        <?php
                        $selectedSubjectId = $editMode ? (int)($editQuestion['subject_id'] ?? 0) : 0;
                        foreach ($subjects as $subject):
                        ?>
                            <option value="<?php echo (int)$subject['id']; ?>" <?php echo $selectedSubjectId === (int)$subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="chapter_id">Chapter</label>
                    <select id="chapter_id" name="chapter_id">
                        <option value="">Select Chapter</option>
                        <?php
                        $selectedChapterId = $editMode ? (int)($editQuestion['chapter_id'] ?? 0) : 0;
                        foreach ($chapters as $chapter):
                        ?>
                            <option value="<?php echo (int)$chapter['id']; ?>" <?php echo $selectedChapterId === (int)$chapter['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="mock_test_id">Mock Test</label>
                    <select id="mock_test_id" name="mock_test_id">
                        <option value="">Select Mock Test</option>
                        <?php
                        $selectedMockId = $editMode ? (int)($editQuestion['mock_test_id'] ?? 0) : 0;
                        foreach ($mockTests as $mock):
                        ?>
                            <option value="<?php echo (int)$mock['id']; ?>" <?php echo $selectedMockId === (int)$mock['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mock['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="question_text">Question Text</label>
                    <textarea id="question_text" name="question_text" required><?php echo $editMode ? htmlspecialchars($editQuestion['question_text'] ?? '') : ''; ?></textarea>
                </div>

                <div class="group">
                    <label for="option_a">Option A</label>
                    <input type="text" id="option_a" name="option_a" value="<?php echo $editMode ? htmlspecialchars($editQuestion['option_a'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="option_b">Option B</label>
                    <input type="text" id="option_b" name="option_b" value="<?php echo $editMode ? htmlspecialchars($editQuestion['option_b'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="option_c">Option C</label>
                    <input type="text" id="option_c" name="option_c" value="<?php echo $editMode ? htmlspecialchars($editQuestion['option_c'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="option_d">Option D</label>
                    <input type="text" id="option_d" name="option_d" value="<?php echo $editMode ? htmlspecialchars($editQuestion['option_d'] ?? '') : ''; ?>" required>
                </div>

                <div class="group">
                    <label for="correct_option">Correct Option</label>
                    <select id="correct_option" name="correct_option" required>
                        <?php
                        $currentCorrect = $editMode ? ($editQuestion['correct_option'] ?? '') : '';
                        foreach (['A','B','C','D'] as $opt):
                        ?>
                            <option value="<?php echo $opt; ?>" <?php echo $currentCorrect === $opt ? 'selected' : ''; ?>>
                                <?php echo $opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="explanation">Explanation</label>
                    <textarea id="explanation" name="explanation"><?php echo $editMode ? htmlspecialchars($editQuestion['explanation'] ?? '') : ''; ?></textarea>
                </div>

                <div class="group">
                    <label for="difficulty_level">Difficulty</label>
                    <select id="difficulty_level" name="difficulty_level">
                        <?php
                        $currentDifficulty = $editMode ? ($editQuestion['difficulty_level'] ?? 'medium') : 'medium';
                        foreach (['easy','medium','hard'] as $level):
                        ?>
                            <option value="<?php echo $level; ?>" <?php echo $currentDifficulty === $level ? 'selected' : ''; ?>>
                                <?php echo ucfirst($level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label for="marks">Marks</label>
                    <input type="number" id="marks" name="marks" value="<?php echo $editMode ? (int)($editQuestion['marks'] ?? 1) : 1; ?>">
                </div>

                <div class="group">
                    <label for="negative_marks">Negative Marks</label>
                    <input type="number" step="0.25" id="negative_marks" name="negative_marks" value="<?php echo $editMode ? htmlspecialchars((string)($editQuestion['negative_marks'] ?? 0)) : '0'; ?>">
                </div>

                <div class="group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php
                        $currentStatus = $editMode ? ($editQuestion['status'] ?? 'draft') : 'draft';
                        $statuses = ['active', 'inactive', 'draft', 'coming soon'];
                        foreach ($statuses as $statusOption):
                        ?>
                            <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $currentStatus === $statusOption ? 'selected' : ''; ?>>
                                <?php echo ucwords($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn"><?php echo $editMode ? 'Update Question' : 'Add Question'; ?></button>

                <?php if ($editMode): ?>
                    <a href="question_bank.php" class="btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>All Questions</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Exam / Subject</th>
                            <th>Correct</th>
                            <th>Difficulty</th>
                            <th>Status</th>
                            <th>Marks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <?php
                            $difficulty = $question['difficulty_level'] ?? 'medium';
                            $statusValue = $question['status'] ?? 'draft';
                            $statusClass = str_replace(' ', '-', strtolower($statusValue));
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($question['question_text'] ?? ''); ?></strong>
                                    <div class="small" style="margin-top:8px;">
                                        A. <?php echo htmlspecialchars($question['option_a'] ?? ''); ?><br>
                                        B. <?php echo htmlspecialchars($question['option_b'] ?? ''); ?><br>
                                        C. <?php echo htmlspecialchars($question['option_c'] ?? ''); ?><br>
                                        D. <?php echo htmlspecialchars($question['option_d'] ?? ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($examMap[$question['exam_id']] ?? 'Unknown Exam'); ?></div>
                                    <div class="small"><?php echo htmlspecialchars($subjectMap[$question['subject_id']] ?? '-'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($question['correct_option'] ?? ''); ?></td>
                                <td><span class="tag <?php echo htmlspecialchars($difficulty); ?>"><?php echo htmlspecialchars(ucfirst($difficulty)); ?></span></td>
                                <td><span class="tag <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars(ucwords($statusValue)); ?></span></td>
                                <td>
                                    <?php echo (int)($question['marks'] ?? 1); ?>
                                    <div class="small">Neg: <?php echo htmlspecialchars((string)($question['negative_marks'] ?? 0)); ?></div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="action-btn edit-btn" href="question_bank.php?edit_id=<?php echo (int)$question['id']; ?>">Edit</a>
                                        <a class="action-btn delete-btn" href="question_bank.php?delete_id=<?php echo (int)$question['id']; ?>" onclick="return confirm('Delete this question?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty">No questions found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>