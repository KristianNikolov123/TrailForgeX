<?php
$AUTH_PAGE_NAME = 'the trails';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/dbconn.php';
require_once __DIR__ . '/includes/navbar.php';
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
                <div class="filter-field"><span class="filter-icon">💪</span><input type="number" id="minDiff" placeholder="Min diffic" min="0" max="10" step="0.5"><span style="opacity:.7;margin:0 3px;">-</span><input type="number" id="maxDiff" placeholder="Max diffic" min="0" max="10" step="0.5"></div>
                <div class="filter-field">
                    <span class="filter-icon">🛣️</span>
                    <div class="tf-select" id="pavementSelect">
                        <button type="button" class="tf-select-btn" id="pavementBtn">
                            <span id="pavementLabel">Pavement</span>
                            <span class="tf-select-arrow">▾</span>
                        </button>

                        <div class="tf-select-menu" id="pavementMenu">
                            <button type="button" data-value="">Pavement</button>
                            <button type="button" data-value="trail">Trail</button>
                            <button type="button" data-value="road">Road</button>
                            <button type="button" data-value="mixed">Mixed</button>
                        </div>

                        <!-- keeps your JS working the same -->
                        <input type="hidden" id="pavementType" value="">
                    </div>
                </div>
            </div>
            <button id="applyFilters" class="filter-apply-btn">Apply Filters</button>
        </div>

        <div id="trailsList" class="trails-list"></div>
        <div id="trailsPagination"></div>

    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</body>
</html>

