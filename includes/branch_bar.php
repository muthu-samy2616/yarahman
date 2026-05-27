<?php
// includes/branch_bar.php — Branch selector bar (owner only)
// FIX S-003: Uses switchBranch() JS function (defined in nav.php)
if ($_SESSION['role'] !== 'owner') return;

$stmtB = $pdo->prepare("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name");
$stmtB->execute();
$branches = $stmtB->fetchAll();

if (count($branches) <= 1) return;
?>
<div class="branch-bar" role="navigation" aria-label="Branch selector">
    <span style="font-size:.82rem;font-weight:600;color:var(--text-muted);">
        <i class="fas fa-store" aria-hidden="true"></i> Branch:
    </span>
    <?php foreach ($branches as $b): ?>
        <button
            onclick="switchBranch(<?= (int)$b['id'] ?>)"
            class="branch-btn <?= ((int)$b['id'] === (int)$_SESSION['branch_id']) ? 'active' : '' ?>"
            aria-label="Switch to <?= htmlspecialchars($b['branch_name']) ?> branch"
            <?= ((int)$b['id'] === (int)$_SESSION['branch_id']) ? 'aria-current="true"' : '' ?>>
            <?= htmlspecialchars($b['branch_name']) ?>
        </button>
    <?php endforeach; ?>
</div>
