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
<html lang="en" data-theme="dark">
<head>
    <script>
    // Theme: apply saved choice before first paint so there's no flash
    (function () {
        var t = localStorage.getItem('cliniq-theme');
        if (!t) t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', t);
    })();
    function toggleTheme() {
        var el = document.documentElement;
        var next = el.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        el.setAttribute('data-theme', next);
        localStorage.setItem('cliniq-theme', next);
    }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ClinIQ Hospital OS</title>
    <link rel="stylesheet" href="css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-bg"></div>

    <div class="login-theme-toggle">
        <button class="icon-btn theme-toggle" onclick="toggleTheme()" title="Switch theme" aria-label="Switch between light and dark mode">
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>
    </div>

    <div class="login-card">
        <div class="login-logo">
            <div class="login-logo-icon" style="background:none;padding:0">
                <img src="assets/logo.png" alt="ClinIQ Logo" style="width:54px;height:54px;object-fit:cover;border-radius:50%;border:2px solid var(--accent-a50);">
            </div>
            <div>
                <div class="login-title">ClinIQ</div>
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
                       placeholder="Enter your email address"
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
    </div>
</div>
</body>
</html>