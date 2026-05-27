<?php
// config/constants.php

define('APP_NAME', 'Biryani Shop Manager');   // FIX B-001: Correct spelling
define('APP_VERSION', '2.0');
define('SESSION_LIFETIME', 28800);            // 8 hours
define('MAX_LOGIN_ATTEMPTS', 5);              // FIX S-007: Rate limiting
define('LOGIN_LOCKOUT_MINUTES', 15);
define('MIN_PASSWORD_LENGTH', 8);            // FIX F-004: Password policy
define('PERM_ALL', 'all');
define('PERM_SALES', 'sales');
define('PERM_EXPENSE', 'expense');

// Allowed redirect paths for branch switching (FIX S-002)
const ALLOWED_REDIRECT_PAGES = [
    'dashboard.php', 'daily_entry.php', 'weekly_report.php',
    'monthly_report.php', 'online_sales.php', 'settings.php'
];

const ROLES = [
    'owner'        => 'Owner',
    'branch_admin' => 'Branch Admin',
    'staff'        => 'Staff'
];
?>
