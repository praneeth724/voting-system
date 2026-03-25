<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['authority_id'])) {
    header('Location: ' . BASE_URL . '/authority/dashboard.php');
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
            $stmt = $pdo->prepare("SELECT * FROM authorities WHERE username = ?");
            $stmt->execute([$username]);
            $auth = $stmt->fetch();

            if (!$auth || !password_verify($password, $auth['password'])) {
                $error = 'Invalid username or password.';
            } else {
                $_SESSION['authority_id']       = $auth['id'];
                $_SESSION['authority_name']     = $auth['full_name'];
                $_SESSION['authority_district'] = $auth['district'];
                setFlash('success', 'Welcome, ' . $auth['full_name'] . '!');
                header('Location: ' . BASE_URL . '/authority/dashboard.php');
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
    <title>Authority Login - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header" style="background:var(--success);">
            <div class="logo">&#x1F4CB;</div>
            <h1>Authority Portal</h1>
            <p>District Authority / Community Leader Login</p>
        </div>
        <div class="auth-body">
            <?php showFlash(); ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           placeholder="e.g. auth_colombo" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-success btn-block btn-lg">Login</button>
            </form>
        </div>
        <div class="auth-footer">
            <a href="<?= BASE_URL ?>/index.php">Back to Home</a>
            &nbsp;|&nbsp;
            <a href="<?= BASE_URL ?>/admin/login.php">Admin Login</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
