<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Route | TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
</head>
<body>
    <header>
        <div class="logo"><img src="TrailForgeX_logo-removebg-preview.png" alt="TrailForgeX Logo" style="height:64px"></div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#">Trails</a></li>
                <li><a href="generate.php" class="highlighted-nav">Generate Route</a></li>
                <li><a href="#">Community</a></li>
                <li><a href="#">About</a></li>
            </ul>
        </nav>
    </header>
    <section class="generate-hero" style="background: linear-gradient(120deg, #24141b 60%, #593247 100%), url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat; position: relative; text-align:center; min-height:290px; display:flex; align-items:center; justify-content:center;">
        <div class="generate-hero-overlay" style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(32,13,25,0.78);"></div>
        <div style="position:relative;z-index:2;max-width:600px;width:100%;padding:2.4rem 1rem;">
            <h1 style="color:#fff;font-size:2.6rem;text-shadow:0 2px 18px #200e19;">Generate Your Next Adventure Route</h1>
            <p style="color:#d9a7c8;font-size:1.23rem;margin:1rem auto .2rem auto;">Let TrailForgeX surprise you with unique paths.</p>
        </div>
    </section>
    <section class="generate-route-section" style="margin-top:-40px;">
        <div class="gen-container" style="display:flex;gap:30px;align-items:center;justify-content:center;flex-wrap:wrap;background: #2e1a25;">
            <img src="https://images.unsplash.com/photo-1519864600265-abb23847ef2c?auto=format&fit=crop&w=500&q=80" alt="Runner on mountain trail" style="width:230px;border-radius:18px;box-shadow:0 6px 36px #4f295455;margin-bottom:1rem;flex-shrink:0;">
            <div style="flex:1;min-width:250px;">
                <h2 style="color:#ea5f94;text-align:center;font-size:2rem;">Personalized Route Generator</h2>
                <p style="color:#e6bfd6;text-align:center;">Choose your starting point, distance, and receive a dynamically crafted trail.</p>
                <form class="gen-form">
                    <label for="start">Start:</label>
                    <input type="text" id="start" name="start" placeholder="Enter your start (e.g., City Park)">
                    <label for="distance">Distance (km):</label>
                    <input type="number" id="distance" name="distance" min="1" max="99" value="5">
                    <button type="submit" class="cta-button route-btn">Generate Route</button>
                </form>
                <div id="route-result" class="route-result"></div>
            </div>
        </div>
    </section>
    <footer style="margin-top:2.5rem;">
        <p>&copy; 2025 TrailForge. All rights reserved.</p>
    </footer>
    <script src="main.js"></script>
</body>
</html>

