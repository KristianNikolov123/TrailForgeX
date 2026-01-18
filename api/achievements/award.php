<?php
// Returns achievement data if it was newly unlocked, otherwise returns null.
function awardAchievement(mysqli $conn, int $userId, string $code): ?array {
    // Find achievement
    $stmt = $conn->prepare("SELECT id, code, title, description, icon, points FROM achievements WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $ach = $res->fetch_assoc();
    $stmt->close();

    if (!$ach) return null;

    // Insert only if not already earned
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_achievements (user_id, achievement_id)
        VALUES (?, ?)
    ");
    $achId = (int)$ach['id'];
    $stmt->bind_param("ii", $userId, $achId);
    $stmt->execute();
    $inserted = ($stmt->affected_rows > 0); // ✅ 1 if newly inserted
    $stmt->close();

    if (!$inserted) return null;

    // Optional: include earned date (nice for UI)
    $stmt = $conn->prepare("
        SELECT earned_at FROM user_achievements
        WHERE user_id = ? AND achievement_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $userId, $achId);
    $stmt->execute();
    $stmt->bind_result($earnedAt);
    $stmt->fetch();
    $stmt->close();

    return [
        "code" => $ach["code"],
        "title" => $ach["title"],
        "description" => $ach["description"],
        "icon" => $ach["icon"],
        "points" => (int)$ach["points"],
        "earned_at" => $earnedAt ? date("Y-m-d", strtotime($earnedAt)) : null
    ];
}
