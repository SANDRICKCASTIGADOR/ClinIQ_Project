<?php
// ==============================================
// Hospital TMS - Login Page
// ==============================================
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) redirectByRole();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email && $password) {
        $result = login($email, $password);
        if ($result['success']) {
            redirectByRole();
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — MediTrack Hospital OS</title>
    <link rel="stylesheet" href="css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-bg"></div>

    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon" style="background:none;padding:0">
                <img src="assets/logo.png" alt="MediTrack Logo" style="width:54px;height:54px;object-fit:cover;border-radius:50%;filter:drop-shadow(0 0 8px rgba(45,212,191,0.5));border:2px solid rgba(45,212,191,0.35);">
            </div>
            <div>
                <div class="login-title">MediTrack</div>
                <div class="login-sub">Hospital OS</div>
            </div>
        </div>

        <h2 class="login-heading">Welcome back</h2>
        <p class="login-desc">Sign in to access your dashboard</p>

        <?php if ($error): ?>
        <div class="error-msg"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="error-msg">You don't have permission to access that page.</div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@hospital.com"
                       value="<?= sanitize($_POST['email'] ?? '') ?>"
                       required autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           placeholder="••••••••" required autocomplete="current-password"
                           style="padding-right:48px;">
                    <button type="button"
                        onclick="
                            const input = document.getElementById('passwordInput');
                            const eyeOpen = document.getElementById('eyeOpen');
                            const eyeClosed = document.getElementById('eyeClosed');
                            if (input.type === 'password') {
                                input.type = 'text';
                                eyeOpen.style.display = 'none';
                                eyeClosed.style.display = 'block';
                            } else {
                                input.type = 'password';
                                eyeOpen.style.display = 'block';
                                eyeClosed.style.display = 'none';
                            }
                        "
                        style="position:absolute;top:50%;right:14px;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;padding:0;
                               color:#6b7280;display:flex;align-items:center;justify-content:center;"
                        aria-label="Toggle password visibility">
                        <!-- Eye Open (password hidden) -->
                        <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             style="display:block;">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <!-- Eye Closed (password visible) -->
                        <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                                     a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                                     a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:12px;">
                Sign In
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <div class="login-hint">
            <p><strong>Demo Accounts:</strong><br>
            Main Admin: <strong>admin@hospital.com</strong> / Admin@123<br>
            Dr. Rivera: <strong>dr.rivera@hospital.com</strong> / Doctor1@123<br>
            Dr. Chen: <strong>dr.chen@hospital.com</strong> / Doctor2@123<br>
        </div>
    </div>
</div>
</body>
</html>