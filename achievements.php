<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

require_once 'includes/dbconn.php';
require_once 'includes/pagination.php';

$user_id = (int)$_SESSION['user_id'];

// Optional catch-up award (silent sync)
require_once __DIR__ . '/api/achievements/award_by_metric.php';
tf_award_by_metric($connection, $user_id, 'favourite_count');
tf_award_by_metric($connection, $user_id, 'share_count');
tf_award_by_metric($connection, $user_id, 'run_count');
tf_award_by_metric($connection, $user_id, 'run_distance_km');
tf_award_by_metric($connection, $user_id, 'run_elevation_m');

// counts for progress bars
$counts = [
  'favourite_count' => 0,
  'share_count' => 0,
  'run_count' => 0,
  'run_distance_km' => 0.0,
  'run_elevation_m' => 0
];

// favourites count
$q = $connection->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$q->bind_param("i", $user_id);
$q->execute();
$q->bind_result($counts['favourite_count']);
$q->fetch();
$q->close();

// shared/published count
$q = $connection->prepare("SELECT COUNT(*) FROM route_shares WHERE user_id = ?");
$q->bind_param("i", $user_id);
$q->execute();
$q->bind_result($counts['share_count']);
$q->fetch();
$q->close();

// activities (runs)
$q = $connection->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ?");
$q->bind_param("i", $user_id);
$q->execute();
$q->bind_result($counts['run_count']);
$q->fetch();
$q->close();

$q = $connection->prepare("SELECT COALESCE(SUM(distance_km),0) FROM activities WHERE user_id = ?");
$q->bind_param("i", $user_id);
$q->execute();
$q->bind_result($counts['run_distance_km']);
$q->fetch();
$q->close();

$q = $connection->prepare("SELECT COALESCE(SUM(elevation_gain_m),0) FROM activities WHERE user_id = ?");
$q->bind_param("i", $user_id);
$q->execute();
$q->bind_result($counts['run_elevation_m']);
$q->fetch();
$q->close();

// Load featured badge IDs from users table
$stmtF = $connection->prepare("SELECT featured_badge_1, featured_badge_2, featured_badge_3 FROM users WHERE id = ?");
$stmtF->bind_param("i", $user_id);
$stmtF->execute();
$stmtF->bind_result($fb1, $fb2, $fb3);
$stmtF->fetch();
$stmtF->close();

$featuredSet = array_flip(array_filter([(int)$fb1, (int)$fb2, (int)$fb3], fn($x) => $x > 0));

/* -------------------------
   Pagination setup
--------------------------*/
$perPage = 12;
$page = (isset($_GET['page']) && ctype_digit($_GET['page'])) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $perPage;

// total achievements count
$stmt = $connection->prepare("SELECT COUNT(*) FROM achievements");
$stmt->execute();
$stmt->bind_result($totalItems);
$stmt->fetch();
$stmt->close();

/* -------------------------
   Load ONE PAGE of achievements
--------------------------*/
$sql = "
  SELECT 
    a.id, a.code, a.title, a.description, a.icon, a.points, a.target, a.metric,
    ua.earned_at
  FROM achievements a
  LEFT JOIN user_achievements ua
    ON ua.achievement_id = a.id AND ua.user_id = ?
  ORDER BY (ua.earned_at IS NULL) ASC, ua.earned_at DESC, a.id ASC
  LIMIT ? OFFSET ?
";

$stmt = $connection->prepare($sql);
$stmt->bind_param('iii', $user_id, $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$achievements = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pagination = render_pagination($totalItems, $perPage, $page, 'achievements.php');

include 'includes/navbar.php';
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
        <p>Badges you earn by using TrailForgeX (runs, favourites, sharing, etc.).</p>
      </div>
      <div class="ach-stats">
        <div class="ach-pill">🏅 Earned: <?= (int)$earnedCount ?> / <?= (int)$totalCount ?></div>
        <div class="ach-pill">✨ Points: <?= array_sum(array_map(fn($x)=>!empty($x['earned_at']) ? (int)$x['points'] : 0, $achievements)) ?></div>
      </div>
    </section>

    <!-- Featured picker bar (JS in main.js) -->
    <section class="ach-featured-bar" aria-label="Featured badges picker">
      <div class="ach-featured-left">
        <div class="ach-featured-title">Featured badges</div>
        <div class="ach-featured-sub">Select up to 3 earned badges to show on your profile.</div>
      </div>

      <div class="ach-featured-right">
        <div class="ach-featured-count">
          Selected: <span id="featuredCount">0</span>/3
        </div>
        <button id="saveFeaturedBadges" class="ach-featured-save" type="button">
          Save featured
        </button>
      </div>
    </section>

    <div id="featuredMsg" class="ach-featured-msg" role="status" aria-live="polite"></div>

    <section class="ach-grid" id="achGrid">
      <?php foreach ($achievements as $a):
        $earned = !empty($a['earned_at']);

        $progress = null;
        if (!empty($a['metric']) && array_key_exists($a['metric'], $counts)) {
          $progress = $counts[$a['metric']];
        }

        $earnedDate = $earned ? date('Y-m-d', strtotime($a['earned_at'])) : null;
        $isFeatured = isset($featuredSet[(int)$a['id']]);
      ?>
        <div
          id="ach_<?= (int)$a['id'] ?>"
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

          <?php if ($earned): ?>
            <label class="ach-feature-toggle" aria-label="Feature this badge on your profile">
              <input
                class="featureBadge"
                type="checkbox"
                data-ach-id="<?= (int)$a['id'] ?>"
                <?= $isFeatured ? 'checked' : '' ?>
              >
              <span>Feature</span>
            </label>
          <?php endif; ?>

          <div class="ach-top">
            <div class="ach-icon"><?= htmlspecialchars($a['icon']) ?></div>
            <div class="ach-top-text">
              <p class="ach-title"><?= htmlspecialchars($a['title']) ?></p>
              <div class="ach-points">
                <?= (int)$a['points'] ?> pts
                <?php if ($earned): ?>
                  <span class="ach-earned-date"> · <?= htmlspecialchars($earnedDate) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <p class="ach-desc"><?= htmlspecialchars($a['description']) ?></p>

          <?php if (!$earned && !empty($a['target']) && $progress !== null):
            $target = (float)$a['target'];
            $pct = $target > 0 ? min(100, ((float)$progress / $target) * 100) : 0;
          ?>
            <div class="ach-progress">
              <div class="ach-progress-bar">
                <div class="ach-progress-fill" style="width:<?= (float)$pct ?>%"></div>
              </div>
              <div class="ach-progress-text">
                <?= is_float($progress) ? number_format((float)$progress, 2) : (int)$progress ?>
                /
                <?= (int)$target ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </section>
    <?= $pagination ?>
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

      <div class="ach-modal-desc" id="achModalDesc"></div>

      <div class="ach-modal-meta">
        <div id="achModalStatus">Status: —</div>
        <div id="achModalPoints">Points: —</div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <script src="main.js"></script>
</body>
</html>
