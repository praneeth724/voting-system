<?php
// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');          // MAMP default MySQL port
define('DB_NAME', 'vote_system_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');          // MAMP default password
define('DB_CHARSET', 'utf8mb4');

// Base URL - adjust if your folder name differs
define('BASE_URL', '/vote system');

/**
 * Get PDO database connection
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:Arial;padding:40px;text-align:center;">
                <h2 style="color:#c62828">Database Connection Failed</h2>
                <p>Please ensure MySQL is running and the database <strong>' . DB_NAME . '</strong> exists.</p>
                <p>Run <a href="' . BASE_URL . '/setup.php">setup.php</a> to initialize the database.</p>
                <p style="color:#999;font-size:12px;">' . htmlspecialchars($e->getMessage()) . '</p>
            </div>');
        }
    }
    return $pdo;
}
