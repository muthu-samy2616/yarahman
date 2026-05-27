<?php
// index.php — FIX U-002: Remove non-functional "Remember Me"
//            FIX M-001: Fix viewport (remove user-scalable=no)
//            FIX P-003: Lazy-load background images via JS
require_once 'auth/session.php';
require_once 'config/constants.php';

// Already logged in → redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

// Map error codes from auth/login.php query string to messages
$error_map = [
    'empty'    => 'Please enter your username and password.',
    'locked'   => 'Too many failed attempts. Please wait 15 minutes and try again.',
    'inactive' => 'Your account has been deactivated. Contact your administrator.',
    'invalid'  => 'Invalid username or password.',
];
$error_code = $_GET['error'] ?? '';
$error = isset($error_map[$error_code]) ? $error_map[$error_code] : '';
$saved_username = htmlspecialchars($_GET['u'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- FIX M-001: Removed user-scalable=no to allow pinch-zoom on mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> — Login</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* FIX P-003: Background set via JS after load (lazy) to avoid blocking render */
        .bg-slider { position:fixed; inset:0; z-index:-1; overflow:hidden; background:#1a1a2e; }
        .bg-slide  { position:absolute; inset:0; background-size:cover; background-position:center;
                     opacity:0; transition:opacity 1.2s ease; }
        .bg-slide.active { opacity:1; }
        .login-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .login-card { background:rgba(255,255,255,.97); border-radius:20px; padding:40px 36px;
                      width:100%; max-width:420px; box-shadow:0 24px 60px rgba(0,0,0,.25); }
        .login-logo { text-align:center; margin-bottom:28px; }
        .login-logo .logo-icon { font-size:2.5rem; margin-bottom:8px; }
        .login-logo h1 { font-size:1.4rem; font-weight:700; color:#1f2937; margin:0; }
        .login-logo p  { font-size:.85rem; color:#6b7280; margin:4px 0 0; }
        .form-group { margin-bottom:18px; }
        .form-group label { font-size:.85rem; font-weight:600; color:#374151; display:block; margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-wrap .icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; pointer-events:none; }
        .input-wrap input { width:100%; padding:11px 12px 11px 38px; border:1.5px solid #e5e7eb;
                            border-radius:10px; font-size:.95rem; box-sizing:border-box;
                            transition:border-color .2s; }
        .input-wrap input:focus { outline:none; border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.15); }
        .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%);
                     background:none; border:none; cursor:pointer; color:#9ca3af; padding:4px; }
        .btn-login { width:100%; padding:12px; background:#10b981; color:#fff; border:none;
                     border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer;
                     transition:background .2s; margin-top:8px; }
        .btn-login:hover { background:#059669; }
        .btn-login:disabled { background:#9ca3af; cursor:not-allowed; }
        .alert-error { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b;
                       border-radius:10px; padding:12px 16px; font-size:.9rem; margin-bottom:16px; }
        .divider { text-align:center; margin:20px 0 14px; font-size:.82rem; color:#9ca3af; }
        .forgot-link { display:block; text-align:center; font-size:.85rem; color:#10b981;
                       text-decoration:none; margin-top:16px; }
        .forgot-link:hover { text-decoration:underline; }
        /* Overlay */
        .overlay-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center; }
        .overlay-modal.open { display:flex; }
        .overlay-box { background:#fff; border-radius:16px; padding:32px; max-width:380px; width:100%; }
        .overlay-box h3 { margin:0 0 12px; font-size:1.1rem; }
        .overlay-box p  { font-size:.9rem; color:#6b7280; margin:0 0 20px; }
        .overlay-box input { width:100%; padding:10px 12px; border:1.5px solid #e5e7eb; border-radius:8px; font-size:.9rem; box-sizing:border-box; margin-bottom:12px; }
        .overlay-actions { display:flex; gap:10px; }
    </style>
</head>
<body>

<!-- Background Slider (images set lazily after load) -->
<div class="bg-slider" aria-hidden="true">
    <div class="bg-slide" data-bg="assets/images/bg1.jpg"></div>
    <div class="bg-slide" data-bg="assets/images/bg2.jpg"></div>
    <div class="bg-slide" data-bg="assets/images/bg3.jpg"></div>
    <div class="bg-slide" data-bg="assets/images/bg4.jpg"></div>
</div>

<div class="login-wrapper">
    <div class="login-card" role="main">
        <div class="login-logo">
            <div class="logo-icon" aria-hidden="true">🍛</div>
            <h1><?= htmlspecialchars(APP_NAME) ?></h1>
            <p>Business Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="auth/login.php" novalidate id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <i class="fas fa-user icon" aria-hidden="true"></i>
                    <input type="text" id="username" name="username"
                           required autocomplete="username" spellcheck="false"
                           aria-label="Username"
                           placeholder="Enter your username"
                           value="<?= $saved_username ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock icon" aria-hidden="true"></i>
                    <input type="password" id="password" name="password"
                           required autocomplete="current-password"
                           aria-label="Password"
                           placeholder="Enter your password">
                    <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="pwIcon" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <!-- FIX U-002: Removed non-functional "Remember Me" checkbox -->

            <button type="submit" class="btn-login" id="loginBtn" aria-label="Sign in to your account">
                <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Sign In
            </button>
        </form>

        <a href="#" class="forgot-link" onclick="openForgot(event)" aria-label="Forgot password">
            Forgot password?
        </a>
    </div>
</div>

<!-- Forgot Password Info Modal -->
<div class="overlay-modal" id="forgotModal" role="dialog" aria-modal="true" aria-labelledby="forgotTitle">
    <div class="overlay-box">
        <h3 id="forgotTitle"><i class="fas fa-key" aria-hidden="true"></i> Forgot Password</h3>
        <p>Please contact your system administrator to reset your password.</p>
        <div class="overlay-actions">
            <button class="btn-login" onclick="closeForgot()" style="margin:0;">OK</button>
        </div>
    </div>
</div>

<script>
// FIX P-003: Lazy-load background images after page is interactive
(function() {
    const slides = document.querySelectorAll('.bg-slide');
    let current = 0;

    // Load first image immediately, rest after a tick
    function loadSlide(idx) {
        if (!slides[idx].style.backgroundImage) {
            slides[idx].style.backgroundImage = 'url(' + slides[idx].dataset.bg + ')';
        }
    }

    loadSlide(0);
    slides[0].classList.add('active');

    function rotate() {
        slides[current].classList.remove('active');
        current = (current + 1) % slides.length;
        loadSlide(current);
        slides[current].classList.add('active');
    }

    // Pre-load the remaining images after 1s
    setTimeout(function() {
        for (let i = 1; i < slides.length; i++) loadSlide(i);
        setInterval(rotate, 5000);
    }, 1000);
})();

function togglePw() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('pwIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function openForgot(e)  { e.preventDefault(); document.getElementById('forgotModal').classList.add('open'); }
function closeForgot()  { document.getElementById('forgotModal').classList.remove('open'); }

// Prevent double-submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-s