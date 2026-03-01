<?php
// ============================================================
// config/db.php - Database Configuration
// Arellano University Digital Clearance System
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'mark');         // Change in production
define('DB_PASS', 'mark123');             // Change in production
define('DB_NAME', 'au_clearance_db');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'AU Digital Clearance System');
define('APP_URL', 'http://localhost/au-clearance');
define('APP_VERSION', '1.0.0');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'au_clearance_session');

// Security
define('HASH_COST', 12);

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error securely, don't expose details
            error_log("DB Connection Error: " . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed. Please contact administrator.']));
        }
    }
    return $pdo;
}
