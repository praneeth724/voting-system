<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password'])) {
                $error = 'Invalid username or password.';
            } else {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                setFlash('success', 'Welcome back, ' . $admin['full_name'] . '!');
                header('Location: ' . BASE_URL . '/admin/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header" style="background:var(--primary-dark);">
            <div class="logo">&#x1F6E1;</div>
            <h1>Admin Portal</h1>
            <p>Sri Lanka Election Commission &mdash; Admin Access</p>
        </div>
        <div class="auth-body">
            <?php showFlash(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           placeholder="Enter admin username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Login</button>
            </form>
        </div>
        <div class="auth-footer">
            <a href="<?= BASE_URL ?>/index.php">Back to Home</a>
            &nbsp;|&nbsp;
            <a href="<?= BASE_URL ?>/authority/login.php">Authority Login</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
