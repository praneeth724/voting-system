<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isVoterLoggedIn()) {
    header('Location: ' . BASE_URL . '/voter/dashboard.php');
    exit;
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $old = [
            'nic'           => trim($_POST['nic'] ?? ''),
            'full_name'     => trim($_POST['full_name'] ?? ''),
            'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
            'gender'        => $_POST['gender'] ?? '',
            'address'       => trim($_POST['address'] ?? ''),
            'district'      => $_POST['district'] ?? '',
            'phone'         => trim($_POST['phone'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
        ];
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Validation
        if (!validateNIC($old['nic']))                    $errors[] = 'Invalid NIC format. Use 9 digits + V/X or 12 digits.';
        if (strlen($old['full_name']) < 3)                $errors[] = 'Full name must be at least 3 characters.';
        if (empty($old['date_of_birth']))                  $errors[] = 'Date of birth is required.';
        if (!in_array($old['gender'], ['Male','Female','Other'])) $errors[] = 'Please select a valid gender.';
        if (strlen($old['address']) < 5)                  $errors[] = 'Address is required.';
        if (empty($old['district']))                       $errors[] = 'Please select a district.';
        if (!preg_match('/^[0-9+\s\-]{9,15}$/', $old['phone'])) $errors[] = 'Invalid phone number.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))   $errors[] = 'Invalid email address.';
        if (strlen($password) < 8)                        $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2)                     $errors[] = 'Passwords do not match.';

        // Age check (must be 18+)
        if (!empty($old['date_of_birth'])) {
            $dob = new DateTime($old['date_of_birth']);
            $age = $dob->diff(new DateTime())->y;
            if ($age < 18) $errors[] = 'You must be at least 18 years old to register.';
        }

        if (empty($errors)) {
            $pdo = getDB();

            // Check NIC uniqueness
            $s = $pdo->prepare("SELECT id FROM voters WHERE nic = ?");
            $s->execute([$old['nic']]);
            if ($s->fetch()) $errors[] = 'This NIC is already registered.';

            // Check email uniqueness
            $s = $pdo->prepare("SELECT id FROM voters WHERE email = ?");
            $s->execute([$old['email']]);
            if ($s->fetch()) $errors[] = 'This email address is already registered.';
        }

        if (empty($errors)) {
            $pdo = getDB();

            // Find authority for selected district
            $s = $pdo->prepare("SELECT id FROM authorities WHERE district = ? LIMIT 1");
            $s->execute([$old['district']]);
            $authority = $s->fetch();

            $stmt = $pdo->prepare(
                "INSERT INTO voters (nic, full_name, date_of_birth, gender, address, district, phone, email, password, authority_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $old['nic'],
                $old['full_name'],
                $old['date_of_birth'],
                $old['gender'],
                $old['address'],
                $old['district'],
                $old['phone'],
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $authority ? $authority['id'] : null,
            ]);

            setFlash('success', 'Registration successful! Your application is pending review by the district authority.');
            header('Location: ' . BASE_URL . '/voter/login.php');
            exit;
        }
    }
}

$districts = getSriLankaDistricts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Registration - Sri Lanka e-Vote</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box auth-box-wide">
        <div class="auth-header">
            <div class="logo">&#x1F5F3;</div>
            <h1>Voter Registration</h1>
            <p>Sri Lanka Online Voting System &mdash; Register to participate in elections</p>
        </div>
        <div class="auth-body">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin:8px 0 0 16px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>

                <p style="font-size:0.82rem;color:var(--gray);margin-bottom:20px;">
                    Fields marked <span class="required" style="color:var(--danger);">*</span> are required.
                    After registration, your local district authority will review and approve your application.
                </p>

                <h4 style="font-size:0.9rem;color:var(--primary);margin-bottom:14px;padding-bottom:6px;border-bottom:2px solid var(--border);">Identity Information</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nic">National Identity Card (NIC) <span class="required">*</span></label>
                        <input type="text" id="nic" name="nic" class="form-control"
                               value="<?= e($old['nic'] ?? '') ?>"
                               placeholder="e.g. 901234567V or 199012345678" maxlength="12" required>
                        <span class="form-text" id="nic-hint"></span>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name (as in NIC) <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               value="<?= e($old['full_name'] ?? '') ?>" placeholder="Your full legal name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                               value="<?= e($old['date_of_birth'] ?? '') ?>"
                               max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">-- Select Gender --</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h4 style="font-size:0.9rem;color:var(--primary);margin:16px 0 14px;padding-bottom:6px;border-bottom:2px solid var(--border);">Contact & Address</h4>

                <div class="form-group">
                    <label for="address">Residential Address <span class="required">*</span></label>
                    <textarea id="address" name="address" class="form-control" rows="2"
                              placeholder="Street address, city" required><?= e($old['address'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="district">Electoral District <span class="required">*</span></label>
                        <select id="district" name="district" class="form-control" required>
                            <option value="">-- Select District --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d ?>" <?= ($old['district'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control"
                               value="<?= e($old['phone'] ?? '') ?>" placeholder="e.g. 0771234567" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($old['email'] ?? '') ?>" placeholder="your@email.com" required>
                    <span class="form-text">Your OTP verification codes will be sent to this address.</span>
                </div>

                <h4 style="font-size:0.9rem;color:var(--primary);margin:16px 0 14px;padding-bottom:6px;border-bottom:2px solid var(--border);">Create Password</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Minimum 8 characters" required>
                        <div style="height:5px;background:#e0e0e0;border-radius:10px;margin-top:6px;overflow:hidden;">
                            <div id="pw-strength-bar" style="height:100%;width:0%;border-radius:10px;transition:all 0.3s;"></div>
                        </div>
                        <span class="form-text">Strength: <span id="pw-strength-label"></span></span>
                    </div>
                    <div class="form-group">
                        <label for="password2">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="password2" name="password2" class="form-control"
                               placeholder="Repeat your password" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:6px;">
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:400;">
                        <input type="checkbox" name="agree" required style="margin-top:3px;">
                        <span style="font-size:0.85rem;color:var(--gray);">
                            I confirm that the information provided is accurate and I am a Sri Lankan citizen eligible to vote.
                            I agree to the terms and conditions of this system.
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
                    &#x1F4DD; Submit Registration
                </button>
            </form>
        </div>
        <div class="auth-footer">
            Already registered? <a href="<?= BASE_URL ?>/voter/login.php">Login here</a> &nbsp;|&nbsp;
            <a href="<?= BASE_URL ?>/index.php">Back to Home</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
