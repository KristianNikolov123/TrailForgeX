<?php
include "includes/navbar.php";
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
    <main class="trails-main">
        <h1>Trails</h1>
        <div class="trails-tabs">
            <button class="trails-tab" data-tab="todo">📌 To-Do</button>
            <button class="trails-tab active" data-tab="favourites">⭐ Favourites</button>
            <button class="trails-tab" data-tab="public">🌍 Public Routes</button>
        </div>
        <div class="trails-filters-card">
            <div class="trails-filters-fields">
                <div class="filter-field"><span class="filter-icon">📏</span><input type="number" id="minDistance" placeholder="Min km" min="0"><span style="opacity:.7;margin:0 3px;">-</span><input type="number" id="maxDistance" placeholder="Max km" min="0"></div>
                <div class="filter-field"><span class="filter-icon">⛰️</span><input type="number" id="minElevation" placeholder="Min m" min="0"><span style="opacity:.7;margin:0 3px;">-</span><input type="number" id="maxElevation" placeholder="Max m" min="0"></div>
                <div class="filter-field"><span class="filter-icon">🛣️</span><select id="pavementType">
                    <option value="">Pavement</option>
                    <option value="trail">Trail</option>
                    <option value="road">Road</option>
                    <option value="mixed">Mixed</option>
                </select></div>
            </div>
            <button id="applyFilters" class="filter-apply-btn">Apply Filters</button>
        </div>
        <div id="trailsList" class="trails-list">
            <!-- Routes loaded here via AJAX/JS -->
            <div class="no-routes">No routes to show. Generate or start favouriting routes!</div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>

