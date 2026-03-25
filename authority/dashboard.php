<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuthorityLogin();

$pdo         = getDB();
$auth_id     = (int)$_SESSION['authority_id'];
$district    = $_SESSION['authority_district'];

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid request.');
    } else {
        $voter_id = (int)($_POST['voter_id'] ?? 0);
        $action   = $_POST['action'];

        if ($voter_id && $action === 'approve') {
            // Verify voter belongs to this authority's district
            $chk = $pdo->prepare("SELECT id FROM voters WHERE id = ? AND district = ? AND status = 'pending'");
            $chk->execute([$voter_id, $district]);
            if ($chk->fetch()) {
                $pdo->prepare(
                    "UPDATE voters SET status = 'authority_approved', authority_id = ? WHERE id = ?"
                )->execute([$auth_id, $voter_id]);
                setFlash('success', 'Voter approved and forwarded for admin review.');
            } else {
                setFlash('danger', 'Invalid voter or already processed.');
            }
        } elseif ($voter_id && $action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? 'Rejected by district authority.');
            $chk = $pdo->prepare("SELECT id FROM voters WHERE id = ? AND district = ? AND status = 'pending'");
            $chk->execute([$voter_id, $district]);
            if ($chk->fetch()) {
                $pdo->prepare(
                    "UPDATE voters SET status = 'rejected', authority_id = ?, rejection_reason = ? WHERE id = ?"
                )->execute([$auth_id, $reason, $voter_id]);
                setFlash('danger', 'Voter registration rejected.');
            }
        }
    }
    header('Location: ' . BASE_URL . '/authority/dashboard.php');
    exit;
}

// Stats
$pending_count  = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE district = ? AND status = 'pending'");
$pending_count->execute([$district]);
$pending_count = $pending_count->fetchColumn();

$approved_count = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE district = ? AND status IN ('authority_approved','approved')");
$approved_count->execute([$district]);
$approved_count = $approved_count->fetchColumn();

$rejected_count = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE district = ? AND status = 'rejected'");
$rejected_count->execute([$district]);
$rejected_count = $rejected_count->fetchColumn();

// Pending voters for this district
$pending_voters = $pdo->prepare(
    "SELECT * FROM voters WHERE district = ? AND status = 'pending' ORDER BY created_at ASC"
);
$pending_voters->execute([$district]);
$pending_voters = $pending_voters->fetchAll();

// View single voter
$view_voter = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? AND district = ?");
    $stmt->execute([(int)$_GET['view'], $district]);
    $view_voter = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authority Dashboard - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">

<!-- Sidebar -->
<aside class="sidebar" style="background:#1b5e20;">
    <div class="sidebar-header">
        <h2 style="color:#a5d6a7;">&#x1F4CB; Authority</h2>
        <p><?= e($_SESSION['authority_name'] ?? '') ?></p>
        <p style="font-size:0.78rem;margin-top:4px;color:rgba(255,255,255,0.5);">District: <?= e($district) ?></p>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section">Navigation</span>
        <a href="<?= BASE_URL ?>/authority/dashboard.php" class="active">
            <span class="nav-icon">&#x1F3E0;</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/authority/dashboard.php">
            <span class="nav-icon">&#x23F3;</span> Pending Voters
            <?php if ($pending_count > 0): ?>
            <span class="badge badge-warning" style="margin-left:auto;"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
        <span class="nav-section">System</span>
        <a href="<?= BASE_URL ?>/authority/logout.php"><span class="nav-icon">&#x1F6AA;</span> Logout</a>
    </nav>
</aside>

<div class="main-content">
    <div class="top-bar">
        <h1><?= e($district) ?> District &mdash; Voter Verification</h1>
        <div class="user-info">
            <div class="avatar" style="background:var(--success);"><?= strtoupper(substr($_SESSION['authority_name'] ?? 'A', 0, 1)) ?></div>
            <span><?= e($_SESSION['authority_name'] ?? '') ?></span>
        </div>
    </div>
    <div class="content-area">

        <?php showFlash(); ?>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card gold">
                <div class="stat-icon">&#x23F3;</div>
                <div class="stat-info"><h3><?= $pending_count ?></h3><p>Pending Review</p></div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">&#x2714;</div>
                <div class="stat-info"><h3><?= $approved_count ?></h3><p>Approved</p></div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">&#x2716;</div>
                <div class="stat-info"><h3><?= $rejected_count ?></h3><p>Rejected</p></div>
            </div>
        </div>

        <!-- View Voter Detail -->
        <?php if ($view_voter): ?>
        <div class="card" style="margin-bottom:24px;border-left:4px solid var(--success);">
            <div class="card-header" style="background:#e8f5e9;">
                <h3>Reviewing: <?= e($view_voter['full_name']) ?></h3>
                <a href="<?= BASE_URL ?>/authority/dashboard.php" class="btn btn-secondary btn-sm">&#x2190; Back to List</a>
            </div>
            <div class="card-body">
                <div class="form-row-3" style="gap:16px;margin-bottom:20px;">
                    <?php
                    $fields = [
                        'NIC' => $view_voter['nic'],
                        'Full Name' => $view_voter['full_name'],
                        'Date of Birth' => formatDate($view_voter['date_of_birth']),
                        'Gender' => $view_voter['gender'],
                        'Phone' => $view_voter['phone'],
                        'Email' => $view_voter['email'],
                        'Address' => $view_voter['address'],
                        'District' => $view_voter['district'],
                        'Registered On' => formatDateTime($view_voter['created_at']),
                    ];
                    foreach ($fields as $label => $val): ?>
                    <div>
                        <p style="font-size:0.75rem;color:var(--gray);margin-bottom:2px;"><?= $label ?></p>
                        <p style="font-weight:600;"><?= e((string)$val) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-info" style="font-size:0.85rem;">
                    <strong>Your Role:</strong> As the <?= e($district) ?> district authority, please verify this voter's identity
                    matches their NIC information. Approving will forward the application to the Election Commission for final approval.
                </div>

                <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-top:16px;">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="voter_id" value="<?= $view_voter['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success btn-lg">
                            &#x2714; Approve &amp; Forward to Admin
                        </button>
                    </form>
                    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;">
                        <?= csrfField() ?>
                        <input type="hidden" name="voter_id" value="<?= $view_voter['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <div>
                            <label style="font-size:0.82rem;font-weight:600;margin-bottom:4px;display:block;">Rejection Reason:</label>
                            <input type="text" name="rejection_reason" class="form-control" style="width:260px;"
                                   placeholder="e.g. NIC details do not match" required>
                        </div>
                        <button type="submit" class="btn btn-danger btn-lg">&#x2716; Reject</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Voters List -->
        <div class="card">
            <div class="card-header">
                <h3>&#x23F3; Pending Voter Applications &mdash; <?= e($district) ?></h3>
                <span style="font-size:0.82rem;color:var(--gray);"><?= count($pending_voters) ?> pending</span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($pending_voters)): ?>
                <div style="text-align:center;padding:50px;color:var(--gray);">
                    <div style="font-size:3rem;margin-bottom:12px;">&#x2705;</div>
                    <h3>All applications reviewed!</h3>
                    <p style="font-size:0.88rem;margin-top:6px;">No pending voter applications in <?= e($district) ?> district.</p>
                </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>NIC</th>
                                <th>Date of Birth</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Applied</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_voters as $v): ?>
                            <tr>
                                <td><strong><?= e($v['full_name']) ?></strong></td>
                                <td style="font-family:monospace;"><?= e($v['nic']) ?></td>
                                <td><?= formatDate($v['date_of_birth']) ?></td>
                                <td><?= e($v['phone']) ?></td>
                                <td style="font-size:0.82rem;"><?= e($v['email']) ?></td>
                                <td style="font-size:0.8rem;color:var(--gray);"><?= formatDate($v['created_at']) ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?view=<?= $v['id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="voter_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
