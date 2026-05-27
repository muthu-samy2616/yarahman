<?php
// api/save_online.php — FIX S-001: CSRF | FIX S-005: Generic errors
require_once '../auth/session.php';
require_once '../config/db.php';
check_auth();
check_role(['owner', 'branch_admin']);

header('Content-Type: application/json');

// FIX S-001: Verify CSRF token
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrfHeader) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['sale_date']) || !isset($data['platform']) || !isset($data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit();
}

$branch_id = (int)$_SESSION['branch_id'];
$user_id   = (int)$_SESSION['user_id'];
$sale_date = $data['sale_date'];
$platform  = $data['platform'];
$amount    = (float)$data['amount'];

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sale_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit();
}

if (!in_array($platform, ['Swiggy', 'Zomato'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid platform.']);
    exit();
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO online_sales (branch_id, sale_date, platform, amount, entered_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$branch_id, $sale_date, $platform, $amount, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Online sale saved successfully']);
} catch (\PDOException $e) {
    error_log('save_online error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save. Please try again.']);
}
?>
