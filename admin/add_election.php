<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo    = getDB();
$errors = [];
$old    = [];
$edit_id = (int)($_GET['edit'] ?? 0);
$election = null;

// Load for editing
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$edit_id]);
    $election = $stmt->fetch();
    if (!$election) { $edit_id = 0; }
    else { $old = $election; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $old = [
            'title'         => trim($_POST['title'] ?? ''),
            'description'   => trim($_POST['description'] ?? ''),
            'election_type' => $_POST['election_type'] ?? '',
            'start_date'    => trim($_POST['start_date'] ?? ''),
            'end_date'      => trim($_POST['end_date'] ?? ''),
            'status'        => $_POST['status'] ?? 'upcoming',
        ];
        $eid = (int)($_POST['edit_id'] ?? 0);

        if (strlen($old['title']) < 5) $errors[] = 'Title must be at least 5 characters.';
        if (!in_array($old['election_type'], ['Presidential','Parliamentary','Provincial','Local']))
            $errors[] = 'Invalid election type.';
        if (empty($old['start_date'])) $errors[] = 'Start date is required.';
        if (empty($old['end_date']))   $errors[] = 'End date is required.';
        if (!empty($old['start_date']) && !empty($old['end_date']) &&
            strtotime($old['end_date']) <= strtotime($old['start_date']))
            $errors[] = 'End date must be after start date.';

        if (empty($errors)) {
            if ($eid) {
                $stmt = $pdo->prepare(
                    "UPDATE elections SET title=?, description=?, election_type=?, start_date=?, end_date=?, status=? WHERE id=?"
                );
                $stmt->execute([$old['title'], $old['description'], $old['election_type'],
                                $old['start_date'], $old['end_date'], $old['status'], $eid]);
                setFlash('success', 'Election updated successfully.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO elections (title, description, election_type, start_date, end_date, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$old['title'], $old['description'], $old['election_type'],
                                $old['start_date'], $old['end_date'], $old['status'], $_SESSION['admin_id']]);
                setFlash('success', 'Election created successfully.');
            }
            header('Location: ' . BASE_URL . '/admin/elections.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Election - Admin</title>
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
        <a href="<?= BASE_URL ?>/admin/add_election.php" class="active"><span class="nav-icon">&#x2795;</span> Add Election</a>
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
        <h1><?= $edit_id ? 'Edit Election' : 'Add New Election' ?></h1>
        <a href="<?= BASE_URL ?>/admin/elections.php" class="btn btn-secondary btn-sm">&#x2190; Back</a>
    </div>
    <div class="content-area">
        <div class="card" style="max-width:700px;">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

                    <div class="form-group">
                        <label>Election Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($old['title'] ?? '') ?>"
                               placeholder="e.g. Presidential Election 2026" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief description of this election"><?= e($old['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Election Type <span class="required">*</span></label>
                            <select name="election_type" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach (['Presidential','Parliamentary','Provincial','Local'] as $type): ?>
                                <option value="<?= $type ?>" <?= ($old['election_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <?php foreach (['upcoming','active','completed','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($old['status'] ?? 'upcoming') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date &amp; Time <span class="required">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control"
                                   value="<?= e(isset($old['start_date']) ? date('Y-m-d\TH:i', strtotime($old['start_date'])) : '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>End Date &amp; Time <span class="required">*</span></label>
                            <input type="datetime-local" name="end_date" class="form-control"
                                   value="<?= e(isset($old['end_date']) ? date('Y-m-d\TH:i', strtotime($old['end_date'])) : '') ?>" required>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?= $edit_id ? '&#x1F4BE; Update Election' : '&#x2795; Create Election' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/elections.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
