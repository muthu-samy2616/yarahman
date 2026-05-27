<?php
// pages/dashboard.php — FIX Q-002: SRI on Chart.js | FIX S-003: switchBranch safe redirect
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();
check_role(['owner', 'branch_admin']);

$branch_name = $_SESSION['branch_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- FIX Q-002: Chart.js with Subresource Integrity hash -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"
            integrity="sha256-wI1sbmfbCxRqA1eO6FsqYcZTl2NjCHFqI+oKz8YCiV8="
            crossorigin="anonymous"></script>
    <style>
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 20px 24px; display: flex; align-items: center; gap: 16px; }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .kpi-icon.green  { background: #d1fae5; color: #065f46; }
        .kpi-icon.red    { background: #fee2e2; color: #991b1b; }
        .kpi-icon.blue   { background: #dbeafe; color: #1e40af; }
        .kpi-icon.purple { background: #ede9fe; color: #5b21b6; }
        .kpi-icon.orange { background: #ffedd5; color: #9a3412; }
        .kpi-val  { font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1; }
        .kpi-lbl  { font-size: .78rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-val.skeleton { width: 100px; height: 1.5rem; background: var(--surface); border-radius: 6px; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0%,100%{opacity:.6} 50%{opacity:1} }
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        @media(max-width:900px) { .charts-grid { grid-template-columns: 1fr; } }
        .section-tabs { display: flex; gap: 4px; margin-bottom: 16px; }
        .tab-btn { padding: 6px 16px; border-radius: 20px; border: 1px solid var(--border); background: transparent; cursor: pointer; font-size: .85rem; color: var(--text-muted); }
        .tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<main class="main-content" role="main">
    <div class="container">
        <?php include '../includes/branch_bar.php'; ?>

        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard</h1>
                <p class="page-subtitle"><?= htmlspecialchars($branch_name) ?> — Live Business Overview</p>
            </div>
            <span id="lastUpdated" style="font-size:.8rem;color:var(--text-muted);"></span>
        </div>

        <!-- TODAY KPIs -->
        <h2 style="font-size:.95rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:0 0 12px;">Today</h2>
        <div class="kpi-grid" role="region" aria-label="Today's key performance indicators">
            <div class="kpi-card">
                <div class="kpi-icon green" aria-hidden="true"><i class="fas fa-rupee-sign"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-today-sales" aria-live="polite" aria-label="Today's sales">—</div>
                    <div class="kpi-lbl">Today's Sales</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon red" aria-hidden="true"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-today-exp" aria-live="polite" aria-label="Today's expenses">—</div>
                    <div class="kpi-lbl">Today's Expenses</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon blue" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-today-cash" aria-live="polite" aria-label="Cash collected today">—</div>
                    <div class="kpi-lbl">Cash Collected</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon purple" aria-hidden="true"><i class="fas fa-mobile-alt"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-today-gpay" aria-live="polite" aria-label="GPay collected today">—</div>
                    <div class="kpi-lbl">GPay Collected</div>
                </div>
            </div>
        </div>

        <!-- WEEK KPIs -->
        <h2 style="font-size:.95rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:0 0 12px;">This Week</h2>
        <div class="kpi-grid" role="region" aria-label="This week's key performance indicators">
            <div class="kpi-card">
                <div class="kpi-icon green" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-week-sales" aria-live="polite" aria-label="Weekly sales">—</div>
                    <div class="kpi-lbl">Weekly Sales</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon red" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-week-exp" aria-live="polite" aria-label="Weekly expenses">—</div>
                    <div class="kpi-lbl">Weekly Expenses</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon orange" aria-hidden="true"><i class="fab fa-swiggy" style="font-style:normal;font-weight:700;">S</i></div>
                <div>
                    <div class="kpi-val" id="kpi-week-swiggy" aria-live="polite" aria-label="Swiggy sales this week">—</div>
                    <div class="kpi-lbl">Swiggy (Week)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon red" aria-hidden="true"><i style="font-weight:700;font-style:normal;">Z</i></div>
                <div>
                    <div class="kpi-val" id="kpi-week-zomato" aria-live="polite" aria-label="Zomato sales this week">—</div>
                    <div class="kpi-lbl">Zomato (Week)</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon green" aria-hidden="true"><i class="fas fa-trophy"></i></div>
                <div>
                    <div class="kpi-val" id="kpi-month-profit" aria-live="polite" aria-label="Month net profit">—</div>
                    <div class="kpi-lbl">Month Net Profit</div>
                </div>
            </div>
        </div>

        <!-- CHARTS -->
        <div class="charts-grid">
            <div class="card">
                <div class="card-body">
                    <h3 style="margin:0 0 16px;font-size:1rem;">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i> Last 7 Days — Sales vs Expenses
                    </h3>
                    <canvas id="barChart" height="220" role="img" aria-label="Bar chart of last 7 days sales versus expenses"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3 style="margin:0 0 16px;font-size:1rem;">
                        <i class="fas fa-chart-pie" aria-hidden="true"></i> Monthly Payment Split
                    </h3>
                    <canvas id="pieChart" height="220" role="img" aria-label="Pie chart of monthly payment breakdown by method"></canvas>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
const fmt = n => '₹' + parseFloat(n || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

let barChart = null, pieChart = null;

function renderCharts(data) {
    // Bar chart — last 7 days
    const barCtx = document.getElementById('barChart').getContext('2d');
    if (barChart) barChart.destroy();
    barChart = new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: data.last7Days.labels,
            datasets: [
                { label: 'Sales',    data: data.last7Days.sales,    backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 6 },
                { label: 'Expenses', data: data.last7Days.expenses, backgroundColor: 'rgba(239,68,68,.65)',  borderRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } }
            }
        }
    });

    // Pie chart — payment split
    const pieCtx = document.getElementById('pieChart').getContext('2d');
    if (pieChart) pieChart.destroy();
    pieChart = new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: ['Cash', 'GPay/UPI', 'Online (Swiggy+Zomato)'],
            datasets: [{
                data: data.pieData,
                backgroundColor: ['#3b82f6','#8b5cf6','#f97316'],
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function loadDashboard() {
    fetch('../api/get_dashboard.php')
        .then(r => r.json())
        .then(res => {
            if (!res.success) { return; }
            const d = res.data;
            document.getElementById('kpi-today-sales').textContent   = fmt(d.todaySales);
            document.getElementById('kpi-today-exp').textContent      = fmt(d.todayExp);
            document.getElementById('kpi-today-cash').textContent     = fmt(d.todayCash);
            document.getElementById('kpi-today-gpay').textContent     = fmt(d.todayGPay);
            document.getElementById('kpi-week-sales').textContent     = fmt(d.weekSales);
            document.getElementById('kpi-week-exp').textContent       = fmt(d.weekExp);
            document.getElementById('kpi-week-swiggy').textContent    = fmt(d.weekSwiggy);
            document.getElementById('kpi-week-zomato').textContent    = fmt(d.weekZomato);
            document.getElementById('kpi-month-profit').textContent   = fmt(d.monthNetProfit);
            document.getElementById('kpi-month-profit').style.color   = d.monthNetProfit >= 0 ? 'var(--primary)' : '#ef4444';
            document.getElementById('lastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();
            renderCharts(d);
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', loadDashboard);
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
