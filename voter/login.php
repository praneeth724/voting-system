<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isVoterLoggedIn()) {
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id, full_name, email, password, status FROM voters WHERE email = ?");
            $stmt->execute([$email]);
            $voter = $stmt->fetch();

            if (!$voter || !password_verify($password, $voter['password'])) {
                $error = 'Invalid email or password.';
            } elseif ($voter['status'] === 'pending') {
                $error = 'Your registration is pending district authority review. Please check back later.';
            } elseif ($voter['status'] === 'authority_approved') {
                $error = 'Your registration has been approved by the authority and is awaiting final admin approval.';
            } elseif ($voter['status'] === 'rejected') {
                $error = 'Your registration has been rejected. Please contact the Election Commission.';
            } elseif ($voter['status'] === 'approved') {
                // Generate OTP and send to voter's email
                $otp = createOTP($pdo, $voter['id'], 'login');

                $sent = sendOTPEmail($voter['email'], $voter['full_name'], $otp, 'login');

                if (!$sent) {
                    $error = 'Failed to send OTP to your email address. Please try again or contact support.';
                } else {
                    $_SESSION['otp_voter_id']    = $voter['id'];
                    $_SESSION['otp_voter_name']  = $voter['full_name'];
                    $_SESSION['otp_voter_email'] = $voter['email'];
                    header('Location: ' . BASE_URL . '/voter/verify_otp.php');
                    exit;
                }
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
    <title>Voter Login - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">&#x1F5F3;</div>
            <h1>Voter Login</h1>
            <p>Sri Lanka Online Voting System</p>
        </div>
        <div class="auth-body">

            <?php showFlash(); ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="your@email.com"
                           value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
                    Login &amp; Get OTP
                </button>
            </form>

            <div class="divider"></div>
            <p style="font-size:0.82rem;color:var(--gray);text-align:center;line-height:1.6;">
                After entering your credentials, a 6-digit One-Time Password (OTP) will be generated to verify your identity before you can vote.
            </p>
        </div>
        <div class="auth-footer">
            Don't have an account? <a href="<?= BASE_URL ?>/voter/register.php">Register here</a>
            &nbsp;|&nbsp; <a href="<?= BASE_URL ?>/index.php">Home</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
