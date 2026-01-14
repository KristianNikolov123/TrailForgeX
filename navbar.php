<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Optionally, pull user info and current page for logic
$user = isset($_SESSION['user_id']) ? ['id' => $_SESSION['user_id']] : null;
$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = !!$user;
?>
<header>
  <div class="branding">
    <a href="index.php" style="display:flex;align-items:center;text-decoration:none;">
      <img src="TrailForgeX-logo.png" alt="TrailForgeX Icon" class="branding-logo" style="height:45px;">
      <img src="TrailForgeX-text.png" alt="TrailForgeX Text" class="branding-text" style="height:45px;">
    </a>
  </div>
  <nav>
    <ul>
      <li><a href="index.php" class="<?= $current_page=='index.php' ? 'highlighted-nav' : '' ?>">Home</a></li>
      <li><a href="trails.php" class="<?= $current_page=='trails.php' ? 'highlighted-nav' : '' ?>">Trails</a></li>
      <li><a href="generate.php" class="<?= $current_page=='generate.php' ? 'highlighted-nav' : '' ?>">Generate Route</a></li>
      <?php if ($is_logged_in): ?>
        <li><a href="profile.php" class="<?= $current_page=='profile.php' ? 'highlighted-nav' : '' ?>">Profile</a></li>
        <li><a href="logout.php">Logout</a></li>
      <?php else: ?>
        <li><a href="#" id="nav-login-btn">Log In</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>