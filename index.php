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
  <title>TrailForgeX — Forge Your Next Adventure</title>
  <link rel="stylesheet" href="master.css">
</head>
<body>

<!-- ═══════════════════════════ HERO ═══════════════════════════ -->
<section class="ix-hero">
  <div class="ix-hero-bg"></div>
  <div class="ix-hero-overlay"></div>

  <div class="ix-hero-inner">
    <div class="ix-eyebrow">🏔️ AI-powered trail generation</div>
    <h1 class="ix-hero-title">
      Forge Your<br><em>Next Adventure</em>
    </h1>
    <p class="ix-hero-sub">
      Tell us where and how far. TrailForgeX generates 3 personalised
      running routes — through parks, trails or city roads — in seconds.
    </p>
    <div class="ix-hero-ctas">
      <?php if ($is_logged_in): ?>
        <a href="generate.php" class="ix-btn-primary">Generate a Route</a>
        <a href="trails.php"   class="ix-btn-ghost">My Trails</a>
      <?php else: ?>
        <a href="signup_page.php" class="ix-btn-primary">Get Started Free</a>
        <a href="login_page.php" class="ix-btn-ghost"
                >Log in</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ix-scroll-hint">
    <div class="ix-scroll-line"></div>
    scroll
  </div>
</section>

<!-- ═══════════════════════════ STATS ═══════════════════════════ -->
<div class="ix-stats">
  <div class="ix-stats-inner">
    <div class="ix-stat">
      <div class="ix-stat-num">12k+</div>
      <div class="ix-stat-label">Routes generated</div>
    </div>
    <div class="ix-stat">
      <div class="ix-stat-num">4.8★</div>
      <div class="ix-stat-label">Community rating</div>
    </div>
    <div class="ix-stat">
      <div class="ix-stat-num">~3s</div>
      <div class="ix-stat-label">Generation time</div>
    </div>
    <div class="ix-stat">
      <div class="ix-stat-num">Free</div>
      <div class="ix-stat-label">To start</div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ FEATURES ═══════════════════════════ -->
<section class="ix-features">
  <p class="ix-section-label">What you get</p>
  <h2 class="ix-section-title">Everything a runner needs</h2>

  <div class="ix-cards">
    <div class="ix-card">
      <div class="ix-card-img" style="background-image:url('https://images.unsplash.com/photo-1502904550040-7534597429ae?auto=format&fit=crop&w=800&q=80');"></div>
      <div class="ix-card-overlay"></div>
      <div class="ix-card-body">
        <span class="ix-card-icon">📍</span>
        <div class="ix-card-title">Smart Route Generation</div>
        <div class="ix-card-text">3 distinct options tailored to your distance, terrain preference and elevation goals.</div>
      </div>
    </div>
    <div class="ix-card">
      <div class="ix-card-img" style="background-image:url('https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=800&q=80');"></div>
      <div class="ix-card-overlay"></div>
      <div class="ix-card-body">
        <span class="ix-card-icon">🗺️</span>
        <div class="ix-card-title">Live GPS Tracking</div>
        <div class="ix-card-text">Follow your planned route in real time. Distance, pace and completion detection built in.</div>
      </div>
    </div>
    <div class="ix-card">
      <div class="ix-card-img" style="background-image:url('https://images.unsplash.com/photo-1521326369027-18f91c0a5e15?auto=format&fit=crop&w=800&q=80');"></div>
      <div class="ix-card-overlay"></div>
      <div class="ix-card-body">
        <span class="ix-card-icon">🏆</span>
        <div class="ix-card-title">Leaderboards & Badges</div>
        <div class="ix-card-text">Compete with the community, earn achievements and climb the weekly rankings.</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ HOW IT WORKS ═══════════════════════════ -->
<section class="ix-how">
  <p class="ix-section-label">Simple by design</p>
  <h2 class="ix-section-title">Three steps to your route</h2>

  <div class="ix-steps">
    <div class="ix-step">
      <div class="ix-step-num">1</div>
      <div class="ix-step-title">Choose a start or area</div>
      <p class="ix-step-text">Enter an address, drop a pin, or name an entire city to generate loops anywhere inside it.</p>
    </div>
    <div class="ix-step">
      <div class="ix-step-num">2</div>
      <div class="ix-step-title">Set distance & style</div>
      <p class="ix-step-text">Pick green parks, dirt trails or paved roads. Add an optional elevation target for extra challenge.</p>
    </div>
    <div class="ix-step">
      <div class="ix-step-num">3</div>
      <div class="ix-step-title">Pick, run, share</div>
      <p class="ix-step-text">Preview all 3 options on the map, select your favourite, then save, publish or lace up and go.</p>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ SHOWCASE ═══════════════════════════ -->
<div class="ix-showcase">
  <div class="ix-showcase-bg"></div>
  <div class="ix-showcase-overlay"></div>
  <div class="ix-showcase-inner">
    <div class="ix-showcase-kicker">Built for the outdoors</div>
    <h2 class="ix-showcase-title">Every run,<br>a new story</h2>
    <p class="ix-showcase-text">
      Whether you're a weekend jogger exploring your city or a trail runner
      chasing elevation, TrailForgeX finds paths worth running.
    </p>
    <?php if (!$is_logged_in): ?>
    <a href="signup_page.php" class="ix-btn-primary" style="width:fit-content;"
            >
      Create free account
    </a>
    <?php else: ?>
    <a href="generate.php" class="ix-btn-primary" style="width:fit-content; display:inline-block;">
      Generate a route
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════ TESTIMONIALS ═══════════════════════════ -->
<section class="ix-testimonials">
  <p class="ix-section-label">Community</p>
  <h2 class="ix-section-title">Runners love it</h2>

  <div class="ix-quotes">
    <div class="ix-quote">
      <div class="ix-quote-stars">★★★★★</div>
      <p class="ix-quote-text">"TrailForgeX made every weekend an adventure. I've discovered streets in my own city I never knew existed."</p>
      <div class="ix-quote-author">— Alex P.</div>
    </div>
    <div class="ix-quote">
      <div class="ix-quote-stars">★★★★★</div>
      <p class="ix-quote-text">"The challenges kept me motivated to push my personal records. The badge system is addictive!"</p>
      <div class="ix-quote-author">— Jamie S.</div>
    </div>
    <div class="ix-quote">
      <div class="ix-quote-stars">★★★★★</div>
      <p class="ix-quote-text">"Far and away the best UI for discovering real hidden trails. Clean, fast, and it actually works."</p>
      <div class="ix-quote-author">— Morgan W.</div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ CTA ═══════════════════════════ -->
<section class="ix-cta">
  <div class="ix-cta-bg"></div>
  <div class="ix-cta-inner">
    <h2 class="ix-cta-title">Ready to forge<br>your trail?</h2>
    <p class="ix-cta-sub">Generate 3 personalised route options in seconds — free, forever.</p>
    <div class="ix-hero-ctas">
      <?php if ($is_logged_in): ?>
        <a href="generate.php" class="ix-btn-primary">Generate now</a>
      <?php else: ?>
        <a href="signup_page.php" class="ix-btn-primary">Start for free</a>
        <a class="ix-btn-ghost"
                href="login_page.php">Log in</a>
      <?php endif; ?>
    </div>
  </div>
</section>



<?php include 'includes/footer.php'; ?>
<script src="main.js"></script>
</body>
</html>