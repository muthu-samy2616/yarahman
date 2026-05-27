<?php
// pages/online_sales.php — FIX S-001: CSRF header on all fetch POSTs
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();
check_role(['owner', 'branch_admin']);

$branch_name = $_SESSION['branch_name'];
$csrf        = csrf_token();
$today       = date('Y-m-d');
$first_month = date('Y-m-01');
$last_month  = date('Y-m-t');

// Monthly online sales summary
$stmtSum = $pdo->prepare("SELECT platform, SUM(amount) as total FROM online_sales WHERE branch_id = ? AND sale_date BETWEEN ? AND ? GROUP BY platform");
$stmtSum->execute([(int)$_SESSION['branch_id'], $first_month, $last_month]);
$monthly = ['Swiggy' => 0, 'Zomato' => 0];
while ($r = $stmtSum->fetch()) { $monthly[$r['platform']] = (float)$r['total']; }

// Recent 30 entries
$stmtRec = $pdo->prepare("SELECT id, sale_date, platform, amount FROM online_sales WHERE branch_id = ? ORDER BY sale_date DESC, id DESC LIMIT 60");
$stmtRec->execute([(int)$_SESSION['branch_id']]);
$recent = $stmtRec->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Sales – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .platform-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
        .platform-card  { border-radius:14px; padding:24px; display:flex; align-items:center; gap:16px; }
        .platform-card.swiggy { background:#fff4e6; border:1px solid #f97316; }
        .platform-card.zomato { background:#fff0f0; border:1px solid #ef4444; }
        .platform-icon { font-size:2rem; }
        .platform-val  { font-size:1.4rem; font-weight:700; }
        .platform-lbl  { font-size:.8rem; color:var(--text-muted); margin-top:2px; }
        .entry-form { display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:12px; align-items:flex-end; }
        @media(max-width:700px){ .entry-form{grid-template-columns:1fr 1fr;} }
        .entry-form label { font-size:.82rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px; }
        .entry-form input, .entry-form select { padding:9px 12px; border:1px solid var(--border); border-radius:8px; width:100%; box-sizing:border-box; font-size:.9rem; }
        .online-table { width:100%; border-collapse:collapse; font-size:.88rem; }
        .online-table th, .online-table td { padding:10px 14px; border-bottom:1px solid var(--border); text-align:left; }
        .online-table th { background:var(--surface); font-weight:600; font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); }
        .badge-swiggy { background:#ffedd5; color:#9a3412; padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
        .badge-zomato { background:#fee2e2; color:#991b1b; padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<main class="main-content" role="main">
    <div class="container">
        <?php include '../includes/branch_bar.php'; ?>

        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-motorcycle" aria-hidden="true"></i> Online Sales</h1>
                <p class="page-subtitle">Swiggy &amp; Zomato orders — <?= htmlspecialchars($branch_name) ?></p>
            </div>
        </div>

        <!-- Monthly Summary Cards -->
        <div class="platform-cards" role="region" aria-label="Monthly online sales summary">
            <div class="platform-card swiggy">
                <div class="platform-icon" aria-hidden="true">🛵</div>
                <div>
                    <div class="platform-val" id="swiggyTotal">₹<?= number_format($monthly['Swiggy'], 2) ?></div>
                    <div class="platform-lbl">Swiggy — This Month</div>
                </div>
            </div>
            <div class="platform-card zomato">
                <div class="platform-icon" aria-hidden="true">🍽️</div>
                <div>
                    <div class="platform-val" id="zomatoTotal">₹<?= number_format($monthly['Zomato'], 2) ?></div>
                    <div class="platform-lbl">Zomato — This Month</div>
                </div>
            </div>
            <div class="platform-card" style="background:var(--surface);border:1px solid var(--border);">
                <div class="platform-icon" aria-hidden="true">💰</div>
                <div>
                    <div class="platform-val" id="onlineTotal" style="color:var(--primary);">₹<?= number_format($monthly['Swiggy'] + $monthly['Zomato'], 2) ?></div>
                    <div class="platform-lbl">Total Online — This Month</div>
                </div>
            </div>
        </div>

        <!-- Add Entry Form -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body">
                <h2 style="margin:0 0 16px;font-size:1rem;"><i class="fas fa-plus-circle" aria-hidden="true"></i> Add Online Sale</h2>
                <div class="entry-form">
                    <div>
                        <label for="saleDate">Sale Date</label>
                        <input type="date" id="saleDate" value="<?= $today ?>" max="<?= $today ?>" aria-label="Sale date">
                    </div>
                    <div>
                        <label for="platform">Platform</label>
                        <select id="platform" aria-label="Delivery platform">
                            <option value="Swiggy">Swiggy</option>
                            <option value="Zomato">Zomato</option>
                        </select>
                    </div>
                    <div>
                        <label for="amount">Amount (₹)</label>
                        <input type="number" id="amount" min="0.01" step="0.01" placeholder="0.00" aria-label="Sale amount in rupees">
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="addSale()" style="width:100%;" aria-label="Save online sale">
                            <i class="fas fa-save" aria-hidden="true"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Entries -->
        <div class="card">
            <div class="card-body">
                <h2 style="margin:0 0 16px;font-size:1rem;"><i class="fas fa-history" aria-hidden="true"></i> Recent Entries</h2>
                <div style="overflow-x:auto;">
                    <table class="online-table" aria-label="Recent online sales">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Platform</th>
                                <th scope="col">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="salesList">
                            <?php if (empty($recent)): ?>
                            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px;">No entries yet.</td></tr>
                            <?php else: foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['sale_date']) ?></td>
                                <td>
                                    <span class="badge-<?= strtolower(htmlspecialchars($r['platform'])) ?>">
                                        <?= htmlspecialchars($r['platform']) ?>
                                    </span>
                                </td>
                                <td>₹<?= number_format((float)$r['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function addSale() {
    const date     = document.getElementById('saleDate').value;
    const platform = document.getElementById('platform').value;
    const amount   = parseFloat(document.getElementById('amount').value);
    const today    = new Date().toISOString().split('T')[0];

    if (!date || date > today) { showToast('Invalid date.', 'error'); return; }
    if (!platform) { showToast('Please select a platform.', 'error'); return; }
    if (!amount || amount <= 0) { showToast('Amount must be greater than 0.', 'error'); return; }

    fetch('../api/save_online.php', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN    // FIX S-001: Send CSRF header
        },
        body: JSON.stringify({ sale_date: date, platform: platform, amount: amount })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('Online sale saved!', 'success');
            document.getElementById('amount').value = '';
            // Add row to table
            const tbody = document.getElementById('salesList');
            const row   = document.createElement('tr');
            row.innerHTML = '<td>' + escHtml(date) + '</td>'
                + '<td><span class="badge-' + platform.toLowerCase() + '">' + escHtml(platform) + '</span></td>'
                + '<td>₹' + amount.toFixed(2) + '</td>';
            tbody.insertBefore(row, tbody.firstChild);
            // Update monthly totals client-side (approximate)
        } else {
            showToast(res.message || 'Failed to save.', 'error');
        }
    })
    .catch(() => showToast('Network error. Please try again.', 'error'));
}

function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
