<?php
include "navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trails | TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

</head>
<body>
<script>
(function() {
  fetch('api/routes/check_auth.php').then(r => r.json()).then(jwt => {
    if (!jwt || !jwt.logged_in) {
      window.location.href = 'index.php';
    }
  });
})();   
</script>
    <main class="trails-main">
        <h1>Trails</h1>
        <div class="trails-tabs">
            <button class="trails-tab active" data-tab="favourites">⭐ Favourites</button>
            <button class="trails-tab" data-tab="public">🌍 Public Routes</button>
        </div>
        <div class="trails-filters">
            Distance: <input type="number" id="minDistance" placeholder="Min km" min="0"> - <input type="number" id="maxDistance" placeholder="Max km" min="0">
            Elevation: <input type="number" id="minElevation" placeholder="Min m" min="0"> - <input type="number" id="maxElevation" placeholder="Max m" min="0">
            Pavement:
            <select id="pavementType">
                <option value="">Any</option>
                <option value="trail">Trail</option>
                <option value="road">Road</option>
                <option value="mixed">Mixed</option>
            </select>
            <button id="applyFilters">Apply</button>
        </div>
        <div id="trailsList" class="trails-list">
            <!-- Routes loaded here via AJAX/JS -->
            <div class="no-routes">No routes to show. Generate or start favouriting routes!</div>
        </div>
    </main>
    <footer style="margin-top:2.5rem;">
        <p>&copy; 2025 TrailForge. All rights reserved.</p>
    </footer>
    <script src="main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>

