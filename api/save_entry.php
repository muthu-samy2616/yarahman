<?php
// api/save_entry.php
// FIX S-005: Generic error messages | FIX F-003: Date validation | FIX S-001: CSRF
require_once '../auth/session.php';
require_once '../config/db.php';
check_auth();

header('Content-Type: application/json');

// FIX S-001: Verify CSRF token from header (sent by JS fetch)
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrfHeader) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid. Please refresh the page.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['entry_date']) || !isset($data['entries'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit();
}

$branch_id = (int)$_SESSION['branch_id'];
$user_id   = (int)$_SESSION['user_id'];
$entry_date = $data['entry_date'];

// FIX F-003: Validate date format strictly
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date) || !checkdate(
    (int)substr($entry_date, 5, 2),
    (int)substr($entry_date, 8, 2),
    (int)substr($entry_date, 0, 4)
)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit();
}

// Future date check
if ($entry_date > date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Cannot enter data for a future date.']);
    exit();
}

// Staff can only enter today's data
if ($_SESSION['role'] === 'staff' && $entry_date !== date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Staff can only enter data for today.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $cash_amount = isset($data['cash_amount']) ? (float)$data['cash_amount'] : 0;
    $gpay_amount = isset($data['gpay_amount']) ? (float)$data['gpay_amount'] : 0;

    $stmtPay = $pdo->prepare("
        INSERT INTO daily_payments (branch_id, entry_date, cash_amount, gpay_amount, entered_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE cash_amount = VALUES(cash_amount), gpay_amount = VALUES(gpay_amount), entered_by = VALUES(entered_by)
    ");
    $stmtPay->execute([$branch_id, $entry_date, $cash_amount, $gpay_amount, $user_id]);

    $stmt = $pdo->prepare("
        INSERT INTO daily_entries (branch_id, entry_date, item_name, quantity, unit_price, amount, payment_mode, entered_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit_price = VALUES(unit_price),
            amount = VALUES(amount), payment_mode = VALUES(payment_mode), entered_by = VALUES(entered_by)
    ");

    foreach ($data['entries'] as $entry) {
        $amount = isset($entry['amount']) ? (float)$entry['amount'] : 0;
        if ($amount > 0) {
            $stmt->execute([
                $branch_id,
                $entry_date,
                htmlspecialchars(strip_tags($entry['item_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                (float)($entry['qty'] ?? 0),
                (float)($entry['unit_price'] ?? 0),
                $amount,
                'CASH',
                $user_id
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);

} catch (\PDOException $e) {
    $pdo->rollBack();
    // FIX S-005: Log real error, show generic message
    error_log('save_entry error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save data. Please try again.']);
}
?>
