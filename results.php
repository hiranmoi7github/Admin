<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();
require_once __DIR__ . '/includes/supabase_api.php';

$success = '';
$error = '';
$viewMode = false;
$viewResult = null;

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| DELETE RESULT
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteResult = deleteResult($deleteId);

    if ($deleteResult['success']) {
        header('Location: results.php?msg=deleted');
        exit;
    } else {
        $error = 'Failed to delete result.';
    }
}

/*
|--------------------------------------------------------------------------
| VIEW MODE
|--------------------------------------------------------------------------
*/
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $viewId = (int)$_GET['view_id'];
    $res = getResultById($viewId);

    if (($res['success'] ?? false) && !empty($res['data'])) {
        $viewMode = true;
        $viewResult = $res['data'][0];
    } else {
        $error = 'Result not found.';
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $success = 'Result deleted successfully.';
    }
}

/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/
$resultsRes = getAllResults();
$results = ($resultsRes['success'] ?? false) ? ($resultsRes['data'] ?? []) : [];

$profilesRes = function_exists('getAllProfiles') ? getAllProfiles(5000) : ['success' => false, 'data' => []];
$profiles = ($profilesRes['success'] ?? false) ? ($profilesRes['data'] ?? []) : [];

$mockTestsRes = function_exists('getAllMockTests') ? getAllMockTests() : ['success' => false, 'data' => []];
$mockTests = ($mockTestsRes['success'] ?? false) ? ($mockTestsRes['data'] ?? []) : [];

$examsRes = function_exists('getAllExams') ? getAllExams() : ['success' => false, 'data' => []];
$exams = ($examsRes['success'] ?? false) ? ($examsRes['data'] ?? []) : [];

$profileMap = [];
foreach ($profiles as $profile) {
    $profileMap[(int)$profile['id']] = [
        'full_name' => $profile['full_name'] ?? '-',
        'phone' => $profile['phone'] ?? '-'
    ];
}

$examMap = [];
foreach ($exams as $exam) {
    $examMap[(int)$exam['id']] = $exam['exam_name'] ?? '-';
}

$mockMap = [];
foreach ($mockTests as $mock) {
    $mockMap[(int)$mock['id']] = [
        'title' => $mock['title'] ?? '-',
        'exam_id' => (int)($mock['exam_id'] ?? 0)
    ];
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/
$filteredResults = array_filter($results, function ($row) use ($search, $statusFilter, $profileMap, $mockMap) {
    $userId = (int)($row['user_id'] ?? 0);
    $mockTestId = (int)($row['mock_test_id'] ?? 0);

    $studentName = $profileMap[$userId]['full_name'] ?? '';
    $phone = $profileMap[$userId]['phone'] ?? '';
    $mockTitle = $mockMap[$mockTestId]['title'] ?? '';

    $matchSearch = true;
    $matchStatus = true;

    if ($search !== '') {
        $haystack = strtolower(
            $studentName . ' ' .
            $phone . ' ' .
            $mockTitle . ' ' .
            ($row['score'] ?? '') . ' ' .
            ($row['percentage'] ?? '')
        );

        $matchSearch = str_contains($haystack, strtolower($search));
    }

    if ($statusFilter !== '') {
        $matchStatus = strtolower((string)($row['result_status'] ?? 'completed')) === strtolower($statusFilter);
    }

    return $matchSearch && $matchStatus;
});

$filteredResults = array_values($filteredResults);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results</title>
    <link rel="stylesheet" href="assets/css/results.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Results</h1>
            <p>Track student performance across all mock tests.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($viewMode && $viewResult): ?>
        <?php
        $viewUserId = (int)($viewResult['user_id'] ?? 0);
        $viewMockId = (int)($viewResult['mock_test_id'] ?? 0);
        $viewExamId = $mockMap[$viewMockId]['exam_id'] ?? 0;
        ?>
        <div class="card detail-card">
            <h3>Result Details</h3>

            <div class="detail-grid">
                <div><strong>Student Name:</strong> <?php echo htmlspecialchars($profileMap[$viewUserId]['full_name'] ?? '-'); ?></div>
                <div><strong>Phone:</strong> <?php echo htmlspecialchars($profileMap[$viewUserId]['phone'] ?? '-'); ?></div>
                <div><strong>Mock Test:</strong> <?php echo htmlspecialchars($mockMap[$viewMockId]['title'] ?? '-'); ?></div>
                <div><strong>Exam:</strong> <?php echo htmlspecialchars($examMap[$viewExamId] ?? '-'); ?></div>
                <div><strong>Score:</strong> <?php echo htmlspecialchars((string)($viewResult['score'] ?? 0)); ?></div>
                <div><strong>Total Marks:</strong> <?php echo htmlspecialchars((string)($viewResult['total_marks'] ?? 0)); ?></div>
                <div><strong>Percentage:</strong> <?php echo htmlspecialchars((string)($viewResult['percentage'] ?? 0)); ?>%</div>
                <div><strong>Correct Answers:</strong> <?php echo htmlspecialchars((string)($viewResult['correct_answers'] ?? 0)); ?></div>
                <div><strong>Wrong Answers:</strong> <?php echo htmlspecialchars((string)($viewResult['wrong_answers'] ?? 0)); ?></div>
                <div><strong>Unanswered:</strong> <?php echo htmlspecialchars((string)($viewResult['unanswered'] ?? 0)); ?></div>
                <div><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst((string)($viewResult['result_status'] ?? 'completed'))); ?></div>
                <div><strong>Submitted At:</strong> <?php echo !empty($viewResult['submitted_at']) ? htmlspecialchars(date('d M Y h:i A', strtotime($viewResult['submitted_at']))) : '-'; ?></div>
            </div>

            <div class="detail-actions">
                <a href="results.php" class="btn secondary-btn">Close</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card filter-card">
        <form method="GET" class="filter-form">
            <div class="group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by student, phone, mock test, score">
            </div>

            <div class="group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="evaluated" <?php echo $statusFilter === 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn primary-btn">Apply</button>
                <a href="results.php" class="btn secondary-btn">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>All Results</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Phone</th>
                        <th>Mock Test</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Total Marks</th>
                        <th>%</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Unanswered</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($filteredResults)): ?>
                    <?php foreach ($filteredResults as $index => $row): ?>
                        <?php
                        $userId = (int)($row['user_id'] ?? 0);
                        $mockTestId = (int)($row['mock_test_id'] ?? 0);
                        $examId = $mockMap[$mockTestId]['exam_id'] ?? 0;
                        $statusValue = strtolower((string)($row['result_status'] ?? 'completed'));
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($profileMap[$userId]['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($profileMap[$userId]['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($mockMap[$mockTestId]['title'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($examMap[$examId] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['score'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['total_marks'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['percentage'] ?? 0)); ?>%</td>
                            <td><?php echo htmlspecialchars((string)($row['correct_answers'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['wrong_answers'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['unanswered'] ?? 0)); ?></td>
                            <td>
                                <span class="tag <?php echo htmlspecialchars(str_replace(' ', '_', $statusValue)); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $statusValue))); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                echo !empty($row['submitted_at'])
                                    ? htmlspecialchars(date('d M Y', strtotime($row['submitted_at'])))
                                    : '-';
                                ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="mini-btn view-btn" href="results.php?view_id=<?php echo (int)$row['id']; ?>">View</a>
                                    <a class="mini-btn delete-btn" href="results.php?delete_id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this result?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14" class="empty-state">No results found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>