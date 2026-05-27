<?php
// pages/settings.php — FIX S-001: CSRF | FIX F-004: Password strength
require_once '../auth/session.php';
require_once '../config/db.php';
require_once '../config/constants.php';
check_auth();
check_role(['owner', 'branch_admin']);

$branch_id   = (int)$_SESSION['branch_id'];
$branch_name = $_SESSION['branch_name'];
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // FIX S-001: Verify CSRF token on all POST actions
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $msg = 'Security token invalid. Please refresh the page.'; $msg_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_rates') {
            if (isset($_POST['delete_item_id'])) {
                $del_id = (int)$_POST['delete_item_id'];
                $pdo->prepare('DELETE FROM item_rates WHERE id = ? AND branch_id = ?')->execute([$del_id, $branch_id]);
                $msg = 'Item deleted successfully.';
            } else {
                foreach ($_POST['rates'] as $id => $rate) {
                    $pdo->prepare('UPDATE item_rates SET rate = ? WHERE id = ? AND branch_id = ?')
                        ->execute([(float)$rate, (int)$id, $branch_id]);
                }
                $msg = 'Item rates updated successfully.';
            }
        }

        elseif ($action === 'add_item') {
            $item_name = trim($_POST['item_name'] ?? '');
            $category  = in_array($_POST['category'] ?? '', ['sales','expense']) ? $_POST['category'] : 'sales';
            $rate      = (float)($_POST['rate'] ?? 0);
            if (!empty($item_name)) {
                try {
                    $ns = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM item_rates WHERE branch_id=?');
                    $ns->execute([$branch_id]); $next_sort = $ns->fetchColumn();
                    $pdo->prepare('INSERT INTO item_rates (branch_id,item_name,rate,category,sort_order) VALUES (?,?,?,?,?)')
                        ->execute([$branch_id, $item_name, $rate, $category, $next_sort]);
                    $msg = 'Item added successfully.';
                } catch (\PDOException $e) { $msg = 'Error: Item already exists.'; $msg_type = 'error'; }
            } else { $msg = 'Item name cannot be empty.'; $msg_type = 'error'; }
        }

        elseif ($action === 'add_user') {
            $name     = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $pass     = $_POST['password'] ?? '';
            $role     = in_array($_POST['role'] ?? '', ['staff','branch_admin']) ? $_POST['role'] : 'staff';
            $allowed  = in_array($_POST['allowed_categories'] ?? '', [PERM_ALL,PERM_SALES,PERM_EXPENSE]) ? $_POST['allowed_categories'] : PERM_ALL;

            // FIX F-004: Password minimum length
            if (strlen($pass) < MIN_PASSWORD_LENGTH) {
                $msg = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.'; $msg_type = 'error';
            } elseif (empty($name) || empty($username)) {
                $msg = 'Name and username are required.'; $msg_type = 'error';
            } else {
                try {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $pdo->prepare('INSERT INTO users (branch_id,name,username,password,role,allowed_categories) VALUES (?,?,?,?,?,?)')
                        ->execute([$branch_id, $name, $username, $hash, $role, $allowed]);
                    $msg = 'User added successfully.';
                } catch (\PDOException $e) { $msg = 'Error: Username might already exist.'; $msg_type = 'error'; }
            }
        }

        elseif ($action === 'update_user_permission') {
            $uid     = (int)$_POST['user_id'];
            $allowed = in_array($_POST['allowed_categories'] ?? '', [PERM_ALL,PERM_SALES,PERM_EXPENSE]) ? $_POST['allowed_categories'] : PERM_ALL;
            $pdo->prepare('UPDATE users SET allowed_categories=? WHERE id=? AND branch_id=?')->execute([$allowed, $uid, $branch_id]);
            if ($uid == $_SESSION['user_id']) $_SESSION['allowed_categories'] = $allowed;
            $msg = 'User permissions updated.';
        }

        elseif ($action === 'toggle_user') {
            $uid    = (int)$_POST['user_id'];
            $status = (int)$_POST['status'] == 1 ? 0 : 1;
            if ($uid != $_SESSION['user_id']) {
                $pdo->prepare('UPDATE users SET is_active=? WHERE id=? AND branch_id=?')->execute([$status, $uid, $branch_id]);
                $msg = 'User status updated.';
            } else { $msg = 'Cannot deactivate your own account.'; $msg_type = 'error'; }
        }

        elseif ($action === 'add_branch' && $_SESSION['role'] === 'owner') {
            $bname = trim($_POST['branch_name'] ?? '');
            $loc   = trim($_POST['location'] ?? '');
            if (!empty($bname)) {
                $pdo->prepare('INSERT INTO branches (name, location) VALUES (?, ?)')->execute([$bname, $loc]);
                $new_b_id = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO item_rates (branch_id, item_name, rate, category, sort_order) SELECT ?, item_name, rate, category, sort_order FROM item_rates WHERE branch_id = ?')
                    ->execute([$new_b_id, $branch_id]);
                if (isset($_SESSION['admin_branches'])) {
                    $_SESSION['admin_branches'][] = ['id' => $new_b_id, 'name' => $bname];
                }
                $msg = 'Branch added successfully.';
            } else { $msg = 'Branch name cannot be empty.'; $msg_type = 'error'; }
        }

        elseif ($action === 'delete_user') {
            $uid = (int)$_POST['user_id'];
            if ($uid != $_SESSION['user_id']) {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE daily_entries SET entered_by=NULL WHERE entered_by=?')->execute([$uid]);
                    $pdo->prepare('UPDATE daily_payments SET entered_by=NULL WHERE entered_by=?')->execute([$uid]);
                    $pdo->prepare('UPDATE online_sales SET entered_by=NULL WHERE entered_by=?')->execute([$uid]);
                    $pdo->prepare('DELETE FROM users WHERE id=? AND branch_id=?')->execute([$uid, $branch_id]);
                    $pdo->commit();
                    $msg = 'User deleted. History preserved.';
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('delete_user error: ' . $e->getMessage());
                    $msg = 'Error deleting user.'; $msg_type = 'error';
                }
            } else { $msg = 'Cannot delete your own account.'; $msg_type = 'error'; }
        }

        elseif ($action === 'delete_branch' && $_SESSION['role'] === 'owner') {
            $del_id = (int)$_POST['branch_id'];
            $count  = (int)$pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn();
            if ($count <= 1) {
                $msg = 'Cannot delete the only existing branch.'; $msg_type = 'error';
            } else {
                try {
                    $pdo->beginTransaction();
                    foreach (['daily_entries','daily_payments','online_sales','item_rates','users'] as $tbl) {
                        $pdo->prepare("DELETE FROM {$tbl} WHERE branch_id=?")->execute([$del_id]);
                    }
                    $pdo->prepare('DELETE FROM branches WHERE id=?')->execute([$del_id]);
                    $pdo->commit();
                    if (isset($_SESSION['admin_branches'])) {
                        $_SESSION['admin_branches'] = array_values(array_filter($_SESSION['admin_branches'], function($b) use ($del_id) { return $b['id'] != $del_id; }));
                    }
                    if ($_SESSION['branch_id'] == $del_id && !empty($_SESSION['admin_branches'])) {
                        $_SESSION['branch_id']   = $_SESSION['admin_branches'][0]['id'];
                        $_SESSION['branch_name'] = $_SESSION['admin_branches'][0]['name'];
                    }
                    $msg = 'Branch deleted successfully.';
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('delete_branch error: ' . $e->getMessage());
                    $msg = 'Error deleting branch.'; $msg_type = 'error';
                }
            }
        }
    }
}

$stmtI = $pdo->prepare('SELECT * FROM item_rates WHERE branch_id=? ORDER BY sort_order');
$stmtI->execute([$branch_id]); $items = $stmtI->fetchAll();

$stmtU = $pdo->prepare('SELECT * FROM users WHERE branch_id=?');
$stmtU->execute([$branch_id]); $users = $stmtU->fetchAll();

$branches = [];
if ($_SESSION['role'] === 'owner') {
    $branches = $pdo->query('SELECT * FROM branches')->fetchAll();
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="../assets/img/favicon.svg">
    <style>
        .tabs { display:flex; background:#f1f5f9; padding:5px; border-radius:12px; margin-bottom:25px; gap:5px; }
        .tab-btn { flex:1; padding:12px; background:transparent; border:none; border-radius:8px; cursor:pointer; font-weight:600; color:#64748b; font-size:14px; transition:all 0.2s ease; }
        .tab-btn:hover { color:var(--primary-color); }
        .tab-btn.active { background:#fff; color:var(--primary-color); box-shadow:0 2px 4px rgba(0,0,0,0.05); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; animation:fadeIn 0.3s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:translateY(0)} }
        .pw-strength { height:4px; border-radius:2px; margin-top:4px; transition:all 0.3s; }
    </style>
</head>
<body>
<header class="top-header">
    <div style="display:flex;align-items:center;gap:20px;">
        <div>
            <h1>Settings</h1>
            <?= render_branch_selector() ?>
        </div>
        <nav class="desktop-nav">
            <a href="daily_entry.php" class="desktop-nav-link">Entry</a>
            <a href="dashboard.php" class="desktop-nav-link">Dashboard</a>
            <a href="weekly_report.php" class="desktop-nav-link">Reports</a>
            <a href="settings.php" class="desktop-nav-link active" aria-current="page">Settings</a>
        </nav>
    </div>
    <div style="display:flex;align-items:center;gap:15px;">
        <a href="dashboard.php" class="logout-btn">Back</a>
        <a href="../logout.php" class="logout-btn" aria-label="Logout">Logout</a>
    </div>
</header>

<div class="container">
    <?php if ($msg): ?>
    <div class="toast <?= $msg_type === 'error' ? 'error' : 'success' ?> show" style="position:relative;margin-bottom:15px;opacity:1;" role="alert">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="tabs" role="tablist">
        <button class="tab-btn active" onclick="switchTab(this,'tab1')" role="tab" aria-selected="true" aria-controls="tab1">Item Rates</button>
        <button class="tab-btn" onclick="switchTab(this,'tab2')" role="tab" aria-selected="false" aria-controls="tab2">Staff</button>
        <?php if ($_SESSION['role'] === 'owner'): ?>
        <button class="tab-btn" onclick="switchTab(this,'tab3')" role="tab" aria-selected="false" aria-controls="tab3">Branches</button>
        <?php endif; ?>
    </div>

    <!-- Tab 1: Item Rates -->
    <div id="tab1" class="tab-pane active" role="tabpanel">
        <div class="card mb-2">
            <h3 class="mb-1">Add New Item</h3>
            <form method="POST" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div style="flex:2;min-width:200px;">
                    <label style="font-size:12px;" for="new_item_name">Item Name</label>
                    <input type="text" id="new_item_name" name="item_name" required placeholder="e.g. Chicken Lollipop" style="margin-top:5px;">
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="font-size:12px;" for="new_cat">Category</label>
                    <select id="new_cat" name="category" style="margin-top:5px;">
                        <option value="sales">Sales (Income)</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div style="flex:1;min-width:100px;">
                    <label style="font-size:12px;" for="new_rate">Default Rate (₹)</label>
                    <input type="number" id="new_rate" step="any" name="rate" value="0.00" style="margin-top:5px;">
                </div>
                <div><button type="submit" class="btn-primary" style="height:44px;min-width:100px;">Add Item</button></div>
            </form>
        </div>

        <div class="card">
            <form method="POST">
                <input type="hidden" name="action" value="save_rates">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="table-responsive">
                    <table aria-label="Item rates">
                        <thead><tr><th>Item Name</th><th>Category</th><th style="width:120px;">Rate (₹)</th><th style="width:100px;">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['item_name']) ?></td>
                            <td><?= ucfirst($it['category']) ?></td>
                            <td><input type="number" step="any" name="rates[<?= (int)$it['id'] ?>]" value="<?= (float)$it['rate'] ?>" style="margin:0;padding:5px;" aria-label="Rate for <?= htmlspecialchars($it['item_name']) ?>"></td>
                            <td>
                                <button type="submit" name="delete_item_id" value="<?= (int)$it['id'] ?>" class="btn-outline" style="padding:4px 8px;font-size:12px;min-height:auto;border-color:var(--danger-color);color:var(--danger-color);"
                                    onclick="return confirm('Delete this item? Historical data will remain intact.')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn-success" style="width:100%;margin-top:10px;">Save Rates</button>
            </form>
        </div>
    </div>

    <!-- Tab 2: Staff -->
    <div id="tab2" class="tab-pane" role="tabpanel">
        <div class="card mb-2">
            <h3 class="mb-1">Add New User</h3>
            <form method="POST" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;" onsubmit="return validatePassword(this)">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div style="flex:1;min-width:140px;">
                    <label style="font-size:12px;" for="u_name">Full Name</label>
                    <input type="text" id="u_name" name="name" required style="margin-top:5px;">
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="font-size:12px;" for="u_uname">Username</label>
                    <input type="text" id="u_uname" name="username" required autocomplete="off" style="margin-top:5px;">
                </div>
                <div style="flex:1;min-width:140px;">
                    <label style="font-size:12px;" for="u_pass">Password (min <?= MIN_PASSWORD_LENGTH ?> chars)</label>
                    <input type="password" id="u_pass" name="password" required minlength="<?= MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" style="margin-top:5px;" oninput="showPwStrength(this)">
                    <div id="pw-strength-bar" class="pw-strength" style="background:#eee;"></div>
                </div>
                <div style="flex:1;min-width:100px;">
                    <label style="font-size:12px;" for="u_role">Role</label>
                    <select id="u_role" name="role" style="margin-top:5px;">
                        <option value="staff">Staff</option>
                        <option value="branch_admin">Branch Admin</option>
                    </select>
                </div>
                <div style="flex:1;min-width:140px;">
                    <label style="font-size:12px;" for="u_cats">Category Access</label>
                    <select id="u_cats" name="allowed_categories" style="margin-top:5px;">
                        <option value="all">All Categories</option>
                        <option value="sales">Sales Only</option>
                        <option value="expense">Expense Only</option>
                    </select>
                </div>
                <div style="width:100%;"><button type="submit" class="btn-primary" style="width:100%;">Add User</button></div>
            </form>
        </div>

        <div class="card">
            <h3 class="mb-1">Existing Users</h3>
            <div class="table-responsive">
                <table aria-label="Staff list">
                    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Access</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= ucfirst($u['role']) ?></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="update_user_permission">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <select name="allowed_categories" onchange="this.form.submit()" style="padding:4px 8px;font-size:12px;margin:0;width:130px;height:32px;border-radius:6px;background:#fff;border:1px solid var(--border-color);" aria-label="Category access for <?= htmlspecialchars($u['name']) ?>">
                                    <option value="all" <?= ($u['allowed_categories']??'all')==='all'?'selected':'' ?>>All</option>
                                    <option value="sales" <?= ($u['allowed_categories']??'all')==='sales'?'selected':'' ?>>Sales Only</option>
                                    <option value="expense" <?= ($u['allowed_categories']??'all')==='expense'?'selected':'' ?>>Expense Only</option>
                                </select>
                            </form>
                            <?php else: ?><span style="font-size:12px;color:#888;">All (Self)</span><?php endif; ?>
                        </td>
                        <td><span style="color:<?= $u['is_active']?'green':'red' ?>;"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="status" value="<?= (int)$u['is_active'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button class="btn-outline" style="padding:4px 8px;font-size:12px;min-height:auto;"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user? Their historical entry data will be preserved.')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button class="btn-outline" style="padding:4px 8px;font-size:12px;min-height:auto;border-color:var(--danger-color);color:var(--danger-color);margin-left:5px;">Delete</button>
                            </form>
                            <?php else: ?><span style="font-size:12px;color:#888;">(Current)</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Branches -->
    <?php if ($_SESSION['role'] === 'owner'): ?>
    <div id="tab3" class="tab-pane" role="tabpanel">
        <div class="card mb-2">
            <h3 class="mb-1">Add Branch</h3>
            <form method="POST" style="display:flex;gap:10px;align-items:flex-end;">
                <input type="hidden" name="action" value="add_branch">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div style="flex:1;"><label style="font-size:12px;" for="b_name">Branch Name</label><input type="text" id="b_name" name="branch_name" required style="margin-top:5px;"></div>
                <div style="flex:1;"><label style="font-size:12px;" for="b_loc">Location</label><input type="text" id="b_loc" name="location" style="margin-top:5px;"></div>
                <div><button type="submit" class="btn-primary">Add</button></div>
            </form>
        </div>
        <div class="card">
            <h3 class="mb-1">Existing Branches</h3>
            <div class="table-responsive">
                <table aria-label="Branches">
                    <thead><tr><th>ID</th><th>Name</th><th>Location</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?= (int)$b['id'] ?></td>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['location'] ?? '') ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will permanently delete ALL sales, expenses, users, and data for this branch. Type DELETE to confirm.\n\nAre you absolutely sure?')">
                                <input type="hidden" name="action" value="delete_branch">
                                <input type="hidden" name="branch_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button class="btn-outline" style="padding:4px 8px;font-size:12px;min-height:auto;border-color:var(--danger-color);color:var(--danger-color);">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<nav class="bottom-nav" aria-label="Main navigation">
    <a href="daily_entry.php" class="nav-item"><span class="nav-icon" aria-hidden="true">📝</span> Entry</a>
    <a href="dashboard.php" class="nav-item"><span class="nav-icon" aria-hidden="true">📊</span> Dash</a>
    <a href="weekly_report.php" class="nav-item"><span class="nav-icon" aria-hidden="true">📅</span> Reports</a>
    <a href="settings.php" class="nav-item active" aria-current="page"><span class="nav-icon" aria-hidden="true">⚙️</span> Settings</a>
</nav>

<script src="../assets/js/app.js"></script>
<script>
function switchTab(btn, tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active'); btn.setAttribute('aria-selected','true');
    document.getElementById(tabId).classList.add('active');
}
function validatePassword(form) {
    const pw = form.querySelector('[name="password"]');
    if (pw && pw.value.length < <?= MIN_PASSWORD_LENGTH ?>) {
        alert('Password must be at least <?= MIN_PASSWORD_LENGTH ?> characters long.');
        pw.focus(); return false;
    }
    return true;
}
function showPwStrength(input) {
    const bar = document.getElementById('pw-strength-bar');
    if (!bar) return;
    const v = input.value; let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const colours = ['#eee','#FB3640','#E67E22','#F39C12','#1E7B4B','#1E7B4B'];
    bar.style.background = colours[score];
    bar.style.width = (score * 20) + '%';
}
// Branch selector navigation
function switchBranch(id) {
    window.location.href = '../api/switch_branch.php?id=' + id + '&redirect=settings.php';
}
</script>
</body>
</html>
