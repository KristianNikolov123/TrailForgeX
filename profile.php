<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'dbconn.php'; // unified DB connection
$user_id = $_SESSION['user_id'];

$stmt = $connection->prepare('SELECT username, email, profile_image, bio, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $profile_image, $bio, $created_at);
$stmt->fetch();
$stmt->close();
?>
<?php include 'navbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | TrailForgeX</title>
    <link rel="stylesheet" href="master.css">
</head>
<body>
<main class="profile-main" style="max-width:560px;margin:2em auto 3em auto;background:#241b24;padding:1.9em 2.3em;border-radius:16px;box-shadow:0 3px 39px #ea5f9430;">
    <h2 style="color:#ea5f94;margin-bottom:.7em;">My Profile</h2>
    <div style="display:flex;align-items:center;gap:1.5em;margin-bottom:1.5em;">
        <img src="<?= htmlspecialchars($profile_image ?? 'https://api.dicebear.com/6.x/identicon/svg?seed=' . urlencode($username)) ?>" alt="Profile Img" style="border-radius:50%;width:90px;height:90px;background:#332033;object-fit:cover;border:2px solid #ea5f94;">
        <div>
            <div style="font-size:1.25em;font-weight:bold;color:#fff;">@<?= htmlspecialchars($username) ?></div>
            <div style="font-size:1em;color:#c5a2c7;"><span>Email:</span> <?= htmlspecialchars($email) ?></div>
        </div>
    </div>
    <div style="margin-bottom:1em;color:#ead0ed;">
        <b>Bio:</b> <br>
        <?= nl2br(htmlspecialchars($bio ?: 'No bio yet.')) ?>
    </div>
    <div style="color:#968dab;font-size:.94em;">Joined: <?= htmlspecialchars(date('Y-m-d', strtotime($created_at))) ?></div>
    <hr style="margin:2em 0 1.1em 0;border-color:#512545;">
    <form method="post" action="profile.php" enctype="multipart/form-data">
        <label for="bio" style="color:#ea5f94;">Edit Bio:</label><br>
        <textarea id="bio" name="bio" rows="3" style="width:100%;margin-bottom:1em;resize:vertical;padding:.7em;border-radius:7px;background:#2b1d29;color:#fff;"><?= htmlspecialchars($bio) ?></textarea><br>
        <label style="color:#ea5f94;">Profile image:</label>
        <input type="file" accept="image/*" name="profile_image" style="margin-bottom:1em;">
        <button type="submit" style="margin-top:.6em;padding:.54em 2.3em;background:#ea5f94;color:#fff;font-weight:bold;border-radius:7px;border:none;">Save Changes</button>
    </form>
    <?php
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $updates = [];
        $params = [];
        $types = '';
        if (isset($_POST['bio'])) {
            $updates[] = 'bio=?';
            $params[] = $_POST['bio'];
            $types .= 's';
        }
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $target = 'uploads/profiles/user_' . $user_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                $updates[] = 'profile_image=?';
                $params[] = $target;
                $types .= 's';
            }
        }
        if ($updates) {
            $query = 'UPDATE users SET ' . implode(',', $updates) . ' WHERE id=?';
            $params[] = $user_id;
            $types .= 'i';
            $stmt = $connection->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
            echo '<div style="margin:1em 0;color:#65e68c;">Profile updated!</div>';
            echo '<script>setTimeout(()=>window.location.reload(),900);</script>';
        }
    }
    ?>
</main>

