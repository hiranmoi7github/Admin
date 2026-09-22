<?php
require_once __DIR__ . '/../admin/includes/supabase_api.php';

header('Content-Type: application/json');

$res = getActiveSubscriptions();

echo json_encode([
    'success' => $res['success'] ?? false,
    'data' => $res['data'] ?? [],
    'error' => $res['error'] ?? null
]);