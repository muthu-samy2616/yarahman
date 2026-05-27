<?php
// config/db.php — Production-hardened database configuration
// FIX S-003: Credentials from .env | FIX S-004: display_errors OFF

// ── Load .env file ────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

// ── PHP Error Handling (Production) ──────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');        // FIX S-004: NEVER show errors to users
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

// ── Database Connection ───────────────────────────────────
$host    = $_ENV['DB_HOST'] ?? 'localhost';
$db      = $_ENV['DB_NAME'] ?? 'u777110831_briyani_shop';
$user    = $_ENV['DB_USER'] ?? 'u777110831_admin';
$pass    = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('DB Connection failed: ' . $e->getMessage());
    // FIX S-004: Generic message, never expose real error
    http_response_code(503);
    die(json_encode(['success' => false, 'message' => 'Service temporarily unavailable. Please try again later.']));
}
?>
