<?php
// pages/daily_entry.php
// FIX S-001: CSRF on add_item form | FIX F-003: Date validation | FIX S-005: Generic errors
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();

$branch_id   = (int)$_SESSION['branch_id'];
$branch_name = $_SESSION['branch_name'];
$user_role   = $_SESSION['role'];
$csrf        = csrf_token();
$msg = ''; $msg_type = 'success';

// Handle add_item POST (owner/branch_admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    check_role(['owner', 'branch_admin']);

    // FIX S-001: Verify CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $msg = 'Security token invalid. Please refresh the page.';
        $msg_type = 'error';
    } else {
        $item_name = trim(htmlspecialchars(strip_tags($_POST['item_name'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $category  = $_POST['category'] ?? '';
        $rate      = (float)($_POST['rate'] ?? 0);
        if (!in_array($category, ['sales', 'expense'])) {
            $msg = 'Invalid category.'; $msg_type = 'error';
        } elseif ($item_name === '') {
            $msg = 'Item name is required.'; $msg_type = 'error';
        } elseif ($rate < 0) {
            $msg = 'Rate cannot be negative.'; $msg_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO item_rates (branch_id, item_name, category, rate, sort_order) VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM item_rates ir2 WHERE ir2.branch_id=?))");
                $stmt->execute([$branch_id, $item_name, $category, $rate, $branch_id]);
                $msg = 'Item added successfully.';
            } catch (\PDOException $e) {
                error_log('add_item error: ' . $e->getMessage());
                $msg = 'Failed to add item. Please try again.'; $msg_type = 'error';
            }
        }
    }
}

// Fetch items
$stmtItems = $pdo->prepare("SELECT id, item_name, category, rate FROM item_rates WHERE branch_id = ? ORDER BY sort_order, item_name");
$stmtItems->execute([$branch_id]);
$all_items = $stmtItems->fetchAll();
// PHP 7.2 compatible closures (no arrow functions)
$sales_items   = array_filter($all_items, function($i) { return $i['category'] === 'sales'; });
$expense_items = array_filter($all_items, function($i) { return $i['category'] === 'expense'; });

// Date limits
$today     = date('Y-m-d');
$min_date  = ($user_role === 'staff') ? $today : date('Y-m-d', strtotime('-90 days'));
$max_date  = $today;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Entry – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .entry-table { width: 100%; border-collapse: collapse; }
        .entry-table th, .entry-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; }
        .entry-table th { background: var(--surface); font-weight: 600; font-size: .85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
        .entry-table tbody tr:hover { background: var(--surface); }
        .qty-input, .amount-input { width: 90px; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px; font-size: .9rem; text-align: right; }
        .amount-input { background: var(--surface); color: var(--text-muted); }
        .payment-row { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; }
        .payment-row label { font-size: .85rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px; }
        .payment-row input { padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; width: 160px; font-size: .9rem; }
        .total-bar { display: flex; gap: 24px; flex-wrap: wrap; padding: 14px 0; border-top: 2px solid var(--border); margin-top: 8px; }
        .total-bar .t-item { text-align: center; }
        .total-bar .t-item .t-val { font-size: 1.3rem; font-weight: 700; color: var(--primary); }
        .total-bar .t-item .t-lbl { font-size: .75rem; color: var(--text-muted); margin-top: 2px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .badge-cat { padding: 2px 10px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .badge-sales { background: #d1fae5; color: #065f46; }
        .badge-expense { background: #fee2e2; color: #991b1b; }
        .add-item-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
        .add-item-modal.open { display:flex; }
        .modal-box { background: var(--bg); border-radius: 16px; padding: 28px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
        .modal-box h3 { margin: 0 0 20px; }
        .modal-box label { font-size:.85rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px; margin-top:12px; }
        .modal-box input, .modal-box select { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:.9rem; box-sizing:border-box; }
        .modal-actions { display:flex; gap:12px; margin-top:20px; }
    </style>
</head>
<body>
<?php include '../includes/nav.php'; ?>

<main class="main-content" role="main">
    <div class="container">
        <?php include '../includes/branch_bar.php'; ?>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'danger' ?>" role="alert">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Daily Entry</h1>
                <p class="page-subtitle">Record daily sales and expenses for <?= htmlspecialchars($branch_name) ?></p>
            </div>
            <?php if (in_array($user_role, ['owner','branch_admin'])): ?>
                <button class="btn btn-secondary" onclick="openAddItem()" aria-label="Add new item">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add Item
                </button>
            <?php endif; ?>
        </div>

        <!-- Date Picker -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <label for="entryDate" style="font-weight:600;margin:0;">
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i> Entry Date:
                </label>
                <input type="date" id="entryDate"
                       value="<?= $today ?>"
                       min="<?= $min_date ?>"
                       max="<?= $max_date ?>"
                       class="form-control"
                       style="width:180px;"
                       aria-label="Select entry date"
                       onchange="loadEntries(this.value)">
                <span id="dateStatus" style="font-size:.85rem;color:var(--text-muted);"></span>
            </div>
        </div>

        <!-- SALES TABLE -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="section-header">
                    <h2 style="margin:0;font-size:1.1rem;"><i class="fas fa-shopping-cart" aria-hidden="true"></i> Sales Items</h2>
                    <span class="badge-cat badge-sales">Sales</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="entry-table" aria-label="Sales entry table">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Rate (₹)</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody id="salesBody">
                            <?php foreach ($sales_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td class="rate-cell" data-rate="<?= (float)$item['rate'] ?>">
                                    ₹<?= number_format((float)$item['rate'], 2) ?>
                                </td>
                                <td>
                                    <input type="number" class="qty-input"
                                           data-item="<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"
                                           data-category="sales"
                                           min="0" step="0.5" value="0"
                                           aria-label="Quantity for <?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"
                                           oninput="calcRow(this)">
                                </td>
                                <td>
                                    <input type="text" class="amount-input" readonly
                                           aria-label="Amount for <?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- EXPENSE TABLE -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div class="section-header">
                    <h2 style="margin:0;font-size:1.1rem;"><i class="fas fa-receipt" aria-hidden="true"></i> Expense Items</h2>
                    <span class="badge-cat badge-expense">Expense</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="entry-table" aria-label="Expense entry table">
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Rate (₹)</th>
                                <th scope="col">Qty</th>
                                <th scope="col">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody id="expenseBody">
                            <?php foreach ($expense_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td class="rate-cell" data-rate="<?= (float)$item['rate'] ?>">
                                    ₹<?= number_format((float)$item['rate'], 2) ?>
                                </td>
                                <td>
                                    <input type="number" class="qty-input"
                                           data-item="<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"
                                           data-category="expense"
                                           min="0" step="0.5" value="0"
                                           aria-label="Quantity for <?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"
                                           oninput="calcRow(this)">
                                </td>
                                <td>
                                    <input type="text" class="amount-input" readonly
                                           aria-label="Amount for <?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PAYMENT + TOTALS -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="margin:0 0 16px;font-size:1.1rem;"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Payment Received</h2>
                <div class="payment-row">
                    <div>
                        <label for="cashAmount">Cash (₹)</label>
                        <input type="number" id="cashAmount" min="0" step="0.01" value="0"
                               aria-label="Cash amount received" oninput="updateTotals()">
                    </div>
                    <div>
                        <label for="gpayAmount">GPay / UPI (₹)</label>
                        <input type="number" id="gpayAmount" min="0" step="0.01" value="0"
                               aria-label="GPay amount received" oninput="updateTotals()">
                    </div>
                </div>
                <div class="total-bar" role="status" aria-live="polite" aria-label="Daily totals">
                    <div class="t-item">
                        <div class="t-val" id="totalSales">₹0.00</div>
                        <div class="t-lbl">Total Sales</div>
                    </div>
                    <div class="t-item">
                        <div class="t-val" id="totalExpense" style="color:#ef4444;">₹0.00</div>
                        <div class="t-lbl">Total Expense</div>
                    </div>
                    <div class="t-item">
                        <div class="t-val" id="netProfit">₹0.00</div>
                        <div class="t-lbl">Net Profit</div>
                    </div>
                    <div class="t-item">
                        <div class="t-val" id="totalPayment" style="color:#8b5cf6;">₹0.00</div>
                        <div class="t-lbl">Payment Collected</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align:right;margin-bottom:40px;">
            <button class="btn btn-primary" id="saveBtn" onclick="saveEntries()" aria-label="Save daily entries">
                <i class="fas fa-save" aria-hidden="true"></i> Save Entries
            </button>
        </div>
    </div>
</main>

<!-- Add Item Modal (owner/branch_admin only) -->
<?php if (in_array($user_role, ['owner','branch_admin'])): ?>
<div class="add-item-modal" id="addItemModal" role="dialog" aria-modal="true" aria-labelledby="addItemTitle">
    <div class="modal-box">
        <h3 id="addItemTitle">Add New Item</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <label for="newItemName">Item Name</label>
            <input type="text" id="newItemName" name="item_name" required
                   maxlength="100" placeholder="e.g. Chicken Biryani"
                   aria-label="New item name">

            <label for="newCategory">Category</label>
            <select id="newCategory" name="category" aria-label="Item category">
                <option value="sales">Sales</option>
                <option value="expense">Expense</option>
            </select>

            <label for="newRate">Default Rate (₹)</label>
            <input type="number" id="newRate" name="rate" min="0" step="0.01" value="0"
                   aria-label="Default rate">

            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Add Item</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddItem()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

// ── Row calculation ──────────────────────────────────────
function calcRow(qtyInput) {
    const row  = qtyInput.closest('tr');
    const rate = parseFloat(row.querySelector('.rate-cell').dataset.rate) || 0;
    const qty  = parseFloat(qtyInput.value) || 0;
    const amt  = rate * qty;
    row.querySelector('.amount-input').value = amt.toFixed(2);
    updateTotals();
}

function updateTotals() {
    let sales = 0, expense = 0;
    document.querySelectorAll('#salesBody .qty-input').forEach(inp => {
        const row  = inp.closest('tr');
        const rate = parseFloat(row.querySelector('.rate-cell').dataset.rate) || 0;
        sales += (parseFloat(inp.value) || 0) * rate;
    });
    document.querySelectorAll('#expenseBody .qty-input').forEach(inp => {
        const row  = inp.closest('tr');
        const rate = parseFloat(row.querySelector('.rate-cell').dataset.rate) || 0;
        expense += (parseFloat(inp.value) || 0) * rate;
    });
    const cash  = parseFloat(document.getElementById('cashAmount').value) || 0;
    const gpay  = parseFloat(document.getElementById('gpayAmount').value) || 0;
    const net   = sales - expense;
    document.getElementById('totalSales').textContent   = '₹' + sales.toFixed(2);
    document.getElementById('totalExpense').textContent = '₹' + expense.toFixed(2);
    document.getElementById('netProfit').textContent    = '₹' + net.toFixed(2);
    document.getElementById('netProfit').style.color    = net >= 0 ? 'var(--primary)' : '#ef4444';
    document.getElementById('totalPayment').textContent = '₹' + (cash + gpay).toFixed(2);
}

// ── Load existing entries for selected date ──────────────
function loadEntries(date) {
    // Validate date client-side
    const dateEl = document.getElementById('entryDate');
    const today  = new Date().toISOString().split('T')[0];
    const role   = <?= json_encode($user_role) ?>;
    if (role === 'staff' && date !== today) {
        showToast('Staff can only enter today\'s data.', 'error');
        dateEl.value = today;
        date = today;
    }
    if (date > today) {
        showToast('Cannot enter data for a future date.', 'error');
        dateEl.value = today;
        return;
    }

    document.getElementById('dateStatus').textContent = 'Loading…';
    fetch('../api/get_entry.php?date=' + encodeURIComponent(date) + '&branch_id=' + <?= $branch_id ?>)
        .then(r => r.json())
        .then(res => {
            document.getElementById('dateStatus').textContent = '';
            if (!res.success) { showToast(res.message || 'Failed to load entries.', 'error'); return; }
            // Populate quantities
            document.querySelectorAll('.qty-input').forEach(inp => {
                const name = inp.dataset.item;
                if (res.entries && res.entries[name]) {
                    inp.value = res.entries[name].qty || 0;
                } else {
                    inp.value = 0;
                }
                calcRow(inp);
            });
            document.getElementById('cashAmount').value = res.cash || 0;
            document.getElementById('gpayAmount').value = res.gpay || 0;
            updateTotals();
        })
        .catch(() => { document.getElementById('dateStatus').textContent = ''; showToast('Failed to load entries.', 'error'); });
}

// ── Save entries ─────────────────────────────────────────
function saveEntries() {
    const date    = document.getElementById('entryDate').value;
    const today   = new Date().toISOString().split('T')[0];
    const role    = <?= json_encode($user_role) ?>;

    // FIX F-003: Client-side date guard
    if (!date || date > today) { showToast('Invalid date selected.', 'error'); return; }
    if (role === 'staff' && date !== today) { showToast('Staff can only save today\'s data.', 'error'); return; }

    const entries = [];
    document.querySelectorAll('.qty-input').forEach(inp => {
        const row  = inp.closest('tr');
        const rate = parseFloat(row.querySelector('.rate-cell').dataset.rate) || 0;
        const qty  = parseFloat(inp.value) || 0;
        if (qty > 0) {
            entries.push({
                item_name: inp.dataset.item,
                qty:       qty,
                unit_price: rate,
                amount:    qty * rate
            });
        }
    });

    const payload = {
        entry_date:   date,
        cash_amount:  parseFloat(document.getElementById('cashAmount').value) || 0,
        gpay_amount:  parseFloat(document.getElementById('gpayAmount').value) || 0,
        entries:      entries
    };

    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    fetch('../api/save_entry.php', {
        method:  'POST',
        headers: {
            'Content-Type':  'application/json',
            'X-CSRF-TOKEN':  CSRF_TOKEN    // FIX S-001: Send CSRF header
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Entries';
        if (res.success) {
            showToast('Entries saved successfully!', 'success');
        } else {
            showToast(res.message || 'Failed to save.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Entries';
        showToast('Network error. Please try again.', 'error');
    });
}

// ── Add Item Modal ───────────────────────────────────────
function openAddItem()  { document.getElementById('addItemModal').classList.add('open');    }
function closeAddItem() { document.getElementById('addItemModal').classList.remove('open'); }

// Close modal on backdrop click
document.getElementById('addItemModal') && document.getElementById('addItemModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddItem();
});

// ── On page load, fetch today's data ────────────────────
document.addEventListener('DOMContentLoaded', function() {
    loadEntries(document.getElementById('entryDate').