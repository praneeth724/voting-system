<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireVoterLogin();

$pdo      = getDB();
$voter_id = (int)$_SESSION['voter_id'];

// Get voter info
$stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ?");
$stmt->execute([$voter_id]);
$voter = $stmt->fetch();

// Get active & upcoming elections
$elections = $pdo->query(
    "SELECT e.*,
        (SELECT candidate_id FROM votes WHERE election_id = e.id AND voter_id = $voter_id LIMIT 1) as voted_candidate_id,
        (SELECT c.full_name FROM candidates c
         JOIN votes v ON v.candidate_id = c.id
         WHERE v.election_id = e.id AND v.voter_id = $voter_id LIMIT 1) as voted_for
     FROM elections e
     WHERE e.status IN ('active','upcoming','completed')
     ORDER BY e.status = 'active' DESC, e.start_date DESC"
)->fetchAll();

// Vote count for voter
$total_voted = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ?");
$total_voted->execute([$voter_id]);
$voted_count = $total_voted->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
            <div class="brand-icon">&#x1F5F3;</div>
            Sri Lanka e-Vote
        </a>
        <ul class="navbar-nav">
            <li><a href="<?= BASE_URL ?>/voter/dashboard.php" class="active">Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/voter/results.php">Results</a></li>
            <li><a href="<?= BASE_URL ?>/voter/logout.php" class="btn-outline-light">Logout</a></li>
        </ul>
    </div>
</nav>

<div style="max-width:1100px;margin:32px auto;padding:0 20px;">

    <?php showFlash(); ?>

    <!-- Welcome Banner -->
    <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-lg);padding:28px 32px;color:#fff;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1 style="font-size:1.4rem;margin-bottom:4px;">Welcome, <?= e($voter['full_name']) ?>!</h1>
            <p style="opacity:0.8;font-size:0.88rem;">NIC: <?= e($voter['nic']) ?> &nbsp;|&nbsp; District: <?= e($voter['district']) ?></p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:2rem;font-weight:800;"><?= $voted_count ?></div>
            <div style="font-size:0.8rem;opacity:0.8;">Election<?= $voted_count !== '1' ? 's' : '' ?> Voted</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
        <div class="stat-card green">
            <div class="stat-icon">&#x2714;</div>
            <div class="stat-info">
                <h3><?= $voted_count ?></h3>
                <p>Votes Cast</p>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon">&#x1F5F3;</div>
            <div class="stat-info">
                <h3><?= count($elections) ?></h3>
                <p>Total Elections</p>
            </div>
        </div>
        <div class="stat-card gold">
            <div class="stat-icon">&#x1F464;</div>
            <div class="stat-info">
                <h3><?= statusBadge($voter['status']) ?></h3>
                <p>Account Status</p>
            </div>
        </div>
    </div>

    <!-- Elections -->
    <div class="card">
        <div class="card-header">
            <h3>&#x1F5F3; Elections</h3>
        </div>
        <div class="card-body">
            <?php if (empty($elections)): ?>
                <p style="text-align:center;color:var(--gray);padding:30px 0;">No elections available at this time.</p>
            <?php else: ?>
                <div class="election-cards">
                    <?php foreach ($elections as $e): ?>
                    <div class="election-card">
                        <div class="election-card-header">
                            <span class="election-type"><?= e($e['election_type']) ?></span>
                            <h3><?= e($e['title']) ?></h3>
                        </div>
                        <div class="election-card-body">
                            <div class="election-meta">
                                <span>&#x1F4C5; <strong>Starts:</strong> <?= formatDateTime($e['start_date']) ?></span>
                                <span>&#x1F4C5; <strong>Ends:</strong> <?= formatDateTime($e['end_date']) ?></span>
                                <span>Status: <?= statusBadge($e['status']) ?></span>
                            </div>

                            <?php if ($e['voted_candidate_id']): ?>
                                <div class="voted-check">
                                    &#x2705; You voted for: <strong><?= e($e['voted_for']) ?></strong>
                                </div>
                            <?php elseif ($e['status'] === 'active'): ?>
                                <?php if ($voter['status'] === 'approved'): ?>
                                    <a href="<?= BASE_URL ?>/voter/vote.php?election=<?= $e['id'] ?>"
                                       class="btn btn-primary btn-block" style="margin-top:8px;">
                                        &#x1F5F3; Cast Your Vote
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning" style="margin-top:8px;font-size:0.82rem;">
                                        Your account must be approved to vote.
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($e['status'] === 'upcoming'): ?>
                                <div style="color:var(--gray);font-size:0.83rem;margin-top:8px;">
                                    &#x23F3; Voting opens on <?= formatDateTime($e['start_date']) ?>
                                </div>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/voter/results.php?election=<?= $e['id'] ?>"
                                   class="btn btn-outline btn-sm btn-block" style="margin-top:8px;">
                                    &#x1F4CA; View Results
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Voter Info -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header"><h3>&#x1F464; My Profile</h3></div>
        <div class="card-body">
            <div class="form-row" style="gap:20px;">
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">Full Name</p>
                    <p class="fw-bold"><?= e($voter['full_name']) ?></p>
                </div>
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">NIC</p>
                    <p class="fw-bold"><?= e($voter['nic']) ?></p>
                </div>
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">Date of Birth</p>
                    <p class="fw-bold"><?= formatDate($voter['date_of_birth']) ?></p>
                </div>
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">District</p>
                    <p class="fw-bold"><?= e($voter['district']) ?></p>
                </div>
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">Phone</p>
                    <p class="fw-bold"><?= e($voter['phone']) ?></p>
                </div>
                <div>
                    <p class="text-muted" style="font-size:0.78rem;">Email</p>
                    <p class="fw-bold"><?= e($voter['email']) ?></p>
                </div>
            </div>
        </div>
    </div>

</div>

<footer class="footer" style="margin-top:40px;">
    <p>&copy; <?= date('Y') ?> Sri Lanka Online Voting System</p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
