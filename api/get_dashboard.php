<?php
// api/get_dashboard.php
// FIX P-001: N+1 queries replaced with single optimised queries
// FIX D-003: Correlated subqueries replaced with JOINs
require_once '../auth/session.php';
require_once '../config/db.php';
check_auth();
check_role(['owner', 'branch_admin']);

header('Content-Type: application/json');

$branch_id      = (int)$_SESSION['branch_id'];
$today          = date('Y-m-d');
$first_day_week = date('Y-m-d', strtotime('monday this week'));
$last_day_week  = date('Y-m-d', strtotime('sunday this week'));
$first_day_month= date('Y-m-01');
$last_day_month = date('Y-m-t');
$data = [];

// ── Helper: single-value sum query ───────────────────────
function getSum($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)($stmt->fetchColumn() ?: 0);
}

// ── TODAY KPIs ────────────────────────────────────────────
$sqlS = "SELECT SUM(de.amount) FROM daily_entries de
         INNER JOIN item_rates ir ON de.item_name = ir.item_name AND ir.branch_id = de.branch_id
         WHERE de.branch_id=? AND de.entry_date=? AND ir.category='sales'";
$sqlE = "SELECT SUM(de.amount) FROM daily_entries de
         INNER JOIN item_rates ir ON de.item_name = ir.item_name AND ir.branch_id = de.branch_id
         WHERE de.branch_id=? AND de.entry_date=? AND ir.category='expense'";

$data['todaySales'] = getSum($pdo, $sqlS, [$branch_id, $today]);
$data['todayExp']   = getSum($pdo, $sqlE, [$branch_id, $today]);
$data['todayCash']  = getSum($pdo, "SELECT SUM(cash_amount) FROM daily_payments WHERE branch_id=? AND entry_date=?", [$branch_id, $today]);
$data['todayGPay']  = getSum($pdo, "SELECT SUM(gpay_amount) FROM daily_payments WHERE branch_id=? AND entry_date=?", [$branch_id, $today]);

// ── WEEK KPIs ─────────────────────────────────────────────
$data['weekSales']  = getSum($pdo, str_replace('entry_date=?', 'entry_date BETWEEN ? AND ?', $sqlS), [$branch_id, $first_day_week, $last_day_week]);
$data['weekExp']    = getSum($pdo, str_replace('entry_date=?', 'entry_date BETWEEN ? AND ?', $sqlE), [$branch_id, $first_day_week, $last_day_week]);
$data['weekSwiggy'] = getSum($pdo, "SELECT SUM(amount) FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? AND platform='Swiggy'", [$branch_id, $first_day_week, $last_day_week]);
$data['weekZomato'] = getSum($pdo, "SELECT SUM(amount) FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? AND platform='Zomato'", [$branch_id, $first_day_week, $last_day_week]);

// ── MONTH KPIs ────────────────────────────────────────────
$sqlMS = str_replace('entry_date=?', 'entry_date BETWEEN ? AND ?', $sqlS);
$sqlME = str_replace('entry_date=?', 'entry_date BETWEEN ? AND ?', $sqlE);
$monthSales  = getSum($pdo, $sqlMS, [$branch_id, $first_day_month, $last_day_month]);
$monthExp    = getSum($pdo, $sqlME, [$branch_id, $first_day_month, $last_day_month]);
$monthSwiggy = getSum($pdo, "SELECT SUM(amount) FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? AND platform='Swiggy'", [$branch_id, $first_day_month, $last_day_month]);
$monthZomato = getSum($pdo, "SELECT SUM(amount) FROM online_sales WHERE branch_id=? AND sale_date BETWEEN ? AND ? AND platform='Zomato'", [$branch_id, $first_day_month, $last_day_month]);
$data['monthNetProfit'] = ($monthSales + $monthSwiggy + $monthZomato) - $monthExp;

// ── FIX P-001: Single query for 7-day chart (was 14 queries) ──
$sqlLast7 = "
    SELECT
        de.entry_date,
        SUM(CASE WHEN ir.category = 'sales'   THEN de.amount ELSE 0 END) AS sales_total,
        SUM(CASE WHEN ir.category = 'expense' THEN de.amount ELSE 0 END) AS expense_total
    FROM daily_entries de
    INNER JOIN item_rates ir ON de.item_name = ir.item_name AND ir.branch_id = de.branch_id
    WHERE de.branch_id = ?
      AND de.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND de.entry_date <= CURDATE()
    GROUP BY de.entry_date
    ORDER BY de.entry_date ASC
";
$stmtChart = $pdo->prepare($sqlLast7);
$stmtChart->execute([$branch_id]);
$chartRows = $stmtChart->fetchAll();

// Build complete 7-day structure (fill 0 for days with no data)
$chartByDate = [];
foreach ($chartRows as $r) {
    $chartByDate[$r['entry_date']] = $r;
}
$data['last7Days'] = ['labels' => [], 'sales' => [], 'expenses' => []];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $data['last7Days']['labels'][]   = date('D', strtotime($d));
    $data['last7Days']['sales'][]    = isset($chartByDate[$d]) ? (float)$chartByDate[$d]['sales_total']   : 0;
    $data['last7Days']['expenses'][] = isset($chartByDate[$d]) ? (float)$chartByDate[$d]['expense_total'] : 0;
}

// ── PAYMENT PIE ───────────────────────────────────────────
$monthCash = getSum($pdo, "SELECT SUM(cash_amount) FROM daily_payments WHERE branch_id=? AND entry_date BETWEEN ? AND ?", [$branch_id, $first_day_month, $last_day_month]);
$monthGPay = getSum($pdo, "SELECT SUM(gpay_amount) FROM daily_payments WHERE branch_id=? AND entry_date BETWEEN ? AND ?", [$branch_id, $first_day_month, $last_day_month]);
$data['pieData'] = [$monthCash, $monthGPay, $monthSwiggy + $monthZomato];

echo json_encode(['success' => true, 'data' => $data]);
?>
