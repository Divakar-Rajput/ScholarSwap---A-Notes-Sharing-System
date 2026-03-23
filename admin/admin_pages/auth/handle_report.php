<?php
// auth/handle_report.php
session_start();
require_once "../config/connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$sa = $conn->prepare("SELECT first_name, last_name FROM admin_user WHERE admin_id=? LIMIT 1");
$sa->execute([$adminId]);
$adm = $sa->fetch(PDO::FETCH_ASSOC);
$adminName = trim(($adm['first_name'] ?? '') . ' ' . ($adm['last_name'] ?? '')) ?: 'Admin';

$action = trim($_POST['action'] ?? '');

// ── Notification helper ───────────────────────────────────────
function insertNotif(
    $conn,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?string $resType = null,
    ?string $resId = null,
    ?string $resTitle = null,
    ?int $fromAdminId = null,
    ?string $fromName = null
): bool {
    $valid = ['warning', 'admin_message', 'upload_approved', 'upload_rejected', 'new_upload', 'banned_content'];
    if (!in_array($type, $valid, true)) $type = 'admin_message';
    if (trim($title) === '' || trim($message) === '') return false;
    $st = $conn->prepare("
        INSERT INTO notifications
            (user_id,type,title,message,resource_type,resource_id,resource_title,from_user_id,from_name,is_read,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,0,NOW())
    ");
    return $st->execute([$userId, $type, $title, $message, $resType, $resId, $resTitle, $fromAdminId, $fromName]);
}

// Map our notif_type values to valid DB enum
function resolveNotifType(string $type): string
{
    return match ($type) {
        'warning'       => 'warning',
        'admin_message' => 'admin_message',
        default         => 'admin_message',
    };
}

try {

    /* ══════════════════════════════════════════════════════════════
   ACTION: send_report_message
   Send message + optional notification to reporter and/or owner
══════════════════════════════════════════════════════════════ */
    if ($action === 'send_report_message') {
        $reportId    = (int)($_POST['report_id']    ?? 0);
        $rawTargets  = json_decode($_POST['targets'] ?? '[]', true);
        $reporterId  = (int)($_POST['reporter_id']  ?? 0);
        $ownerId     = (int)($_POST['owner_id']     ?? 0);
        $notifType   = resolveNotifType(trim($_POST['notif_type'] ?? 'admin_message'));
        $title       = trim($_POST['title']         ?? '');
        $message     = trim($_POST['message']       ?? '');
        $sendNotif   = ($_POST['send_notif'] ?? '0') === '1';
        $resourceId  = trim($_POST['resource_id']   ?? '');
        $docType     = trim($_POST['doc_type']      ?? '');
        $contentTitle = trim($_POST['content_title'] ?? '');

        if ($reportId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
            exit;
        }
        if (empty($rawTargets)) {
            echo json_encode(['success' => false, 'message' => 'No targets selected']);
            exit;
        }
        if ($title === '' || $message === '') {
            echo json_encode(['success' => false, 'message' => 'Title and message are required']);
            exit;
        }

        $targets = is_array($rawTargets) ? $rawTargets : [];
        $sent    = 0;
        $errors  = [];

        foreach ($targets as $target) {
            $uid = 0;
            if ($target === 'reporter' && $reporterId > 0) {
                $uid = $reporterId;
            } elseif ($target === 'owner' && $ownerId > 0) {
                $uid = $ownerId;
            }
            if ($uid <= 0) continue;

            if ($sendNotif) {
                $ok = insertNotif(
                    $conn,
                    $uid,
                    $notifType,
                    $title,
                    $message,
                    $docType ?: null,
                    $resourceId ?: null,
                    $contentTitle ?: null,
                    null,           // admin_id is not a users.user_id
                    $adminName
                );
                if ($ok) $sent++;
                else $errors[] = "Failed to notify user #{$uid}";
            } else {
                $sent++;
            }
        }

        if ($sent === 0 && !empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode('; ', $errors)]);
            exit;
        }

        // Log admin action in reports table (mark as 'actioned' only if it was 'pending')
        $conn->prepare("
        UPDATE reports SET status='actioned', reviewed_at=NOW(), reviewed_by=?
        WHERE report_id=? AND status='pending'
    ")->execute([$adminId, $reportId]);

        echo json_encode([
            'success' => true,
            'message' => "Message sent to " . count($targets) . " recipient(s). " .
                ($sendNotif ? "Notifications delivered." : "In-app notifications skipped.")
        ]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: reply_feedback
   Save admin reply to feedback.admin_reply + optionally update status
══════════════════════════════════════════════════════════════ */
    if ($action === 'reply_feedback') {
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        $userId     = (int)($_POST['user_id']     ?? 0);
        $message    = trim($_POST['message']      ?? '');
        $newStatus  = trim($_POST['new_status']   ?? '');

        if ($feedbackId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
            exit;
        }
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Reply message cannot be empty']);
            exit;
        }

        $validStatuses = ['new', 'in_review', 'resolved', 'closed', ''];
        if (!in_array($newStatus, $validStatuses, true)) $newStatus = '';

        // Save reply
        if ($newStatus !== '') {
            $st = $conn->prepare("
            UPDATE feedback
            SET admin_reply=?, replied_at=NOW(), replied_by=?,
                status=?, updated_at=NOW()
            WHERE feedback_id=?
        ");
            $st->execute([$message, $adminId, $newStatus, $feedbackId]);
        } else {
            $st = $conn->prepare("
            UPDATE feedback
            SET admin_reply=?, replied_at=NOW(), replied_by=?,
                status=IF(status='new','in_review',status), updated_at=NOW()
            WHERE feedback_id=?
        ");
            $st->execute([$message, $adminId, $feedbackId]);
        }

        if ($st->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Feedback not found or nothing changed']);
            exit;
        }

        // Also send an in-app notification to the user
        if ($userId > 0) {
            insertNotif(
                $conn,
                $userId,
                'admin_message',
                'Admin replied to your feedback',
                $message,
                null,
                null,
                null,
                null,
                $adminName
            );
        }

        echo json_encode(['success' => true, 'message' => 'Reply saved and user notified.']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: send_feedback_notification
   Send a standalone notification to feedback submitter
══════════════════════════════════════════════════════════════ */
    if ($action === 'send_feedback_notification') {
        $feedbackId  = (int)($_POST['feedback_id']  ?? 0);
        $userId      = (int)($_POST['user_id']      ?? 0);
        $message     = trim($_POST['message']       ?? '');
        $notifType   = resolveNotifType(trim($_POST['notif_type']  ?? 'admin_message'));
        $notifTitle  = trim($_POST['notif_title']   ?? 'Admin Message');
        $newStatus   = trim($_POST['new_status']    ?? '');

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot notify a guest user (no account)']);
            exit;
        }
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }
        if ($notifTitle === '') {
            echo json_encode(['success' => false, 'message' => 'Notification title is required']);
            exit;
        }

        $ok = insertNotif($conn, $userId, $notifType, $notifTitle, $message, null, null, null, null, $adminName);
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Failed to insert notification']);
            exit;
        }

        // Update feedback status if requested
        if ($newStatus !== '' && in_array($newStatus, ['in_review', 'resolved', 'closed'], true)) {
            $conn->prepare("UPDATE feedback SET status=?, updated_at=NOW() WHERE feedback_id=?")
                ->execute([$newStatus, $feedbackId]);
        }

        echo json_encode(['success' => true, 'message' => 'Notification sent successfully.']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: ban
   Ban reported content (note/book/newspaper)
══════════════════════════════════════════════════════════════ */
    if ($action === 'ban') {
        $reportId   = (int)($_POST['report_id']   ?? 0);
        $docType    = trim($_POST['doc_type']     ?? '');
        $resourceId = trim($_POST['resource_id']  ?? '');

        if ($reportId <= 0 || $resourceId === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        if (!in_array($docType, ['note', 'book', 'newspaper'], true)) {
            echo json_encode(['success' => false, 'message' => 'Unknown document type']);
            exit;
        }

        // Fetch owner + title before banning
        $info = null;
        if ($docType === 'note') {
            $q = $conn->prepare("SELECT user_id, title FROM notes WHERE n_code=? LIMIT 1");
            $q->execute([$resourceId]);
            $info = $q->fetch(PDO::FETCH_ASSOC);
            if ($info) $conn->prepare("UPDATE notes SET approval_status='rejected' WHERE n_code=?")->execute([$resourceId]);
        } elseif ($docType === 'book') {
            $q = $conn->prepare("SELECT user_id, title FROM books WHERE b_code=? LIMIT 1");
            $q->execute([$resourceId]);
            $info = $q->fetch(PDO::FETCH_ASSOC);
            if ($info) $conn->prepare("UPDATE books SET approval_status='rejected' WHERE b_code=?")->execute([$resourceId]);
        } elseif ($docType === 'newspaper') {
            $q = $conn->prepare("SELECT admin_id AS user_id, title FROM newspapers WHERE n_code=? LIMIT 1");
            $q->execute([$resourceId]);
            $info = $q->fetch(PDO::FETCH_ASSOC);
            if ($info) $conn->prepare("UPDATE newspapers SET approval_status='rejected' WHERE n_code=?")->execute([$resourceId]);
        }

        // Mark report as actioned
        $conn->prepare("UPDATE reports SET status='actioned', reviewed_at=NOW(), reviewed_by=? WHERE report_id=?")
            ->execute([$adminId, $reportId]);

        // Notify content owner
        if (!empty($info['user_id']) && $docType !== 'newspaper') {
            insertNotif(
                $conn,
                (int)$info['user_id'],
                'banned_content',
                'Your content has been banned',
                "Your {$docType} \"{$info['title']}\" has been banned due to a community report. Please review our content policy.",
                $docType,
                $resourceId,
                $info['title'] ?? null,
                null,
                $adminName
            );
        }

        echo json_encode(['success' => true, 'message' => 'Content banned and owner notified.']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: warn
   Send a warning notification to a user
══════════════════════════════════════════════════════════════ */
    if ($action === 'warn') {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $message = trim($_POST['message']  ?? '');

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Warning message cannot be empty']);
            exit;
        }

        $ok = insertNotif($conn, $userId, 'warning', 'You have received a warning', $message, null, null, null, null, $adminName);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Warning sent.' : 'Failed to send warning.']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: dismiss
   Mark a report as dismissed
══════════════════════════════════════════════════════════════ */
    if ($action === 'dismiss') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $st = $conn->prepare("UPDATE reports SET status='dismissed', reviewed_at=NOW(), reviewed_by=? WHERE report_id=?");
        $st->execute([$adminId, $reportId]);
        echo json_encode(['success' => $st->rowCount() > 0, 'message' => $st->rowCount() > 0 ? 'Report dismissed.' : 'Not found.']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: resolve_feedback
══════════════════════════════════════════════════════════════ */
    if ($action === 'resolve_feedback') {
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        if ($feedbackId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $st = $conn->prepare("UPDATE feedback SET status='resolved', updated_at=NOW() WHERE feedback_id=?");
        $st->execute([$feedbackId]);
        echo json_encode(['success' => $st->rowCount() > 0, 'message' => $st->rowCount() > 0 ? 'Marked as resolved.' : 'Not found.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
} catch (PDOException $e) {
    error_log('[handle_report.php] DB error: ' . $e->getMessage());
    // Show actual error so you can diagnose the column/table issue
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
