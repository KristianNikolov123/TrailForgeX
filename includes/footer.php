<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$user = null;
if (!empty($_SESSION['user_id'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null
    ];
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = ($user !== null);
?>


<footer class="site-footer">
  <div class="footer-inner">

    <!-- Brand -->
    <div class="footer-col footer-brand">
      <div class="footer-logo">
        <span class="logo-icon">🏔️</span>
        <span class="logo-text">TrailForgeX</span>
      </div>
      <p class="footer-desc">
        Discover, generate, and share unique outdoor routes.
        Built for explorers.
      </p>
    </div>

    <!-- Navigation -->
    <div class="footer-col">
      <h4>Explore</h4>
      <ul>
        <li><a href="generate.php">Generate Route</a></li>
        <li><a href="#">Discover</a></li>
        <li><a href="#">Challenges</a></li>
        <li><a href="#">Community</a></li>
      </ul>
    </div>

    <!-- Account -->
    <div class="footer-col">
        <h4>Account</h4>
        <ul>
            <?php if (!$is_logged_in): ?>
                <li><a href="#" onclick="openLoginModal()">Log in</a></li>
                <li><a href="#" onclick="openLoginModal()">Sign up</a></li>
            <?php else: ?>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="favourites.php">Favourites</a></li>
                <li><a href="trails.php">My Routes</a></li>
                <li><a href="logout.php" style="color:#ff8aa1;">Log out</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Legal -->
    <div class="footer-col">
      <h4>Legal</h4>
      <ul>
        <li><a href="#">Privacy policy</a></li>
        <li><a href="#">Terms of service</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

  </div>

  <div class="footer-bottom">
    <span>© <?= date('Y') ?> TrailForgeX</span>

    <?php if ($is_logged_in): ?>
        <span class="footer-status">
        👤 Logged in as <?= htmlspecialchars($user['username'] ?? 'User') ?>
        </span>
    <?php else: ?>
        <span class="footer-status">🟢 All systems operational</span>
    <?php endif; ?>
  </div>

</footer>
