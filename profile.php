<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'dbconn.php';
$user_id = $_SESSION['user_id'];

/* =========================
   HANDLE PROFILE UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
$stmt = $connection->prepare(
    'SELECT username, email, profile_image, bio, created_at
     FROM users WHERE id = ?'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $profile_image, $bio, $created_at);
$stmt->fetch();
$stmt->close();

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
</main>

</body>
</html>
