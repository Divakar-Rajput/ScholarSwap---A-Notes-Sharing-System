<?php
session_start();
require_once '../../config/connection.php';
header('Content-Type: application/json');

// ── Session check first — always ─────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$followerId  = (int)$_SESSION['user_id'];
$followingId = (int)($_POST['following_id'] ?? 0);

if (!$followingId || $followerId === $followingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

// ── Wrap EVERYTHING in try/catch so no crash ever returns blank output ────
// Previously $nameStmt ran OUTSIDE try/catch — a DB error there would
// crash with no JSON response, causing the JS fetch to throw and the
// followers count to stay blank.
try {

    // ── Fetch follower's display name ─────────────────────────────────────
    // Uses COALESCE: students → tutors → username fallback.
    // IMPORTANT: only use ONE named placeholder ':id' — MySQL doesn't allow
    // the same named placeholder twice in one query with PDO emulation off.
    // Fix: switch the subqueries to positional ? placeholders.
    $nameStmt = $conn->prepare("
        SELECT COALESCE(
            (SELECT CONCAT(s.first_name, ' ', s.last_name)
             FROM students s WHERE s.user_id = ?),
            (SELECT CONCAT(t.first_name, ' ', t.last_name)
             FROM tutors t WHERE t.user_id = ?),
            u.username
        ) AS display_name
        FROM users u
        WHERE u.user_id = ?
        LIMIT 1
    ");
    // Pass $followerId three times — once for each ? placeholder
    $nameStmt->execute([$followerId, $followerId, $followerId]);
    $followerName = (string)($nameStmt->fetchColumn() ?: 'Someone');

    // ── Check if already following ────────────────────────────────────────
    $check = $conn->prepare("
        SELECT COUNT(*) FROM follows
        WHERE follower_id = ? AND following_id = ?
    ");
    $check->execute([$followerId, $followingId]);
    $isFollowing = (bool)$check->fetchColumn();

    if ($isFollowing) {
        // ── Unfollow ──────────────────────────────────────────────────────
        $conn->prepare("
            DELETE FROM follows
            WHERE follower_id = ? AND following_id = ?
        ")->execute([$followerId, $followingId]);

        $status = 'unfollowed';
    } else {
        // ── Follow ────────────────────────────────────────────────────────
        $conn->prepare("
            INSERT INTO follows (follower_id, following_id)
            VALUES (?, ?)
        ")->execute([$followerId, $followingId]);

        $status = 'followed';

        // ── Insert notification directly (no helper dependency) ───────────
        // Removed require_once for notifications.php because a wrong path
        // causes a fatal error that returns a blank/HTML response instead
        // of JSON, breaking the JS fetch silently.
        // Doing a direct INSERT here is safer and has zero dependencies.
        $notif = $conn->prepare("
            INSERT INTO notifications
                (user_id, type, title, message, from_user_id, from_name)
            VALUES
                (?, 'admin_message', ?, ?, ?, ?)
        ");
        $notif->execute([
            $followingId,
            $followerName . ' started following you',
            $followerName . ' is now following your uploads and activity.',
            $followerId,
            $followerName,
        ]);
    }

    // ── Get updated follower count ────────────────────────────────────────
    $cnt = $conn->prepare("
        SELECT COUNT(*) FROM follows WHERE following_id = ?
    ");
    $cnt->execute([$followingId]);
    $followers = (int)$cnt->fetchColumn();

    echo json_encode([
        'success'   => true,
        'status'    => $status,
        'followers' => $followers,
    ]);
} catch (Throwable $e) {
    // Log full detail server-side, return clean JSON to client
    error_log('[follow_user] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}
