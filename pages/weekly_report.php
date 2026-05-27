<?php
// pages/weekly_report.php
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();
check_role(['owner', 'branch_admin']);

$branch_name = $_SESSION['branch_name'];
$today = date('Y-m-d');
$default_from = date('Y-m-d', strtotime('monday this week'));
$default_to   = date('Y-m-d', strtotime('sunday this week'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Report – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"
            integrity="sha256-wI1sbmfbCxRqA1eO6FsqYcZTl2NjCHFqI+oKz8YCiV8="
            crossorigin="anonymous"></script>
    <style>
        .report-table { width:100%; border-collapse:collapse; font-size:.88rem; }
        .report-table th, .report-table td { padding:9px 12px; border:1px solid var(--border); text-align:right; }
        .report-table th { background:var(--surface); font-weight:600; text-align:center; }
        .report-table td:first-child { text-align:left; }
        .report-table tfoot td { font-weight:700; background:var(--surface); }
        .filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px; }
        .filter-row label { font-size:.82rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px; }
        .filter-row input, .filter-row select { padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:.88rem; }
        .summary-bar { display:flex; gap:20px; flex-wrap:wrap; padding:16px; background:var(--surface); border-radius:12px; margin-bottom:20px; }
        .sum-item .sum-val { font-size:1.25rem; font-weight:700; }
        .sum-item .sum-lbl { font-size:.75rem; color:var(--text-muted); }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<main class="main-content" role="main">
    <div class="container">
        <?php include '../includes/branch_bar.php'; ?>

        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-chart-bar" aria-hidden="true"></i> Weekly Report</h1>
                <p class="page-subtitle"><?= htmlspecialchars($branch_name) ?></p>
            </div>
            <button class="btn btn-secondary" onclick="printReport()" aria-label="Print weekly report">
                <i class="fas fa-print" aria-hidden="true"></i> Print
            </button>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="filter-row">
                    <div>
                        <label for="fromDate">From</label>
                        <input type="date" id="fromDate" value="<?= $default_from ?>" max="<?= $today ?>" aria-label="Report start date">
                    </div>
                    <div>
                        <label for="toDate">To</label>
                        <input type="date" id="toDate" value="<?= $default_to ?>" max="<?= $today ?>" aria-label="Report end date">
                    </div>
                    <div>
                        <label for="itemFilter">Item</label>
                        <select id="itemFilter" aria-label="Filter by item">
                            <option value="all">All Items</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" onclick="loadReport()" aria-label="Load report for selected date range">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i> Load Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary bar -->
        <div class="summary-bar" role="region" aria-label="Weekly summary totals" aria-live="polite">
            <div class="sum-item">
                <div class="sum-val" id="sumSales" style="color:var(--primary);">₹0.00</div>
                <div class="sum-lbl">Total Sales</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumExpense" style="color:#ef4444;">₹0.00</div>
                <div class="sum-lbl">Total Expenses</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumProfit">₹0.00</div>
                <div class="sum-lbl">Net Profit</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumCash" style="color:#3b82f6;">₹0.00</div>
                <div class="sum-lbl">Cash</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumGPay" style="color:#8b5cf6;">₹0.00</div>
                <div class="sum-lbl">GPay</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumSwiggy" style="color:#f97316;">₹0.00</div>
                <div class="sum-lbl">Swiggy</div>
            </div>
            <div class="sum-item">
                <div class="sum-val" id="sumZomato" style="color:#ef4444;">₹0.00</div>
                <div class="sum-lbl">Zomato</div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <canvas id="weekChart" height="120" role="img" aria-label="Weekly sales and expenses bar chart"></canvas>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-body">
                <div style="overflow-x:auto;">
                    <table class="report-table" id="reportTable" aria-label="Weekly detailed report">
                        <thead id="reportHead"></thead>
                        <tbody id="reportBody"></tbody>
                        <tfoot id="reportFoot"></tfoot>
                    </table>
                </div>
                <p id="noData" style="text-align:center;color:var(--text-muted);display:none;padding:20px;">No data found for selected range.</p>
            </div>
        </div>
    </div>
</main>

<script>
const fmt = n => '₹' + parseFloat(n || 0).toFixed(2);
let weekChart = null;

function loadReport() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    const item = document.getElementById('itemFilter').value;

    if (!from || !to || from > to) { showToast('Invalid date range.', 'error'); return; }

    fetch('../api/get_report.php?type=weekly&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to) + '&item=' + encodeURIComponent(item))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.message || 'Failed to load report.', 'error'); return; }
            renderReport(res);
        })
        .catch(() => showToast('Network error.', 'error'));
}

function renderReport(res) {
    const { dates, items, daily_cash, daily_gpay, swiggy, zomato } = res;
    const itemNames = Object.keys(items);

    // Totals
    let totalSales = 0, totalExp = 0, totalCash = 0, totalGPay = 0;
    dates.forEach(d => { totalCash += daily_cash[d] || 0; totalGPay += daily_gpay[d] || 0; });
    itemNames.forEach(name => {
        const cat = items[name].category;
        dates.forEach(d => {
            const v = items[name].days[d] || 0;
            if (cat === 'sales') totalSales += v;
            else totalExp += v;
        });
    });

    document.getElementById('sumSales').textContent   = fmt(totalSales);
    document.getElementById('sumExpense').textContent  = fmt(totalExp);
    const profit = totalSales - totalExp;
    document.getElementById('sumProfit').textContent   = fmt(profit);
    document.getElementById('sumProfit').style.color   = profit >= 0 ? 'var(--primary)' : '#ef4444';
    document.getElementById('sumCash').textContent     = fmt(totalCash);
    document.getElementById('sumGPay').textContent     = fmt(totalGPay);
    document.getElementById('sumSwiggy').textContent   = fmt(swiggy);
    document.getElementById('sumZomato').textContent   = fmt(zomato);

    // Chart
    const barCtx = document.getElementById('weekChart').getContext('2d');
    if (weekChart) weekChart.destroy();
    const dailySales = dates.map(d => {
        return itemNames.filter(n => items[n].category === 'sales').reduce((s, n) => s + (items[n].days[d] || 0), 0);
    });
    const dailyExp = dates.map(d => {
        return itemNames.filter(n => items[n].category === 'expense').reduce((s, n) => s + (items[n].days[d] || 0), 0);
    });
    weekChart = new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: dates.map(d => new Date(d).toLocaleDateString('en-IN',{weekday:'short',day:'numeric'})),
            datasets: [
                { label: 'Sales',    data: dailySales, backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 5 },
                { label: 'Expenses', data: dailyExp,   backgroundColor: 'rgba(239,68,68,.65)',  borderRadius: 5 }
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });

    // Table
    const thead = document.getElementById('reportHead');
    const tbody = document.getElementById('reportBody');
    const tfoot = document.getElementById('reportFoot');
    const noData = document.getElementById('noData');

    if (itemNames.length === 0) { thead.innerHTML = ''; tbody.innerHTML = ''; tfoot.innerHTML = ''; noData.style.display = ''; return; }
    noData.style.display = 'none';

    let headHtml = '<tr><th scope="col">Item</th><th scope="col">Category</th>';
    dates.forEach(d => headHtml += '<th scope="col">' + new Date(d).toLocaleDateString('en-IN',{day:'2-digit',month:'short'}) + '</th>');
    headHtml += '<th scope="col">Total</th></tr>';
    thead.innerHTML = headHtml;

    let bodyHtml = '';
    let colTotals = Array(dates.length).fill(0);
    let grandTotal = 0;
    itemNames.forEach(name => {
        const cat = items[name].category;
        bodyHtml += '<tr><td>' + escHtml(name) + '</td><td><span class="badge-cat badge-' + cat + '">' + cap(cat) + '</span></td>';
        let rowTotal = 0;
        dates.forEach((d, i) => {
            const v = items[name].days[d] || 0;
            rowTotal += v; colTotals[i] += v;
            bodyHtml += '<td>' + (v > 0 ? fmt(v) : '—') + '</td>';
        });
        grandTotal += rowTotal;
        bodyHtml += '<td><strong>' + fmt(rowTotal) + '</strong></td></tr>';
    });
    // Cash + GPay rows
    bodyHtml += '<tr style="background:var(--surface);"><td colspan="2"><strong>Cash Collected</strong></td>';
    let cashTotal = 0;
    dates.forEach(d => { cashTotal += daily_cash[d] || 0; bodyHtml += '<td>' + fmt(daily_cash[d] || 0) + '</td>'; });
    bodyHtml += '<td><strong>' + fmt(cashTotal) + '</strong></td></tr>';
    bodyHtml += '<tr style="background:var(--surface);"><td colspan="2"><strong>GPay Collected</strong></td>';
    let gpayTotal = 0;
    dates.forEach(d => { gpayTotal += daily_gpay[d] || 0; bodyHtml += '<td>' + fmt(daily_gpay[d] || 0) + '</td>'; });
    bodyHtml += '<td><strong>' + fmt(gpayTotal) + '</strong></td></tr>';
    tbody.innerHTML = bodyHtml;

    let footHtml = '<tr><td colspan="2"><strong>Daily Total</strong></td>';
    colTotals.forEach(v => footHtml += '<td><strong>' + fmt(v) + '</strong></td>');
    footHtml += '<td><strong>' + fmt(grandTotal) + '</strong></td></tr>';
    tfoot.innerHTML = footHtml;
}

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function cap(s)     { return s.charAt(0).toUpperCase() + s.slice(1); }
function printReport() { window.print(); }

// Load items for filter dropdown
fetch('../api/get_report.php?type=weekly&from=<?= $default_from ?>&to=<?= $default_to ?>')
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        const sel = document.getElementById('itemFilter');
        Object.keys(res.items).forEach(name => {
            const opt = document.createElement('option');
            opt.value = name; opt.textContent = name;
            sel.appendChild(opt);
        });
    }).catch(() => {});

document.addEventListener('DOMContentLoaded', loadReport);
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
