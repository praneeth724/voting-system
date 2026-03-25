<?php
// ============================================================
// Shared Helper Functions
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Authentication Guards ----

function requireVoterLogin(): void {
    if (empty($_SESSION['voter_id'])) {
        header('Location: ' . BASE_URL . '/voter/login.php');
        exit;
    }
}

function requireAdminLogin(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function requireAuthorityLogin(): void {
    if (empty($_SESSION['authority_id'])) {
        header('Location: ' . BASE_URL . '/authority/login.php');
        exit;
    }
}

function isVoterLoggedIn(): bool {
    return !empty($_SESSION['voter_id']);
}

// ---- CSRF Protection ----

function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

// ---- OTP Functions ----

function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function createOTP(PDO $pdo, int $voter_id, string $purpose): string {
    // Invalidate previous unused OTPs for this voter+purpose
    $stmt = $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE voter_id = ? AND purpose = ? AND is_used = 0");
    $stmt->execute([$voter_id, $purpose]);

    $otp = generateOTP();
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    $stmt = $pdo->prepare("INSERT INTO otp_tokens (voter_id, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$voter_id, $otp, $purpose, $expires_at]);

    return $otp;
}

function verifyOTP(PDO $pdo, int $voter_id, string $otp_code, string $purpose): bool {
    $stmt = $pdo->prepare(
        "SELECT id FROM otp_tokens
         WHERE voter_id = ? AND otp_code = ? AND purpose = ?
         AND is_used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$voter_id, $otp_code, $purpose]);
    $token = $stmt->fetch();

    if ($token) {
        $pdo->prepare("UPDATE otp_tokens SET is_used = 1 WHERE id = ?")->execute([$token['id']]);
        return true;
    }
    return false;
}

// ---- Flash Messages ----

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $message = htmlspecialchars($flash['message']);
        echo "<div class=\"alert alert-{$type}\">{$message}</div>";
    }
}

// ---- Formatting Helpers ----

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date): string {
    return date('d M Y', strtotime($date));
}

function formatDateTime(string $date): string {
    return date('d M Y, h:i A', strtotime($date));
}

function statusBadge(string $status): string {
    $map = [
        'pending'            => ['class' => 'badge-warning',  'label' => 'Pending'],
        'authority_approved' => ['class' => 'badge-info',     'label' => 'Authority Approved'],
        'approved'           => ['class' => 'badge-success',  'label' => 'Approved'],
        'rejected'           => ['class' => 'badge-danger',   'label' => 'Rejected'],
        'upcoming'           => ['class' => 'badge-info',     'label' => 'Upcoming'],
        'active'             => ['class' => 'badge-success',  'label' => 'Active'],
        'completed'          => ['class' => 'badge-secondary','label' => 'Completed'],
        'cancelled'          => ['class' => 'badge-danger',   'label' => 'Cancelled'],
    ];
    $b = $map[$status] ?? ['class' => 'badge-secondary', 'label' => ucfirst($status)];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

function getSriLankaDistricts(): array {
    return [
        'Colombo', 'Gampaha', 'Kalutara',
        'Kandy', 'Matale', 'Nuwara Eliya',
        'Galle', 'Matara', 'Hambantota',
        'Jaffna', 'Kilinochchi', 'Mannar', 'Vavuniya', 'Mullaitivu',
        'Batticaloa', 'Ampara', 'Trincomalee',
        'Kurunegala', 'Puttalam',
        'Anuradhapura', 'Polonnaruwa',
        'Badulla', 'Monaragala',
        'Ratnapura', 'Kegalle',
    ];
}

function validateNIC(string $nic): bool {
    // Old NIC: 9 digits + V/X  e.g. 901234567V
    // New NIC: 12 digits       e.g. 199012345678
    return preg_match('/^[0-9]{9}[VXvx]$/', $nic) || preg_match('/^[0-9]{12}$/', $nic);
}
