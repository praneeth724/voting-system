<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo = getDB();

// Stats
$total_voters    = $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$pending_voters  = $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'pending'")->fetchColumn();
$approved_voters = $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'approved'")->fetchColumn();
$auth_approved   = $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'authority_approved'")->fetchColumn();
$total_elections = $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$active_elections= $pdo->query("SELECT COUNT(*) FROM elections WHERE status = 'active'")->fetchColumn();
$total_votes     = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$total_candidates= $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

// Recent voters
$recent_voters = $pdo->query(
    "SELECT v.full_name, v.nic, v.district, v.status, v.created_at
     FROM voters v ORDER BY v.created_at DESC LIMIT 8"
)->fetchAll();

// Active elections
$active_election_list = $pdo->query(
    "SELECT e.*, COUNT(v.id) as vote_count
     FROM elections e
     LEFT JOIN votes v ON v.election_id = e.id
     WHERE e.status = 'active'
     GROUP BY e.id"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>&#x1F6E1; Admin Panel</h2>
        <p><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></p>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section">Main</span>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="active">
            <span class="nav-icon">&#x1F3E0;</span> Dashboard
        </a>
        <span class="nav-section">Elections</span>
        <a href="<?= BASE_URL ?>/admin/elections.php">
            <span class="nav-icon">&#x1F5F3;</span> Manage Elections
        </a>
        <a href="<?= BASE_URL ?>/admin/add_election.php">
            <span class="nav-icon">&#x2795;</span> Add Election
        </a>
        <a href="<?= BASE_URL ?>/admin/candidates.php">
            <span class="nav-icon">&#x1F464;</span> Candidates
        </a>
        <span class="nav-section">Voters</span>
        <a href="<?= BASE_URL ?>/admin/voters.php">
            <span class="nav-icon">&#x1F4CB;</span> All Voters
        </a>
        <a href="<?= BASE_URL ?>/admin/voters.php?filter=authority_approved">
            <span class="nav-icon">&#x23F3;</span> Awaiting Approval
            <?php if ($auth_approved > 0): ?>
            <span class="badge badge-warning" style="margin-left:auto;"><?= $auth_approved ?></span>
            <?php endif; ?>
        </a>
        <span class="nav-section">Reports</span>
        <a href="<?= BASE_URL ?>/admin/results.php">
            <span class="nav-icon">&#x1F4CA;</span> Election Results
        </a>
        <span class="nav-section">System</span>
        <a href="<?= BASE_URL ?>/admin/logout.php">
            <span class="nav-icon">&#x1F6AA;</span> Logout
        </a>
    </nav>
</aside>

<!-- Main Content -->
<div class="main-content">
    <div class="top-bar">
        <h1>Dashboard</h1>
        <div class="user-info">
            <span><?= date('d M Y') ?></span>
            <div class="avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
            <span><?= e($_SESSION['admin_name'] ?? '') ?></span>
        </div>
    </div>
    <div class="content-area">

        <?php showFlash(); ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">&#x1F464;</div>
                <div class="stat-info"><h3><?= $total_voters ?></h3><p>Total Voters</p></div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">&#x2705;</div>
                <div class="stat-info"><h3><?= $approved_voters ?></h3><p>Approved Voters</p></div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon">&#x23F3;</div>
                <div class="stat-info"><h3><?= $auth_approved ?></h3><p>Awaiting Admin Approval</p></div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon">&#x1F5F3;</div>
                <div class="stat-info"><h3><?= $total_elections ?></h3><p>Elections</p></div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">&#x25B6;</div>
                <div class="stat-info"><h3><?= $active_elections ?></h3><p>Active Elections</p></div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">&#x1F5F3;</div>
                <div class="stat-info"><h3><?= $total_votes ?></h3><p>Total Votes Cast</p></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

            <!-- Active Elections -->
            <div class="card">
                <div class="card-header">
                    <h3>&#x25B6; Active Elections</h3>
                    <a href="<?= BASE_URL ?>/admin/elections.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($active_election_list)): ?>
                        <p style="text-align:center;color:var(--gray);padding:30px;">No active elections.</p>
                    <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Election</th><th>Votes</th><th>Ends</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_election_list as $el): ?>
                                <tr>
                                    <td><strong><?= e($el['title']) ?></strong><br><small style="color:var(--gray);"><?= e($el['election_type']) ?></small></td>
                                    <td><strong><?= number_format($el['vote_count']) ?></strong></td>
                                    <td style="font-size:0.8rem;"><?= formatDate($el['end_date']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="card">
                <div class="card-header">
                    <h3>&#x23F3; Pending Voter Approvals</h3>
                    <a href="<?= BASE_URL ?>/admin/voters.php?filter=authority_approved" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if ($auth_approved == 0): ?>
                        <p style="text-align:center;color:var(--gray);padding:30px;">No pending approvals.</p>
                    <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Name</th><th>District</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php
                                $pending = $pdo->query(
                                    "SELECT id, full_name, district FROM voters
                                     WHERE status = 'authority_approved' LIMIT 6"
                                )->fetchAll();
                                foreach ($pending as $v): ?>
                                <tr>
                                    <td><?= e($v['full_name']) ?></td>
                                    <td><?= e($v['district']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/voters.php?view=<?= $v['id'] ?>"
                                           class="btn btn-primary btn-sm">Review</a>
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

        <!-- Recent Registrations -->
        <div class="card" style="margin-top:24px;">
            <div class="card-header">
                <h3>&#x1F4CB; Recent Voter Registrations</h3>
                <a href="<?= BASE_URL ?>/admin/voters.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Name</th><th>NIC</th><th>District</th><th>Status</th><th>Registered</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_voters as $v): ?>
                            <tr>
                                <td><strong><?= e($v['full_name']) ?></strong></td>
                                <td style="font-family:monospace;"><?= e($v['nic']) ?></td>
                                <td><?= e($v['district']) ?></td>
                                <td><?= statusBadge($v['status']) ?></td>
                                <td style="font-size:0.8rem;color:var(--gray);"><?= formatDate($v['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
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
