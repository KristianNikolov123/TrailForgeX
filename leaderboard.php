<?php

$AUTH_PAGE_NAME = 'the leaderboard';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/dbconn.php';

$limit = 100;
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$search = trim($_GET['q'] ?? '');
$search = mb_substr($search, 0, 40); // small safety cap

$view = $_GET['view'] ?? 'points';
$view = ($view === 'km') ? 'km' : 'points'; // allow only points|km

// ISO week start (Monday 00:00:00) and end (next Monday)
$weekStart = (new DateTime('today'))->modify('-' . ((int)date('N') - 1) . ' days')->format('Y-m-d 00:00:00');
$weekEnd   = (new DateTime($weekStart))->modify('+7 days')->format('Y-m-d 00:00:00');

// -----------------------
// Helpers
// -----------------------
function avatarUrl($username, $profile_image) {
  if (!empty($profile_image)) return $profile_image;
  return 'https://api.dicebear.com/6.x/identicon/svg?seed=' . urlencode($username);
}

function medal($rank) {
    if ($rank === 1) return '🥇';
    if ($rank === 2) return '🥈';
    if ($rank === 3) return '🥉';
    return '';
  }

// -----------------------
// Top 100 Points Earned All-time
// -----------------------
$stmt = $connection->prepare("
  SELECT 
    u.id, u.username, u.profile_image,
    COALESCE(SUM(a.points), 0) AS points
  FROM users u
  LEFT JOIN user_achievements ua ON ua.user_id = u.id
  LEFT JOIN achievements a ON a.id = ua.achievement_id
  GROUP BY u.id, u.username, u.profile_image
  ORDER BY points DESC, u.username ASC
  LIMIT ?
");
$stmt->bind_param("i", $limit);
$stmt->execute();
$all_time = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------
// Top 100 Points Earned Weekly (Mon–Sun)
// -----------------------
$stmt = $connection->prepare("
  SELECT 
    u.id, u.username, u.profile_image,
    COALESCE(SUM(a.points), 0) AS points
  FROM users u
  JOIN user_achievements ua ON ua.user_id = u.id
  JOIN achievements a ON a.id = ua.achievement_id
  WHERE ua.earned_at >= ? AND ua.earned_at < ?
  GROUP BY u.id, u.username, u.profile_image
  ORDER BY points DESC, u.username ASC
  LIMIT ?
");
$stmt->bind_param("ssi", $weekStart, $weekEnd, $limit);
$stmt->execute();
$weekly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// -----------------------
// Top 100 KM All-time
// -----------------------
$stmt = $connection->prepare("
  SELECT 
    u.id, u.username, u.profile_image,
    COALESCE(SUM(act.distance_km), 0) AS km
  FROM users u
  LEFT JOIN activities act ON act.user_id = u.id
  GROUP BY u.id, u.username, u.profile_image
  ORDER BY km DESC, u.username ASC
  LIMIT ?
");
$stmt->bind_param("i", $limit);
$stmt->execute();
$km_all_time = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// -----------------------
// Top 100 KM Weekly (Mon–Sun)
// -----------------------
$stmt = $connection->prepare("
  SELECT 
    u.id, u.username, u.profile_image,
    COALESCE(SUM(act.distance_km), 0) AS km
  FROM users u
  JOIN activities act ON act.user_id = u.id
  WHERE act.completed_at >= ? AND act.completed_at < ?
  GROUP BY u.id, u.username, u.profile_image
  ORDER BY km DESC, u.username ASC
  LIMIT ?
");
$stmt->bind_param("ssi", $weekStart, $weekEnd, $limit);
$stmt->execute();
$km_weekly = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// -----------------------
// Your stats (points + rank)
// -----------------------
$me_all = null;
$me_week = null;

if ($user_id) {
  // Your all-time points
  $stmt = $connection->prepare("
    SELECT u.id, u.username, u.profile_image, COALESCE(SUM(a.points),0) AS points
    FROM users u
    LEFT JOIN user_achievements ua ON ua.user_id = u.id
    LEFT JOIN achievements a ON a.id = ua.achievement_id
    WHERE u.id = ?
    GROUP BY u.id, u.username, u.profile_image
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $me_all = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Your weekly points
  $stmt = $connection->prepare("
    SELECT u.id, u.username, u.profile_image, COALESCE(SUM(a.points),0) AS points
    FROM users u
    LEFT JOIN user_achievements ua
      ON ua.user_id = u.id AND ua.earned_at >= ? AND ua.earned_at < ?
    LEFT JOIN achievements a ON a.id = ua.achievement_id
    WHERE u.id = ?
    GROUP BY u.id, u.username, u.profile_image
  ");
  $stmt->bind_param("ssi", $weekStart, $weekEnd, $user_id);
  $stmt->execute();
  $me_week = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Rank calculation (all-time)
  $myPts = (int)($me_all['points'] ?? 0);
  $stmt = $connection->prepare("
    SELECT 1 + COUNT(*) AS rnk
    FROM (
      SELECT ua.user_id, COALESCE(SUM(a.points),0) AS pts
      FROM users u
      LEFT JOIN user_achievements ua ON ua.user_id = u.id
      LEFT JOIN achievements a ON a.id = ua.achievement_id
      GROUP BY u.id
      HAVING pts > ?
    ) t
  ");
  $stmt->bind_param("i", $myPts);
  $stmt->execute();
  $stmt->bind_result($meAllRank);
  $stmt->fetch();
  $stmt->close();

  // Rank calculation (weekly)
  $myWeekPts = (int)($me_week['points'] ?? 0);
  $stmt = $connection->prepare("
    SELECT 1 + COUNT(*) AS rnk
    FROM (
      SELECT ua.user_id, COALESCE(SUM(a.points),0) AS pts
      FROM users u
      LEFT JOIN user_achievements ua
        ON ua.user_id = u.id AND ua.earned_at >= ? AND ua.earned_at < ?
      LEFT JOIN achievements a ON a.id = ua.achievement_id
      GROUP BY u.id
      HAVING pts > ?
    ) t
  ");
  $stmt->bind_param("ssi", $weekStart, $weekEnd, $myWeekPts);
  $stmt->execute();
  $stmt->bind_result($meWeekRank);
  $stmt->fetch();
  $stmt->close();

  $me_all['rank'] = (int)$meAllRank;
  $me_week['rank'] = (int)$meWeekRank;

  // Your KM all-time
  $stmt = $connection->prepare("
    SELECT u.id, u.username, u.profile_image, COALESCE(SUM(act.distance_km),0) AS km
    FROM users u
    LEFT JOIN activities act ON act.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id, u.username, u.profile_image
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $me_km_all = $stmt->get_result()->fetch_assoc();
  $stmt->close();

    // Your KM weekly
  $stmt = $connection->prepare("
    SELECT u.id, u.username, u.profile_image, COALESCE(SUM(act.distance_km),0) AS km
    FROM users u
    LEFT JOIN activities act
    ON act.user_id = u.id AND act.completed_at >= ? AND act.completed_at < ?
    WHERE u.id = ?
    GROUP BY u.id, u.username, u.profile_image
  ");
  $stmt->bind_param("ssi", $weekStart, $weekEnd, $user_id);
  $stmt->execute();
  $me_km_week = $stmt->get_result()->fetch_assoc();
  $stmt->close();

    // KM ranks
  $myKm = (float)($me_km_all['km'] ?? 0);
  $stmt = $connection->prepare("
    SELECT 1 + COUNT(*) AS rnk
    FROM (
    SELECT u.id, COALESCE(SUM(act.distance_km),0) AS km
    FROM users u
    LEFT JOIN activities act ON act.user_id = u.id
    GROUP BY u.id
    HAVING km > ?
    ) t
  ");
  $stmt->bind_param("d", $myKm);
  $stmt->execute();
  $stmt->bind_result($meKmAllRank);
  $stmt->fetch();
  $stmt->close();

  $myKmW = (float)($me_km_week['km'] ?? 0);
  $stmt = $connection->prepare("
    SELECT 1 + COUNT(*) AS rnk
    FROM (
    SELECT u.id, COALESCE(SUM(act.distance_km),0) AS km
    FROM users u
    LEFT JOIN activities act
        ON act.user_id = u.id AND act.completed_at >= ? AND act.completed_at < ?
    GROUP BY u.id
    HAVING km > ?
    ) t
  ");
  $stmt->bind_param("ssd", $weekStart, $weekEnd, $myKmW);
  $stmt->execute();
  $stmt->bind_result($meKmWeekRank);
  $stmt->fetch();
  $stmt->close();

  $me_km_all['rank'] = (int)$meKmAllRank;
  $me_km_week['rank'] = (int)$meKmWeekRank;

}

// -----------------------
// Search results (username contains query)
// Shows points OR km depending on active tab
// -----------------------
$search_rows = [];
if ($search !== '') {
  $like = '%' . $search . '%';

  if ($view === 'points') {
    $stmt = $connection->prepare("
      WITH pts AS (
        SELECT 
          u.id,
          u.username,
          u.profile_image,
          COALESCE(SUM(a.points), 0) AS all_points,
          COALESCE(SUM(CASE WHEN ua.earned_at >= ? AND ua.earned_at < ? THEN a.points ELSE 0 END), 0) AS week_points
        FROM users u
        LEFT JOIN user_achievements ua ON ua.user_id = u.id
        LEFT JOIN achievements a ON a.id = ua.achievement_id
        GROUP BY u.id, u.username, u.profile_image
      ),
      ranked AS (
        SELECT
          *,
          DENSE_RANK() OVER (ORDER BY all_points DESC, username ASC) AS all_rank,
          DENSE_RANK() OVER (ORDER BY week_points DESC, username ASC) AS week_rank
        FROM pts
      )
      SELECT *
      FROM ranked
      WHERE username LIKE ?
      ORDER BY all_points DESC, username ASC
      LIMIT 25
    ");
    $stmt->bind_param("sss", $weekStart, $weekEnd, $like);

  } else { // km
    $stmt = $connection->prepare("
      WITH kms AS (
        SELECT 
          u.id,
          u.username,
          u.profile_image,
          COALESCE(SUM(act.distance_km), 0) AS all_km,
          COALESCE(SUM(CASE WHEN act.completed_at >= ? AND act.completed_at < ? THEN act.distance_km ELSE 0 END), 0) AS week_km
        FROM users u
        LEFT JOIN activities act ON act.user_id = u.id
        GROUP BY u.id, u.username, u.profile_image
      ),
      ranked AS (
        SELECT
          *,
          DENSE_RANK() OVER (ORDER BY all_km DESC, username ASC) AS all_rank,
          DENSE_RANK() OVER (ORDER BY week_km DESC, username ASC) AS week_rank
        FROM kms
      )
      SELECT *
      FROM ranked
      WHERE username LIKE ?
      ORDER BY all_km DESC, username ASC
      LIMIT 25
    ");
    $stmt->bind_param("sss", $weekStart, $weekEnd, $like);
  }

  $stmt->execute();
  $search_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}


include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Leaderboard | TrailForgeX</title>
  <link rel="stylesheet" href="master.css">
</head>
<body>

<main class="lb-main">
  <section class="lb-hero">
    <div>
      <h2>Leaderboard</h2>
      <p>See who’s leading in achievements and kilometres this week and all time. Weekly resets every Monday.</p>
    </div>
  </section>

  <nav class="lb-tabs">
    <a class="lb-tab <?= $view === 'points' ? 'active' : '' ?>" href="leaderboard.php?view=points<?= $search!=='' ? '&q='.urlencode($search) : '' ?>">
        🏅 Points
    </a>
    <a class="lb-tab <?= $view === 'km' ? 'active' : '' ?>" href="leaderboard.php?view=km<?= $search!=='' ? '&q='.urlencode($search) : '' ?>">
        🏃 Kilometres
    </a>
  </nav>

  <section class="lb-search">
    <form class="lb-search-form" method="get" action="leaderboard.php">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
      <input
        class="lb-search-input"
        type="text"
        name="q"
        placeholder="Search users…"
        value="<?= htmlspecialchars($search) ?>"
        autocomplete="off"
      >
      <button class="lb-search-btn" type="submit">Search</button>

      <?php if ($search !== ''): ?>
        <a class="lb-search-clear" href="leaderboard.php?view=<?= htmlspecialchars($view) ?>">Clear</a>
      <?php endif; ?>
    </form>

    <?php if ($search !== ''): ?>
      <div class="lb-search-results">
        <div class="lb-search-title">Search results (top 25)</div>

        <?php if (empty($search_rows)): ?>
          <div class="lb-empty">No users match “<?= htmlspecialchars($search) ?>”.</div>
        <?php else: ?>
          <div class="lb-list">
          <?php foreach ($search_rows as $row):
            $img = avatarUrl($row['username'], $row['profile_image']);
            $isMe = $user_id && (int)$row['id'] === $user_id;
          ?>
            <?php
              $rAll = (int)($row['all_rank'] ?? 0);
              $rW   = (int)($row['week_rank'] ?? 0);
            ?>
            <a id="lb-row-display"class="lb-row <?= $isMe ? 'me' : '' ?>" href="profile.php?user=<?= (int)$row['id'] ?>">

            <div class="lb-rank lb-rank-duo">
              <span class="lb-rank-chip lb-rank-all">
                <?= medal($rAll) ? medal($rAll) . ' ' : '' ?>#<?= $rAll ?>
              </span>
              <span class="lb-rank-chip lb-rank-week">
                W <?= medal($rW) ? medal($rW) . ' ' : '' ?>#<?= $rW ?>
              </span>
          </div>

            <img class="lb-avatar" src="<?= htmlspecialchars($img) ?>" alt="Avatar">
            <div class="lb-name">
                @<?= htmlspecialchars($row['username']) ?>
                <?php if ($isMe): ?><span class="lb-me-tag">you</span><?php endif; ?>
            </div>
            <div class="lb-points">
              <?php if ($view === 'points'): ?>
                <span class="lb-points-pill">All-time: <?= (int)$row['all_points'] ?> pts</span>
                <span class="lb-points-pill">Week: <?= (int)$row['week_points'] ?> pts</span>
              <?php else: ?>
                <span class="lb-points-pill">All-time: <?= number_format((float)$row['all_km'], 2) ?> km</span>
                <span class="lb-points-pill">Week: <?= number_format((float)$row['week_km'], 2) ?> km</span>
              <?php endif; ?>
            </div>

        </a>
        <?php endforeach; ?>

          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php if ($view === 'points'): ?>
    <section class="lb-hero">
      <div>
        <h2>Points leaderboard</h2>
        <p>Top users by achievement points.</p>
      </div>
    </section>
    <section class="lb-grid">

        <!-- All-time -->
        <div class="lb-card">
        <div class="lb-card-head">
            <h3>All-time points</h3>
            <div class="lb-sub">Top <?= (int)$limit ?></div>
        </div>

        <?php if (empty($all_time)): ?>
            <div class="lb-empty">No data yet.</div>
        <?php else: ?>
            <div class="lb-list">
            <?php foreach ($all_time as $i => $row):
                $rank = $i + 1;
                $img = avatarUrl($row['username'], $row['profile_image']);
                $isMe = $user_id && (int)$row['id'] === $user_id;
            ?>
                <a class="lb-row <?= $isMe ? 'me' : '' ?>" href="profile.php?user=<?= (int)$row['id'] ?>">
                    <div class="lb-rank">
                        <?= medal($rank) ? medal($rank) . ' ' : '' ?>#<?= $rank ?>
                    </div>
                    <img class="lb-avatar" src="<?= htmlspecialchars($img) ?>" alt="Avatar">
                    <div class="lb-name">
                        @<?= htmlspecialchars($row['username']) ?>
                        <?php if ($isMe): ?><span class="lb-me-tag">you</span><?php endif; ?>
                    </div>
                    <div class="lb-points"><?= (int)$row['points'] ?> pts</div>
                </a>
            <?php endforeach; ?>


            <?php
                // show "You" row if not already in top 100
                $inTop = false;
                if ($me_all && $user_id) {
                foreach ($all_time as $r) { if ((int)$r['id'] === $user_id) { $inTop = true; break; } }
                }
            ?>

            <?php if ($me_all && $user_id && !$inTop): ?>
                <div class="lb-divider">Your rank</div>
                <div class="lb-row me">
                <div class="lb-rank">#<?= (int)$me_all['rank'] ?></div>
                <img class="lb-avatar" src="<?= htmlspecialchars(avatarUrl($me_all['username'], $me_all['profile_image'])) ?>" alt="Avatar">
                <div class="lb-name">@<?= htmlspecialchars($me_all['username']) ?> <span class="lb-me-tag">you</span></div>
                <div class="lb-points"><?= (int)$me_all['points'] ?> pts</div>
                </div>
            <?php endif; ?>

            </div>
        <?php endif; ?>
        </div>

        <!-- Weekly -->
        <div class="lb-card">
        <div class="lb-card-head">
            <h3>This week's points</h3>
            <div class="lb-sub">Mon–Sun</div>
        </div>

        <?php if (empty($weekly)): ?>
            <div class="lb-empty">No points earned this week yet.</div>
        <?php else: ?>
            <div class="lb-list">
            <?php foreach ($weekly as $i => $row):
                $rank = $i + 1;
                $img = avatarUrl($row['username'], $row['profile_image']);
                $isMe = $user_id && (int)$row['id'] === $user_id;
            ?>
                <a class="lb-row <?= $isMe ? 'me' : '' ?>" href="profile.php?user=<?= (int)$row['id'] ?>">
                <div class="lb-rank">
                    <?= medal($rank) ? medal($rank) . ' ' : '' ?>#<?= $rank ?>
                </div>
                <img class="lb-avatar" src="<?= htmlspecialchars($img) ?>" alt="Avatar">
                <div class="lb-name">
                    @<?= htmlspecialchars($row['username']) ?>
                    <?php if ($isMe): ?><span class="lb-me-tag">you</span><?php endif; ?>
                </div>
                <div class="lb-points"><?= (int)$row['points'] ?> pts</div>
                </a>
            <?php endforeach; ?>

            <?php
                $inTopW = false;
                if ($me_week && $user_id) {
                foreach ($weekly as $r) { if ((int)$r['id'] === $user_id) { $inTopW = true; break; } }
                }
            ?>

            <?php if ($me_week && $user_id && !$inTopW): ?>
                <div class="lb-divider">Your rank</div>
                <a class="lb-row me" href="profile.php?user=<?= (int)$me_week['id'] ?>">
                <div class="lb-rank">#<?= (int)$me_week['rank'] ?></div>
                <img class="lb-avatar" src="<?= htmlspecialchars(avatarUrl($me_week['username'], $me_week['profile_image'])) ?>" alt="Avatar">
                <div class="lb-name">@<?= htmlspecialchars($me_week['username']) ?> <span class="lb-me-tag">you</span></div>
                <div class="lb-points"><?= (int)$me_week['points'] ?> pts</div>
                </a>
            <?php endif; ?>

            </div>
        <?php endif; ?>
        </div>

    </section>
  <?php endif; ?>
  <section class="lb-hero lb-hero-small">
</section>
  <?php if ($view === 'km'): ?>
    <section class="lb-hero">
      <div>
        <h2>Distance leaderboard</h2>
        <p>Top users by kilometres ran.</p>
      </div>
    </section>
    <section class="lb-grid">
    <!-- KM All-time -->
    <div class="lb-card">
        <div class="lb-card-head">
        <h3>All-time kilometres</h3>
        <div class="lb-sub">Top <?= (int)$limit ?></div>
        </div>

        <?php if (empty($km_all_time)): ?>
        <div class="lb-empty">No data yet.</div>
        <?php else: ?>
        <div class="lb-list">
            <?php foreach ($km_all_time as $i => $row):
            $rank = $i + 1;
            $img = avatarUrl($row['username'], $row['profile_image']);
            $isMe = $user_id && (int)$row['id'] === $user_id;
            $km = (float)$row['km'];
            ?>
            <a class="lb-row <?= $isMe ? 'me' : '' ?>" href="profile.php?user=<?= (int)$row['id'] ?>">
                <div class="lb-rank">
                <?= medal($rank) ? medal($rank) . ' ' : '' ?>#<?= $rank ?>
                </div>
                <img class="lb-avatar" src="<?= htmlspecialchars($img) ?>" alt="Avatar">
                <div class="lb-name">
                @<?= htmlspecialchars($row['username']) ?>
                <?php if ($isMe): ?><span class="lb-me-tag">you</span><?php endif; ?>
                </div>
                <div class="lb-points"><?= number_format($km, 2) ?> km</div>
            </a>
            <?php endforeach; ?>

            <?php
            $inTopKm = false;
            if (!empty($me_km_all) && $user_id) {
                foreach ($km_all_time as $r) { if ((int)$r['id'] === $user_id) { $inTopKm = true; break; } }
            }
            ?>

            <?php if (!empty($me_km_all) && $user_id && !$inTopKm): ?>
            <div class="lb-divider">Your rank</div>
            <a class="lb-row me" href="profile.php?user=<?= (int)$me_km_all['id'] ?>">
                <div class="lb-rank">#<?= (int)$me_km_all['rank'] ?></div>
                <img class="lb-avatar" src="<?= htmlspecialchars(avatarUrl($me_km_all['username'], $me_km_all['profile_image'])) ?>" alt="Avatar">
                <div class="lb-name">@<?= htmlspecialchars($me_km_all['username']) ?> <span class="lb-me-tag">you</span></div>
                <div class="lb-points"><?= number_format((float)$me_km_all['km'], 2) ?> km</div>
            </a>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    <!-- KM Weekly -->
    <div class="lb-card">
        <div class="lb-card-head">
        <h3>This week kilometres</h3>
        <div class="lb-sub">Mon–Sun</div>
        </div>

        <?php if (empty($km_weekly)): ?>
        <div class="lb-empty">No distance ran this week yet.</div>
        <?php else: ?>
        <div class="lb-list">
            <?php foreach ($km_weekly as $i => $row):
            $rank = $i + 1;
            $img = avatarUrl($row['username'], $row['profile_image']);
            $isMe = $user_id && (int)$row['id'] === $user_id;
            $km = (float)$row['km'];
            ?>
            <a class="lb-row <?= $isMe ? 'me' : '' ?>" href="profile.php?user=<?= (int)$row['id'] ?>">
                <div class="lb-rank">
                <?= medal($rank) ? medal($rank) . ' ' : '' ?>#<?= $rank ?>
                </div>
                <img class="lb-avatar" src="<?= htmlspecialchars($img) ?>" alt="Avatar">
                <div class="lb-name">
                @<?= htmlspecialchars($row['username']) ?>
                <?php if ($isMe): ?><span class="lb-me-tag">you</span><?php endif; ?>
                </div>
                <div class="lb-points"><?= number_format($km, 2) ?> km</div>
            </a>
            <?php endforeach; ?>

            <?php
            $inTopKmW = false;
            if (!empty($me_km_week) && $user_id) {
                foreach ($km_weekly as $r) { if ((int)$r['id'] === $user_id) { $inTopKmW = true; break; } }
            }
            ?>

            <?php if (!empty($me_km_week) && $user_id && !$inTopKmW): ?>
            <div class="lb-divider">Your rank</div>
            <a class="lb-row me" href="profile.php?user=<?= (int)$me_km_week['id'] ?>">
                <div class="lb-rank">#<?= (int)$me_km_week['rank'] ?></div>
                <img class="lb-avatar" src="<?= htmlspecialchars(avatarUrl($me_km_week['username'], $me_km_week['profile_image'])) ?>" alt="Avatar">
                <div class="lb-name">@<?= htmlspecialchars($me_km_week['username']) ?> <span class="lb-me-tag">you</span></div>
                <div class="lb-points"><?= number_format((float)$me_km_week['km'], 2) ?> km</div>
            </a>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>
    </section>
  <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
