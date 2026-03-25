<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pdo     = getDB();
$errors  = [];
$old     = [];
$edit_id = (int)($_GET['edit'] ?? 0);
$candidate = null;

if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$edit_id]);
    $candidate = $stmt->fetch();
    if ($candidate) $old = $candidate;
}

$elections = $pdo->query("SELECT id, title FROM elections ORDER BY title")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $old = [
            'election_id'  => (int)($_POST['election_id'] ?? 0),
            'full_name'    => trim($_POST['full_name'] ?? ''),
            'party'        => trim($_POST['party'] ?? ''),
            'party_color'  => trim($_POST['party_color'] ?? '#333333'),
            'symbol_text'  => trim($_POST['symbol_text'] ?? ''),
            'bio'          => trim($_POST['bio'] ?? ''),
        ];
        $eid = (int)($_POST['edit_id'] ?? 0);

        if (!$old['election_id']) $errors[] = 'Please select an election.';
        if (strlen($old['full_name']) < 3) $errors[] = 'Full name is required.';
        if (strlen($old['party']) < 2)     $errors[] = 'Party name is required.';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $old['party_color'])) $old['party_color'] = '#333333';

        if (empty($errors)) {
            if ($eid) {
                $stmt = $pdo->prepare(
                    "UPDATE candidates SET election_id=?, full_name=?, party=?, party_color=?, symbol_text=?, bio=? WHERE id=?"
                );
                $stmt->execute([$old['election_id'], $old['full_name'], $old['party'],
                                $old['party_color'], $old['symbol_text'], $old['bio'], $eid]);
                setFlash('success', 'Candidate updated.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO candidates (election_id, full_name, party, party_color, symbol_text, bio) VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$old['election_id'], $old['full_name'], $old['party'],
                                $old['party_color'], $old['symbol_text'], $old['bio']]);
                setFlash('success', 'Candidate added.');
            }
            header('Location: ' . BASE_URL . '/admin/candidates.php?election=' . $old['election_id']);
            exit;
        }
    }
}

$preselect_election = (int)($_GET['election'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Candidate - Admin</title>
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
        <h1><?= $edit_id ? 'Edit Candidate' : 'Add Candidate' ?></h1>
        <a href="<?= BASE_URL ?>/admin/candidates.php" class="btn btn-secondary btn-sm">&#x2190; Back</a>
    </div>
    <div class="content-area">
        <div class="card" style="max-width:640px;">
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
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" class="form-control" required>
                            <option value="">-- Select Election --</option>
                            <?php foreach ($elections as $el): ?>
                            <option value="<?= $el['id'] ?>"
                                <?= (($old['election_id'] ?? $preselect_election) == $el['id']) ? 'selected' : '' ?>>
                                <?= e($el['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= e($old['full_name'] ?? '') ?>"
                               placeholder="Candidate's full name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Political Party <span class="required">*</span></label>
                            <input type="text" name="party" class="form-control"
                                   value="<?= e($old['party'] ?? '') ?>"
                                   placeholder="e.g. United National Party" required>
                        </div>
                        <div class="form-group">
                            <label>Party Symbol / Number</label>
                            <input type="text" name="symbol_text" class="form-control"
                                   value="<?= e($old['symbol_text'] ?? '') ?>"
                                   placeholder="e.g. Elephant, 12">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Party Color</label>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="color" name="party_color" class="form-control"
                                   value="<?= e($old['party_color'] ?? '#333333') ?>"
                                   style="width:60px;height:40px;padding:2px;cursor:pointer;">
                            <span style="font-size:0.82rem;color:var(--gray);">Choose a color to represent the party.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Biography / Description</label>
                        <textarea name="bio" class="form-control" rows="3"
                                  placeholder="Brief bio or manifesto highlights"><?= e($old['bio'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:8px;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <?= $edit_id ? '&#x1F4BE; Update' : '&#x2795; Add Candidate' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/candidates.php" class="btn btn-secondary btn-lg">Cancel</a>
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
