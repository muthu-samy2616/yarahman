<?php
// api/switch_branch.php
// FIX S-002: Open redirect fixed — only allow whitelisted page names
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();

if ($_SESSION['role'] === 'owner' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $pdo->prepare('SELECT name FROM branches WHERE id = ?');
    $stmt->execute([$id]);
    $branch = $stmt->fetch();

    if ($branch) {
        $_SESSION['branch_id']   = $id;
        $_SESSION['branch_name'] = $branch['name'];
    }
}

// FIX S-002: Validate redirect — only allow known page filenames
$redirectParam = basename($_GET['redirect'] ?? 'dashboard.php');
$allowed = ALLOWED_REDIRECT_PAGES;

if (in_array($redirectParam, $allowed)) {
    header('Location: ../pages/' . $redirectParam);
} else {
    header('Location: ../pages/dashboard.php');
}
exit();
?>
