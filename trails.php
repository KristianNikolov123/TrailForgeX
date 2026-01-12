<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trails | TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
</head>
<body>
    <header>
        <div class="logo"><img src="TrailForgeX-logo.png" alt="TrailForgeX Logo" style="height:64px"></div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="trails.php" class="highlighted-nav">Trails</a></li>
                <li><a href="generate.php">Generate Route</a></li>
                <li><a href="#">Community</a></li>
                <li><a href="#">About</a></li>
            </ul>
        </nav>
    </header>
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
</body>
</html>

