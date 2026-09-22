<?php

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| BASE SUPABASE REQUEST FUNCTION
|--------------------------------------------------------------------------
*/
function supabaseRequest(string $method, string $endpoint, array $data = null, bool $useServiceRole = false): array
{
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $apiKey = $useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;

    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true)) {
        $headers[] = 'Prefer: return=representation';
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'status' => 0,
            'error' => $curlError,
            'data' => null
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'status' => $httpCode,
            'error' => null,
            'data' => $decoded
        ];
    }

    return [
        'success' => false,
        'status' => $httpCode,
        'error' => $decoded ?: $response,
        'data' => null
    ];
}

/*
|--------------------------------------------------------------------------
| COMMON CRUD HELPERS
|--------------------------------------------------------------------------
*/
function getRows(string $table, string $query = 'select=*', bool $useServiceRole = false): array
{
    $endpoint = $table . '?' . $query;
    return supabaseRequest('GET', $endpoint, null, $useServiceRole);
}

function insertRow(string $table, array $row, bool $useServiceRole = true): array
{
    return supabaseRequest('POST', $table, $row, $useServiceRole);
}

function insertRows(string $table, array $rows, bool $useServiceRole = true): array
{
    return supabaseRequest('POST', $table, $rows, $useServiceRole);
}

function updateRows(string $table, string $filterQuery, array $data, bool $useServiceRole = true): array
{
    $endpoint = $table . '?' . $filterQuery;
    return supabaseRequest('PATCH', $endpoint, $data, $useServiceRole);
}

function deleteRows(string $table, string $filterQuery, bool $useServiceRole = true): array
{
    $endpoint = $table . '?' . $filterQuery;
    return supabaseRequest('DELETE', $endpoint, null, $useServiceRole);
}


/*
|--------------------------------------------------------------------------
| TABLE COUNTS FOR DASHBOARD
|--------------------------------------------------------------------------
*/
function getTableCount(string $table): int
{
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $table . '?select=*';
    $apiKey = SUPABASE_SERVICE_ROLE_KEY;

    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Prefer: count=exact'
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError || $httpCode < 200 || $httpCode >= 300) {
        return 0;
    }

    if (preg_match('/content-range:\\s*\\d+-\\d+\\/(\\d+)/i', $response, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function getDashboardCounts(): array
{
    return [
        'users' => getTableCount(TABLE_USERS),
        'exams' => getTableCount(TABLE_EXAMS),
        'mock_tests' => getTableCount(TABLE_MOCK_TESTS),
        'questions' => getTableCount(TABLE_QUESTIONS)
    ];
}

/*
|--------------------------------------------------------------------------
| ADMINS
|--------------------------------------------------------------------------
*/

function getAllAdmins(): array
{
    return getRows('admins', 'select=*&order=employee_id.asc.nullslast,id.asc', true);
}

function getAdminById(int $id): array
{
    return getRows('admins', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function getAdminByEmail(string $email): array
{
    $email = strtolower(trim($email));
    return getRows('admins', 'select=*&email=eq.' . urlencode($email) . '&limit=1', true);
}

function getNextAdminEmployeeId(): int
{
    $response = getRows('admins', 'select=employee_id&order=employee_id.desc.nullslast&limit=1', true);

    if (($response['success'] ?? false) && !empty($response['data'])) {
        $lastEmployeeId = (int)($response['data'][0]['employee_id'] ?? 0);
        return max(1, $lastEmployeeId + 1);
    }

    return 1;
}

function getAdminsForReporting(): array
{
    return getRows(
        'admins',
        'select=id,name,employee_id,designation,role,status&status=eq.active&order=employee_id.asc.nullslast,id.asc',
        true
    );
}

function addAdminAdvanced(
    string $name,
    string $email,
    string $passwordHash,
    string $designation,
    string $role,
    array $permissions,
    string $status = 'active',
    ?int $reportsToAdminId = null
): array {
    $name = trim($name);
    $email = strtolower(trim($email));
    $designation = trim($designation);
    $role = trim($role);
    $status = trim($status);

    $employeeId = getNextAdminEmployeeId();

    $payload = [
        'employee_id' => $employeeId,
        'name' => $name,
        'email' => $email,
        'password' => $passwordHash,
        'designation' => $designation,
        'role' => $role,
        'permissions' => array_values($permissions),
        'status' => $status,
        'reports_to_admin_id' => $reportsToAdminId,
        'created_at' => date('c')
    ];

    return insertRow('admins', $payload, true);
}

function updateAdminAdvanced(
    int $id,
    string $name,
    string $email,
    string $designation,
    string $role,
    array $permissions,
    string $status = 'active',
    ?int $reportsToAdminId = null
): array {
    $name = trim($name);
    $email = strtolower(trim($email));
    $designation = trim($designation);
    $role = trim($role);
    $status = trim($status);

    $payload = [
        'name' => $name,
        'email' => $email,
        'designation' => $designation,
        'role' => $role,
        'permissions' => array_values($permissions),
        'status' => $status,
        'reports_to_admin_id' => $reportsToAdminId
    ];

    return updateRows('admins', 'id=eq.' . $id, $payload, true);
}

function updateAdminPassword(int $id, string $passwordHash): array
{
    return updateRows('admins', 'id=eq.' . $id, [
        'password' => $passwordHash
    ], true);
}

function setAdminResetToken(string $email, string $token, string $expiry): array
{
    $email = strtolower(trim($email));

    return updateRows(
        'admins',
        'email=eq.' . urlencode($email),
        [
            'reset_token' => $token,
            'reset_token_expiry' => $expiry
        ],
        true
    );
}

function getAdminByResetToken(string $token): array
{
    return getRows(
        'admins',
        'select=*&reset_token=eq.' . urlencode($token) . '&limit=1',
        true
    );
}

function clearAdminResetToken(int $id): array
{
    return updateRows(
        'admins',
        'id=eq.' . $id,
        [
            'reset_token' => null,
            'reset_token_expiry' => null
        ],
        true
    );
}

function updateAdminAvatar(int $id, string $avatarUrl): array
{
    return updateRows(
        'admins',
        'id=eq.' . $id,
        ['avatar_url' => $avatarUrl],
        true
    );
}

function deleteAdminById(int $id): array
{
    return deleteRows('admins', 'id=eq.' . $id, true);
}

function authenticateAdmin(string $email, string $password): array
{
    $response = getAdminByEmail($email);

    if (!(($response['success'] ?? false)) || empty($response['data'])) {
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }

    $admin = $response['data'][0];
    $storedPassword = (string)($admin['password'] ?? '');
    $status = strtolower((string)($admin['status'] ?? 'active'));

    if ($status !== 'active') {
        return [
            'success' => false,
            'message' => 'This admin account is inactive.'
        ];
    }

    if ($storedPassword === '' || !password_verify($password, $storedPassword)) {
        return [
            'success' => false,
            'message' => 'Invalid email or password.'
        ];
    }

    return [
        'success' => true,
        'admin' => $admin
    ];
}
/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/
function getAllProfiles(int $limit = 100): array
{
    $limit = max(1, $limit);
    return getRows('profiles', 'select=*&order=id.desc&limit=' . $limit, true);
}

function updateProfileStatus(int $id, string $status): array
{
    return updateRows('profiles', 'id=eq.' . $id, [
        'status' => $status
    ], true);
}

function getProfilesCount(): int
{
    return getTableCount('profiles');
}

/*
|--------------------------------------------------------------------------
| EXAMS
|--------------------------------------------------------------------------
*/
function getAllExams(): array
{
    return getRows('exams', 'select=*&order=id.desc', true);
}

function getExamById(int $id): array
{
    return getRows(TABLE_EXAMS, 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addExam(
    string $chooseCategories,
    string $examName,
    string $slug,
    string $description = '',
    string $logoUrl = '',
    string $status = 'active'
): array
{
    $payload = [
        'choose_categories' => $chooseCategories,
        'exam_name' => $examName,
        'slug' => $slug,
        'description' => $description,
        'logo_url' => $logoUrl,
        'status' => $status,
        'created_at' => date('c')
    ];

    return insertRow(TABLE_EXAMS, $payload, true);
}

function updateExam(
    int $id,
    string $chooseCategories,
    string $examName,
    string $slug,
    string $description = '',
    string $logoUrl = '',
    string $status = 'active'
): array
{
    $payload = [
        'choose_categories' => $chooseCategories,
        'exam_name' => $examName,
        'slug' => $slug,
        'description' => $description,
        'logo_url' => $logoUrl,
        'status' => $status
    ];

    return updateRows(
        TABLE_EXAMS,
        'id=eq.' . $id,
        $payload,
        true
    );
}

function deleteExam(int $id): array
{
    return deleteRows(
        TABLE_EXAMS,
        'id=eq.' . $id,
        true
    );
}

/*
|--------------------------------------------------------------------------
| SUBJECTS
|--------------------------------------------------------------------------
*/
function getAllSubjects(): array
{
    return getRows(TABLE_SUBJECTS, 'select=*&order=id.asc', true);
}

function getSubjectsByExamId(int $examId): array
{
    return getRows(TABLE_SUBJECTS, 'select=*&exam_id=eq.' . $examId . '&order=id.asc', true);
}

function getSubjectById(int $id): array
{
    return getRows(TABLE_SUBJECTS, 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addSubject(int $examId, string $subjectName, string $status = 'active'): array
{
    $payload = [
        'exam_id' => $examId,
        'subject_name' => $subjectName,
        'status' => $status,
        'created_at' => date('c')
    ];

    return insertRow(TABLE_SUBJECTS, $payload, true);
}

function updateSubject(int $id, int $examId, string $subjectName, string $status): array
{
    $payload = [
        'exam_id' => $examId,
        'subject_name' => $subjectName,
        'status' => $status
    ];

    return updateRows(TABLE_SUBJECTS, 'id=eq.' . $id, $payload, true);
}

function deleteSubject(int $id): array
{
    return deleteRows(TABLE_SUBJECTS, 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| MOCK TESTS
|--------------------------------------------------------------------------
*/
function getAllMockTests(): array
{
    return getRows('mock_tests', 'select=*&order=id.desc', true);
}

function getMockTestById(int $id): array
{
    return getRows('mock_tests', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addMockTest(
    string $title,
    int $examId,
    int $durationMinutes,
    int $totalQuestions,
    int $totalMarks,
    float $negativeMarks,
    string $accessType,
    string $status
): array {
    return insertRow('mock_tests', [
        'title' => $title,
        'exam_id' => $examId,
        'duration_minutes' => $durationMinutes,
        'total_questions' => $totalQuestions,
        'total_marks' => $totalMarks,
        'negative_marks' => $negativeMarks,
        'access_type' => $accessType,
        'status' => $status,
        'created_at' => date('c')
    ], true);
}

function updateMockTest(
    int $id,
    string $title,
    int $examId,
    int $durationMinutes,
    int $totalQuestions,
    int $totalMarks,
    float $negativeMarks,
    string $accessType,
    string $status
): array {
    return updateRows('mock_tests', 'id=eq.' . $id, [
        'title' => $title,
        'exam_id' => $examId,
        'duration_minutes' => $durationMinutes,
        'total_questions' => $totalQuestions,
        'total_marks' => $totalMarks,
        'negative_marks' => $negativeMarks,
        'access_type' => $accessType,
        'status' => $status
    ], true);
}

function deleteMockTest(int $id): array
{
    return deleteRows('mock_tests', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| MOCKTEST_SUBJECTS
|--------------------------------------------------------------------------
*/

function getMockTestSubjects(int $mockTestId): array
{
    return getRows(
        'mock_test_subjects',
        'select=*&mock_test_id=eq.' . $mockTestId . '&order=id.asc',
        true
    );
}

function addMockTestSubject(int $mockTestId, int $subjectId, int $questionCount): array
{
    return insertRow('mock_test_subjects', [
        'mock_test_id' => $mockTestId,
        'subject_id' => $subjectId,
        'question_count' => $questionCount,
        'created_at' => date('c')
    ], true);
}

function updateMockTestSubject(int $id, int $subjectId, int $questionCount): array
{
    return updateRows('mock_test_subjects', 'id=eq.' . $id, [
        'subject_id' => $subjectId,
        'question_count' => $questionCount
    ], true);
}

function deleteMockTestSubject(int $id): array
{
    return deleteRows('mock_test_subjects', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| MOCKTEST_QUESTIONS
|--------------------------------------------------------------------------
*/
function getMockTestQuestions(int $mockTestId): array
{
    return getRows(
        'mock_test_questions',
        'select=*&mock_test_id=eq.' . $mockTestId . '&order=question_order.asc,id.asc',
        true
    );
}

function addMockTestQuestion(int $mockTestId, int $questionId, int $questionOrder): array
{
    return insertRow('mock_test_questions', [
        'mock_test_id' => $mockTestId,
        'question_id' => $questionId,
        'question_order' => $questionOrder,
        'created_at' => date('c')
    ], true);
}

function updateMockTestQuestion(int $id, int $questionId, int $questionOrder): array
{
    return updateRows('mock_test_questions', 'id=eq.' . $id, [
        'question_id' => $questionId,
        'question_order' => $questionOrder
    ], true);
}

function deleteMockTestQuestion(int $id): array
{
    return deleteRows('mock_test_questions', 'id=eq.' . $id, true);
}

function deleteMockTestQuestionsByMockTest(int $mockTestId): array
{
    return deleteRows('mock_test_questions', 'mock_test_id=eq.' . $mockTestId, true);
}

/*
|--------------------------------------------------------------------------
| QUESTIONS
|--------------------------------------------------------------------------
*/
function getAllQuestions(int $limit = 100): array
{
    $limit = max(1, $limit);
    return getRows(TABLE_QUESTIONS, 'select=*&order=id.desc&limit=' . $limit, true);
}

function getQuestionById(int $id): array
{
    return getRows(TABLE_QUESTIONS, 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addQuestion(array $questionData): array
{
    $payload = [
        'exam_id' => $questionData['exam_id'] ?? null,
        'subject_id' => $questionData['subject_id'] ?? null,
        'mock_test_id' => $questionData['mock_test_id'] ?? null,
        'question_text' => $questionData['question_text'] ?? '',
        'option_a' => $questionData['option_a'] ?? '',
        'option_b' => $questionData['option_b'] ?? '',
        'option_c' => $questionData['option_c'] ?? '',
        'option_d' => $questionData['option_d'] ?? '',
        'correct_option' => $questionData['correct_option'] ?? '',
        'explanation' => $questionData['explanation'] ?? '',
        'difficulty_level' => $questionData['difficulty_level'] ?? 'medium',
        'marks' => $questionData['marks'] ?? 1,
        'negative_marks' => $questionData['negative_marks'] ?? 0,
        'status' => $questionData['status'] ?? 'draft',
        'created_at' => date('c')
    ];

    return insertRow(TABLE_QUESTIONS, $payload, true);
}

function updateQuestion(int $id, array $questionData): array
{
    $payload = [
        'exam_id' => $questionData['exam_id'] ?? null,
        'subject_id' => $questionData['subject_id'] ?? null,
        'mock_test_id' => $questionData['mock_test_id'] ?? null,
        'question_text' => $questionData['question_text'] ?? '',
        'option_a' => $questionData['option_a'] ?? '',
        'option_b' => $questionData['option_b'] ?? '',
        'option_c' => $questionData['option_c'] ?? '',
        'option_d' => $questionData['option_d'] ?? '',
        'correct_option' => $questionData['correct_option'] ?? '',
        'explanation' => $questionData['explanation'] ?? '',
        'difficulty_level' => $questionData['difficulty_level'] ?? 'medium',
        'marks' => $questionData['marks'] ?? 1,
        'negative_marks' => $questionData['negative_marks'] ?? 0,
        'status' => $questionData['status'] ?? 'draft'
    ];

    return updateRows(TABLE_QUESTIONS, 'id=eq.' . $id, $payload, true);
}

function deleteQuestion(int $id): array
{
    return deleteRows(TABLE_QUESTIONS, 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| MATERIALS
|--------------------------------------------------------------------------
*/
function getAllMaterials(): array
{
    return getRows('materials', 'select=*&order=id.desc', true);
}

function getMaterialById(int $id): array
{
    return getRows('materials', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addMaterial(
    int $examId,
    string $materialTitle,
    string $materialCategory,
    string $accessType,
    string $pdfUrl,
    string $status
): array {
    return insertRow('materials', [
        'exam_id' => $examId,
        'material_title' => $materialTitle,
        'material_category' => $materialCategory,
        'access_type' => $accessType,
        'pdf_url' => $pdfUrl,
        'status' => $status,
        'created_at' => date('c')
    ], true);
}

function updateMaterial(
    int $id,
    int $examId,
    string $materialTitle,
    string $materialCategory,
    string $accessType,
    string $pdfUrl,
    string $status
): array {
    return updateRows('materials', 'id=eq.' . $id, [
        'exam_id' => $examId,
        'material_title' => $materialTitle,
        'material_category' => $materialCategory,
        'access_type' => $accessType,
        'pdf_url' => $pdfUrl,
        'status' => $status
    ], true);
}

function deleteMaterial(int $id): array
{
    return deleteRows('materials', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| RESULTS
|--------------------------------------------------------------------------
*/
function getAllResults(): array
{
    return getRows('results', 'select=*&order=submitted_at.desc', true);
}

function getResultById(int $id): array
{
    return getRows('results', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function deleteResult(int $id): array
{
    return deleteRows('results', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| GENERIC STATUS HELPERS
|--------------------------------------------------------------------------
*/
function updateStatus(string $table, int $id, string $status): array
{
    return updateRows($table, 'id=eq.' . $id, ['status' => $status], true);
}

function deleteById(string $table, int $id): array
{
    return deleteRows($table, 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| TOTAL SALES
|--------------------------------------------------------------------------
*/
function getTableCountSafe(string $table): int
{
    return getTableCount($table);
}

function getTotalSales(): float
{
    $response = getRows('payments', 'select=amount,payment_status', true);

    if (!($response['success'] ?? false)) {
        return 0;
    }

    $rows = $response['data'] ?? [];
    $total = 0;

    foreach ($rows as $row) {
        if (($row['payment_status'] ?? '') === 'paid') {
            $total += (float)($row['amount'] ?? 0);
        }
    }

    return $total;
}

function getDashboardStats(): array
{
    return [
        'users' => getProfilesCount(),
        'exams' => defined('TABLE_EXAMS') ? getTableCount(TABLE_EXAMS) : 0,
        'subjects' => defined('TABLE_SUBJECTS') ? getTableCount(TABLE_SUBJECTS) : 0,
        'mock_tests' => defined('TABLE_MOCK_TESTS') ? getTableCount(TABLE_MOCK_TESTS) : 0,
        'questions' => defined('TABLE_QUESTIONS') ? getTableCount(TABLE_QUESTIONS) : 0,
        'sales' => function_exists('getTotalSales') ? getTotalSales() : 0
    ];
}

/*
|--------------------------------------------------------------------------
| ANNOUNCEMENTS
|--------------------------------------------------------------------------
*/
function getAllAnnouncements(): array
{
    return getRows('announcements', 'select=*&order=id.asc', true);
}

function getAnnouncementById(int $id): array
{
    return getRows('announcements', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addAnnouncement(
    string $sectionType,
    string $title,
    string $message,
    string $imageUrl,
    bool $showButton,
    string $buttonText,
    string $buttonLink,
    string $buttonColor,
    string $backgroundColor,
    string $textColor,
    bool $isEnabled,
    string $status
): array {
    return insertRow('announcements', [
        'section_type' => $sectionType,
        'title' => $title,
        'message' => $message,
        'image_url' => $imageUrl,
        'show_button' => $showButton,
        'button_text' => $buttonText,
        'button_link' => $buttonLink,
        'button_color' => $buttonColor,
        'background_color' => $backgroundColor,
        'text_color' => $textColor,
        'is_enabled' => $isEnabled,
        'status' => $status,
        'created_at' => date('c')
    ], true);
}

function updateAnnouncement(
    int $id,
    string $sectionType,
    string $title,
    string $message,
    string $imageUrl,
    bool $showButton,
    string $buttonText,
    string $buttonLink,
    string $buttonColor,
    string $backgroundColor,
    string $textColor,
    bool $isEnabled,
    string $status
): array {
    return updateRows('announcements', 'id=eq.' . $id, [
        'section_type' => $sectionType,
        'title' => $title,
        'message' => $message,
        'image_url' => $imageUrl,
        'show_button' => $showButton,
        'button_text' => $buttonText,
        'button_link' => $buttonLink,
        'button_color' => $buttonColor,
        'background_color' => $backgroundColor,
        'text_color' => $textColor,
        'is_enabled' => $isEnabled,
        'status' => $status
    ], true);
}

function deleteAnnouncement(int $id): array
{
    return deleteRows('announcements', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| SUPPORT_FEEDBACK
|--------------------------------------------------------------------------
*/
function getAllSupportFeedback(): array
{
    return getRows('support_feedback', 'select=*&order=id.desc', true);
}

function getSupportFeedbackById(int $id): array
{
    return getRows('support_feedback', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function updateSupportFeedback(
    int $id,
    string $status,
    string $adminNote
): array {
    return updateRows('support_feedback', 'id=eq.' . $id, [
        'status' => $status,
        'admin_note' => $adminNote
    ], true);
}

function deleteSupportFeedback(int $id): array
{
    return deleteRows('support_feedback', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| SUBSCRIPTION
|--------------------------------------------------------------------------
*/

function getActiveSubscriptions(): array
{
    return getRows(
        'subscriptions',
        'select=*&status=eq.active&order=sort_order.asc,id.asc',
        true
    );
}

function getAllSubscriptions(): array
{
    return getRows('subscriptions', 'select=*&order=id.asc', true);
}

function getSubscriptionById(int $id): array
{
    return getRows('subscriptions', 'select=*&id=eq.' . $id . '&limit=1', true);
}

function addSubscription(
    string $planName,
    string $durationLabel,
    float $originalPrice,
    float $sellingPrice,
    string $description,
    array $offerings,
    string $status
): array {
    return insertRow('subscriptions', [
        'plan_name' => $planName,
        'duration_label' => $durationLabel,
        'original_price' => $originalPrice,
        'selling_price' => $sellingPrice,
        'description' => $description,
        'offerings' => array_values($offerings),
        'status' => $status,
        'created_at' => date('c')
    ], true);
}

function updateSubscription(
    int $id,
    string $planName,
    string $durationLabel,
    float $originalPrice,
    float $sellingPrice,
    string $description,
    array $offerings,
    string $status
): array {
    return updateRows('subscriptions', 'id=eq.' . $id, [
        'plan_name' => $planName,
        'duration_label' => $durationLabel,
        'original_price' => $originalPrice,
        'selling_price' => $sellingPrice,
        'description' => $description,
        'offerings' => array_values($offerings),
        'status' => $status
    ], true);
}

function deleteSubscription(int $id): array
{
    return deleteRows('subscriptions', 'id=eq.' . $id, true);
}

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/
function getSettings(): array
{
    return getRows('settings', 'select=*&limit=1', true);
}

function updateSettings(array $data): array
{
    return updateRows('settings', 'id=gt.0', $data, true);
}
?>