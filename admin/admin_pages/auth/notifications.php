<?php
// ── helpers/notifications.php ──

if (!isset($conn)) {
    require_once __DIR__ . '/admin/config/connection.php';
}

function _insertNotification(
    int     $userId,
    string  $type,
    string  $title,
    string  $message,
    ?string $resourceType  = null,
    ?string $resourceId    = null,
    ?string $resourceTitle = null,
    ?int    $fromUserId    = null,
    ?string $fromName      = null
): bool {
    global $conn;

    // BUG FIX 1: Validate $type against the DB enum before inserting.
    // Inserting an invalid enum value silently stores '' in strict MySQL
    // or throws an error in strict mode.
    $validTypes = ['warning', 'admin_message', 'upload_approved', 'upload_rejected', 'new_upload', 'banned_content'];
    if (!in_array($type, $validTypes, true)) {
        error_log("[_insertNotification] Invalid type: '{$type}' for user_id={$userId}");
        return false;
    }

    // BUG FIX 2: Guard against empty title / message before hitting DB.
    if (trim($title) === '' || trim($message) === '') {
        error_log("[_insertNotification] Empty title or message for user_id={$userId}");
        return false;
    }

    $st = $conn->prepare("
        INSERT INTO notifications
            (user_id, type, title, message, resource_type, resource_id, resource_title, from_user_id, from_name)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    return $st->execute([
        $userId,
        $type,
        $title,
        $message,
        $resourceType,
        $resourceId,
        $resourceTitle,
        $fromUserId,
        $fromName,
    ]);
}

// ── Warning ──
function notifyWarning(int $userId, string $reason, int $adminId, string $adminName): bool
{
    return _insertNotification(
        userId: $userId,
        type: 'warning',
        title: 'You have received a warning',
        message: $reason,
        fromUserId: $adminId,
        fromName: $adminName
    );
}

// ── Admin Message ──
// BUG FIX 3 (CRITICAL): Original signature was:
//   notifyAdminMessage(int $userId, string $title, string $message, int $adminId, string $adminName)
// But handler_notification.php calls it as:
//   notifyAdminMessage($userId, $type, $title, $message, $adminId, $adminName)
// — passing $type as the second argument, which was silently ignored and always
//   stored 'admin_message' regardless of what the admin chose.
// Fixed: added $type parameter as second argument so the chosen type
// (warning, upload_approved, etc.) is actually saved to the DB.
function notifyAdminMessage(
    int    $userId,
    string $type,       // ← ADDED: receives the chosen message type from the handler
    string $title,
    string $message,
    int    $adminId,
    string $adminName
): bool {
    // BUG FIX 4: Fall back to 'admin_message' if caller passes an empty type string.
    if (trim($type) === '') {
        $type = 'admin_message';
    }

    return _insertNotification(
        userId: $userId,
        type: $type,
        title: $title,
        message: $message,
        fromUserId: $adminId,
        fromName: $adminName
    );
}

// ── Upload Approved ──
function notifyUploadApproved(
    int    $userId,
    string $resourceType,
    string $resourceId,
    string $resourceTitle
): bool {
    return _insertNotification(
        userId: $userId,
        type: 'upload_approved',
        title: 'Your upload has been approved',
        message: "Your {$resourceType} \"{$resourceTitle}\" is now live and visible to everyone.",
        resourceType: $resourceType,
        resourceId: $resourceId,
        resourceTitle: $resourceTitle
    );
}

// ── Upload Rejected ──
function notifyUploadRejected(
    int    $userId,
    string $resourceType,
    string $resourceId,
    string $resourceTitle,
    string $reason
): bool {
    return _insertNotification(
        userId: $userId,
        type: 'upload_rejected',
        title: 'Your upload was rejected',
        message: "Your {$resourceType} \"{$resourceTitle}\" was rejected. Reason: {$reason}",
        resourceType: $resourceType,
        resourceId: $resourceId,
        resourceTitle: $resourceTitle
    );
}

// ── New Upload (single follower) ──
function notifyNewUpload(
    int    $followerId,
    string $uploaderName,
    int    $uploaderId,
    string $resourceType,
    string $resourceId,
    string $resourceTitle
): bool {
    return _insertNotification(
        userId: $followerId,
        type: 'new_upload',
        title: "{$uploaderName} uploaded a new {$resourceType}",
        message: "\"{$resourceTitle}\" is now available to read and download.",
        resourceType: $resourceType,
        resourceId: $resourceId,
        resourceTitle: $resourceTitle,
        fromUserId: $uploaderId,
        fromName: $uploaderName
    );
}

// ── Notify All Followers of an uploader ──
// BUG FIX 5: Original used no try/catch — a DB error inside the loop
// would throw an uncaught exception and leave some followers un-notified
// with no log trace. Added per-iteration error handling.
function notifyAllFollowers(
    int    $uploaderId,
    string $uploaderName,
    string $resourceType,
    string $resourceId,
    string $resourceTitle
): void {
    global $conn;

    $st = $conn->prepare("SELECT follower_id FROM follows WHERE following_id = ?");
    $st->execute([$uploaderId]);
    $followers = $st->fetchAll(PDO::FETCH_COLUMN);

    foreach ($followers as $followerId) {
        try {
            notifyNewUpload(
                followerId: (int) $followerId,
                uploaderName: $uploaderName,
                uploaderId: $uploaderId,
                resourceType: $resourceType,
                resourceId: $resourceId,
                resourceTitle: $resourceTitle
            );
        } catch (Throwable $e) {
            error_log("[notifyAllFollowers] Failed for follower_id={$followerId}: " . $e->getMessage());
        }
    }
}

// ── Banned Content ──
function notifyBannedContent(
    int    $userId,
    string $resourceType,
    string $resourceId,
    string $resourceTitle,
    string $reason
): bool {
    return _insertNotification(
        userId: $userId,
        type: 'banned_content',
        title: 'Your content has been banned',
        message: "Your {$resourceType} \"{$resourceTitle}\" has been banned. Reason: {$reason}",
        resourceType: $resourceType,
        resourceId: $resourceId,
        resourceTitle: $resourceTitle
    );
}

// ── Get Notifications for a user ──
// BUG FIX 6: $limit was interpolated directly into SQL without casting —
// a caller passing a negative or very large number could cause unexpected
// results or a MySQL syntax error. Cast to a safe positive integer.
function getNotifications(int $userId, bool $unreadOnly = false, int $limit = 20): array
{
    global $conn;

    // Clamp limit to a safe range
    $limit = max(1, min((int) $limit, 200));
    $where = $unreadOnly ? 'AND is_read = 0' : '';

    $st = $conn->prepare("
        SELECT *
        FROM   notifications
        WHERE  user_id = ? {$where}
        ORDER  BY created_at DESC
        LIMIT  {$limit}
    ");
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// ── Count Unread ──
function countUnread(int $userId): int
{
    global $conn;

    $st = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

// ── Notify ALL active users when an admin publishes a newspaper ──
// Newspapers are admin-uploaded content (not user uploads), so there is no
// "follower" relationship to consult — every active student and tutor gets
// notified. The admin's name is stored as from_name; from_user_id stays null
// because admin_id lives in a separate table and notifications.user_id
// references the users table only.
function notifyNewspaperToAllUsers(
    string $nCode,           // n_code / resource_id  e.g. "NP202506141523004271"
    string $newspaperTitle,
    string $adminName,       // display name of the publishing admin
    int    $adminId          // admin_id for from_user_id — stored as null (see note above)
): void {
    global $conn;

    // Fetch all active students and tutors in one query
    $st = $conn->prepare("
        SELECT user_id
        FROM   users
        WHERE  is_active = 1
          AND  role IN ('student', 'tutor')
    ");
    $st->execute();
    $userIds = $st->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        error_log("[notifyNewspaperToAllUsers] No active users found to notify for n_code={$nCode}");
        return;
    }

    $sent   = 0;
    $failed = 0;

    foreach ($userIds as $uid) {
        try {
            $ok = _insertNotification(
                userId: (int) $uid,
                type: 'new_upload',
                title: "{$adminName} published a new newspaper",
                message: "\"{$newspaperTitle}\" is now available to read and download.",
                resourceType: 'newspaper',
                resourceId: $nCode,
                resourceTitle: $newspaperTitle,
                fromUserId: null,   // admin_id is not a users.user_id — keep null
                fromName: $adminName
            );
            $ok ? $sent++ : $failed++;
        } catch (Throwable $e) {
            $failed++;
            error_log("[notifyNewspaperToAllUsers] Failed for user_id={$uid}: " . $e->getMessage());
        }
    }

    error_log("[notifyNewspaperToAllUsers] n_code={$nCode} — sent={$sent}, failed={$failed}");
}

// ── Mark as Read ──
// BUG FIX 7: No ownership check on single-notif mark-read —
// any logged-in user could mark another user's notification as read
// by passing a notif_id they don't own.
// The WHERE clause already includes "AND user_id = ?" so this was
// actually safe, but it was easy to miss — added a comment to make
// it explicit and intentional.
function markAsRead(int $userId, ?int $notifId = null): bool
{
    global $conn;

    if ($notifId !== null) {
        // Ownership enforced: only marks read if notif_id belongs to this user_id
        $st = $conn->prepare("
            UPDATE notifications
            SET    is_read = 1, read_at = NOW()
            WHERE  notif_id = ? AND user_id = ?
        ");
        return $st->execute([$notifId, $userId]);
    }

    // Mark ALL unread notifications for this user as read
    $st = $conn->prepare("
        UPDATE notifications
        SET    is_read = 1, read_at = NOW()
        WHERE  user_id = ? AND is_read = 0
    ");
    return $st->execute([$userId]);
}
