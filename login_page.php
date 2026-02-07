<?php
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
  <title>Log In - TrailForgeX</title>
  <link rel="stylesheet" href="master.css" />
</head>
<body class="auth-page">
  <main class="auth-shell">
    <div class="auth-panel">
        <div class="auth-brand">
            <div class="auth-logo">TRAILFORGEX</div>
            <div class="auth-tag">Log in to unlock all features</div>
        </div>

        <a class="auth-oauth-btn facebook" href="/trailforgex/api/auth/facebook_start.php?next=<?= urlencode($next) ?>">
            Continue with Facebook
        </a>
        <a class="auth-oauth-btn google" href="/trailforgex/api/auth/google_start.php?next=<?= urlencode($next) ?>">
          <span class="oauth-icon">G</span>
          Continue with Google
        </a>

        <div class="auth-divider"><span>or</span></div>

        <div class="auth-card">
          <div class="auth-tabs">
            <a class="auth-tab is-active" href="login_page.php?next=<?= urlencode($next) ?>">Log in</a>
            <a class="auth-tab" href="signup_page.php?next=<?= urlencode($next) ?>">Sign up</a>
          </div>

        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-sub">Log in to generate routes, save favourites, and track runs.</p>

        <form id="loginForm" class="auth-form">
            <label class="auth-label">Username</label>
            <input type="text" id="loginUsername" class="auth-input" placeholder="e.g. kristian" autocomplete="username" required>

            <label class="auth-label">Password</label>
            <input type="password" id="loginPassword" class="auth-input" placeholder="••••••••" autocomplete="current-password" required>

            <button type="submit" class="auth-btn">Log In</button>
        </form>

        <div id="loginError" class="auth-error"></div>

        <div class="auth-footer">
            <span>New here?</span>
            <a class="auth-link" href="signup_page.php?next=<?= urlencode($next) ?>">Create an account</a>
        </div>
        </div>
    </div>
  </main>
  <script src="main.js"></script>
</body>
</html>
