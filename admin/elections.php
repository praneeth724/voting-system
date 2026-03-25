<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo = getDB();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid request.');
    } else {
        $id = (int)($_POST['election_id'] ?? 0);
        $action = $_POST['action'];
        $allowed = ['upcoming', 'active', 'completed', 'cancelled'];
        if ($id && in_array($action, $allowed)) {
            $pdo->prepare("UPDATE elections SET status = ? WHERE id = ?")->execute([$action, $id]);
            setFlash('success', 'Election status updated to "' . $action . '".');
        }
    }
    header('Location: ' . BASE_URL . '/admin/elections.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    if (!verifyCSRF($_GET['csrf'] ?? '')) {
        setFlash('danger', 'Invalid request.');
    } else {
        $id = (int)$_GET['delete'];
        // Check no votes cast
        $votes = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = ?");
        $votes->execute([$id]);
        if ($votes->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete an election that has votes already cast.');
        } else {
            $pdo->prepare("DELETE FROM elections WHERE id = ?")->execute([$id]);
            setFlash('success', 'Election deleted.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/elections.php');
    exit;
}

$elections = $pdo->query(
    "SELECT e.*, COUNT(DISTINCT c.id) as candidate_count, COUNT(DISTINCT v.id) as vote_count
     FROM elections e
     LEFT JOIN candidates c ON c.election_id = e.id
     LEFT JOIN votes v ON v.election_id = e.id
     GROUP BY e.id
     ORDER BY e.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections - Admin</title>
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
        <a href="<?= BASE_URL ?>/admin/elections.php" class="active"><span class="nav-icon">&#x1F5F3;</span> Manage Elections</a>
        <a href="<?= BASE_URL ?>/admin/add_election.php"><span class="nav-icon">&#x2795;</span> Add Election</a>
        <a href="<?= BASE_URL ?>/admin/candidates.php"><span class="nav-icon">&#x1F464;</span> Candidates</a>
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
        <h1>Manage Elections</h1>
        <a href="<?= BASE_URL ?>/admin/add_election.php" class="btn btn-primary btn-sm">+ Add Election</a>
    </div>
    <div class="content-area">
        <?php showFlash(); ?>
        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Candidates</th>
                                <th>Votes</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($elections)): ?>
                            <tr><td colspan="9" style="text-align:center;color:var(--gray);padding:30px;">No elections found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($elections as $el): ?>
                            <tr>
                                <td><?= $el['id'] ?></td>
                                <td>
                                    <strong><?= e($el['title']) ?></strong>
                                    <?php if ($el['description']): ?>
                                    <br><small style="color:var(--gray);"><?= e(substr($el['description'], 0, 60)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($el['election_type']) ?></td>
                                <td style="text-align:center;"><?= $el['candidate_count'] ?></td>
                                <td style="text-align:center;"><strong><?= number_format($el['vote_count']) ?></strong></td>
                                <td style="font-size:0.82rem;"><?= formatDateTime($el['start_date']) ?></td>
                                <td style="font-size:0.82rem;"><?= formatDateTime($el['end_date']) ?></td>
                                <td><?= statusBadge($el['status']) ?></td>
                                <td>
                                    <div class="actions">
                                        <!-- Status change form -->
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="election_id" value="<?= $el['id'] ?>">
                                            <select name="action" class="form-control" style="width:120px;padding:5px 8px;font-size:0.78rem;display:inline-block;"
                                                    onchange="this.form.submit()">
                                                <option value="">Set Status</option>
                                                <option value="upcoming">Upcoming</option>
                                                <option value="active">Active</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                        </form>
                                        <a href="<?= BASE_URL ?>/admin/candidates.php?election=<?= $el['id'] ?>"
                                           class="btn btn-secondary btn-sm">Candidates</a>
                                        <a href="<?= BASE_URL ?>/admin/results.php?election=<?= $el['id'] ?>"
                                           class="btn btn-outline btn-sm">Results</a>
                                        <?php if ($el['vote_count'] == 0): ?>
                                        <a href="<?= BASE_URL ?>/admin/elections.php?delete=<?= $el['id'] ?>&csrf=<?= generateCSRF() ?>"
                                           class="btn btn-danger btn-sm"
                                           data-confirm="Delete this election? This cannot be undone.">Delete</a>
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
