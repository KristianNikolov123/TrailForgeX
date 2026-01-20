<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'dbconn.php';
$viewer_id = (int)$_SESSION['user_id'];
$user_id = $viewer_id;

if (isset($_GET['user']) && ctype_digit($_GET['user'])) {
  $user_id = (int)$_GET['user'];
}

/* =========================
   HANDLE PROFILE UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id === $viewer_id) {

    $updates = [];
    $params  = [];
    $types   = '';

    // --- Update bio ---
    if (isset($_POST['bio'])) {
        $updates[] = 'bio = ?';
        $params[]  = trim($_POST['bio']);
        $types    .= 's';
    }

    // --- Handle profile image upload ---
    if (!empty($_FILES['profile_image']['tmp_name'])) {

        $uploadDir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpFile = $_FILES['profile_image']['tmp_name'];

        // Validate MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpFile);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        if (!isset($allowed[$mime])) {
            $_SESSION['profile_error'] = 'Invalid image type.';
            header('Location: profile.php');
            exit;
        }

        $ext  = $allowed[$mime];
        $rand = bin2hex(random_bytes(6)); // 12 hex chars

        // user_5_1705500000_ab12cd34ef56.png
        $filename   = "user_{$user_id}_" . time() . "_{$rand}.{$ext}";
        $targetPath = $uploadDir . $filename;
        $publicPath = "uploads/profiles/" . $filename;

        if (move_uploaded_file($tmpFile, $targetPath)) {

            // OPTIONAL: delete old image
            /*
            $old = $connection->prepare("SELECT profile_image FROM users WHERE id = ?");
            $old->bind_param('i', $user_id);
            $old->execute();
            $old->bind_result($oldImg);
            $old->fetch();
            $old->close();

            if ($oldImg && str_starts_with($oldImg, 'uploads/profiles/') && file_exists(__DIR__ . '/' . $oldImg)) {
                unlink(__DIR__ . '/' . $oldImg);
            }
            */

            $updates[] = 'profile_image = ?';
            $params[]  = $publicPath;
            $types    .= 's';
        }
    }

    // --- Save changes ---
    if ($updates) {
        $params[] = $user_id;
        $types   .= 'i';

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['profile_success'] = true;
    header('Location: profile.php');
    exit;
}

/* =========================
   LOAD USER DATA
========================= */
$stmt = $connection->prepare('
  SELECT username, email, profile_image, bio, created_at,
         featured_badge_1, featured_badge_2, featured_badge_3
  FROM users WHERE id = ?
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $profile_image, $bio, $created_at,
                   $fb1, $fb2, $fb3);

$stmt->fetch();
$stmt->close();

$featuredIds = array_values(array_filter([$fb1, $fb2, $fb3], fn($x) => !empty($x)));

$badgesToShow = [];

/* If user has featured badges, show them (only if earned) */
if (!empty($featuredIds)) {
  $in = implode(',', array_fill(0, count($featuredIds), '?'));
  $types = str_repeat('i', count($featuredIds) + 1);

  $sql = "
    SELECT a.id, a.title, a.icon, ua.earned_at
    FROM achievements a
    JOIN user_achievements ua
      ON ua.achievement_id = a.id AND ua.user_id = ?
    WHERE a.id IN ($in)
  ";

  $stmt2 = $connection->prepare($sql);
  $params = array_merge([$user_id], $featuredIds);

  // bind_param needs references
  $bind = [];
  $bind[] = $types;
  foreach ($params as $k => $v) $bind[] = &$params[$k];

  call_user_func_array([$stmt2, 'bind_param'], $bind);
  $stmt2->execute();
  $res = $stmt2->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $stmt2->close();

  // Keep order according to featuredIds
  $byId = [];
  foreach ($rows as $r) $byId[(int)$r['id']] = $r;
  foreach ($featuredIds as $id) {
    if (isset($byId[(int)$id])) $badgesToShow[] = $byId[(int)$id];
  }
}

/* Fallback: latest earned badges (top 3) */
if (count($badgesToShow) < 3) {
  $stmt3 = $connection->prepare("
    SELECT a.id, a.title, a.icon, ua.earned_at
    FROM user_achievements ua
    JOIN achievements a ON a.id = ua.achievement_id
    WHERE ua.user_id = ?
    ORDER BY ua.earned_at DESC
    LIMIT 3
  ");
  $stmt3->bind_param('i', $user_id);
  $stmt3->execute();
  $res3 = $stmt3->get_result();
  $badgesToShow = $res3->fetch_all(MYSQLI_ASSOC);
  $stmt3->close();
}


include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
</head>
<body>

<main class="profile-main"
      style="max-width:560px;margin:2em auto 3em auto;
             background:#241b24;padding:1.9em 2.3em;
             border-radius:16px;box-shadow:0 3px 39px #ea5f9430;">

    <h2 style="color:#ea5f94;margin-bottom:.7em;">My Profile</h2>

    <?php if (!empty($_SESSION['profile_success'])): ?>
        <div style="color:#65e68c;margin-bottom:1em;">Profile updated successfully!</div>
        <?php unset($_SESSION['profile_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['profile_error'])): ?>
        <div style="color:#ff6b6b;margin-bottom:1em;">
            <?= htmlspecialchars($_SESSION['profile_error']) ?>
        </div>
        <?php unset($_SESSION['profile_error']); ?>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1.5em;">
        <img
            src="<?= htmlspecialchars(
                $profile_image ?: 'https://api.dicebear.com/6.x/identicon/svg?seed=' . urlencode($username)
            ) ?>"
            alt="Profile image"
            style="border-radius:50%;width:90px;height:90px;
                   background:#332033;object-fit:cover;
                   border:2px solid #ea5f94;"
        >
        <div>
            <div style="font-size:1.25em;font-weight:bold;color:#fff;">
                @<?= htmlspecialchars($username) ?>
            </div>
            <div style="color:#c5a2c7;">
                <?= htmlspecialchars($email) ?>
            </div>
        </div>
    </div>

    <div style="margin-bottom:1em;color:#ead0ed;">
        <b>Bio:</b><br>
        <?= nl2br(htmlspecialchars($bio ?: 'No bio yet.')) ?>
    </div>

    <div style="color:#968dab;font-size:.94em;">
        Joined: <?= htmlspecialchars(date('Y-m-d', strtotime($created_at))) ?>
    </div>
    <hr style="margin:2em 0 1.1em 0;border-color:#512545;">

    <section class="profile-achievements">
    <div class="profile-ach-head">
        <h3>Achievements</h3>
        <a class="profile-ach-viewall" href="achievements.php">View all</a>
    </div>

    <?php if (empty($badgesToShow)): ?>
        <div class="profile-ach-empty">
        No achievements yet. Generate, save, favourite, or share routes to earn badges.
        <div style="margin-top:.8em;">
            <a class="cta-button" style="display:inline-block;padding:.55em 1.2em;" href="generate.php">Generate a route</a>
        </div>
        </div>
    <?php else: ?>
        <div class="profile-ach-row">
        <?php foreach ($badgesToShow as $b): ?>
            <a class="profile-ach-card" href="achievements.php#ach_<?= (int)$b['id'] ?>">
            <div class="profile-ach-icon"><?= htmlspecialchars($b['icon']) ?></div>
            <div class="profile-ach-title"><?= htmlspecialchars($b['title']) ?></div>
            <div class="profile-ach-date">
                <?= htmlspecialchars(date('Y-m-d', strtotime($b['earned_at']))) ?>
            </div>
            </a>
        <?php endforeach; ?>
        </div>
        <div class="profile-ach-hint">
        Tip: on the Achievements page you’ll be able to pick 3 badges to feature on your profile.
        </div>
    <?php endif; ?>
    </section>


    <hr style="margin:2em 0 1.1em 0;border-color:#512545;">
    <?php if ($user_id === $viewer_id): ?>
        <form method="post" enctype="multipart/form-data">
            <label style="color:#ea5f94;">Edit Bio</label>
            <textarea
                name="bio"
                rows="3"
                style="width:100%;margin-bottom:1em;
                    resize:vertical;padding:.7em;
                    border-radius:7px;background:#2b1d29;
                    color:#fff;"
            ><?= htmlspecialchars($bio) ?></textarea>

            <label style="color:#ea5f94;">Profile image</label>
            <input type="file" name="profile_image" accept="image/*" style="margin-bottom:1em;">

            <button type="submit"
                    style="padding:.54em 2.3em;
                        background:#ea5f94;color:#fff;
                        font-weight:bold;border-radius:7px;border:none;">
                Save Changes
            </button>
        </form>
    <?php endif; ?>
</main>

</body>
</html>
