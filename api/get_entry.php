<?php
// api/get_entry.php — Load existing entries for a given date
require_once '../auth/session.php';
require_once '../config/db.php';
check_auth();

header('Content-Type: application/json');

$branch_id  = (int)$_SESSION['branch_id'];
$user_role  = $_SESSION['role'];
$date       = $_GET['date'] ?? '';

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
    (int)substr($date, 5, 2),
    (int)substr($date, 8, 2),
    (int)substr($date, 0, 4)
)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date.']);
    exit();
}

if ($date > date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Cannot load future date.']);
    exit();
}

// Staff can only view today
if ($user_role === 'staff' && $date !== date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

// Fetch entries
$stmtE = $pdo->prepare("SELECT item_name, quantity, unit_price, amount FROM daily_entries WHERE branch_id = ? AND entry_date = ?");
$stmtE->execute([$branch_id, $date]);
$entries = [];
while ($r = $stmtE->fetch()) {
    $entries[$r['item_name']] = ['qty' => (float)$r['quantity'], 'unit_price' => (float)$r['unit_price'], 'amount' => (float)$r['amount']];
}

// Fetch payments
$stmtP = $pdo->prepare("SELECT cash_amount, gpay_amount FROM daily_payments WHERE branch_id = ? AND entry_date = ?");
$stmtP->execute([$branch_id, $date]);
$payment = $stmtP->fetch();

echo json_encode([
    'success'  => true,
    'entries'  => $entries,
    'cash'     => $payment ? (float)$payment['cash_amount'] : 0,
    'gpay'     => $payment ? (float)$payment['gpay_amount'] : 0,
]);
?>
