<?php
// logout.php — Secure session destruction with CSRF cleanup
require_once 'auth/session.php';

// Destroy all session data (includes csrf_token, user_id, branch_id, role, etc.)
$_SESSION = [];

// Remove the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect to login with a cache-busting header to prevent back-button access
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Location: index.php?logout=1');
exit();
?>
