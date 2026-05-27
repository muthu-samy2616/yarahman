<?php
// pages/monthly_report.php
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();
check_role(['owner', 'branch_admin']);

$branch_name   = $_SESSION['branch_name'];
$current_month = date('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"
            integrity="sha256-wI1sbmfbCxRqA1eO6FsqYcZTl2NjCHFqI+oKz8YCiV8="
            crossorigin="anonymous"></script>
    <style>
        .report-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .report-table th, .report-table td { padding:8px 10px; border:1px solid var(--border); text-align:right; white-space:nowrap; }
        .report-table th { background:var(--surface); font-weight:600; text-align:center; }
        .report-table td:first-child { text-align:left; position:sticky; left:0; background:var(--bg); z-index:1; }
        .report-table tfoot td { font-weight:700; background:var(--surface); }
        .month-filter { display:flex; gap:12px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; }
        .month-filter label { font-size:.82rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px; }
        .month-filter input { padding:8px 10px; border:1px solid var(--border); border-radius:8px; font-size:.88rem; }
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
                <h1 class="page-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Monthly Report</h1>
                <p class="page-subtitle"><?= htmlspecialchars($branch_name) ?></p>
            </div>
            <button class="btn btn-secondary" onclick="window.print()" aria-label="Print monthly report">
                <i class="fas fa-print" aria-hidden="true"></i> Print
            </button>
        </div>

        <!-- Month Picker -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="month-filter">
                    <div>
                        <label for="monthPicker">Select Month</label>
                        <input type="month" id="monthPicker" value="<?= $current_month ?>" max="<?= $current_month ?>" aria-label="Select month for report">
                    </div>
                    <button class="btn btn-primary" onclick="loadReport()" aria-label="Load monthly report">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i> Load Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-bar" role="region" aria-label="Monthly summary totals" aria-live="polite">
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

        <!-- Trend Chart -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <canvas id="monthChart" height="100" role="img" aria-label="Monthly daily trend chart"></canvas>
            </div>
        </div>

        <!-- Scrollable table -->
        <div class="card">
            <div class="card-body" style="overflow-x:auto;">
                <table class="report-table" id="reportTable" aria-label="Monthly detailed report">
                    <thead id="reportHead"></thead>
                    <tbody id="reportBody"></tbody>
                    <tfoot id="reportFoot"></tfoot>
                </table>
                <p id="noData" style="text-align:center;color:var(--text-muted);display:none;padding:20px;">No data found for selected month.</p>
            </div>
        </div>
    </div>
</main>

<script>
const fmt = n => '₹' + parseFloat(n || 0).toFixed(2);
let monthChart = null;

function loadReport() {
    const month = document.getElementById('monthPicker').value;
    if (!month) { showToast('Please select a month.', 'error'); return; }

    fetch('../api/get_report.php?type=monthly&month=' + encodeURIComponent(month))
        .then(r => r.json())
        .then(res => {
            if (!res.success) { showToast(res.message || 'Failed to load report.', 'error'); return; }
            renderReport(res);
        })
        .catch(() => showToast('Network error.', 'error'));
}

function renderReport(res) {
    const { days, items, data, swiggy, zomato } = res;
    const itemNames = items.map(i => i.item_name);

    let totalSales = 0, totalExp = 0, totalCash = 0, totalGPay = 0;

    days.forEach(d => {
        totalCash += data[d].cash || 0;
        totalGPay += data[d].gpay || 0;
        itemNames.forEach((name, idx) => {
            const v = data[d].items[name] || 0;
            if (items[idx].category === 'sales') totalSales += v;
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

    // Trend chart
    const ctx = document.getElementById('monthChart').getContext('2d');
    if (monthChart) monthChart.destroy();
    monthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days.map(d => new Date(d).getDate()),
            datasets: [
                {
                    label: 'Daily Sales',
                    data: days.map(d => itemNames.filter((n,i) => items[i].category==='sales').reduce((s,n) => s+(data[d].items[n]||0),0)),
                    borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', tension: .35, fill: true, borderWidth: 2
                },
                {
                    label: 'Daily Expenses',
                    data: days.map(d => itemNames.filter((n,i) => items[i].category==='expense').reduce((s,n) => s+(data[d].items[n]||0),0)),
                    borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', tension: .35, fill: true, borderWidth: 2
                }
            ]
        },
        options: { responsive: true, plugins: { legend: { position:'top' } }, scales: { y: { beginAtZero:true } } }
    });

    // Build table — rows = items, cols = days
    const thead = document.getElementById('reportHead');
    const tbody = document.getElementById('reportBody');
    const tfoot = document.getElementById('reportFoot');
    const noData = document.getElementById('noData');

    if (itemNames.length === 0) { thead.innerHTML=''; tbody.innerHTML=''; tfoot.innerHTML=''; noData.style.display=''; return; }
    noData.style.display = 'none';

    let headHtml = '<tr><th scope="col" style="text-align:left;">Item</th>';
    days.forEach(d => headHtml += '<th scope="col">' + new Date(d).getDate() + '</th>');
    headHtml += '<th scope="col">Total</th></tr>';
    thead.innerHTML = headHtml;

    let bodyHtml = '';
    let colTotals = Array(days.length).fill(0);
    let grandTotal = 0;
    items.forEach(it => {
        bodyHtml += '<tr><td>' + escHtml(it.item_name) + '</td>';
        let rowTotal = 0;
        days.forEach((d, i) => {
            const v = data[d].items[it.item_name] || 0;
            rowTotal += v; colTotals[i] += v;
            bodyHtml += '<td>' + (v > 0 ? fmt(v) : '—') + '</td>';
        });
        grandTotal += rowTotal;
        bodyHtml += '<td><strong>' + fmt(rowTotal) + '</strong></td></tr>';
    });
    // Cash & GPay rows
    bodyHtml += '<tr style="background:var(--surface);"><td><strong>Cash</strong></td>';
    let cTotal = 0;
    days.forEach(d => { const v = data[d].cash||0; cTotal+=v; bodyHtml+='<td>'+fmt(v)+'</td>'; });
    bodyHtml += '<td><strong>'+fmt(cTotal)+'</strong></td></tr>';
    bodyHtml += '<tr style="background:var(--surface);"><td><strong>GPay</strong></td>';
    let gTotal = 0;
    days.forEach(d => { const v = data[d].gpay||0; gTotal+=v; bodyHtml+='<td>'+fmt(v)+'</td>'; });
    bodyHtml += '<td><strong>'+fmt(gTotal)+'</strong></td></tr>';
    tbody.innerHTML = bodyHtml;

    let footHtml = '<tr><td><strong>Day Total</strong></td>';
    colTotals.forEach(v => footHtml += '<td><strong>' + fmt(v) + '</strong></td>');
    footHtml += '<td><strong>' + fmt(grandTotal) + '</strong></td></tr>';
    tfoot.innerHTML = footHtml;
}

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.addEventListener('DOMContentLoaded', loadReport);
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
