<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();
require_once __DIR__ . '/includes/supabase_api.php';

function safeDataFromResponse(array $response): array
{
    return ($response['success'] ?? false) ? ($response['data'] ?? []) : [];
}

$profiles = safeDataFromResponse(getAllProfiles(5000));
$exams = safeDataFromResponse(getAllExams());
$subjects = safeDataFromResponse(getAllSubjects());
$mockTests = safeDataFromResponse(getAllMockTests());
$materials = function_exists('getAllMaterials') ? safeDataFromResponse(getAllMaterials()) : [];
$subscriptions = function_exists('getAllSubscriptions') ? safeDataFromResponse(getAllSubscriptions()) : [];
$supportFeedback = function_exists('getAllSupportFeedback') ? safeDataFromResponse(getAllSupportFeedback()) : [];
$announcements = function_exists('getAllAnnouncements') ? safeDataFromResponse(getAllAnnouncements()) : [];
$questionsResponse = getRows('questions', 'select=*&order=id.desc&limit=10000', true);
$questions = safeDataFromResponse($questionsResponse);

$totalUsers = count($profiles);
$totalExams = count($exams);
$totalSubjects = count($subjects);
$totalMockTests = count($mockTests);
$totalQuestions = count($questions);
$totalMaterials = count($materials);
$totalPlans = count($subscriptions);
$totalAnnouncements = count($announcements);
$totalSupportEntries = count($supportFeedback);

$activeUsers = 0;
$inactiveUsers = 0;
foreach ($profiles as $profile) {
    $status = strtolower((string)($profile['status'] ?? 'active'));
    if ($status === 'inactive') {
        $inactiveUsers++;
    } else {
        $activeUsers++;
    }
}

$activeMockTests = 0;
$draftMockTests = 0;
$inactiveMockTests = 0;
foreach ($mockTests as $mock) {
    $status = strtolower((string)($mock['status'] ?? 'draft'));
    if ($status === 'active') {
        $activeMockTests++;
    } elseif ($status === 'inactive') {
        $inactiveMockTests++;
    } else {
        $draftMockTests++;
    }
}

$freeMaterials = 0;
$paidMaterials = 0;
foreach ($materials as $material) {
    $access = strtolower((string)($material['access_type'] ?? 'free'));
    if ($access === 'paid') {
        $paidMaterials++;
    } else {
        $freeMaterials++;
    }
}

$newSupport = 0;
$inProgressSupport = 0;
$resolvedSupport = 0;
$closedSupport = 0;
foreach ($supportFeedback as $entry) {
    $status = strtolower((string)($entry['status'] ?? 'new'));
    if ($status === 'new') {
        $newSupport++;
    } elseif ($status === 'in_progress') {
        $inProgressSupport++;
    } elseif ($status === 'resolved') {
        $resolvedSupport++;
    } elseif ($status === 'closed') {
        $closedSupport++;
    }
}

$examMap = [];
foreach ($exams as $exam) {
    $examMap[(int)$exam['id']] = [
        'exam_name' => $exam['exam_name'] ?? '-',
        'department_name' => $exam['department_name'] ?? '-'
    ];
}

$subjectCountByExam = [];
foreach ($subjects as $subject) {
    $examId = (int)($subject['exam_id'] ?? 0);
    if (!isset($subjectCountByExam[$examId])) {
        $subjectCountByExam[$examId] = 0;
    }
    $subjectCountByExam[$examId]++;
}

$questionCountByExam = [];
foreach ($questions as $question) {
    $examId = (int)($question['exam_id'] ?? 0);
    if (!isset($questionCountByExam[$examId])) {
        $questionCountByExam[$examId] = 0;
    }
    $questionCountByExam[$examId]++;
}

$mockCountByExam = [];
foreach ($mockTests as $mock) {
    $examId = (int)($mock['exam_id'] ?? 0);
    if (!isset($mockCountByExam[$examId])) {
        $mockCountByExam[$examId] = 0;
    }
    $mockCountByExam[$examId]++;
}

$examAnalytics = [];
foreach ($examMap as $examId => $examInfo) {
    $examAnalytics[] = [
        'department_name' => $examInfo['department_name'],
        'exam_name' => $examInfo['exam_name'],
        'subjects' => $subjectCountByExam[$examId] ?? 0,
        'questions' => $questionCountByExam[$examId] ?? 0,
        'mock_tests' => $mockCountByExam[$examId] ?? 0
    ];
}

usort($examAnalytics, function ($a, $b) {
    return $b['questions'] <=> $a['questions'];
});

$recentUsers = $profiles;
usort($recentUsers, function ($a, $b) {
    return strtotime((string)($b['created_at'] ?? '1970-01-01')) <=> strtotime((string)($a['created_at'] ?? '1970-01-01'));
});
$recentUsers = array_slice($recentUsers, 0, 5);

$recentMockTests = $mockTests;
usort($recentMockTests, function ($a, $b) {
    return strtotime((string)($b['created_at'] ?? '1970-01-01')) <=> strtotime((string)($a['created_at'] ?? '1970-01-01'));
});
$recentMockTests = array_slice($recentMockTests, 0, 5);

$recentSupport = $supportFeedback;
usort($recentSupport, function ($a, $b) {
    return strtotime((string)($b['created_at'] ?? '1970-01-01')) <=> strtotime((string)($a['created_at'] ?? '1970-01-01'));
});
$recentSupport = array_slice($recentSupport, 0, 5);

$totalSubscriptionValue = 0;
$activeSubscriptionCount = 0;
foreach ($subscriptions as $plan) {
    $totalSubscriptionValue += (float)($plan['selling_price'] ?? 0);
    if (strtolower((string)($plan['status'] ?? 'active')) === 'active') {
        $activeSubscriptionCount++;
    }
}

$averagePlanPrice = $totalPlans > 0 ? ($totalSubscriptionValue / $totalPlans) : 0;

$topExamAnalytics = array_slice($examAnalytics, 0, 8);

$chartData = [
    'userStatus' => [
        'labels' => ['Active Users', 'Inactive Users'],
        'values' => [$activeUsers, $inactiveUsers]
    ],
    'mockStatus' => [
        'labels' => ['Active', 'Draft', 'Inactive'],
        'values' => [$activeMockTests, $draftMockTests, $inactiveMockTests]
    ],
    'materialAccess' => [
        'labels' => ['Free', 'Paid'],
        'values' => [$freeMaterials, $paidMaterials]
    ],
    'supportStatus' => [
        'labels' => ['New', 'In Progress', 'Resolved', 'Closed'],
        'values' => [$newSupport, $inProgressSupport, $resolvedSupport, $closedSupport]
    ],
    'examQuestions' => [
        'labels' => array_map(function ($row) {
            return $row['exam_name'];
        }, $topExamAnalytics),
        'values' => array_map(function ($row) {
            return (int)$row['questions'];
        }, $topExamAnalytics)
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics</title>
    <link rel="stylesheet" href="assets/css/analytics.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="page-wrap">
    <div class="page-top">
        <div>
            <h1>Analytics</h1>
            <p>Platform overview, content growth, support workload, and exam-wise breakdown.</p>
        </div>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <section class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <p><?php echo number_format($totalUsers); ?></p>
            <span><?php echo number_format($activeUsers); ?> active · <?php echo number_format($inactiveUsers); ?> inactive</span>
        </div>

        <div class="stat-card">
            <h3>Total Exams</h3>
            <p><?php echo number_format($totalExams); ?></p>
            <span><?php echo number_format($totalSubjects); ?> subjects mapped</span>
        </div>

        <div class="stat-card">
            <h3>Total Mock Tests</h3>
            <p><?php echo number_format($totalMockTests); ?></p>
            <span><?php echo number_format($activeMockTests); ?> active · <?php echo number_format($draftMockTests); ?> draft</span>
        </div>

        <div class="stat-card">
            <h3>Total Questions</h3>
            <p><?php echo number_format($totalQuestions); ?></p>
            <span>Master question bank size</span>
        </div>

        <div class="stat-card">
            <h3>Total Materials</h3>
            <p><?php echo number_format($totalMaterials); ?></p>
            <span><?php echo number_format($freeMaterials); ?> free · <?php echo number_format($paidMaterials); ?> paid</span>
        </div>

        <div class="stat-card">
            <h3>Support Entries</h3>
            <p><?php echo number_format($totalSupportEntries); ?></p>
            <span><?php echo number_format($newSupport); ?> new · <?php echo number_format($inProgressSupport); ?> in progress</span>
        </div>
    </section>

    <section class="charts-grid">
        <div class="card chart-card">
            <h2>User Status</h2>
            <canvas id="userStatusChart"></canvas>
        </div>

        <div class="card chart-card">
            <h2>Mock Test Status</h2>
            <canvas id="mockStatusChart"></canvas>
        </div>

        <div class="card chart-card">
            <h2>Material Access</h2>
            <canvas id="materialAccessChart"></canvas>
        </div>

        <div class="card chart-card">
            <h2>Support Status</h2>
            <canvas id="supportStatusChart"></canvas>
        </div>
    </section>

    <section class="card chart-card full-width-card">
        <h2>Exam-wise Question Count</h2>
        <canvas id="examQuestionsChart"></canvas>
    </section>

    <section class="two-grid">
        <div class="card">
            <h2>Platform Snapshot</h2>
            <div class="mini-grid">
                <div class="mini-card">
                    <span>Active Plans</span>
                    <strong><?php echo number_format($activeSubscriptionCount); ?></strong>
                </div>
                <div class="mini-card">
                    <span>Total Plans</span>
                    <strong><?php echo number_format($totalPlans); ?></strong>
                </div>
                <div class="mini-card">
                    <span>Average Plan Price</span>
                    <strong>₹<?php echo number_format($averagePlanPrice, 2); ?></strong>
                </div>
                <div class="mini-card">
                    <span>Announcements</span>
                    <strong><?php echo number_format($totalAnnouncements); ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Support Status Breakdown</h2>
            <div class="status-list">
                <div class="status-row">
                    <span>New</span>
                    <strong><?php echo number_format($newSupport); ?></strong>
                </div>
                <div class="status-row">
                    <span>In Progress</span>
                    <strong><?php echo number_format($inProgressSupport); ?></strong>
                </div>
                <div class="status-row">
                    <span>Resolved</span>
                    <strong><?php echo number_format($resolvedSupport); ?></strong>
                </div>
                <div class="status-row">
                    <span>Closed</span>
                    <strong><?php echo number_format($closedSupport); ?></strong>
                </div>
            </div>
        </div>
    </section>

    <section class="two-grid">
        <div class="card">
            <h2>Recent Users</h2>
            <div class="table-lite-wrap">
                <table class="table-lite">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recentUsers)): ?>
                        <?php foreach ($recentUsers as $user): ?>
                            <?php $status = strtolower((string)($user['status'] ?? 'active')); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td><span class="tag <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty-state">No users found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Recent Mock Tests</h2>
            <div class="table-lite-wrap">
                <table class="table-lite">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Exam</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recentMockTests)): ?>
                        <?php foreach ($recentMockTests as $mock): ?>
                            <?php
                            $status = strtolower((string)($mock['status'] ?? 'draft'));
                            $examName = $examMap[(int)($mock['exam_id'] ?? 0)]['exam_name'] ?? '-';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mock['title'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($examName); ?></td>
                                <td><span class="tag <?php echo htmlspecialchars(str_replace(' ', '-', $status)); ?>"><?php echo htmlspecialchars(ucwords($status)); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty-state">No mock tests found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Exam-wise Content Analytics</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Exam</th>
                        <th>Subjects</th>
                        <th>Questions</th>
                        <th>Mock Tests</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($examAnalytics)): ?>
                    <?php foreach ($examAnalytics as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['department_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['exam_name'] ?? '-'); ?></td>
                            <td><?php echo number_format((int)$row['subjects']); ?></td>
                            <td><?php echo number_format((int)$row['questions']); ?></td>
                            <td><?php echo number_format((int)$row['mock_tests']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">No analytics data found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Recent Support & Feedback</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($recentSupport)): ?>
                    <?php foreach ($recentSupport as $entry): ?>
                        <?php
                        $type = strtolower((string)($entry['request_type'] ?? 'support'));
                        $status = strtolower((string)($entry['status'] ?? 'new'));
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['full_name'] ?? '-'); ?></td>
                            <td><span class="tag type-<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucfirst($type)); ?></span></td>
                            <td><?php echo htmlspecialchars($entry['subject'] ?? '-'); ?></td>
                            <td class="message-cell"><?php echo htmlspecialchars($entry['message'] ?? '-'); ?></td>
                            <td><span class="tag status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">No support entries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
const analyticsData = <?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom'
        }
    }
};

new Chart(document.getElementById('userStatusChart'), {
    type: 'doughnut',
    data: {
        labels: analyticsData.userStatus.labels,
        datasets: [{
            data: analyticsData.userStatus.values,
            borderWidth: 0
        }]
    },
    options: commonOptions
});

new Chart(document.getElementById('mockStatusChart'), {
    type: 'pie',
    data: {
        labels: analyticsData.mockStatus.labels,
        datasets: [{
            data: analyticsData.mockStatus.values,
            borderWidth: 0
        }]
    },
    options: commonOptions
});

new Chart(document.getElementById('materialAccessChart'), {
    type: 'doughnut',
    data: {
        labels: analyticsData.materialAccess.labels,
        datasets: [{
            data: analyticsData.materialAccess.values,
            borderWidth: 0
        }]
    },
    options: commonOptions
});

new Chart(document.getElementById('supportStatusChart'), {
    type: 'bar',
    data: {
        labels: analyticsData.supportStatus.labels,
        datasets: [{
            label: 'Entries',
            data: analyticsData.supportStatus.values,
            borderWidth: 1
        }]
    },
    options: {
        ...commonOptions,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

new Chart(document.getElementById('examQuestionsChart'), {
    type: 'bar',
    data: {
        labels: analyticsData.examQuestions.labels,
        datasets: [{
            label: 'Questions',
            data: analyticsData.examQuestions.values,
            borderWidth: 1
        }]
    },
    options: {
        ...commonOptions,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>
</body>
</html>