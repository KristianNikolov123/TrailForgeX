<?php
include "includes/navbar.php";
$route = null;
$route_id = null;
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
<script>
(function() {
  fetch('api/routes/check_auth.php', { credentials: 'include' })
  .then(r => r.json())
  .then(jwt => {
    console.log('auth check:', jwt);
    if (!jwt || !jwt.logged_in) window.location.href = 'index.php';
  })
  .catch(err => console.error('auth check failed:', err));
})();
</script>

<section class="generate-hero">

  <div class="gen-card">

    <h1 class="gen-title">Generate Your Next Adventure Route</h1>
    <p class="gen-subtitle">Let TrailForgeX surprise you with unique paths.</p>


    <!-- FORM START -->
    <form class="gen-form" method="POST" action="" id="routeForm" style="width:100%;margin-top:0.4em;">

      <!-- NEW: Mode toggle -->
      <label style="margin-top:.2em;">Generation Mode:</label>
      <div class="mode-toggle">
        <label class="mode-pill">
          <input type="radio" name="gen_mode" id="mode_start" value="start" checked>
          Start (and optional end)
        </label>

        <label class="mode-pill">
          <input type="radio" name="gen_mode" id="mode_area" value="area">
          No start/end (generate in an area)
        </label>
      </div>


      <!-- Start -->
      <div id="startBlock">
        <label for="start">Start Location:</label>
        <input type="text" id="start" name="start" placeholder="Enter address or place name">
        <input type="hidden" id="start_lat" name="start_lat">
        <input type="hidden" id="start_lng" name="start_lng">
        <div id="start_geocoded_result" class="geocode-hint"></div>

        <label for="end">End Location (optional, leave empty for loop):</label>
        <input type="text" id="end" name="end" placeholder="Enter address or leave empty for loop">
        <input type="hidden" id="end_lat" name="end_lat">
        <input type="hidden" id="end_lng" name="end_lng">
        <div id="end_geocoded_result" class="geocode-hint"></div>
      </div>

      <!-- NEW: Area mode input -->
      <div id="areaBlock" style="display:none;">
        <label for="area">Area / City / Landmark:</label>
        <input type="text" id="area" name="area" placeholder="e.g. Sofia, Bulgaria">

        <!-- Hidden center coords for backend -->
        <input type="hidden" id="center_lat" name="center_lat">
        <input type="hidden" id="center_lng" name="center_lng">

        <div id="area_geocoded_result" class="geocode-hint"></div>

        <div style="color:#d9a7c8;font-size:.95em;opacity:.9;line-height:1.35;margin-top:.2em;">
          In this mode, TrailForgeX generates 3 loop routes inside the selected area and you pick one.
        </div>
      </div>

      <!-- Distance -->
      <label for="distance">Distance (km):</label>
      <input type="number" id="distance" name="distance" min="1" max="50" value="10" step="0.5" required>

      <!-- Elevation target (optional) -->
      <label for="elevation_gain">Elevation Gain Target (m, optional):</label>
      <input type="number" id="elevation_gain" name="elevation_gain" min="0" max="2000" placeholder="e.g., 300" step="10">

      <!-- Prefer -->
      <label for="prefer">Route Preference:</label>
      <select id="prefer" name="prefer" required>
        <option value="green">Green Areas & Parks</option>
        <option value="trail">Trails & Dirt Paths</option>
        <option value="road">Roads & Pavement</option>
      </select>

      <button type="submit" class="cta-button route-btn" id="generateBtn" style="margin-top:1.05em;">
        Generate (3 Options)
      </button>
    </form>

    <!-- NEW: Cards for Top 3 -->
    <div id="routeChoices" class="route-choices" style="display:none;width:100%;"></div>

    <!-- Results/Error Sections -->
    <div id="route-result" style="margin-top:1.2rem; padding:1rem; background: rgba(234, 95, 148, 0.09); border-radius: 10px; width:100%; display:none;">
      <h3 style="color:#ea5f94; margin-bottom:0.5rem;">Selected Route</h3>
      <p id="route-distance" style="color:#e6bfd6;"></p>
      <p id="route-elevation" style="color:#e6bfd6;"></p>
      <div id="map" style="width: 100%; height: 340px; margin-top: 1rem; border-radius: 8px;"></div>

      <?php if (isset($route_id) && $route_id): ?>
        <input type="hidden" id="currentRouteId" value="<?= htmlspecialchars($route_id) ?>">
        <script>window.currentRouteId = <?= (int)$route_id ?>;</script>
      <?php endif; ?>

      <div class="route-actions" style="margin-top:1rem; display:flex; gap:0.85rem;">
        <button class="btn-favourite" id="favouriteRouteBtn" disabled type="button" title="Add to Favourites">
          <span id="favouriteIcon" style="font-size:1.5em;color:#ea5f94;">☆</span> Favourite
        </button>
        <button class="btn-share" id="shareRouteBtn" type="button" title="Share this Route">
          <span style="font-size:1.3em;">🔗</span> Publish
        </button>
      </div>

      <div id="shareModal" class="modal" style="display:none;position: fixed;z-index:999;background:rgba(33,12,24,0.97);top:0;left:0;width:100vw;height:100vh;align-items:center;justify-content:center;">
        <div class="modal-content" style="background:#2f1723;padding:2.2rem 2.6rem;border-radius:20px;max-width:96vw;width:350px;box-shadow:0 9px 60px #ea5f9445;position:relative;">
          <span class="close" id="closeShareModal" style="position:absolute;top:1rem;right:1.6rem;font-size:2rem;cursor:pointer;color:#ea5f94">&times;</span>
          <p style="margin-bottom:.6rem;">Share this route:</p>
          <input type="text" id="shareLink" readonly style="padding:.5em 1em;width:90%;border-radius:6px;border:none;font-size:1.08em;background:#e6bfd6;color:#51263d;outline:none;">
          <button class="btn-copy" id="copyShareLink" style="margin-top:.9em;background:#ea5f94;color:#fff;border:none;padding:.45em 1.2em;border-radius:7px;font-weight:700;">Copy</button>
        </div>
      </div>
    </div>

    <div id="error-message" style="margin-top:1rem; padding:1rem; background:rgba(255,0,0,0.11); border-radius:8px; color:#ff6b6b; width:100%;display:none;"></div>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="main.js"></script>

</body>
</html>
