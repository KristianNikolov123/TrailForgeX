<?php
require_once __DIR__ . '/bootstrap.php';

// Auth state
$is_logged_in = !empty($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
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
      <?php if ($is_logged_in): ?>
        <li><a href="index.php" class="<?= $current_page=='index.php' ? 'highlighted-nav' : '' ?>">Home</a></li>
        <li><a href="trails.php" class="<?= $current_page=='trails.php' ? 'highlighted-nav' : '' ?>">Trails</a></li>
        <li><a href="generate.php" class="<?= $current_page=='generate.php' ? 'highlighted-nav' : '' ?>">Generate Route</a></li>
        <li><a href="leaderboard.php" class="<?= $current_page=='leaderboard.php' ? 'highlighted-nav' : '' ?>">Leaderboard</a></li>
        <li><a href="achievements.php" class="<?= $current_page=='achievements.php' ? 'highlighted-nav' : '' ?>">Achievements</a></li>
        <li><a href="profile.php" class="<?= $current_page=='profile.php' ? 'highlighted-nav' : '' ?>">Profile</a></li>
        <li><a href="logout.php" class="<?= $current_page=='logout.php' ? 'highlighted-nav' : '' ?>">Logout</a></li>
      <?php else: ?>
        <span class="nav-hint">Log in to unlock all features</span>
        <li><a href="#" id="nav-login-btn" class="<?= $current_page=='login.php' ? 'highlighted-nav' : '' ?>">Log In</a></li>
      <?php endif; ?>
    </ul>
  </nav>  
</header>