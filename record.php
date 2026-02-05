<?php
$AUTH_PAGE_NAME = 'record';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Record | TrailForgeX</title>
  <link rel="stylesheet" href="master.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body class="requires-auth">

<main class="run-wrap">
  <div id="runMap"></div>

  <!-- keep your existing UI style / ids -->
  <div class="run-ui">
    <div class="run-top-pill">
      <div class="run-title">Record activity</div>
      <div class="run-gps" id="gpsStatus">GPS: —</div>
      <span id="completePill" class="run-complete" style="display:none;">✅ Recording</span>
    </div>

    <div class="run-right">
      <button class="run-fab" id="btnRecenter" type="button" title="Recenter">◎</button>
    </div>

    <div class="run-bottom">
      <div class="run-stats">
        <div class="stat">
          <div class="stat-label">Pace</div>
          <div class="stat-value" id="paceTxt">—</div>
        </div>
        <div class="stat">
          <div class="stat-label">Time</div>
          <div class="stat-value" id="timeTxt">00:00</div>
        </div>
        <div class="stat">
          <div class="stat-label">Distance</div>
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

<?php include 'includes/footer.php'; ?>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  window.RECORD_PAGE = {
    activityType: 'run'
  };
</script>
<script src="main.js?v=2026-02-05-1"></script>

</body>
</html>
