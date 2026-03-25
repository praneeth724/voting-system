<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sri Lanka Online Voting System</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
            <div class="brand-icon">&#x1F5F3;</div>
            <div>
                <div style="font-size:0.75rem;opacity:0.7;line-height:1;">Democratic Republic of</div>
                Sri Lanka e-Vote
            </div>
        </a>
        <ul class="navbar-nav">
            <?php if (isVoterLoggedIn()): ?>
                <li><a href="<?= BASE_URL ?>/voter/dashboard.php">My Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/voter/results.php">Results</a></li>
                <li><a href="<?= BASE_URL ?>/voter/logout.php" class="btn-outline-light">Logout</a></li>
            <?php else: ?>
                <li><a href="#features">Features</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="<?= BASE_URL ?>/voter/login.php">Voter Login</a></li>
                <li><a href="<?= BASE_URL ?>/voter/register.php" class="btn-outline-light">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="container hero-content">
        <h1>Your Vote,<br><span>Your Future</span></h1>
        <p>A secure, transparent, and accessible online voting platform for Sri Lankan citizens. Exercise your democratic right from anywhere.</p>
        <div class="hero-btns">
            <?php if (isVoterLoggedIn()): ?>
                <a href="<?= BASE_URL ?>/voter/dashboard.php" class="btn btn-hero-primary">Go to Dashboard</a>
                <a href="<?= BASE_URL ?>/voter/results.php"   class="btn btn-hero-secondary">View Results</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/voter/register.php" class="btn btn-hero-primary">Register to Vote</a>
                <a href="<?= BASE_URL ?>/voter/login.php"    class="btn btn-hero-secondary">Voter Login</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Features -->
<section class="section" id="features">
    <div class="container">
        <div class="section-title">
            <h2>Why Choose e-Vote?</h2>
            <p>Modern, secure, and built for every Sri Lankan citizen.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">&#x1F512;</div>
                <h3>Secure Authentication</h3>
                <p>OTP-based multi-factor authentication with NIC verification ensures only legitimate voters cast their ballot.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:#e8f5e9;">&#x2705;</div>
                <h3>Duplicate Vote Prevention</h3>
                <p>Cryptographic checks and database constraints guarantee each voter can only vote once per election.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:#e3f2fd;">&#x1F310;</div>
                <h3>Vote From Anywhere</h3>
                <p>Accessible from any device with internet access &mdash; ideal for overseas citizens and remote areas.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:#fff8e1;">&#x1F4CA;</div>
                <h3>Real-Time Results</h3>
                <p>Instant result tabulation and display after polls close, reducing uncertainty and delays.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:#fce4ec;">&#x1F91D;</div>
                <h3>Community Verification</h3>
                <p>Local authority pre-approval adds a trusted community layer to combat impersonation in remote areas.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:#f3e5f5;">&#x267F;</div>
                <h3>Accessible Design</h3>
                <p>Simple, intuitive interface designed for users of all ages and varying levels of digital literacy.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="section" style="background:var(--white);" id="how-it-works">
    <div class="container">
        <div class="section-title">
            <h2>How It Works</h2>
            <p>Four simple steps to cast your vote securely.</p>
        </div>
        <div class="steps-grid">
            <div class="step-item">
                <div class="step-num">1</div>
                <h4>Register</h4>
                <p>Submit your NIC, personal details, and create an account. Your local district officer verifies your identity.</p>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <h4>Get Approved</h4>
                <p>Your registration is reviewed by the district authority and then the election commission administrator.</p>
            </div>
            <div class="step-item">
                <div class="step-num">3</div>
                <h4>Login with OTP</h4>
                <p>Sign in with your credentials. A one-time password (OTP) is sent to verify your identity for each session.</p>
            </div>
            <div class="step-item">
                <div class="step-num">4</div>
                <h4>Cast Your Vote</h4>
                <p>Select your candidate, confirm with OTP verification, and your vote is securely recorded.</p>
            </div>
        </div>
    </div>
</section>

<!-- Active Elections Banner -->
<?php
$pdo = getDB();
$active = $pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'active'")->fetchColumn();
if ($active > 0):
?>
<section class="section" style="padding:40px 0;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);">
    <div class="container text-center">
        <h2 style="color:#1b5e20;font-size:1.5rem;">&#x1F5F3; <?= $active ?> Active Election<?= $active > 1 ? 's' : '' ?> Now Open!</h2>
        <p style="color:#2e7d32;margin:8px 0 20px;">Voting is currently open. Make your voice heard today.</p>
        <?php if (!isVoterLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/voter/login.php" class="btn btn-success btn-lg">Vote Now</a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/voter/dashboard.php" class="btn btn-success btn-lg">Go to Voting</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Admin Links -->
<section style="background:var(--light);padding:40px 0;border-top:1px solid var(--border);">
    <div class="container text-center">
        <p style="color:var(--gray);font-size:0.88rem;margin-bottom:14px;">Portal Access</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/admin/login.php"     class="btn btn-secondary btn-sm">&#x1F6E1; Admin Portal</a>
            <a href="<?= BASE_URL ?>/authority/login.php" class="btn btn-secondary btn-sm">&#x1F4CB; Authority Portal</a>
            <a href="<?= BASE_URL ?>/voter/results.php"   class="btn btn-outline  btn-sm">&#x1F4CA; View Results</a>
        </div>
    </div>
</section>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> <strong>Sri Lanka Online Voting System</strong> &mdash; Secure, Transparent &amp; Inclusive Democracy</p>
    <p style="margin-top:4px;font-size:0.75rem;">This is a prototype system for academic demonstration purposes.</p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
