<?php
// api/get_report.php — FIX D-003: JOIN instead of correlated subqueries
require_once '../auth/session.php';
require_once '../config/db.php';
check_auth();
check_role(['owner', 'branch_admin']);

header('Content-Type: application/json');

$type      = $_GET['type'] ?? '';
$branch_id = (int)$_SESSION['branch_id'];

if ($type === 'weekly') {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
    $to   = $_GET['to']   ?? date('Y-m-d', strtotime('sunday this week'));
    $item = $_GET['item']  ?? 'all';

    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
        exit();
    }

    if ($item !== 'all') {
        $stmtI = $pdo->prepare("SELECT item_name, category FROM item_rates WHERE branch_id=? AND item_name=? ORDER BY sort_order");
        $stmtI->execute([$branch_id, $item]);
    } else {
        $stmtI = $pdo->prepare("SELECT item_name, category FROM item_rates WHERE branch_id=? ORDER BY sort_order");
        $stmtI->execute([$branch_id]);
    }
    $items = $stmtI->fetchAll();

    // Build date range
    $dates = []; $daily_cash = []; $daily_gpay = [];
    $cur = strtotime($from); $end = strtotime($to);
    while ($cur <= $end) {
        $d = date('Y-m-d', $cur);
        $dates[] = $d; $daily_cash[$d] = 0; $daily_gpay[$d] = 0;
        $cur = strtotime('+1 day', $cur);
    }

    // Fetch entries
    if ($item !== 'all') {
        $stmtE = $pdo->prepare("SELECT entry_date, item_name, amount FROM daily_entries WHERE branch_id=? AND entry_date BETWEEN ? AND ? AND item_name=?");
        $stmtE->execute([$branch_id, $from, $to, $item]);
    } else {
        $stmtE = $pdo->prepare("SELECT entry_date, item_name, amount FROM daily_entries WHERE branch_id=? AND entry_date BETWEEN ? AND ?");
        $stmtE->execute([$branch_id, $from, $to]);
    }

    $item_data = [];
    foreach ($items as $ir) {
        $item_data[$ir['item_name']] = ['category' => $ir['category'], 'days' => array_fill_keys($dates, 0)];
    }
    while ($row = $stmtE->fetch()) {
        if (isset($item_data[$row['item_name']][$row['entry_date']])) {
            $item_data[$row['item_name']]['days'][$row['entry_date']] += (float)$row['amount'];
        } elseif (isset($item_data[$row['item_name']])) {
            $item_data[$row['item_name']]['days'][$row['entry_date']] = (float)$row['amount'];
        }
    }

    $stmtP = $pdo->prepare("SELECT entry_date, cash_amount, gpay_amount FROM daily_payments WHERE branch_id=? AND entry_date BETWEEN ? AND ?");
    $stmtP->execute([$branch_id, $from, $to]);
    while ($r = $stmtP->fetch()) {
        if (isset($daily_cash[$r['entry_date']])) { $daily_cash[$r['entry_date']] = (float)$r['cash_amount']; $daily_gpay[$r['entry_date']] = (float)$r['gpay_amount']; }
    }

    $stmtO = $pdo->prepare("SELECT platform, SUM(amount) as amt FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? GROUP BY platform");
    $stmtO->execute([$branch_id, $from, $to]);
    $swiggy = 0; $zomato = 0;
    while ($r = $stmtO->fetch()) { if ($r['platform']==='Swiggy') $swiggy=(float)$r['amt']; else $zomato=(float)$r['amt']; }

    echo json_encode(['success'=>true,'dates'=>$dates,'items'=>$item_data,'daily_cash'=>$daily_cash,'daily_gpay'=>$daily_gpay,'swiggy'=>$swiggy,'zomato'=>$zomato]);
    exit();
}

if ($type === 'monthly') {
    $month = preg_replace('/[^0-9-]/', '', $_GET['month'] ?? date('Y-m'));
    $start = $month . '-01';
    $end   = date('Y-m-t', strtotime($start));

    $stmtI = $pdo->prepare("SELECT item_name, category FROM item_rates WHERE branch_id=? ORDER BY sort_order");
    $stmtI->execute([$branch_id]);
    $items = $stmtI->fetchAll();

    $days = []; $cur = strtotime($start); $endT = strtotime($end);
    while ($cur <= $endT) { $days[] = date('Y-m-d', $cur); $cur = strtotime('+1 day', $cur); }

    $data = [];
    foreach ($days as $d) { $data[$d] = ['cash'=>0,'gpay'=>0,'items'=>[]]; foreach ($items as $it) { $data[$d]['items'][$it['item_name']] = 0; } }

    $stmtE = $pdo->prepare("SELECT entry_date, item_name, amount FROM daily_entries WHERE branch_id=? AND entry_date BETWEEN ? AND ?");
    $stmtE->execute([$branch_id, $start, $end]);
    while ($r = $stmtE->fetch()) {
        if (isset($data[$r['entry_date']]['items'][$r['item_name']])) { $data[$r['entry_date']]['items'][$r['item_name']] += (float)$r['amount']; }
    }

    $stmtP = $pdo->prepare("SELECT entry_date, cash_amount, gpay_amount FROM daily_payments WHERE branch_id=? AND entry_date BETWEEN ? AND ?");
    $stmtP->execute([$branch_id, $start, $end]);
    while ($r = $stmtP->fetch()) { if (isset($data[$r['entry_date']])) { $data[$r['entry_date']]['cash']=(float)$r['cash_amount']; $data[$r['entry_date']]['gpay']=(float)$r['gpay_amount']; } }

    $stmtO = $pdo->prepare("SELECT platform, SUM(amount) as amt FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? GROUP BY platform");
    $stmtO->execute([$branch_id, $start, $end]);
    $swiggy = 0; $zomato = 0;
    while ($r = $stmtO->fetch()) { if ($r['platform']==='Swiggy') $swiggy=(float)$r['amt']; else $zomato=(float)$r['amt']; }

    echo json_encode(['success'=>true,'days'=>$days,'items'=>$items,'data'=>$data,'swiggy'=>$swiggy,'zomato'=>$zomato]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid report type.']);
?>
