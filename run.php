<?php
$AUTH_PAGE_NAME = 'run';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/dbconn.php';
require_once __DIR__ . '/includes/navbar.php';

$route_id = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
if ($route_id <= 0) {
  http_response_code(400);
  echo "Missing/invalid route_id.";
  exit;
}

// Route meta
$stmt = $connection->prepare("SELECT id, title, distance_km, elevation_gain_m FROM routes WHERE id = ?");
$stmt->bind_param("i", $route_id);
$stmt->execute();
$route = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$route) {
  http_response_code(404);
  echo "Route not found.";
  exit;
}

// Route points
$stmt = $connection->prepare("SELECT latitude, longitude FROM route_points WHERE route_id = ? ORDER BY point_order ASC");
$stmt->bind_param("i", $route_id);
$stmt->execute();
$res = $stmt->get_result();

$coords = [];
while ($row = $res->fetch_assoc()) {
  $coords[] = [(float)$row['latitude'], (float)$row['longitude']];
}
$stmt->close();

if (count($coords) < 2) {
  http_response_code(400);
  echo "Route has insufficient points.";
  exit;
}

$title = trim((string)($route['title'] ?? ''));
if ($title === '') $title = 'Run route';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> | TrailForgeX</title>

  <link rel="stylesheet" href="master.css?v=2026-02-11-1" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body class="requires-auth run-prestart">
  <main class="run-main">
    <div class="run-wrap">

      <div id="runMap"></div>

      <!-- UI Overlay -->
      <div class="run-ui">

        <!-- Top pill -->
        <div class="run-top-pill">
          <div class="run-title" id="routeTitle"><?= htmlspecialchars($title) ?></div>
          <div class="run-gps" id="gpsStatus">GPS: —</div>
          <div class="run-complete" id="completePill" style="display:none;">✅ Route completed</div>
          <button class="run-min-btn" id="btnMinimize" type="button" aria-label="Show map" title="Show map">
            ↘
          </button>
          <button class="run-expand-btn" id="btnExpand" type="button" aria-label="Show stats" title="Show stats">
            ↖
          </button>

        </div>

        <!-- Right floating -->
        <div class="run-right">
          <button class="run-fab" id="btnRecenter" title="Recenter">🧭</button>
        </div>

        <!-- Bottom -->
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
              <div class="stat-label">DIST</div>
              <div class="stat-value" id="distTxt">0.00 km</div>
            </div>
          </div>

          <div class="run-actions">
            <button id="startPauseBtn" class="run-btn run-primary">Start</button>
            <button id="finishBtn" class="run-btn run-secondary" disabled>Finish</button>
          </div>
        </div>

      </div>
    </div>
  </main>

  <!-- =========================
     FINISH / CONGRATS MODAL
  ========================== -->
  <div id="finishModal" class="finish-modal" aria-hidden="true">
    <div class="finish-backdrop" data-close="1"></div>

    <div class="finish-sheet" role="dialog" aria-modal="true" aria-labelledby="finishTitle">
      <button class="finish-close" type="button" aria-label="Close" data-close="1">✕</button>

      <div class="finish-header">
        <div class="finish-badge">🎉</div>
        <h2 id="finishTitle" class="finish-title">Congrats!</h2>
        <p class="finish-sub">Activity completed</p>
      </div>

      <div class="finish-stats">
        <div class="finish-stat">
          <div class="finish-label">Time</div>
          <div class="finish-value" id="finishTime">00:00</div>
        </div>

        <div class="finish-stat">
          <div class="finish-label">Distance</div>
          <div class="finish-value" id="finishDist">0.00 km</div>
        </div>

        <div class="finish-stat">
          <div class="finish-label">Avg pace</div>
          <div class="finish-value" id="finishPace">—</div>
        </div>
      </div>

      <div class="finish-actions">
        <button class="finish-btn finish-secondary" type="button" id="finishKeepEditing">
          Back
        </button>
        <button class="finish-btn finish-primary" type="button" id="finishConfirm">
          Save & Exit
        </button>
      </div>
    </div>
  </div>


  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Expose data to main.js
    window.RUN_PAGE = {
      routeId: <?= (int)$route_id ?>,
      title: <?= json_encode($title, JSON_UNESCAPED_UNICODE) ?>,
      planned: <?= json_encode($coords, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="main.js?v=2026-02-11-1"></script>
</body>
</html>
