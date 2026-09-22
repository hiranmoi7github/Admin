<?php
require_once __DIR__ . '/includes/auth_check.php';
requireSuperAdmin();

require_once __DIR__ . '/includes/supabase_api.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$response = getAllProfiles(5000);
$allUsers = $response['success'] ? ($response['data'] ?? []) : [];

$filteredUsers = array_filter($allUsers, function ($user) use ($search, $statusFilter) {
    $matchesSearch = true;
    $matchesStatus = true;

    if ($search !== '') {
        $haystack = strtolower(
            ($user['id'] ?? '') . ' ' .
            ($user['full_name'] ?? '') . ' ' .
            ($user['phone'] ?? '') . ' ' .
            ($user['dob'] ?? '')
        );

        $matchesSearch = str_contains($haystack, strtolower($search));
    }

    if ($statusFilter !== '') {
        $matchesStatus = (($user['status'] ?? 'active') === $statusFilter);
    }

    return $matchesSearch && $matchesStatus;
});

$filteredUsers = array_values($filteredUsers);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Users');

$headers = ['Profile ID', 'Full Name', 'Phone', 'DOB', 'Status'];
$sheet->fromArray($headers, null, 'A1');

$rowNumber = 2;
foreach ($filteredUsers as $user) {
    $sheet->setCellValue('A' . $rowNumber, $user['id'] ?? '');
    $sheet->setCellValue('B' . $rowNumber, $user['full_name'] ?? '');
    $sheet->setCellValue('C' . $rowNumber, $user['phone'] ?? '');
    $sheet->setCellValue('D' . $rowNumber, $user['dob'] ?? '');
    $sheet->setCellValue('E' . $rowNumber, $user['status'] ?? 'active');
    $rowNumber++;
}

foreach (range('A', 'E') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$filename = 'users_export_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;