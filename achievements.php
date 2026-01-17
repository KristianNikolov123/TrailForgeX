<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

require_once 'dbconn.php';
$user_id = $_SESSION['user_id'];

/*
  This page shows:
  - all achievements
  - whether the user earned them
  - earned date if yes
*/
$sql = "
  SELECT 
    a.id, a.code, a.title, a.description, a.icon, a.points,
    ua.earned_at
  FROM achievements a
  LEFT JOIN user_achievements ua
    ON ua.achievement_id = a.id AND ua.user_id = ?
  ORDER BY (ua.earned_at IS NULL) ASC, ua.earned_at DESC, a.id ASC
";

$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$achievements = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Achievements | TrailForgeX</title>
  <link rel="stylesheet" href="master.css">
</head>

<body>
  <main class="ach-main">
    <?php
      $earnedCount = 0;
      foreach ($achievements as $a) {
        if (!empty($a['earned_at'])) $earnedCount++;
      }
      $totalCount = count($achievements);
    ?>
    <section class="ach-hero">
      <div>
        <h2>Achievements</h2>
        <p>Badges you earn by using TrailForgeX (routes, favourites, sharing, etc.).</p>
      </div>
      <div class="ach-stats">
        <div class="ach-pill">🏅 Earned: <?= $earnedCount ?> / <?= $totalCount ?></div>
        <div class="ach-pill">✨ Points: <?= array_sum(array_map(fn($x)=>!empty($x['earned_at']) ? (int)$x['points'] : 0, $achievements)) ?></div>
      </div>
    </section>

    <section class="ach-grid" id="achGrid">
      <?php foreach ($achievements as $a):
        $earned = !empty($a['earned_at']);
        $earnedDate = $earned ? date('Y-m-d', strtotime($a['earned_at'])) : null;
      ?>
        <div
          class="ach-card <?= $earned ? '' : 'locked' ?>"
          data-title="<?= htmlspecialchars($a['title']) ?>"
          data-desc="<?= htmlspecialchars($a['description']) ?>"
          data-icon="<?= htmlspecialchars($a['icon']) ?>"
          data-earned="<?= $earned ? '1' : '0' ?>"
          data-earned-date="<?= htmlspecialchars($earnedDate ?? '') ?>"
          data-points="<?= (int)$a['points'] ?>"
          tabindex="0"
          role="button"
          aria-label="Open achievement details"
        >
          <span class="ach-badge <?= $earned ? 'earned' : '' ?>">
            <?= $earned ? 'Earned' : 'Locked' ?>
          </span>

          <div class="ach-top">
            <div class="ach-icon"><?= htmlspecialchars($a['icon']) ?></div>
            <div style="flex:1;">
              <p class="ach-title"><?= htmlspecialchars($a['title']) ?></p>
              <div style="margin-top:.35em;color:#cbb1cd;font-weight:900;">
                <?= (int)$a['points'] ?> pts
                <?php if ($earned): ?>
                  <span style="opacity:.8;"> · <?= htmlspecialchars($earnedDate) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <p class="ach-desc"><?= htmlspecialchars($a['description']) ?></p>
        </div>
      <?php endforeach; ?>
    </section>
  </main>

  <!-- Modal -->
  <div id="achModal" aria-hidden="true">
    <div class="ach-modal-card" role="dialog" aria-modal="true" aria-labelledby="achModalTitle">
      <button class="ach-modal-close" id="achModalClose" aria-label="Close">&times;</button>

      <div class="ach-modal-head">
        <div class="ach-modal-icon" id="achModalIcon">🏅</div>
        <div>
          <h3 class="ach-modal-title" id="achModalTitle">Achievement</h3>
          <p class="ach-modal-sub" id="achModalSub">Details</p>
        </div>
      </div>

      <div style="color:#e6bfd6;font-weight:700;line-height:1.45;" id="achModalDesc"></div>

      <div class="ach-modal-meta">
        <div id="achModalStatus">Status: —</div>
        <div id="achModalPoints">Points: —</div>
      </div>
    </div>
  </div>
  <script src="main.js"></script>
</body>
</html>
