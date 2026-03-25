<?php
// ============================================================
// Database Setup & Initialization Script
// Run this ONCE at http://localhost/vote system/setup.php
// DELETE this file after setup is complete for security.
// ============================================================

$host    = 'localhost';
$port    = '3306';       // MAMP default
$user    = 'root';
$pass    = 'root';       // MAMP default
$dbname  = 'vote_system_db';

$errors  = [];
$success = [];

// Connect without selecting DB first
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('<p style="color:red;font-family:Arial;padding:20px;">Cannot connect to MySQL: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        // 1. Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        $success[] = "Database '$dbname' created/verified.";

        // 2. Create tables
        $tables = <<<SQL
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS authorities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            district VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS voters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nic VARCHAR(12) UNIQUE NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender ENUM('Male','Female','Other') NOT NULL,
            address TEXT NOT NULL,
            district VARCHAR(100) NOT NULL,
            phone VARCHAR(15) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            status ENUM('pending','authority_approved','approved','rejected') DEFAULT 'pending',
            authority_id INT NULL,
            rejection_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (authority_id) REFERENCES authorities(id) ON DELETE SET NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS elections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            election_type ENUM('Presidential','Parliamentary','Provincial','Local') NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status ENUM('upcoming','active','completed','cancelled') DEFAULT 'upcoming',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admins(id)
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS candidates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            election_id INT NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            party VARCHAR(100) NOT NULL,
            party_color VARCHAR(7) DEFAULT '#333333',
            symbol_text VARCHAR(50),
            bio TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            election_id INT NOT NULL,
            voter_id INT NOT NULL,
            candidate_id INT NOT NULL,
            ip_address VARCHAR(45),
            voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_vote (election_id, voter_id),
            FOREIGN KEY (election_id) REFERENCES elections(id),
            FOREIGN KEY (voter_id) REFERENCES voters(id),
            FOREIGN KEY (candidate_id) REFERENCES candidates(id)
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS otp_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voter_id INT NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            purpose ENUM('login','vote_confirm') NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voter_id) REFERENCES voters(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
SQL;

        foreach (explode(';', $tables) as $sql) {
            $sql = trim($sql);
            if ($sql) $pdo->exec($sql);
        }
        $success[] = "All tables created.";

        // 3. Insert default admin
        $adminPw = password_hash('Admin@123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPw, 'System Administrator', 'admin@votesystem.lk']);
        $success[] = "Default admin created (username: admin, password: Admin@123).";

        // 4. Insert default authorities
        $authPw = password_hash('Auth@123', PASSWORD_DEFAULT);
        $authorities = [
            ['auth_colombo', $authPw, 'Colombo District Officer',      'colombo@authority.lk',    'Colombo'],
            ['auth_kandy',   $authPw, 'Kandy District Officer',         'kandy@authority.lk',      'Kandy'],
            ['auth_galle',   $authPw, 'Galle District Officer',         'galle@authority.lk',      'Galle'],
            ['auth_jaffna',  $authPw, 'Jaffna District Officer',        'jaffna@authority.lk',     'Jaffna'],
            ['auth_gampaha', $authPw, 'Gampaha District Officer',       'gampaha@authority.lk',    'Gampaha'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO authorities (username, password, full_name, email, district) VALUES (?, ?, ?, ?, ?)");
        foreach ($authorities as $a) $stmt->execute($a);
        $success[] = "Default authority accounts created (password: Auth@123).";

        // 5. Insert sample election
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO elections (id, title, description, election_type, start_date, end_date, status, created_by) VALUES
             (1, 'Presidential Election 2026', 'Sri Lanka Presidential Election 2026 - Choose your President for the next term.',
              'Presidential', '2026-03-25 08:00:00', '2026-12-31 20:00:00', 'active', 1)"
        );
        $stmt->execute();

        // 6. Insert sample candidates
        $candidates = [
            [1, 'Kamal Perera',   'United National Party',    '#006400', 'Elephant',   'Experienced leader with 20 years in public service.'],
            [1, 'Nimal Silva',    'Samagi Jana Balawegaya',   '#FF8C00', 'Gas Cylinder','Advocate for economic reform and social welfare.'],
            [1, 'Sunil Fernando', 'National People Power',    '#FF0000', 'Compass',    'Champion of grassroots democracy and anti-corruption.'],
            [1, 'Priya Kumari',   'Sri Lanka Podujana Peramuna','#9400D3','Betel Leaf',  'Committed to national development and rural upliftment.'],
        ];
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO candidates (election_id, full_name, party, party_color, symbol_text, bio) VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($candidates as $c) $stmt->execute($c);
        $success[] = "Sample election and candidates inserted.";

        $success[] = "<strong>Setup complete!</strong> Please delete setup.php now.";

    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Setup - Vote System</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; }
        .box { max-width: 620px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #8B0000; color: #fff; padding: 28px 32px; }
        .header h1 { font-size: 1.3rem; margin: 0; }
        .header p  { font-size: 0.85rem; opacity: 0.8; margin: 4px 0 0; }
        .body { padding: 28px 32px; }
        .alert-s { background: #e8f5e9; color: #1b5e20; padding: 10px 14px; border-radius: 6px; border-left: 4px solid #2e7d32; margin-bottom: 8px; font-size: 0.88rem; }
        .alert-e { background: #ffebee; color: #b71c1c; padding: 10px 14px; border-radius: 6px; border-left: 4px solid #c62828; margin-bottom: 8px; font-size: 0.88rem; }
        .creds { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
        .creds h3 { font-size: 0.9rem; color: #c8960c; margin-bottom: 10px; }
        .creds table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .creds td { padding: 5px 8px; border-bottom: 1px solid #fff3cd; }
        .creds td:first-child { font-weight: 600; width: 160px; }
        .btn { display: inline-block; background: #8B0000; color: #fff; padding: 12px 28px; border: none; border-radius: 7px; font-size: 0.95rem; font-weight: 700; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn:hover { background: #5c0000; }
        .warning { background: #fff3e0; border: 1px solid #ffcc02; border-radius: 8px; padding: 12px 16px; font-size: 0.82rem; color: #e65100; margin-top: 16px; }
    </style>
</head>
<body>
<div class="box">
    <div class="header">
        <h1>Sri Lanka Online Voting System</h1>
        <p>Database Setup & Initialization</p>
    </div>
    <div class="body">
        <?php foreach ($errors  as $e): ?>
            <div class="alert-e"><?= $e ?></div>
        <?php endforeach; ?>
        <?php foreach ($success as $s): ?>
            <div class="alert-s"><?= $s ?></div>
        <?php endforeach; ?>

        <?php if (empty($success)): ?>
        <p style="margin-bottom:20px;color:#555;font-size:0.9rem;">
            This script will create the <strong>vote_system_db</strong> database, all tables,
            and insert default admin, authority, and sample election data.
        </p>
        <div class="creds">
            <h3>Default Login Credentials (after setup)</h3>
            <table>
                <tr><td>Admin Username</td><td><code>admin</code></td></tr>
                <tr><td>Admin Password</td><td><code>Admin@123</code></td></tr>
                <tr><td>Authority Username</td><td><code>auth_colombo</code> / <code>auth_kandy</code></td></tr>
                <tr><td>Authority Password</td><td><code>Auth@123</code></td></tr>
            </table>
        </div>
        <form method="POST">
            <button type="submit" name="setup" class="btn">Run Setup Now</button>
        </form>
        <?php else: ?>
        <div style="text-align:center;margin-top:16px;">
            <a href="/vote system/index.php" style="background:#8B0000;color:#fff;padding:12px 24px;border-radius:7px;font-weight:700;display:inline-block;margin:4px;">Go to Home Page</a>
            <a href="/vote system/admin/login.php" style="background:#2e7d32;color:#fff;padding:12px 24px;border-radius:7px;font-weight:700;display:inline-block;margin:4px;">Admin Login</a>
        </div>
        <?php endif; ?>

        <div class="warning">
            <strong>Security Notice:</strong> Delete this file (<code>setup.php</code>) immediately after setup is complete.
        </div>
    </div>
</div>
</body>
</html>
