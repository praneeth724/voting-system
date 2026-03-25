<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo = getDB();
$election_id = (int)($_GET['election'] ?? 0);

$elections = $pdo->query("SELECT * FROM elections ORDER BY created_at DESC")->fetchAll();
if (!$election_id && !empty($elections)) $election_id = $elections[0]['id'];

$election    = null;
$results     = [];
$total_votes = 0;
$total_eligible = 0;

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
             GROUP BY c.id ORDER BY vote_count DESC"
        );
        $stmt->execute([$election_id, $election_id]);
        $results = $stmt->fetchAll();
        $total_votes = array_sum(array_column($results, 'vote_count'));
        $total_eligible = $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'approved'")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
<aside class="sidebar">
    <div class="sidebar-header"><h2>&#x1F6E1; Admin Panel</h2><p><?= e($_SESSION['admin_name'] ?? '') ?></p></div>
    <nav class="sidebar-nav">
        <span class="nav-section">Main</span>
        <a href="<?= BASE_URL ?>/admin/dashboard.php"><span class="nav-icon">&#x1F3E0;</span> Dashboard</a>
        <span class="nav-section">Elections</span>
        <a href="<?= BASE_URL ?>/admin/elections.php"><span class="nav-icon">&#x1F5F3;</span> Manage Elections</a>
        <a href="<?= BASE_URL ?>/admin/add_election.php"><span class="nav-icon">&#x2795;</span> Add Election</a>
        <a href="<?= BASE_URL ?>/admin/candidates.php"><span class="nav-icon">&#x1F464;</span> Candidates</a>
        <span class="nav-section">Voters</span>
        <a href="<?= BASE_URL ?>/admin/voters.php"><span class="nav-icon">&#x1F4CB;</span> All Voters</a>
        <a href="<?= BASE_URL ?>/admin/voters.php?filter=authority_approved"><span class="nav-icon">&#x23F3;</span> Awaiting Approval</a>
        <span class="nav-section">Reports</span>
        <a href="<?= BASE_URL ?>/admin/results.php" class="active"><span class="nav-icon">&#x1F4CA;</span> Election Results</a>
        <span class="nav-section">System</span>
        <a href="<?= BASE_URL ?>/admin/logout.php"><span class="nav-icon">&#x1F6AA;</span> Logout</a>
    </nav>
</aside>
<div class="main-content">
    <div class="top-bar">
        <h1>Election Results</h1>
    </div>
    <div class="content-area">

        <!-- Election Selector -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body" style="padding:16px 24px;">
                <form method="GET" style="display:flex;align-items:center;gap:14px;">
                    <label style="font-weight:600;font-size:0.88rem;white-space:nowrap;">Election:</label>
                    <select name="election" class="form-control" style="max-width:420px;" onchange="this.form.submit()">
                        <?php foreach ($elections as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $el['id'] == $election_id ? 'selected' : '' ?>>
                            <?= e($el['title']) ?> (<?= ucfirst($el['status']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if ($election): ?>

        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-icon">&#x1F5F3;</div>
                <div class="stat-info"><h3><?= number_format($total_votes) ?></h3><p>Total Votes</p></div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">&#x1F464;</div>
                <div class="stat-info"><h3><?= number_format($total_eligible) ?></h3><p>Eligible Voters</p></div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon">&#x1F4CA;</div>
                <div class="stat-info">
                    <h3><?= $total_eligible > 0 ? round(($total_votes / $total_eligible) * 100, 1) : 0 ?>%</h3>
                    <p>Voter Turnout</p>
                </div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon">&#x1F464;</div>
                <div class="stat-info"><h3><?= count($results) ?></h3><p>Candidates</p></div>
            </div>
        </div>

        <!-- Election Info -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3><?= e($election['title']) ?></h3>
                <?= statusBadge($election['status']) ?>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:0.88rem;">
                    <div><span style="color:var(--gray);">Type:</span> <strong><?= e($election['election_type']) ?></strong></div>
                    <div><span style="color:var(--gray);">Start:</span> <strong><?= formatDateTime($election['start_date']) ?></strong></div>
                    <div><span style="color:var(--gray);">End:</span> <strong><?= formatDateTime($election['end_date']) ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Results Table & Bars -->
        <div class="card">
            <div class="card-header">
                <h3>Results Breakdown</h3>
                <?php if ($election['status'] === 'completed' && !empty($results) && $total_votes > 0): ?>
                <span class="winner-badge">&#x1F3C6; Winner: <?= e($results[0]['full_name']) ?> (<?= round(($results[0]['vote_count']/$total_votes)*100,1) ?>%)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($results) || $total_votes == 0): ?>
                <p style="text-align:center;color:var(--gray);padding:20px;">No votes cast yet.</p>
                <?php else: ?>
                <div class="results-grid">
                    <?php foreach ($results as $rank => $row):
                        $pct = $total_votes > 0 ? round(($row['vote_count'] / $total_votes) * 100, 1) : 0;
                        $rankClass = $rank === 0 ? 'first' : ($rank === 1 ? 'second' : ($rank === 2 ? 'third' : ''));
                    ?>
                    <div class="result-row">
                        <div class="result-rank <?= $rankClass ?>"><?= $rank + 1 ?></div>
                        <div class="result-info">
                            <h4><?= e($row['full_name']) ?></h4>
                            <p><?= e($row['party']) ?></p>
                        </div>
                        <div class="result-bar-wrap">
                            <div class="result-bar-bg">
                                <div class="result-bar-fill"
                                     style="background:<?= e($row['party_color']) ?>;"
                                     data-width="<?= $pct ?>"></div>
                            </div>
                        </div>
                        <div class="result-pct"><?= $pct ?>%</div>
                        <div class="result-votes"><?= number_format($row['vote_count']) ?> votes</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($election['status'] === 'active'): ?>
            <div class="card-footer">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <small style="color:var(--gray);">Live results &mdash; updates when page is refreshed.</small>
                    <a href="?election=<?= $election_id ?>" class="btn btn-outline btn-sm">&#x27F3; Refresh</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/elections.php" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="election_id" value="<?= $election_id ?>">
                        <input type="hidden" name="action" value="completed">
                        <button type="submit" class="btn btn-success btn-sm"
                                data-confirm="Mark this election as completed and close voting?">
                            Mark as Completed
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
