<?php
// includes/nav.php — Shared navigation bar
// FIX S-003: Branch switching uses JS switchBranch() (safe redirect via server-side whitelist)
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['username'] ?? 'User';
?>
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-inner">
        <a class="nav-brand" href="../pages/dashboard.php" aria-label="<?= htmlspecialchars(APP_NAME) ?> home">
            <span aria-hidden="true">🍛</span> <?= htmlspecialchars(APP_NAME) ?>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="navMenu">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>

        <ul class="nav-menu" id="navMenu" role="menubar">
            <li role="none">
                <a href="../pages/dashboard.php" role="menuitem"
                   class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>"
                   <?= $current_page === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
                </a>
            </li>
            <li role="none">
                <a href="../pages/daily_entry.php" role="menuitem"
                   class="nav-link <?= $current_page === 'daily_entry.php' ? 'active' : '' ?>"
                   <?= $current_page === 'daily_entry.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i> Daily Entry
                </a>
            </li>
            <?php if (in_array($role, ['owner', 'branch_admin'])): ?>
            <li role="none">
                <a href="../pages/weekly_report.php" role="menuitem"
                   class="nav-link <?= $current_page === 'weekly_report.php' ? 'active' : '' ?>"
                   <?= $current_page === 'weekly_report.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-chart-bar" aria-hidden="true"></i> Weekly
                </a>
            </li>
            <li role="none">
                <a href="../pages/monthly_report.php" role="menuitem"
                   class="nav-link <?= $current_page === 'monthly_report.php' ? 'active' : '' ?>"
                   <?= $current_page === 'monthly_report.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-calendar-alt" aria-hidden="true"></i> Monthly
                </a>
            </li>
            <li role="none">
                <a href="../pages/online_sales.php" role="menuitem"
                   class="nav-link <?= $current_page === 'online_sales.php' ? 'active' : '' ?>"
                   <?= $current_page === 'online_sales.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-motorcycle" aria-hidden="true"></i> Online
                </a>
            </li>
            <li role="none">
                <a href="../pages/settings.php" role="menuitem"
                   class="nav-link <?= $current_page === 'settings.php' ? 'active' : '' ?>"
                   <?= $current_page === 'settings.php' ? 'aria-current="page"' : '' ?>>
                    <i class="fas fa-cog" aria-hidden="true"></i> Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="nav-user" aria-label="Logged in as <?= htmlspecialchars($user_name) ?>">
            <span class="user-badge" aria-hidden="true">
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user_name) ?>
            </span>
            <a href="../logout.php" class="btn-logout" aria-label="Sign out">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</nav>

<script>
// Mobile nav toggle
document.getElementById('navToggle').addEventListener('click', function() {
    const menu = document.getElementById('navMenu');
    const isOpen = menu.classList.toggle('open');
    this.setAttribute('aria-expanded', isOpen);
});

// FIX S-003: Safe branch switch — uses server-side whitelist redirect
function switchBranch(branchId) {
    const current = <?= json_encode(basename($_SERVER['PHP_SELF'])) ?>;
    fetch('../api/switch_branch.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ branch_id: branchId, redirect: current })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.redirect) {
            window.location.href = res.redirect;
        } else {
            showToast(res.message || 'Branch switch failed.', 'error');
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}
</script>
