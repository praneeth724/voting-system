<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo = getDB();

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    if (!verifyCSRF($_GET['csrf'])) {
        setFlash('danger', 'Invalid request.');
    } else {
        $id = (int)$_GET['delete'];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE candidate_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete a candidate who has received votes.');
        } else {
            $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
            setFlash('success', 'Candidate deleted.');
        }
    }
    $back = isset($_GET['election']) ? '?election=' . (int)$_GET['election'] : '';
    header('Location: ' . BASE_URL . '/admin/candidates.php' . $back);
    exit;
}

// Filter by election
$filter_election = (int)($_GET['election'] ?? 0);
$elections = $pdo->query("SELECT id, title FROM elections ORDER BY created_at DESC")->fetchAll();

if ($filter_election) {
    $stmt = $pdo->prepare(
        "SELECT c.*, COUNT(v.id) as vote_count, e.title as election_title
         FROM candidates c
         JOIN elections e ON e.id = c.election_id
         LEFT JOIN votes v ON v.candidate_id = c.id
         WHERE c.election_id = ?
         GROUP BY c.id ORDER BY c.full_name"
    );
    $stmt->execute([$filter_election]);
} else {
    $stmt = $pdo->query(
        "SELECT c.*, COUNT(v.id) as vote_count, e.title as election_title
         FROM candidates c
         JOIN elections e ON e.id = c.election_id
         LEFT JOIN votes v ON v.candidate_id = c.id
         GROUP BY c.id ORDER BY e.title, c.full_name"
    );
}
$candidates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates - Admin</title>
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
        <a href="<?= BASE_URL ?>/admin/candidates.php" class="active"><span class="nav-icon">&#x1F464;</span> Candidates</a>
        <span class="nav-section">Voters</span>
        <a href="<?= BASE_URL ?>/admin/voters.php"><span class="nav-icon">&#x1F4CB;</span> All Voters</a>
        <a href="<?= BASE_URL ?>/admin/voters.php?filter=authority_approved"><span class="nav-icon">&#x23F3;</span> Awaiting Approval</a>
        <span class="nav-section">Reports</span>
        <a href="<?= BASE_URL ?>/admin/results.php"><span class="nav-icon">&#x1F4CA;</span> Election Results</a>
        <span class="nav-section">System</span>
        <a href="<?= BASE_URL ?>/admin/logout.php"><span class="nav-icon">&#x1F6AA;</span> Logout</a>
    </nav>
</aside>
<div class="main-content">
    <div class="top-bar">
        <h1>Manage Candidates</h1>
        <a href="<?= BASE_URL ?>/admin/add_candidate.php<?= $filter_election ? '?election='.$filter_election : '' ?>"
           class="btn btn-primary btn-sm">+ Add Candidate</a>
    </div>
    <div class="content-area">
        <?php showFlash(); ?>

        <!-- Filter -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="padding:14px 20px;">
                <form method="GET" style="display:flex;align-items:center;gap:14px;">
                    <label style="font-weight:600;font-size:0.88rem;white-space:nowrap;">Filter by Election:</label>
                    <select name="election" class="form-control" style="max-width:360px;" onchange="this.form.submit()">
                        <option value="">All Elections</option>
                        <?php foreach ($elections as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $el['id'] == $filter_election ? 'selected' : '' ?>><?= e($el['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Candidate</th>
                                <th>Party</th>
                                <th>Symbol</th>
                                <th>Election</th>
                                <th>Votes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($candidates)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--gray);">No candidates found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($candidates as $c):
                                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $c['full_name'])));
                                $initials = substr($initials, 0, 2);
                            ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:36px;height:36px;border-radius:50%;background:<?= e($c['party_color']) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.78rem;flex-shrink:0;">
                                            <?= e($initials) ?>
                                        </div>
                                        <div>
                                            <strong><?= e($c['full_name']) ?></strong>
                                            <?php if ($c['bio']): ?>
                                            <br><small style="color:var(--gray);"><?= e(substr($c['bio'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:6px;">
                                        <span style="width:12px;height:12px;border-radius:50%;background:<?= e($c['party_color']) ?>;display:inline-block;"></span>
                                        <?= e($c['party']) ?>
                                    </span>
                                </td>
                                <td><?= e($c['symbol_text'] ?? '-') ?></td>
                                <td style="font-size:0.82rem;"><?= e($c['election_title']) ?></td>
                                <td><strong><?= number_format($c['vote_count']) ?></strong></td>
                                <td>
                                    <div class="actions">
                                        <a href="<?= BASE_URL ?>/admin/add_candidate.php?edit=<?= $c['id'] ?>"
                                           class="btn btn-warning btn-sm">Edit</a>
                                        <?php if ($c['vote_count'] == 0): ?>
                                        <a href="<?= BASE_URL ?>/admin/candidates.php?delete=<?= $c['id'] ?>&csrf=<?= generateCSRF() ?><?= $filter_election ? '&election='.$filter_election : '' ?>"
                                           class="btn btn-danger btn-sm"
                                           data-confirm="Delete candidate <?= e($c['full_name']) ?>?">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
