<?php
require_once __DIR__ . '/includes/auth_check.php';
requireContentAccess();

require_once __DIR__ . '/includes/supabase_api.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['bulk_preview_rows'])) {
    $_SESSION['bulk_preview_rows'] = [];
}

if (!isset($_SESSION['bulk_preview_meta'])) {
    $_SESSION['bulk_preview_meta'] = [
        'exam_id' => '',
        'subject_id' => '',
        'mock_test_id' => '',
        'status' => 'active'
    ];
}

$success = '';
$error = '';
$debug = [];
$failedRows = [];
$editIndex = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;

function cleanText($value): string
{
    return trim((string)$value);
}

function normalizeCorrectOption(string $value): string
{
    $value = strtoupper(trim($value));
    return in_array($value, ['A', 'B', 'C', 'D'], true) ? $value : '';
}

function normalizeDifficulty(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['easy', 'medium', 'hard'], true) ? $value : 'medium';
}

function normalizeStatus(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['active', 'inactive'], true) ? $value : 'active';
}

function parseExcelFile(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    if (count($rows) < 2) {
        return [
            'success' => false,
            'rows' => [],
            'errors' => ['The uploaded file is empty or has no data rows.']
        ];
    }

    $headers = array_map(fn($h) => strtolower(trim((string)$h)), $rows[0]);
    $required = [
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option'
    ];

    $headerMap = [];
    foreach ($headers as $index => $header) {
        $headerMap[$header] = $index;
    }

    $errors = [];
    foreach ($required as $col) {
        if (!array_key_exists($col, $headerMap)) {
            $errors[] = 'Missing required column: ' . $col;
        }
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'rows' => [],
            'errors' => $errors
        ];
    }

    $parsedRows = [];

    for ($i = 1; $i < count($rows); $i++) {
        $excelRowNo = $i + 1;
        $row = $rows[$i];

        $questionText = cleanText($row[$headerMap['question_text']] ?? '');
        $optionA = cleanText($row[$headerMap['option_a']] ?? '');
        $optionB = cleanText($row[$headerMap['option_b']] ?? '');
        $optionC = cleanText($row[$headerMap['option_c']] ?? '');
        $optionD = cleanText($row[$headerMap['option_d']] ?? '');
        $correctOption = normalizeCorrectOption($row[$headerMap['correct_option']] ?? '');

        $explanation = array_key_exists('explanation', $headerMap)
            ? cleanText($row[$headerMap['explanation']] ?? '')
            : '';

        $difficulty = array_key_exists('difficulty_level', $headerMap)
            ? normalizeDifficulty($row[$headerMap['difficulty_level']] ?? '')
            : 'medium';

        $marks = array_key_exists('marks', $headerMap)
            ? (int)cleanText($row[$headerMap['marks']] ?? '1')
            : 1;

        $negativeMarks = array_key_exists('negative_marks', $headerMap)
            ? (float)cleanText($row[$headerMap['negative_marks']] ?? '0')
            : 0;

        if (
            $questionText === '' ||
            $optionA === '' ||
            $optionB === '' ||
            $optionC === '' ||
            $optionD === '' ||
            $correctOption === ''
        ) {
            $errors[] = "Row {$excelRowNo}: Required fields are missing.";
            continue;
        }

        $parsedRows[] = [
            'question_text' => $questionText,
            'option_a' => $optionA,
            'option_b' => $optionB,
            'option_c' => $optionC,
            'option_d' => $optionD,
            'correct_option' => $correctOption,
            'explanation' => $explanation,
            'difficulty_level' => $difficulty,
            'marks' => $marks > 0 ? $marks : 1,
            'negative_marks' => $negativeMarks,
            'status' => 'active'
        ];
    }

    return [
        'success' => true,
        'rows' => $parsedRows,
        'errors' => $errors
    ];
}

$examResponse = getAllExams();
$subjectResponse = getAllSubjects();
$mockResponse = getAllMockTests();

$exams = $examResponse['success'] ? ($examResponse['data'] ?? []) : [];
$subjects = $subjectResponse['success'] ? ($subjectResponse['data'] ?? []) : [];
$mockTests = $mockResponse['success'] ? ($mockResponse['data'] ?? []) : [];

$previewRows = $_SESSION['bulk_preview_rows'];
$previewMeta = $_SESSION['bulk_preview_meta'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $debug[] = 'Action: ' . $action;

    if ($action === 'upload_preview') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $mockTestId = (int)($_POST['mock_test_id'] ?? 0);
        $status = normalizeStatus($_POST['status'] ?? 'active');

        if ($examId <= 0) {
            $error = 'Please select exam.';
        } elseif ($subjectId <= 0) {
            $error = 'Please select subject.';
        } elseif (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a valid Excel or CSV file.';
        } else {
            $tmpPath = $_FILES['excel_file']['tmp_name'];
            $fileName = $_FILES['excel_file']['name'] ?? '';
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $debug[] = 'File: ' . $fileName;

            if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                $error = 'Only xlsx, xls, csv allowed.';
            } else {
                try {
                    $parsed = parseExcelFile($tmpPath);
                    $failedRows = $parsed['errors'];

                    if (!$parsed['success']) {
                        $error = implode(' | ', $parsed['errors']);
                    } else {
                        $_SESSION['bulk_preview_rows'] = $parsed['rows'];

                        foreach ($_SESSION['bulk_preview_rows'] as $k => $previewRow) {
                            $_SESSION['bulk_preview_rows'][$k]['status'] = $status;
                        }

                        $_SESSION['bulk_preview_meta'] = [
                            'exam_id' => $examId,
                            'subject_id' => $subjectId,
                            'mock_test_id' => $mockTestId,
                            'status' => $status
                        ];

                        $previewRows = $_SESSION['bulk_preview_rows'];
                        $previewMeta = $_SESSION['bulk_preview_meta'];

                        if (count($previewRows) > 0) {
                            $success = count($previewRows) . ' row(s) loaded into preview.';
                        } else {
                            $error = 'No valid rows found in uploaded file.';
                        }
                    }
                } catch (Throwable $e) {
                    $error = 'File parsing failed: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'delete_row') {
        $rowIndex = (int)($_POST['row_index'] ?? -1);

        if (isset($_SESSION['bulk_preview_rows'][$rowIndex])) {
            unset($_SESSION['bulk_preview_rows'][$rowIndex]);
            $_SESSION['bulk_preview_rows'] = array_values($_SESSION['bulk_preview_rows']);
            $previewRows = $_SESSION['bulk_preview_rows'];
            $success = 'Preview row deleted.';
        } else {
            $error = 'Preview row not found.';
        }
    }

    if ($action === 'save_row') {
        $rowIndex = (int)($_POST['row_index'] ?? -1);

        if (!isset($_SESSION['bulk_preview_rows'][$rowIndex])) {
            $error = 'Preview row not found.';
        } else {
            $row = [
                'question_text' => cleanText($_POST['question_text'] ?? ''),
                'option_a' => cleanText($_POST['option_a'] ?? ''),
                'option_b' => cleanText($_POST['option_b'] ?? ''),
                'option_c' => cleanText($_POST['option_c'] ?? ''),
                'option_d' => cleanText($_POST['option_d'] ?? ''),
                'correct_option' => normalizeCorrectOption($_POST['correct_option'] ?? ''),
                'explanation' => cleanText($_POST['explanation'] ?? ''),
                'difficulty_level' => normalizeDifficulty($_POST['difficulty_level'] ?? ''),
                'marks' => (int)cleanText($_POST['marks'] ?? '1'),
                'negative_marks' => (float)cleanText($_POST['negative_marks'] ?? '0'),
                'status' => normalizeStatus($_POST['status'] ?? 'active')
            ];

            if (
                $row['question_text'] === '' ||
                $row['option_a'] === '' ||
                $row['option_b'] === '' ||
                $row['option_c'] === '' ||
                $row['option_d'] === '' ||
                $row['correct_option'] === ''
            ) {
                $error = 'Please fill all required fields.';
            } else {
                if ($row['marks'] <= 0) {
                    $row['marks'] = 1;
                }

                $_SESSION['bulk_preview_rows'][$rowIndex] = $row;
                $previewRows = $_SESSION['bulk_preview_rows'];
                $success = 'Preview row updated.';
            }
        }
    }

    if ($action === 'submit_all') {
        $previewRows = $_SESSION['bulk_preview_rows'] ?? [];
        $previewMeta = $_SESSION['bulk_preview_meta'] ?? [];

        $examId = (int)($previewMeta['exam_id'] ?? 0);
        $subjectId = (int)($previewMeta['subject_id'] ?? 0);
        $mockTestId = (int)($previewMeta['mock_test_id'] ?? 0);
        $selectedStatus = normalizeStatus($previewMeta['status'] ?? 'active');

        if ($examId <= 0 || $subjectId <= 0) {
            $error = 'Exam and subject not selected.';
        } elseif (empty($previewRows)) {
            $error = 'No preview rows to submit.';
        } else {
            $inserted = 0;
            $failedRows = [];

            foreach ($previewRows as $index => $row) {
                $payload = [
                    'exam_id' => $examId,
                    'subject_id' => $subjectId,
                    'mock_test_id' => $mockTestId > 0 ? $mockTestId : null,
                    'question_text' => $row['question_text'],
                    'option_a' => $row['option_a'],
                    'option_b' => $row['option_b'],
                    'option_c' => $row['option_c'],
                    'option_d' => $row['option_d'],
                    'correct_option' => $row['correct_option'],
                    'explanation' => $row['explanation'],
                    'difficulty_level' => $row['difficulty_level'],
                    'marks' => $row['marks'],
                    'negative_marks' => $row['negative_marks'],
                    'status' => $selectedStatus
                ];

                $result = addQuestion($payload);

                if (($result['success'] ?? false) === true) {
                    $inserted++;
                } else {
                    $failedRows[] = 'Row ' . ($index + 1) . ' insert failed.';
                    $debug[] = 'Insert error row ' . ($index + 1) . ': ' . json_encode($result);
                }
            }

            if ($inserted > 0) {
                $success = $inserted . ' question(s) submitted successfully.';
            }

            if ($inserted > 0 && empty($failedRows)) {
                $_SESSION['bulk_preview_rows'] = [];
                $_SESSION['bulk_preview_meta'] = [
                    'exam_id' => '',
                    'subject_id' => '',
                    'mock_test_id' => '',
                    'status' => 'active'
                ];
                $previewRows = [];
                $previewMeta = $_SESSION['bulk_preview_meta'];
            }

            if ($inserted === 0) {
                $error = 'No questions were inserted.';
            }
        }
    }

    if ($action === 'clear_preview') {
        $_SESSION['bulk_preview_rows'] = [];
        $_SESSION['bulk_preview_meta'] = [
            'exam_id' => '',
            'subject_id' => '',
            'mock_test_id' => '',
            'status' => 'active'
        ];
        $previewRows = [];
        $previewMeta = $_SESSION['bulk_preview_meta'];
        $success = 'Preview cleared.';
    }
}

$selectedExamId = (int)($previewMeta['exam_id'] ?? 0);
$selectedSubjectId = (int)($previewMeta['subject_id'] ?? 0);
$selectedMockTestId = (int)($previewMeta['mock_test_id'] ?? 0);
$selectedStatus = normalizeStatus($previewMeta['status'] ?? 'active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload Questions</title>
    <link rel="stylesheet" href="assets/css/bulk_upload.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Bulk Upload Questions</h1>
            <p class="small">Subject-wise preview upload</p>
        </div>
        <a href="dashboard.php" class="back">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?><div class="msg success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if (!empty($debug)): ?>
        <div class="debug-box"><?php echo htmlspecialchars(implode("\n", $debug)); ?></div>
    <?php endif; ?>

    <?php if (!empty($failedRows)): ?>
        <div class="card full-card">
            <h3>Issues</h3>
            <ul class="fail-list">
                <?php foreach ($failedRows as $fail): ?>
                    <li><?php echo htmlspecialchars($fail); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>Upload File</h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_preview">

                <div class="group">
                    <label>Exam</label>
                    <select name="exam_id" required>
                        <option value="">Select Exam</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo (int)$exam['id']; ?>" <?php echo $selectedExamId === (int)$exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo (int)$subject['id']; ?>" <?php echo $selectedSubjectId === (int)$subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label>Mock Test (Optional)</label>
                    <select name="mock_test_id">
                        <option value="">Select Mock Test</option>
                        <?php foreach ($mockTests as $mock): ?>
                            <option value="<?php echo (int)$mock['id']; ?>" <?php echo $selectedMockTestId === (int)$mock['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mock['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="active" <?php echo $selectedStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $selectedStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="group">
                    <label>File</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                </div>

                <button type="submit" class="btn">Upload & Preview</button>
            </form>

            <?php if (!empty($previewRows)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_preview">
                    <button type="submit" class="btn-danger">Clear Preview</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Preview</h3>

            <?php if (empty($previewRows)): ?>
                <div class="empty">No preview rows loaded.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Correct</th>
                                <th>Difficulty</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($previewRows as $index => $row): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['question_text']); ?></strong>
                                    <div class="small">
                                        A: <?php echo htmlspecialchars($row['option_a']); ?><br>
                                        B: <?php echo htmlspecialchars($row['option_b']); ?><br>
                                        C: <?php echo htmlspecialchars($row['option_c']); ?><br>
                                        D: <?php echo htmlspecialchars($row['option_d']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['correct_option']); ?></td>
                                <td><?php echo htmlspecialchars($row['difficulty_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="mini-link" href="bulk_upload.php?edit=<?php echo $index; ?>">Edit</a>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="delete_row">
                                            <input type="hidden" name="row_index" value="<?php echo $index; ?>">
                                            <button type="submit" class="mini-btn delete-btn">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <?php if ($editIndex === $index): ?>
                                <tr>
                                    <td colspan="6">
                                        <form method="POST" class="edit-form">
                                            <input type="hidden" name="action" value="save_row">
                                            <input type="hidden" name="row_index" value="<?php echo $index; ?>">

                                            <div class="edit-grid">
                                                <div class="group full-width">
                                                    <label>Question Text</label>
                                                    <textarea name="question_text" required><?php echo htmlspecialchars($row['question_text']); ?></textarea>
                                                </div>
                                                <div class="group">
                                                    <label>Option A</label>
                                                    <input type="text" name="option_a" value="<?php echo htmlspecialchars($row['option_a']); ?>" required>
                                                </div>
                                                <div class="group">
                                                    <label>Option B</label>
                                                    <input type="text" name="option_b" value="<?php echo htmlspecialchars($row['option_b']); ?>" required>
                                                </div>
                                                <div class="group">
                                                    <label>Option C</label>
                                                    <input type="text" name="option_c" value="<?php echo htmlspecialchars($row['option_c']); ?>" required>
                                                </div>
                                                <div class="group">
                                                    <label>Option D</label>
                                                    <input type="text" name="option_d" value="<?php echo htmlspecialchars($row['option_d']); ?>" required>
                                                </div>
                                                <div class="group">
                                                    <label>Correct Option</label>
                                                    <select name="correct_option">
                                                        <?php foreach (['A','B','C','D'] as $opt): ?>
                                                            <option value="<?php echo $opt; ?>" <?php echo $row['correct_option'] === $opt ? 'selected' : ''; ?>>
                                                                <?php echo $opt; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="group">
                                                    <label>Difficulty</label>
                                                    <select name="difficulty_level">
                                                        <?php foreach (['easy','medium','hard'] as $diff): ?>
                                                            <option value="<?php echo $diff; ?>" <?php echo $row['difficulty_level'] === $diff ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst($diff); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="group">
                                                    <label>Marks</label>
                                                    <input type="number" name="marks" value="<?php echo (int)$row['marks']; ?>">
                                                </div>
                                                <div class="group">
                                                    <label>Negative Marks</label>
                                                    <input type="number" step="0.25" name="negative_marks" value="<?php echo htmlspecialchars((string)$row['negative_marks']); ?>">
                                                </div>
                                                <div class="group">
                                                    <label>Status</label>
                                                    <select name="status">
                                                        <?php foreach (['active','inactive'] as $st): ?>
                                                            <option value="<?php echo $st; ?>" <?php echo $row['status'] === $st ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst($st); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="group full-width">
                                                    <label>Explanation</label>
                                                    <textarea name="explanation"><?php echo htmlspecialchars($row['explanation']); ?></textarea>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn-green">Save Row</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" class="submit-form">
                    <input type="hidden" name="action" value="submit_all">
                    <button type="submit" class="btn-green">Submit All</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>