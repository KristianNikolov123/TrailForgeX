<?php
$route = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Geocoding will be handled by JavaScript, but we can keep PHP fallback
    $payload = [
        "start_lat" => floatval($_POST["start_lat"] ?? 42.6977),
        "start_lng" => floatval($_POST["start_lng"] ?? 23.3219),
        "end_lat"   => !empty($_POST["end_lat"]) ? floatval($_POST["end_lat"]) : null,
        "end_lng"   => !empty($_POST["end_lng"]) ? floatval($_POST["end_lng"]) : null,
        "distance_km" => floatval($_POST["distance"] ?? 5),
        "elevation_gain_target" => !empty($_POST["elevation_gain"]) ? floatval($_POST["elevation_gain"]) : null,
        "prefer" => $_POST["prefer"] ?? "green"
    ];

    $ch = curl_init("http://localhost:8000/generate");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    curl_close($ch);

    $route = json_decode($response, true);
}
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
        <div class="logo"><img src="TrailForgeX-logo.png" alt="TrailForgeX Logo" style="height:64px"></div>
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
    <section class="generate-hero" style="background:
        linear-gradient(120deg, rgba(36,20,27,0.54) 55%, rgba(89,50,71,0.78) 100%),
        url('https://images.unsplash.com/photo-1464983953574-0892a716854b?auto=format&fit=crop&w=1800&q=80') center center/cover no-repeat;
        position: relative;
        min-height:100vh;
        min-width:100vw;
        width:100vw;
        max-width:100vw;
        box-sizing:border-box;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:0;
        margin:0;
        overflow:hidden;">
        <div style="position:relative;z-index:2;max-width:500px;width:97%;background:rgba(32,13,25,0.76);box-shadow:0 8px 45px #321926a5,0 2px 9px #ea5f9430;border-radius:30px;padding:2.6rem 1.8rem 2.9rem 1.8rem;backdrop-filter:blur(8px);margin:2rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.6rem;">
            <h1 style="color:#fff;font-size:2.35rem;font-weight:800;text-shadow:0 2px 22px #200e19, 0 1px 1px #593247, 0 0px 30px #ea5f9436;margin-bottom:0.2em;">Generate Your Next Adventure Route</h1>
            <p style="color:#d9a7c8;font-size:1.12rem;margin:0 auto 0.3rem auto;">Let TrailForgeX surprise you with unique paths.</p>
            <!-- FORM START -->
            <form class="gen-form" method="POST" action="" id="routeForm" style="width:100%;margin-top:0.6em;">
                <label for="start">Start Location:</label>
                <input type="text" id="start" name="start" placeholder="Enter address or place name" required>
                <input type="hidden" id="start_lat" name="start_lat">
                <input type="hidden" id="start_lng" name="start_lng">
                <div id="start_geocoded_result" style="color:#e6bfd6;font-size:0.94em;margin:2px 0 8px 2px;"></div>

                <label for="end">End Location (optional, leave empty for loop):</label>
                <input type="text" id="end" name="end" placeholder="Enter address or leave empty for loop">
                <input type="hidden" id="end_lat" name="end_lat">
                <input type="hidden" id="end_lng" name="end_lng">
                <div id="end_geocoded_result" style="color:#e6bfd6;font-size:0.94em;margin:2px 0 8px 2px;"></div>

                <label for="distance">Distance (km):</label>
                <input type="number" id="distance" name="distance" min="1" max="50" value="10" step="0.5" required>

                <label for="elevation_gain">Elevation Gain Target (m, optional):</label>
                <input type="number" id="elevation_gain" name="elevation_gain" min="0" max="2000" placeholder="e.g., 300" step="10">

                <label for="prefer">Route Preference:</label>
                <select id="prefer" name="prefer" required>
                    <option value="green">Green Areas & Parks</option>
                    <option value="trail">Trails & Dirt Paths</option>
                    <option value="road">Roads & Pavement</option>
                </select>
                <button type="submit" class="cta-button route-btn" id="generateBtn" style="margin-top:1.05em;">Generate Route</button>
            </form>
            <!-- Results/Error Sections -->
            <div id="route-result" style="margin-top:1.5rem; padding:1rem; background: rgba(234, 95, 148, 0.09); border-radius: 10px; width:100%; display:none;">
                <h3 style="color:#ea5f94; margin-bottom:0.5rem;">Route Generated!</h3>
                <p id="route-distance" style="color:#e6bfd6;"></p>
                <p id="route-elevation" style="color:#e6bfd6;"></p>
                <div id="map" style="width: 100%; height: 340px; margin-top: 1rem; border-radius: 8px;"></div>
            </div>
            <div id="error-message" style="margin-top:1rem; padding:1rem; background:rgba(255,0,0,0.11); border-radius:8px; color:#ff6b6b; width:100%;display:none;"></div>
        </div>
    </section>
    <footer style="margin-top:2.5rem;">
        <p>&copy; 2025 TrailForge. All rights reserved.</p>
    </footer>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="main.js"></script>
</body>
</html>

