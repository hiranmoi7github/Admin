<?php
require_once __DIR__ . '/includes/supabase_api.php';
require_once __DIR__ . '/includes/mail_helper.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your admin email.';
    } else {
        $adminRes = getAdminByEmail($email);

        if (($adminRes['success'] ?? false) && !empty($adminRes['data'])) {
            $admin = $adminRes['data'][0];

            $token = bin2hex(random_bytes(32));
            $expiry = date('c', strtotime('+1 hour'));

            $setTokenResult = setAdminResetToken($email, $token, $expiry);

            if ($setTokenResult['success']) {
                $resetLink = 'http://localhost/tyt_admin/reset_password.php?token=' . urlencode($token);

                $mailResult = sendResetPasswordEmail(
                    $admin['email'] ?? '',
                    $admin['name'] ?? 'Admin',
                    $resetLink
                );

                if ($mailResult['success']) {
                    $success = 'Reset password email sent successfully.';
                } else {
                    $error = $mailResult['message'] ?? 'Failed to send email.';
                }
            } else {
                $error = 'Failed to generate reset token.';
            }
        } else {
            $error = 'No admin account found with this email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <h1>Forgot Password</h1>
                <p>Enter your admin email to receive a reset link.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="auth-msg success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="auth-msg error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter admin email" required>
                </div>

                <button type="submit" class="auth-btn">Send Reset Link</button>
            </form>

            <div class="auth-bottom-link">
                <a href="admin_login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>