<?php
// auth/session.php — Session management with CSRF protection
// FIX S-001: CSRF token system | FIX S-006: session_regenerate_id

require_once __DIR__ . '/../config/constants.php';

// Secure session settings — PHP 7.2 compatible (no array form, no samesite key)
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
session_set_cookie_params(SESSION_LIFETIME, '/');
session_start();

// ── CSRF Token Functions ──────────────────────────────────
// FIX S-001: Generate a CSRF token for the session
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// FIX S-001: Verify the submitted CSRF token
function csrf_verify() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page.']));
    }
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']));
    }
}

// ── Auth Helpers ──────────────────────────────────────────
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_auth() {
    if (!is_logged_in()) {
        header('Location: ../index.php');
        exit();
    }
    // 2FA enforcement
    if (!isset($_SESSION['2fa_verified'])) {
        global $pdo;
        if (!isset($pdo)) require_once __DIR__ . '/../config/db.php';
        $stmt = $pdo->prepare('SELECT totp_secret FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();
        if ($u && !empty($u['totp_secret']) && basename($_SERVER['PHP_SELF']) !== 'verify_2fa.php') {
            header('Location: ../pages/verify_2fa.php');
            exit();
        }
    }
    // Session timeout check
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?timeout=1');
        exit();
    }
}

function check_role($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
            header('Location: ../pages/daily_entry.php');
        } else {
            header('Location: ../pages/dashboard.php');
        }
        exit();
    }
}

function render_branch_selector() {
    if ($_SESSION['role'] !== 'owner' || empty($_SESSION['admin_branches'])) {
        return '<div class="branch-name">' . htmlspecialchars($_SESSION['branch_name'] ?? '') . '</div>';
    }
    $html = '<select class="branch-selector" onchange="switchBranch(this.value)">';
    foreach ($_SESSION['admin_branches'] as $b) {
        $sel = ($b['id'] == $_SESSION['branch_id']) ? 'selected' : '';
        $html .= '<option value="' . (int)$b['id'] . '" ' . $sel . '>' . htmlspecialchars($b['name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}