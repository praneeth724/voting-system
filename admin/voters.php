<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo = getDB();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid request.');
    } else {
        $voter_id = (int)($_POST['voter_id'] ?? 0);
        $action   = $_POST['action'];

        if ($voter_id && $action === 'approve') {
            $pdo->prepare("UPDATE voters SET status = 'approved' WHERE id = ?")->execute([$voter_id]);
            setFlash('success', 'Voter approved successfully.');
        } elseif ($voter_id && $action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? 'Rejected by administrator.');
            $pdo->prepare("UPDATE voters SET status = 'rejected', rejection_reason = ? WHERE id = ?")
                ->execute([$reason, $voter_id]);
            setFlash('danger', 'Voter registration rejected.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/voters.php?filter=' . ($_POST['current_filter'] ?? ''));
    exit;
}

// Filter
$filter   = $_GET['filter'] ?? '';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$where = '1=1';
$params = [];
if ($filter && in_array($filter, ['pending','authority_approved','approved','rejected'])) {
    $where .= ' AND v.status = ?';
    $params[] = $filter;
}
if ($search) {
    $where .= ' AND (v.full_name LIKE ? OR v.nic LIKE ? OR v.email LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM voters v WHERE $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$pages = ceil($total / $per_page);

$stmt = $pdo->prepare(
    "SELECT v.*, a.full_name as authority_name
     FROM voters v
     LEFT JOIN authorities a ON a.id = v.authority_id
     WHERE $where
     ORDER BY
       CASE v.status WHEN 'authority_approved' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,
       v.created_at DESC
     LIMIT $per_page OFFSET $offset"
);
$stmt->execute($params);
$voters = $stmt->fetchAll();

// View single voter
$view_voter = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare(
        "SELECT v.*, a.full_name as authority_name
         FROM voters v LEFT JOIN authorities a ON a.id = v.authority_id
         WHERE v.id = ?"
    );
    $stmt->execute([(int)$_GET['view']]);
    $view_voter = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voters - Admin</title>
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
        <a href="<?= BASE_URL ?>/admin/voters.php" class="<?= !$filter ? 'active' : '' ?>"><span class="nav-icon">&#x1F4CB;</span> All Voters</a>
        <a href="<?= BASE_URL ?>/admin/voters.php?filter=authority_approved" class="<?= $filter === 'authority_approved' ? 'active' : '' ?>">
            <span class="nav-icon">&#x23F3;</span> Awaiting Approval
        </a>
        <span class="nav-section">Reports</span>
        <a href="<?= BASE_URL ?>/admin/results.php"><span class="nav-icon">&#x1F4CA;</span> Election Results</a>
        <span class="nav-section">System</span>
        <a href="<?= BASE_URL ?>/admin/logout.php"><span class="nav-icon">&#x1F6AA;</span> Logout</a>
    </nav>
</aside>
<div class="main-content">
    <div class="top-bar">
        <h1>Voter Management <?= $filter ? '&mdash; ' . ucfirst(str_replace('_', ' ', $filter)) : '' ?></h1>
        <span style="font-size:0.85rem;color:var(--gray);"><?= number_format($total) ?> voter(s)</span>
    </div>
    <div class="content-area">
        <?php showFlash(); ?>

        <!-- View Voter Modal -->
        <?php if ($view_voter): ?>
        <div class="card" style="margin-bottom:24px;border-left:4px solid var(--primary);">
            <div class="card-header" style="background:var(--light);">
                <h3>Voter Details: <?= e($view_voter['full_name']) ?></h3>
                <a href="<?= BASE_URL ?>/admin/voters.php?filter=<?= $filter ?>" class="btn btn-secondary btn-sm">Close</a>
            </div>
            <div class="card-body">
                <div class="form-row-3" style="gap:16px;margin-bottom:16px;">
                    <?php
                    $details = [
                        'NIC' => $view_voter['nic'],
                        'Full Name' => $view_voter['full_name'],
                        'Date of Birth' => formatDate($view_voter['date_of_birth']),
                        'Gender' => $view_voter['gender'],
                        'District' => $view_voter['district'],
                        'Phone' => $view_voter['phone'],
                        'Email' => $view_voter['email'],
                        'Address' => $view_voter['address'],
                        'Authority' => $view_voter['authority_name'] ?? 'N/A',
                        'Status' => statusBadge($view_voter['status']),
                        'Registered' => formatDateTime($view_voter['created_at']),
                    ];
                    foreach ($details as $label => $val): ?>
                    <div>
                        <p style="font-size:0.75rem;color:var(--gray);margin-bottom:2px;"><?= $label ?></p>
                        <p style="font-weight:600;"><?= in_array($label, ['Status']) ? $val : e((string)$val) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($view_voter['rejection_reason']): ?>
                <div class="alert alert-danger">
                    <strong>Rejection Reason:</strong> <?= e($view_voter['rejection_reason']) ?>
                </div>
                <?php endif; ?>

                <?php if (in_array($view_voter['status'], ['pending', 'authority_approved'])): ?>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
                    <form method="POST" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="voter_id" value="<?= $view_voter['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="current_filter" value="<?= $filter ?>">
                        <button type="submit" class="btn btn-success">&#x2714; Approve Voter</button>
                    </form>
                    <form method="POST" style="display:inline-flex;align-items:center;gap:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="voter_id" value="<?= $view_voter['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="current_filter" value="<?= $filter ?>">
                        <input type="text" name="rejection_reason" class="form-control" style="width:220px;"
                               placeholder="Rejection reason" required>
                        <button type="submit" class="btn btn-danger">&#x2716; Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="padding:14px 20px;">
                <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <input type="text" name="search" class="form-control" style="max-width:280px;"
                           placeholder="Search name, NIC, or email..."
                           value="<?= e($search) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    <a href="<?= BASE_URL ?>/admin/voters.php?filter=<?= $filter ?>" class="btn btn-secondary btn-sm">Clear</a>
                    <div style="margin-left:auto;display:flex;gap:8px;">
                        <a href="?filter=" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
                        <a href="?filter=pending" class="btn btn-sm <?= $filter==='pending' ? 'btn-primary' : 'btn-outline' ?>">Pending</a>
                        <a href="?filter=authority_approved" class="btn btn-sm <?= $filter==='authority_approved' ? 'btn-primary' : 'btn-outline' ?>">Auth Approved</a>
                        <a href="?filter=approved" class="btn btn-sm <?= $filter==='approved' ? 'btn-success' : 'btn-outline' ?>">Approved</a>
                        <a href="?filter=rejected" class="btn btn-sm <?= $filter==='rejected' ? 'btn-danger' : 'btn-outline' ?>">Rejected</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Name</th><th>NIC</th><th>District</th><th>Phone</th><th>Authority</th><th>Status</th><th>Registered</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($voters)): ?>
                            <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray);">No voters found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($voters as $v): ?>
                            <tr>
                                <td><strong><?= e($v['full_name']) ?></strong><br><small style="color:var(--gray);"><?= e($v['email']) ?></small></td>
                                <td style="font-family:monospace;"><?= e($v['nic']) ?></td>
                                <td><?= e($v['district']) ?></td>
                                <td><?= e($v['phone']) ?></td>
                                <td style="font-size:0.82rem;"><?= e($v['authority_name'] ?? 'N/A') ?></td>
                                <td><?= statusBadge($v['status']) ?></td>
                                <td style="font-size:0.8rem;color:var(--gray);"><?= formatDate($v['created_at']) ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?view=<?= $v['id'] ?>&filter=<?= $filter ?>"
                                           class="btn btn-outline btn-sm">View</a>
                                        <?php if (in_array($v['status'], ['pending','authority_approved'])): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="voter_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="current_filter" value="<?= $filter ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </form>
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
            <?php if ($pages > 1): ?>
            <div class="card-footer">
                <div class="pagination">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a href="?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"
                       class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
