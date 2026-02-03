<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(401);

  $page = $AUTH_PAGE_NAME ?? 'this page';
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Login required · TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
  </head>
  <body class="auth-page">
    <div class="auth-card">
      <h1 class="auth-title">Login required</h1>
      <p class="auth-text">
        You must be logged in to view <?= htmlspecialchars($page) ?>.
      </p>
      <div class="auth-actions">
        <a href="/TrailForgeX/index.php" class="btn btn-primary">Go back</a>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}
