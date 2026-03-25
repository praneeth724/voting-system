<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
// Results are public - no login required

$pdo = getDB();

// Get selected election or default to first completed/active
$election_id = (int)($_GET['election'] ?? 0);

// Get all elections for the dropdown
$all_elections = $pdo->query(
    "SELECT id, title, status, election_type FROM elections
     WHERE status IN ('active','completed') ORDER BY created_at DESC"
)->fetchAll();

if (!$election_id && !empty($all_elections)) {
    $election_id = $all_elections[0]['id'];
}

$election = null;
$results  = [];
$total_votes = 0;

if ($election_id) {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();

    if ($election) {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.full_name, c.party, c.party_color, c.symbol_text,
                    COUNT(v.id) as vote_count
             FROM candidates c
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = ?
             WHERE c.election_id = ?
             GROUP BY c.id
             ORDER BY vote_count DESC"
        );
        $stmt->execute([$election_id, $election_id]);
        $results = $stmt->fetchAll();

        $total_votes = array_sum(array_column($results, 'vote_count'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - Sri Lanka e-Vote</title>
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
            <?php if (isVoterLoggedIn()): ?>
                <li><a href="<?= BASE_URL ?>/voter/dashboard.php">Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/voter/logout.php" class="btn-outline-light">Logout</a></li>
            <?php else: ?>
                <li><a href="<?= BASE_URL ?>/voter/login.php">Login</a></li>
                <li><a href="<?= BASE_URL ?>/voter/register.php" class="btn-outline-light">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<div style="max-width:860px;margin:32px auto;padding:0 20px;">

    <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:20px;">&#x1F4CA; Election Results</h1>

    <!-- Election Selector -->
    <?php if (!empty($all_elections)): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-body" style="padding:16px 24px;">
            <form method="GET" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                <label style="font-weight:600;font-size:0.88rem;white-space:nowrap;">Select Election:</label>
                <select name="election" class="form-control" style="max-width:400px;" onchange="this.form.submit()">
                    <?php foreach ($all_elections as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $el['id'] == $election_id ? 'selected' : '' ?>>
                            <?= e($el['title']) ?> (<?= ucfirst($el['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$election): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:60px;">
                <div style="font-size:3rem;margin-bottom:16px;">&#x1F5F3;</div>
                <h3 style="color:var(--gray);">No Results Available</h3>
                <p style="color:var(--gray);font-size:0.88rem;margin-top:8px;">Results will be shown when elections become active or completed.</p>
            </div>
        </div>
    <?php else: ?>

    <!-- Election Info -->
    <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-lg);padding:22px 28px;color:#fff;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <span style="font-size:0.72rem;opacity:0.75;text-transform:uppercase;letter-spacing:1px;"><?= e($election['election_type']) ?></span>
            <h2 style="font-size:1.2rem;margin:4px 0 6px;"><?= e($election['title']) ?></h2>
            <p style="opacity:0.8;font-size:0.82rem;">
                <?= formatDateTime($election['start_date']) ?> &mdash; <?= formatDateTime($election['end_date']) ?>
            </p>
        </div>
        <div style="text-align:center;">
            <div style="font-size:2.2rem;font-weight:800;"><?= number_format($total_votes) ?></div>
            <div style="font-size:0.8rem;opacity:0.8;">Total Votes</div>
        </div>
    </div>

    <!-- Status Banner -->
    <?php if ($election['status'] === 'active'): ?>
    <div class="alert alert-info" style="margin-bottom:20px;">
        &#x23F1; <strong>Voting is currently in progress.</strong> Results are live and update in real time.
    </div>
    <?php endif; ?>

    <!-- Results -->
    <?php if (empty($results) || $total_votes == 0): ?>
        <div class="card">
            <div class="card-body" style="text-align:center;padding:40px;">
                <p style="color:var(--gray);">No votes have been cast yet.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3>Results Breakdown</h3>
                <?php if ($election['status'] === 'completed' && !empty($results)): ?>
                    <span class="winner-badge">&#x1F3C6; Winner: <?= e($results[0]['full_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="results-grid">
                    <?php foreach ($results as $rank => $row):
                        $pct = $total_votes > 0 ? round(($row['vote_count'] / $total_votes) * 100, 1) : 0;
                        $rankClass = $rank === 0 ? 'first' : ($rank === 1 ? 'second' : ($rank === 2 ? 'third' : ''));
                    ?>
                    <div class="result-row">
                        <div class="result-rank <?= $rankClass ?>"><?= $rank + 1 ?></div>
                        <div class="result-info">
                            <h4><?= e($row['full_name']) ?></h4>
                            <p><?= e($row['party']) ?> <?= $row['symbol_text'] ? '&nbsp;&bull;&nbsp;' . e($row['symbol_text']) : '' ?></p>
                        </div>
                        <div class="result-bar-wrap">
                            <div class="result-bar-bg">
                                <div class="result-bar-fill"
                                     style="background:<?= e($row['party_color']) ?>;"
                                     data-width="<?= $pct ?>">
                                </div>
                            </div>
                        </div>
                        <div class="result-pct"><?= $pct ?>%</div>
                        <div class="result-votes"><?= number_format($row['vote_count']) ?> votes</div>
                        <?php if ($rank === 0 && $election['status'] === 'completed'): ?>
                        <span class="winner-badge" style="margin-left:8px;">&#x1F3C6;</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-footer">
                <small style="color:var(--gray);">Total valid votes: <strong><?= number_format($total_votes) ?></strong> &nbsp;|&nbsp; Last updated: <?= date('d M Y, h:i A') ?></small>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<footer class="footer" style="margin-top:40px;">
    <p>&copy; <?= date('Y') ?> Sri Lanka Online Voting System</p>
</footer>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
