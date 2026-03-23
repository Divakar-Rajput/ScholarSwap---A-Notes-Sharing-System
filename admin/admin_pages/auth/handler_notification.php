<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include_once __DIR__ . '/../../config/connection.php';
include_once __DIR__ . '/notifications.php';

if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$action  = $_POST['action'] ?? '';
$adminId = (int)$_SESSION['admin_id'];

// ── Fetch admin name ──
$adminStmt = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM admin_user WHERE admin_id = ? LIMIT 1");
$adminStmt->execute([$adminId]);
$adminName = $adminStmt->fetchColumn() ?: 'Admin';

// ── Whitelist of valid notification types (must match DB enum) ──
$validTypes = ['warning', 'admin_message', 'upload_approved', 'upload_rejected', 'new_upload', 'banned_content'];

// ── Helper: insert a notification into the admin_id column (for admin recipients) ──
function insertAdminNotif(PDO $conn, int $adminId, string $type, string $title, string $message, int $fromAdminId, string $fromName): bool {
    try {
        $st = $conn->prepare("
            INSERT INTO notifications (admin_id, type, title, message, from_user_id, from_name, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        return $st->execute([$adminId, $type, $title, $message, $fromAdminId, $fromName]);
    } catch (PDOException $e) {
        error_log('[insertAdminNotif] ' . $e->getMessage());
        return false;
    }
}

// ════════════════════════════════════════════════════════
// SEND CUSTOM MESSAGE
// ════════════════════════════════════════════════════════
if ($action === 'send_message') {

    $title     = trim($_POST['title']      ?? '');
    $message   = trim($_POST['message']    ?? '');
    $recipMode = trim($_POST['recip_mode'] ?? 'single');
    $userType  = trim($_POST['user_type']  ?? 'student');
    $userId    = (int)($_POST['user_id']   ?? 0);

    // BUG FIX 1: Added fallback default so $type is never undefined/empty
    $type = trim($_POST['msg_type'] ?? 'admin_message');

    // BUG FIX 2: Validate type against whitelist — reject invalid values
    if (!in_array($type, $validTypes, true)) {
        $type = 'admin_message'; // safe fallback
    }

    // BUG FIX 3: Validate title AND message before doing anything
    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Title is required.']);
        exit;
    }
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Message is required.']);
        exit;
    }

    try {

        if ($recipMode === 'all') {

            $sent = 0;

            // BUG FIX 4: Use parameterised queries instead of raw query() for safety
            if (in_array($userType, ['all_students', 'everyone'], true)) {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE is_active = 1 AND role = 'student'");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    if (notifyAdminMessage((int)$uid, $type, $title, $message, $adminId, $adminName)) $sent++;
                }
            }

            if (in_array($userType, ['all_tutors', 'everyone'], true)) {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE is_active = 1 AND role = 'tutor'");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    if (notifyAdminMessage((int)$uid, $type, $title, $message, $adminId, $adminName)) $sent++;
                }
            }

            if (in_array($userType, ['all_admins', 'everyone'], true)) {
                $stmt = $conn->prepare("SELECT admin_id FROM admin_user WHERE status = 'approved'");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                    if (insertAdminNotif($conn, (int)$uid, $type, $title, $message, $adminId, $adminName)) $sent++;
                }
            }

            // BUG FIX 5: Warn if nobody was actually notified
            if ($sent === 0) {
                echo json_encode(['success' => false, 'message' => 'No active users found to notify.']);
            } else {
                echo json_encode(['success' => true, 'message' => "Message sent to {$sent} user(s)."]);
            }

        } else {
            // ── SINGLE USER ──

            // BUG FIX 6: Validate userId is positive before any DB query
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a user.']);
                exit;
            }

            // BUG FIX 7: Validate userType — reject unexpected values early
            $validUserTypes = ['student', 'tutor', 'admin'];
            if (!in_array($userType, $validUserTypes, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid user type.']);
                exit;
            }

            // Check user actually exists and is active
            if ($userType === 'admin') {
                $chk = $conn->prepare("SELECT admin_id FROM admin_user WHERE admin_id = ? AND status = 'approved' LIMIT 1");
            } else {
                $chk = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND is_active = 1 LIMIT 1");
            }
            $chk->execute([$userId]);

            if (!$chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'User not found or inactive.']);
                exit;
            }

            $ok = ($userType === 'admin')
                ? insertAdminNotif($conn, $userId, $type, $title, $message, $adminId, $adminName)
                : notifyAdminMessage($userId, $type, $title, $message, $adminId, $adminName);
            echo json_encode(
                $ok
                    ? ['success' => true,  'message' => 'Message sent successfully.']
                    : ['success' => false, 'message' => 'Failed to insert notification. Check notifyAdminMessage().']
            );
        }

    } catch (PDOException $e) {
        // BUG FIX 8: Never expose raw DB error to client in production
        // Log it server-side; return generic message
        error_log('[handler_notification] PDOException: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
    }

// ════════════════════════════════════════════════════════
// DELETE NOTIFICATION
// ════════════════════════════════════════════════════════
} elseif ($action === 'delete') {

    $notifId = (int)($_POST['notif_id'] ?? 0);

    // BUG FIX 9: Validate notifId is positive
    if ($notifId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notif_id = ?");
        $stmt->execute([$notifId]);

        // BUG FIX 10: Check rowCount — if 0 the ID didn't exist
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Notification not found or already deleted.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Notification deleted.']);
        }

    } catch (PDOException $e) {
        error_log('[handler_notification] Delete PDOException: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
    }

// ════════════════════════════════════════════════════════
// UNKNOWN ACTION
// ════════════════════════════════════════════════════════
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown or missing action.']);
}