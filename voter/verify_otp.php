<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// Must have a pending OTP session
if (empty($_SESSION['otp_voter_id'])) {
    header('Location: ' . BASE_URL . '/voter/login.php');
    exit;
}

$voter_id    = (int)$_SESSION['otp_voter_id'];
$voter_name  = $_SESSION['otp_voter_name']  ?? 'Voter';
$voter_email = $_SESSION['otp_voter_email'] ?? '';
$masked_email = '';
if ($voter_email) {
    [$local, $domain] = explode('@', $voter_email);
    $masked_email = substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2)) . '@' . $domain;
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $otp_input = trim($_POST['otp_hidden'] ?? '');

        if (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
            $error = 'Please enter all 6 digits of the OTP.';
        } else {
            $pdo = getDB();
            if (verifyOTP($pdo, $voter_id, $otp_input, 'login')) {
                // OTP valid — create full session
                $_SESSION['voter_id']   = $voter_id;
                $_SESSION['voter_name'] = $voter_name;
                unset($_SESSION['otp_voter_id'], $_SESSION['otp_voter_name'], $_SESSION['otp_voter_email']);

                setFlash('success', 'Welcome back, ' . $voter_name . '! You are now logged in.');
                header('Location: ' . BASE_URL . '/voter/dashboard.php');
                exit;
            } else {
                $error = 'Invalid or expired OTP. Please try again or request a new OTP.';
            }
        }
    }
}

// Resend OTP
if (isset($_GET['resend'])) {
    $pdo     = getDB();
    $new_otp = createOTP($pdo, $voter_id, 'login');
    $sent    = sendOTPEmail($voter_email, $voter_name, $new_otp, 'login');
    if ($sent) {
        setFlash('success', 'A new OTP has been sent to your email.');
    } else {
        setFlash('danger', 'Failed to resend OTP. Please try again.');
    }
    header('Location: ' . BASE_URL . '/voter/verify_otp.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="logo">&#x1F510;</div>
            <h1>OTP Verification</h1>
            <p>Enter the 6-digit code to verify your identity</p>
        </div>
        <div class="auth-body">

            <?php showFlash(); ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="alert alert-info" style="text-align:center;">
                &#x1F4E7; A 6-digit OTP has been sent to
                <strong><?= e($masked_email) ?></strong><br>
                <small>Check your inbox (and spam folder).</small>
            </div>

            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="otp_hidden" id="otp_hidden" value="">

                <div class="otp-inputs">
                    <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    &#x2714; Verify &amp; Login
                </button>
            </form>

            <div style="text-align:center;margin-top:16px;">
                <p style="font-size:0.83rem;color:var(--gray);">
                    Didn't receive your OTP?
                    <a href="<?= BASE_URL ?>/voter/verify_otp.php?resend=1" style="color:var(--primary);font-weight:600;">Resend OTP</a>
                </p>
                <p style="font-size:0.8rem;color:var(--gray);margin-top:8px;">
                    OTP expires in 10 minutes.
                    <a href="<?= BASE_URL ?>/voter/login.php" style="color:var(--gray);">Back to Login</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
