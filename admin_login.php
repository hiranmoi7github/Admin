<?php
session_start();
require_once __DIR__ . '/includes/supabase_api.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'account_deleted') {
        $error = 'Your account was deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $result = authenticateAdmin($email, $password);

        if ($result['success']) {
            $admin = $result['admin'];

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'] ?? 'Admin';
            $_SESSION['admin_role'] = $admin['role'] ?? 'member';
            $_SESSION['admin_designation'] = $admin['designation'] ?? '';
            $_SESSION['admin_permissions'] = is_array($admin['permissions'])
                ? $admin['permissions']
                : json_decode($admin['permissions'] ?? '[]', true);
            $_SESSION['admin_avatar'] = $admin['avatar_url'] ?? '';

            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'] ?? 'Login failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <h1>TYT Admin Login</h1>
                <p>Sign in to manage your platform.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="auth-msg error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter admin email" required>
                </div>

                <div class="group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="auth-link-row">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button type="submit" class="auth-btn">Login</button>
            </form>
        </div>
    </div>
</body>
</html>