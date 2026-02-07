<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/navbar.php';
$is_logged_in = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrailForgeX - Explore New Trails</title>
    <link rel="stylesheet" href="master.css">
</head>
<body>
    <section class="hero animated-bg">
        <div class="hero-overlay"></div>
        <h1><span class="hero-icon">&#x1f3d4;&#xfe0f;</span> Discover &amp; Forge New Trails</h1>
        <p>Join TrailForgeX to explore, track, and share your trail adventures.</p>
        <a
            href="<?= $is_logged_in ? 'generate.php' : 'login_page.php' ?>"
            <?= $is_logged_in ? '' : 'onclick="document.getElementById(\'loginModal\').style.display=\'flex\';"' ?>
            class="cta-button"
            >
            <?= $is_logged_in ? 'Get Started' : 'Log in / Sign up' ?>
        </a>
    </section>

    <section class="hero-stats">
        <div class="stats-wrap">
            <div class="stat"><div class="stat-num">12k+</div><div class="stat-label">Routes generated</div></div>
            <div class="stat"><div class="stat-num">4.8★</div><div class="stat-label">Community rating</div></div>
            <div class="stat"><div class="stat-num">3 sec</div><div class="stat-label">Avg. generation time</div></div>
            <div class="stat"><div class="stat-num">100%</div><div class="stat-label">Free to start</div></div>
        </div>
    </section>

    <section class="features parallax-features">
        <h2>Why TrailForge?</h2>
        <div class="feature-cards">
            <div class="card"><span class="feature-icon">&#x1f4cd;</span>
                <h3>Track Your Trails</h3>
                <p>Record your hikes, runs, or rides with dynamic maps & real stats.</p>
            </div>
            <div class="card"><span class="feature-icon">&#x1f30f;</span>
                <h3>Discover New Paths</h3>
                <p>Find hidden & trending trails shared by the global community.</p>
            </div>
            <div class="card"><span class="feature-icon">&#x1f3c6;</span>
                <h3>Community Challenges</h3>
                <p>Compete, join events, rise on leaderboards & earn badges!</p>
            </div>
        </div>
    </section>

    <section class="how-it-works">
        <h2>How it works</h2>
        <div class="steps">
            <div class="step card">
                <div class="step-badge">1</div>
                    <h3>Choose a start or area</h3>
                    <p>Enter a location—or pick a whole city to generate loops.</p>
                </div>
            <div class="step card">
                <div class="step-badge">2</div>
                <h3>Set distance & preference</h3>
                <p>Parks, trails, or roads. Add optional elevation goals.</p>
            </div>
            <div class="step card">
                <div class="step-badge">3</div>
                <h3>Preview, save, share</h3>
                <p>Pick your favorite option, then favourite or publish it.</p>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <h2>User Stories</h2>
        <div class="testimonial-cards">
            <div class="testimonial-card">
                <p>“TrailForgeX made every weekend an adventure with friends!”</p>
                <span>- Alex P.</span>
            </div>
            <div class="testimonial-card">
                <p>“The challenges kept me motivated to break personal records!”</p>
                <span>- Jamie S.</span>
            </div>
            <div class="testimonial-card">
                <p>“By far the best UI to discover real hidden trails.”</p>
                <span>- Morgan W.</span>
            </div>
        </div>
    </section>
    <section class="news">
        <h2>Latest Updates</h2>
        <ul>
            <li class="news-item"><strong>Nov 1, 2025:</strong> TrailForge 2.0 Beta Released!</li>
            <li class="news-item"><strong>Oct 20, 2025:</strong> New community trail maps available.</li>
            <li class="news-item"><strong>Oct 5, 2025:</strong> Mobile app launch coming soon.</li>
        </ul>
    </section>
    <div id="loginModal" style="display:none;">
      <div class="login-modal-content">
        <button onclick="document.getElementById('loginModal').style.display='none';" style="position:absolute;top:1rem;right:1.3rem;font-size:1.45em;background:none;color:#aaa;border:none;cursor:pointer;">&times;</button>
        <h2>Log In</h2>
        <form id="loginForm">
          <input type="text" id="loginUsername" placeholder="Username" required>
          <input type="password" id="loginPassword" placeholder="Password" required>
          <button type="submit">Log In</button>
        </form>
        <div id="loginError"></div>
        <hr style="margin:1.5em 0; opacity:0.4">
        <h2 style="margin-bottom:.6em;">Sign Up</h2>
        <form id="registerForm">
          <input type="text" id="registerUsername" placeholder="Username" required>
          <input type="email" id="registerEmail" placeholder="Email" required>
          <input type="password" id="registerPassword" placeholder="Password" required>
          <button type="submit">Sign Up</button>
        </form>
        <div id="registerError"></div>
      </div>
    </div>

    <section class="cta-band">
        <div class="cta-band-inner">
            <div>
                <h2>Ready to forge your next trail?</h2>
                <p>Generate 3 options instantly and pick the best adventure.</p>
            </div>
            <div class="cta-actions">
                <a href="generate.php" class="cta-button">Generate a Route</a>
                <?php if (!$is_logged_in): ?>
                    <button class="btn-secondary" onclick="document.getElementById('loginModal').style.display='flex';">
                        Log in / Sign up
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    <script src="main.js"></script>
</body>
</html>
