<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireVoterLogin();

$pdo       = getDB();
$voter_id  = (int)$_SESSION['voter_id'];
$election_id = (int)($_GET['election'] ?? 0);

if (!$election_id) {
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

// Get voter info - must be approved
$stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? AND status = 'approved'");
$stmt->execute([$voter_id]);
$voter = $stmt->fetch();
if (!$voter) {
    setFlash('danger', 'Your account is not yet approved for voting.');
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

// Get election - must be active
$stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ? AND status = 'active'");
$stmt->execute([$election_id]);
$election = $stmt->fetch();
if (!$election) {
    setFlash('danger', 'This election is not currently active.');
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

// Check if already voted
$stmt = $pdo->prepare("SELECT id FROM votes WHERE election_id = ? AND voter_id = ?");
$stmt->execute([$election_id, $voter_id]);
if ($stmt->fetch()) {
    setFlash('warning', 'You have already cast your vote for this election.');
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

// Get candidates
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY full_name");
$stmt->execute([$election_id]);
$candidates = $stmt->fetchAll();

$error = '';
$step  = $_SESSION['vote_step'] ?? 1; // 1=select, 2=otp, 3=done

// STEP 1: Candidate selected → send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id']) && $step === 1) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $candidate_id = (int)$_POST['candidate_id'];
        // Validate candidate belongs to this election
        $stmt = $pdo->prepare("SELECT id, full_name, party FROM candidates WHERE id = ? AND election_id = ?");
        $stmt->execute([$candidate_id, $election_id]);
        $chosen = $stmt->fetch();
        if (!$chosen) {
            $error = 'Invalid candidate selection.';
        } else {
            // Generate OTP and email to voter for vote confirmation
            $otp  = createOTP($pdo, $voter_id, 'vote_confirm');
            $sent = sendOTPEmail($voter['email'], $voter['full_name'], $otp, 'vote_confirm');

            if (!$sent) {
                $error = 'Failed to send confirmation OTP to your email. Please try again.';
            } else {
                $_SESSION['vote_candidate_id']   = $candidate_id;
                $_SESSION['vote_election_id']    = $election_id;
                $_SESSION['vote_candidate_name'] = $chosen['full_name'];
                $_SESSION['vote_step']           = 2;
                header('Location: ' . BASE_URL . '/voter/vote.php?election=' . $election_id);
                exit;
            }
        }
    }
}

// STEP 2: OTP verified → cast vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_hidden']) && $step === 2) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $otp_input    = trim($_POST['otp_hidden'] ?? '');
        $candidate_id = (int)($_SESSION['vote_candidate_id'] ?? 0);

        if (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
            $error = 'Please enter the complete 6-digit OTP.';
        } elseif (!verifyOTP($pdo, $voter_id, $otp_input, 'vote_confirm')) {
            $error = 'Invalid or expired OTP. Please go back and try again.';
        } elseif (!$candidate_id) {
            $error = 'Session error. Please start over.';
        } else {
            // Cast vote
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO votes (election_id, voter_id, candidate_id, ip_address) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$election_id, $voter_id, $candidate_id, $_SERVER['REMOTE_ADDR'] ?? '']);

                // Clean up session
                unset($_SESSION['vote_step'], $_SESSION['vote_candidate_id'],
                      $_SESSION['vote_election_id'], $_SESSION['vote_candidate_name']);

                setFlash('success', 'Your vote has been cast successfully! Thank you for participating.');
                header('Location: ' . BASE_URL . '/voter/dashboard.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'You have already voted in this election.';
                } else {
                    $error = 'An error occurred while casting your vote. Please try again.';
                }
            }
        }
    }
}

// Back button in step 2
if (isset($_GET['back']) && $step === 2) {
    unset($_SESSION['vote_step'], $_SESSION['vote_candidate_id'],
          $_SESSION['vote_election_id'], $_SESSION['vote_candidate_name'],
          $_SESSION['demo_vote_otp']);
    header('Location: ' . BASE_URL . '/voter/vote.php?election=' . $election_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
            <div class="brand-icon">&#x1F5F3;</div>
            Sri Lanka e-Vote
        </a>
        <ul class="navbar-nav">
            <li><a href="<?= BASE_URL ?>/voter/dashboard.php">Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/voter/logout.php" class="btn-outline-light">Logout</a></li>
        </ul>
    </div>
</nav>

<div style="max-width:900px;margin:32px auto;padding:0 20px;">

    <!-- Election Header -->
    <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-lg);padding:24px 28px;color:#fff;margin-bottom:24px;">
        <span style="font-size:0.75rem;opacity:0.75;text-transform:uppercase;letter-spacing:1px;"><?= e($election['election_type']) ?></span>
        <h1 style="font-size:1.4rem;margin:4px 0 6px;"><?= e($election['title']) ?></h1>
        <p style="opacity:0.8;font-size:0.85rem;">
            Voting closes: <?= formatDateTime($election['end_date']) ?>
        </p>
    </div>

    <!-- Progress Steps -->
    <div style="display:flex;gap:0;margin-bottom:28px;">
        <?php
        $steps = ['Select Candidate', 'Confirm with OTP'];
        foreach ($steps as $i => $label):
            $num     = $i + 1;
            $active  = ($step === $num);
            $done    = ($step > $num);
            $bg      = $done ? '#2e7d32' : ($active ? 'var(--primary)' : '#e0e0e0');
            $col     = ($done || $active) ? '#fff' : '#999';
            $lbcol   = ($done || $active) ? 'var(--dark)' : '#999';
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;position:relative;">
            <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;color:<?= $col ?>;display:flex;align-items:center;justify-content:center;font-weight:800;z-index:1;">
                <?= $done ? '&#x2714;' : $num ?>
            </div>
            <div style="font-size:0.78rem;margin-top:6px;color:<?= $lbcol ?>;font-weight:<?= $active ? '700' : '500' ?>;"><?= $label ?></div>
            <?php if ($i < count($steps)-1): ?>
            <div style="position:absolute;top:18px;left:50%;width:100%;height:2px;background:<?= $done ? '#2e7d32' : '#e0e0e0' ?>;z-index:0;"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- STEP 1: Select Candidate -->
    <?php if ($step === 1): ?>
    <div class="card">
        <div class="card-header">
            <h3>Step 1: Select Your Candidate</h3>
            <span style="font-size:0.82rem;color:var(--gray);"><?= count($candidates) ?> candidate(s)</span>
        </div>
        <div class="card-body">
            <form method="POST" id="vote-form">
                <?= csrfField() ?>
                <div class="candidates-grid">
                    <?php foreach ($candidates as $candidate):
                        $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $candidate['full_name'])));
                        $initials = substr($initials, 0, 2);
                    ?>
                    <label class="candidate-card" style="cursor:pointer;">
                        <input type="radio" name="candidate_id" value="<?= $candidate['id'] ?>"
                               class="candidate-radio" required>
                        <div class="candidate-party-bar" style="background:<?= e($candidate['party_color']) ?>;"></div>
                        <div class="candidate-body">
                            <div class="candidate-avatar" style="background:<?= e($candidate['party_color']) ?>;">
                                <?= e($initials) ?>
                            </div>
                            <h3><?= e($candidate['full_name']) ?></h3>
                            <div class="candidate-party"><?= e($candidate['party']) ?></div>
                            <?php if ($candidate['symbol_text']): ?>
                            <span class="candidate-symbol">&#x1F3F4; <?= e($candidate['symbol_text']) ?></span>
                            <?php endif; ?>
                            <?php if ($candidate['bio']): ?>
                            <p class="candidate-bio"><?= e($candidate['bio']) ?></p>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:24px;text-align:center;">
                    <button type="submit" id="confirm-vote-btn" class="btn btn-primary btn-lg" disabled>
                        Continue to OTP Confirmation &#x2192;
                    </button>
                    <p style="font-size:0.78rem;color:var(--gray);margin-top:10px;">
                        You must verify with OTP before your vote is recorded.
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- STEP 2: OTP Verification -->
    <?php elseif ($step === 2): ?>
    <div class="card">
        <div class="card-header">
            <h3>Step 2: Confirm Your Vote with OTP</h3>
        </div>
        <div class="card-body" style="text-align:center;">

            <div style="background:var(--light);border-radius:var(--radius);padding:18px;margin-bottom:20px;display:inline-block;min-width:280px;">
                <p style="font-size:0.82rem;color:var(--gray);">You are voting for:</p>
                <h2 style="font-size:1.2rem;color:var(--dark);margin:6px 0 4px;"><?= e($_SESSION['vote_candidate_name'] ?? '') ?></h2>
                <p style="font-size:0.85rem;color:var(--gray);"><?= e($election['title']) ?></p>
            </div>

            <div class="alert alert-info" style="text-align:center;">
                &#x1F4E7; A vote confirmation OTP has been sent to your registered email address.<br>
                <small>Check your inbox (and spam folder). Expires in 10 minutes.</small>
            </div>

            <form method="POST" style="margin-top:10px;">
                <?= csrfField() ?>
                <input type="hidden" name="otp_hidden" id="otp_hidden" value="">
                <p style="color:var(--gray);font-size:0.88rem;margin-bottom:4px;">Enter the 6-digit OTP to finalize your vote:</p>
                <div class="otp-inputs" style="justify-content:center;">
                    <input type="text" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                    <input type="text" maxlength="1" inputmode="numeric">
                </div>
                <button type="submit" class="btn btn-success btn-lg" style="margin-top:8px;">
                    &#x2714; Cast My Vote
                </button>
            </form>

            <div style="margin-top:16px;">
                <a href="<?= BASE_URL ?>/voter/vote.php?election=<?= $election_id ?>&back=1"
                   style="color:var(--gray);font-size:0.83rem;">&#x2190; Back to candidate selection</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<footer class="footer" style="margin-top:40px;">
    <p>&copy; <?= date('Y') ?> Sri Lanka Online Voting System</p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
