<?php

$AUTH_PAGE_NAME = 'your profile';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/dbconn.php';

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

    // --- Update username ---
    if (isset($_POST['username'])) {
        $newUsername = trim($_POST['username']);

        // basic validation
        if ($newUsername === '' || strlen($newUsername) < 3 || strlen($newUsername) > 20) {
            $_SESSION['profile_error'] = 'Username must be 3–20 characters.';
            header('Location: profile.php');
            exit;
        }

        // allow letters, numbers, underscore, dot
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $newUsername)) {
            $_SESSION['profile_error'] = 'Username can contain only letters, numbers, "_" and "."';
            header('Location: profile.php');
            exit;
        }

        // check uniqueness (ignore yourself)
        $chk = $connection->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $chk->bind_param('si', $newUsername, $user_id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $chk->close();
            $_SESSION['profile_error'] = 'That username is already taken.';
            header('Location: profile.php');
            exit;
        }
        $chk->close();

        $updates[] = 'username = ?';
        $params[]  = $newUsername;
        $types    .= 's';
    }


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


include 'includes/navbar.php';
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

<main class="profile-page">
  <div class="profile-shell">

    <!-- LEFT: Profile card -->
    <section class="profile-card">
      <div class="profile-cover"></div>

      <div class="profile-header">
        <img
          class="profile-avatar"
          src="<?= htmlspecialchars(
            $profile_image ?: 'https://api.dicebear.com/6.x/identicon/svg?seed=' . urlencode($username)
          ) ?>"
          alt="Profile image"
        >
        <div class="profile-identity">
          <div class="profile-username">@<?= htmlspecialchars($username) ?></div>
          <div class="profile-email"><?= htmlspecialchars($email) ?></div>
        </div>
      </div>

      <?php if (!empty($_SESSION['profile_success'])): ?>
        <div class="profile-alert success">Profile updated successfully!</div>
        <?php unset($_SESSION['profile_success']); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION['profile_error'])): ?>
        <div class="profile-alert error">
          <?= htmlspecialchars($_SESSION['profile_error']) ?>
        </div>
        <?php unset($_SESSION['profile_error']); ?>
      <?php endif; ?>

      <div class="profile-meta-row">
        <span class="profile-pill">
          Joined: <?= htmlspecialchars(date('Y-m-d', strtotime($created_at))) ?>
        </span>
        <?php if ($user_id !== $viewer_id): ?>
          <span class="profile-pill">Viewing profile</span>
        <?php else: ?>
          <span class="profile-pill">Your account</span>
        <?php endif; ?>
      </div>

      <div class="profile-body">
        <h3 class="profile-section-title">Bio</h3>
        <div class="profile-bio">
            <?= nl2br(htmlspecialchars($bio ?: 'No bio yet.')) ?>
        </div>

        <?php if ($user_id === $viewer_id): ?>
            <div style="margin-top:1rem;">
            <button class="edit-open-btn" type="button" data-modal-open>
                Edit profile
            </button>
            </div>
        <?php endif; ?>
      </div>


    </section>

    <!-- RIGHT: Achievements + Edit panel -->
    <aside class="profile-side">

      <section class="profile-panel">
        <div class="profile-panel-head">
          <h3>Achievements</h3>
          <a href="achievements.php">View all</a>
        </div>

        <?php if (empty($badgesToShow)): ?>
          <div class="profile-ach-empty">
            No achievements yet. Generate, save, favourite, or share routes to earn badges.
            <div style="margin-top:.9rem;">
              <a class="cta-button" style="display:inline-block;padding:.55em 1.2em;" href="generate.php">
                Generate a route
              </a>
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

    </aside>
  </div>
</main>

<?php if ($user_id === $viewer_id): ?>
  <div id="editModal" class="modal-backdrop" aria-hidden="true">
    <div class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="editTitle">
      <div class="modal-head">
        <h3 id="editTitle">Edit profile</h3>
        <button type="button" class="modal-x" data-modal-close>✕</button>
      </div>

      <form class="profile-form" method="post" enctype="multipart/form-data">
        <div class="form-row">
          <label for="username">Username</label>
          <input
            id="username"
            name="username"
            type="text"
            value="<?= htmlspecialchars($username) ?>"
            maxlength="20"
            required
          >
          <div class="modal-help">3–20 chars. Letters, numbers, “_” and “.”</div>
        </div>

        <div class="form-row">
          <label for="bio">Bio</label>
          <textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($bio) ?></textarea>
        </div>

        <div class="form-row">
          <label for="profile_image">Profile image</label>
          <input id="profile_image" type="file" name="profile_image" accept="image/*">
          <div class="modal-help">Tip: square images look best.</div>
        </div>

        <div class="modal-actions">
          <button class="save-btn" type="submit">Save changes</button>
          <button class="cancel-btn" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<script src="main.js" defer></script>
</body>
</html>
