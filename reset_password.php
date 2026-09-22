<?php
require_once __DIR__ . '/includes/supabase_api.php';

$error = '';
$success = '';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die('Invalid reset token.');
}

$adminRes = getAdminByResetToken($token);

if (!(($adminRes['success'] ?? false) && !empty($adminRes['data']))) {
    die('Invalid or expired reset token.');
}

$admin = $adminRes['data'][0];
$expiry = strtotime((string)($admin['reset_token_expiry'] ?? ''));

if ($expiry < time()) {
    die('Reset token has expired.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirmPassword === '') {
        $error = 'Please fill all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $updatePasswordRes = updateAdminPassword((int)$admin['id'], $passwordHash);
        $clearTokenRes = clearAdminResetToken((int)$admin['id']);

        if (($updatePasswordRes['success'] ?? false) && ($clearTokenRes['success'] ?? false)) {
            $success = 'Password reset successfully. You can now login.';
        } else {
            $error = 'Failed to reset password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <h1>Reset Password</h1>
                <p>Set a new password for your admin account.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="auth-msg success"><?php echo htmlspecialchars($success); ?></div>
                <div class="auth-bottom-link">
                    <a href="admin_login.php">Go to Login</a>
                </div>
            <?php else: ?>

                <?php if ($error !== ''): ?>
                    <div class="auth-msg error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password" required>
                    </div>

                    <div class="group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>

                    <button type="submit" class="auth-btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>