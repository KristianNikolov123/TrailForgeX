<?php
// home.php
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
    <header>
        <div class="branding">
            <img src="TrailForgeX-logo.png" alt="TrailForgeX Icon" class="branding-logo">
            <img src="TrailForgeX-text.png" alt="TrailForgeX Text" class="branding-text">
        </div>
        <nav>
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="#">Trails</a></li>
                <li><a href="generate.php" class="highlighted-nav">Generate Route</a></li>
                <li><a href="#">Community</a></li>
                <li><a href="#">About</a></li>
            </ul>
        </nav>
    </header>
    <section class="hero animated-bg">
        <div class="hero-overlay"></div>
        <h1><span class="hero-icon">&#x1f3d4;&#xfe0f;</span> Discover &amp; Forge New Trails</h1>
        <p>Join TrailForgeX to explore, track, and share your trail adventures.</p>
        <a href="#" class="cta-button">Get Started</a>
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
    <footer>
        <p>&copy; 2025 TrailForgeX. All rights reserved.</p>
    </footer>
    <script src="main.js"></script>
</body>
</html>
