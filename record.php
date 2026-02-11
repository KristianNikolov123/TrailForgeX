<?php
$AUTH_PAGE_NAME = 'record';
require_once __DIR__ . '/includes/auth_guard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Record | TrailForgeX</title>
  <link rel="stylesheet" href="master.css?v=2026-02-11-1">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body class="requires-auth run-prestart">

<main class="run-wrap">
  <div id="runMap"></div>

  <!-- keep your existing UI style / ids -->
  <div class="run-ui">
    <div class="run-top-pill">
      <div class="run-title">Record activity</div>
      <div class="run-gps" id="gpsStatus">GPS: —</div>
      <span id="completePill" class="run-complete" style="display:none;">✅ Recording</span>
      <button class="run-min-btn" id="btnMinimize" type="button" aria-label="Show map" title="Show map">
        ↘
      </button>
      <button class="run-expand-btn" id="btnExpand" type="button" aria-label="Show stats" title="Show stats">
        ↖
      </button>
    </div>

    <div class="run-right">
      <button class="run-fab" id="btnRecenter" type="button" title="Recenter">◎</button>
    </div>

    <div class="run-bottom">
      <div class="run-stats">
        <div class="stat">
          <div class="stat-label">PACE</div>
          <div class="stat-value" id="paceTxt">—</div>
        </div>
        <div class="stat">
          <div class="stat-label">TIME</div>
          <div class="stat-value" id="timeTxt">00:00</div>
        </div>
        <div class="stat">
          <div class="stat-label">DISTANCE</div>
          <div class="stat-value" id="distTxt">0.00 km</div>
        </div>
      </div>

      <div class="run-actions">
        <button class="run-btn run-secondary" id="finishBtn" type="button" disabled>Finish</button>
        <button class="run-btn run-primary" id="startPauseBtn" type="button">Waiting for GPS…</button>
      </div>
    </div>
  </div>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  window.RECORD_PAGE = {
    activityType: 'run'
  };
</script>
<script src="main.js?v=2026-02-11-1"></script>

</body>
</html>
