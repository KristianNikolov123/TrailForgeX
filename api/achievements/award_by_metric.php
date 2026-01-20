<?php

function tf_metric_value(mysqli $connection, int $user_id, string $metric) {
  switch ($metric) {
    case 'favourite_count': {
      $stmt = $connection->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->bind_result($v);
      $stmt->fetch();
      $stmt->close();
      return (int)$v;
    }

    case 'share_count': {
      $stmt = $connection->prepare("SELECT COUNT(*) FROM route_shares WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->bind_result($v);
      $stmt->fetch();
      $stmt->close();
      return (int)$v;
    }

    case 'run_count': {
      $stmt = $connection->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->bind_result($v);
      $stmt->fetch();
      $stmt->close();
      return (int)$v;
    }

    case 'run_distance_km': {
      $stmt = $connection->prepare("SELECT COALESCE(SUM(distance_km),0) FROM activities WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->bind_result($v);
      $stmt->fetch();
      $stmt->close();
      return (float)$v;
    }

    case 'run_elevation_m': {
      $stmt = $connection->prepare("SELECT COALESCE(SUM(elevation_gain_m),0) FROM activities WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->bind_result($v);
      $stmt->fetch();
      $stmt->close();
      return (int)$v;
    }
      

    default:
      return null;
  }
}

/**
 * Awards all achievements for a metric where current_value >= target.
 * Inserts into user_achievements if missing.
 * Returns list of newly unlocked achievements (as objects for toast).
 */
function tf_award_by_metric(mysqli $connection, int $user_id, string $metric): array {
  $current = tf_metric_value($connection, $user_id, $metric);
  if ($current === null) return [];

  // All achievements using this metric
  $stmt = $connection->prepare("
    SELECT id, code, title, description, icon, points, target
    FROM achievements
    WHERE metric = ?
      AND target IS NOT NULL
    ORDER BY target ASC
  ");
  $stmt->bind_param("s", $metric);
  $stmt->execute();
  $res = $stmt->get_result();
  $achs = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  if (!$achs) return [];

  // Get already-earned for this user (avoid duplicates)
  $earned = [];
  $stmt = $connection->prepare("
    SELECT achievement_id FROM user_achievements WHERE user_id = ?
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc()) {
    $earned[(int)$row['achievement_id']] = true;
  }
  $stmt->close();

  $insert = $connection->prepare("
    INSERT INTO user_achievements (user_id, achievement_id, earned_at)
    VALUES (?, ?, NOW())
  ");

  $unlocked = [];

  foreach ($achs as $a) {
    $aid = (int)$a['id'];
    $target = (float)$a['target'];

    if ((float)$current < $target) continue;
    if (isset($earned[$aid])) continue;

    $insert->bind_param("ii", $user_id, $aid);
    if ($insert->execute()) {
      $unlocked[] = [
        'code' => $a['code'],
        'title' => $a['title'],
        'description' => $a['description'],
        'icon' => $a['icon'],
        'points' => (int)$a['points'],
        'earned_at' => date('Y-m-d H:i:s')
      ];
    }
  }

  $insert->close();
  return $unlocked;
}
