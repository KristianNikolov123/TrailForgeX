<?php
$HIDE_NAVBAR = true;
require_once __DIR__ . '/includes/bootstrap.php';


if (!empty($_SESSION['user_id'])) {
  header('Location: generate.php');
  exit;
}

$next = $_GET['next'] ?? 'generate.php';
if (!preg_match('/^[a-zA-Z0-9_\-\/]+\.php$/', $next)) $next = 'generate.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up - TrailForgeX</title>
  <link rel="stylesheet" href="master.css" />
</head>
<body class="auth-page">
  <main class="auth-shell">
    <div class="auth-panel">
        <div class="auth-brand">
        <div class="auth-logo">TRAILFORGEX</div>
        <div class="auth-tag">Create your account</div>
        </div>
        <div class="auth-card">
          <div class="auth-tabs">
            <a class="auth-tab" href="login_page.php?next=<?= urlencode($next) ?>">Log in</a>
            <a class="auth-tab is-active" href="signup_page.php?next=<?= urlencode($next) ?>">Sign up</a>
          </div>

        <h1 class="auth-title">Join TrailForgeX</h1>
        <p class="auth-sub">Sign up to save routes, publish trails, and earn badges.</p>

        <form id="registerForm" class="auth-form">
            <label class="auth-label">Username</label>
            <input type="text" id="registerUsername" class="auth-input" placeholder="Choose a username" autocomplete="username" required>

            <label class="auth-label">Email</label>
            <input type="email" id="registerEmail" class="auth-input" placeholder="you@example.com" autocomplete="email" required>

            <label class="auth-label">Password</label>
            <input type="password" id="registerPassword" class="auth-input" placeholder="Create a password" autocomplete="new-password" required>

            <label class="auth-label">Confirm Password</label>
            <input type="password" id="registerConfirmPassword" class="auth-input" placeholder="Confirm your password" autocomplete="new-password" required>
            
            <div class="auth-oauth">
                <a class="auth-oauth-btn google" href="auth/google_start.php?next=<?= urlencode($next) ?>">
                    Continue with Google
                </a>
                <a class="auth-oauth-btn facebook" href="auth/facebook_start.php?next=<?= urlencode($next) ?>">
                    Continue with Facebook
                </a>

                <div class="auth-divider"><span>or</span></div>
            </div>

            <button type="submit" class="auth-btn">Sign Up</button>
        </form>

        <div id="registerError" class="auth-error"></div>

        <div class="auth-footer">
            <span>Already have an account?</span>
            <a class="auth-link" href="login_page.php?next=<?= urlencode($next) ?>">Log in</a>
        </div>
        </div>
    </div> 
  </main>
  <script src="main.js"></script>
</body>
</html>
