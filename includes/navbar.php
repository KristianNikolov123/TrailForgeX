<?php
require_once __DIR__ . '/bootstrap.php';

$is_logged_in = !empty($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);

$profile_img = 'assets/default-avatar.png';

if ($is_logged_in) {
  require_once __DIR__ . '/dbconn.php';

  $uid = (int)$_SESSION['user_id'];
  $stmt = $connection->prepare("SELECT profile_image FROM users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $stmt->bind_result($dbImg);
  $stmt->fetch();
  $stmt->close();

  if (!empty($dbImg)) $profile_img = $dbImg;
}

?>

<header class="app-header">
  <div class="branding">
    <a href="index.php" class="branding-link">
      <img src="TrailForgeX-logo.png" class="branding-logo" alt="">
      <img src="TrailForgeX-text.png" class="branding-text" alt="">
    </a>
  </div>

  <nav class="nav-bar">
    <?php if ($is_logged_in): ?>

      <a href="index.php" class="<?= $current_page=='index.php' ? 'active' : '' ?>">Home</a>
      <a href="trails.php" class="<?= $current_page=='trails.php' ? 'active' : '' ?>">Trails</a>
      <a href="generate.php" class="<?= $current_page=='generate.php' ? 'active' : '' ?>">Generate</a>

      <a href="record.php" class="nav-record <?= $current_page=='record.php' ? 'active' : '' ?>">Record</a>

      <a href="profile.php" class="nav-avatar" title="Profile">
        <img src="<?= htmlspecialchars($profile_img) ?>" alt="Profile">
      </a>

      <div class="nav-menu">
        <button type="button" class="menu-btn" aria-label="Open menu" aria-expanded="false">☰</button>

        <div class="menu-dropdown" aria-label="Menu">
          <a href="achievements.php">🏅 Achievements</a>
          <a href="leaderboard.php">🏆 Leaderboard</a>
          <hr> 
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>

    <?php else: ?>
      <span class="nav-hint">Log in to unlock all features</span>
      <a href="#" id="nav-login-btn">Log In</a>
    <?php endif; ?>
  </nav>
</header>
