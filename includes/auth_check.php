<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: admin_login.php');
        exit;
    }
}

function getAdminId(): int
{
    return (int)($_SESSION['admin_id'] ?? 0);
}

function getAdminRole(): string
{
    return (string)($_SESSION['admin_role'] ?? 'member');
}

function getAdminPermissions(): array
{
    $permissions = $_SESSION['admin_permissions'] ?? [];

    if (is_string($permissions)) {
        $decoded = json_decode($permissions, true);
        return is_array($decoded) ? $decoded : [];
    }

    return is_array($permissions) ? $permissions : [];
}

function isSuperAdmin(): bool
{
    return getAdminRole() === 'super_admin';
}

function hasPermission(string $permission): bool
{
    if (isSuperAdmin()) {
        return true;
    }

    return in_array($permission, getAdminPermissions(), true);
}

function requireSuperAdmin(): void
{
    requireLogin();

    if (!isSuperAdmin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requirePermission(string $permission): void
{
    requireLogin();

    if (!hasPermission($permission)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requireAdminsAccess(): void
{
    requirePermission('admins');
}

function requireDashboardAccess(): void
{
    requirePermission('dashboard');
}

function requireContentAccess(): void
{
    requireLogin();

    if (
        hasPermission('exams') ||
        hasPermission('subjects') ||
        hasPermission('mock_tests') ||
        hasPermission('question_bank') ||
        hasPermission('materials')
    ) {
        return;
    }

    http_response_code(403);
    exit('Access denied.');
}