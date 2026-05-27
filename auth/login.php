<?php
// auth/login.php
// FIX S-006: session_regenerate_id | FIX S-007: Rate limiting | FIX S-001: CSRF
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: ../index.php?error=empty&u=' . urlencode($username));
    exit();
}

$ip = $_SERVER['REMOTE_ADDR'];

// FIX S-007: Check login rate limiting (graceful if login_attempts table not yet migrated)
try {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE) AND success = 0
    ");
    $stmtCheck->execute([$ip, LOGIN_LOCKOUT_MINUTES]);
    $failCount = (int)$stmtCheck->fetchColumn();

    if ($failCount >= MAX_LOGIN_ATTEMPTS) {
        header('Location: ../index.php?error=locked');
        exit();
    }
} catch (\PDOException $e) {
    error_log('login_attempts table missing — run migration_001_login_attempts.sql: ' . $e->getMessage());
    $failCount = 0;
}

// Fetch user
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.password, u.role, u.branch_id, b.branch_name,
           u.is_active, u.allowed_categories
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, 1)")
            ->execute([$ip, $username]);
    } catch (\PDOException $e) {
        error_log('login_attempts insert failed: ' . $e->getMessage());
    }

    if ($user['is_active'] == 0) {
        header('Location: ../index.php?error=inactive');
        exit();
    }

    // FIX S-006: session fixation prevention
    session_regenerate_id(true);

    $_SESSION['user_id']            = $user['id'];
    $_SESSION['username']           = $user['username'];
    $_SESSION['role']               = $user['role'];
    $_SESSION['branch_id']          = $user['branch_id'];
    $_SESSION['branch_name']        = $user['branch_name'];
    $_SESSION['login_time']         = time();
    $_SESSION['allowed_categories'] = $user['allowed_categories'] ?? PERM_ALL;

    if ($user['role'] === 'owner') {
        $stmtB = $pdo->query('SELECT id, branch_name as name FROM branches ORDER BY id ASC');
        $_SESSION['admin_branches'] = $stmtB->fetchAll();
    }

    $redirect = ($user['role'] === 'staff')
        ? '../pages/daily_entry.php'
        : '../pages/dashboard.php';
    header('Location: ' . $redirect);
    exit();

} else {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, 0)")
            ->execute([$ip, $username]);
    } catch (\PDOException $e) {
        error_log('login_attempts insert failed: ' . $e->getMessage());
    }
    header('Location: ../index.php?error=invalid&u=' . urlencode($username));
    exit();
}
?>
