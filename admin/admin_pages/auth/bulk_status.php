<?php
/**
 * bulk_status.php
 * Place at: admin/auth/bulk_status.php
 *
 * Accepts JSON POST: { items: [{id, doctype}], action: "approved"|"rejected" }
 * Returns JSON: { success, message, updated, failed }
 */
session_start();
require_once "../config/connection.php";

header('Content-Type: application/json');

// ── Auth ──
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Load notification helpers ──
// Wrapped so a missing file never crashes the approval flow
try {
    require_once __DIR__ . '/notifications.php';
    $notifEnabled = true;
} catch (Throwable $e) {
    $notifEnabled = false;
    error_log('[bulk_status] Could not load notifications.php: ' . $e->getMessage());
}

// ── Parse JSON body ──
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$items  = $body['items']  ?? [];
$action = $body['action'] ?? '';

// ── Validate action ──
if (!in_array($action, ['approved', 'rejected'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Must be "approved" or "rejected".']);
    exit;
}

// ── Validate items ──
if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'No items provided.']);
    exit;
}

// Hard cap — prevent accidental bulk operations on thousands of rows
if (count($items) > 200) {
    echo json_encode(['success' => false, 'message' => 'Too many items. Maximum 200 per request.']);
    exit;
}

// ── Fetch admin display name once (used in notifyAllFollowers) ──
$adminId = (int)$_SESSION['admin_id'];
$now     = date('Y-m-d H:i:s');

$adminName = 'Admin';
try {
    $ast = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) FROM admin_user WHERE admin_id = ? LIMIT 1");
    $ast->execute([$adminId]);
    $adminName = trim($ast->fetchColumn() ?: 'Admin');
} catch (Throwable $e) {
    error_log('[bulk_status] Could not fetch admin name: ' . $e->getMessage());
}

// ── Process each item ──
$updated = 0;
$failed  = 0;

foreach ($items as $item) {
    $id      = (int)($item['id']      ?? 0);
    $doctype = trim($item['doctype']  ?? '');

    // Validate this item
    if ($id <= 0 || !in_array($doctype, ['note', 'book'], true)) {
        $failed++;
        continue;
    }

    // ── Step 1: Update approval_status ──
    try {
        if ($doctype === 'note') {
            $stmt = $conn->prepare("
                UPDATE notes
                SET    approval_status = ?,
                       approved_by     = ?,
                       approved_at     = ?
                WHERE  note_id         = ?
                  AND  approval_status = 'pending'
            ");
            $stmt->execute([$action, $adminId, $now, $id]);
        } else {
            $stmt = $conn->prepare("
                UPDATE books
                SET    approval_status = ?,
                       approved_by     = ?,
                       approved_at     = ?
                WHERE  book_id         = ?
                  AND  approval_status = 'pending'
            ");
            $stmt->execute([$action, $adminId, $now, $id]);
        }

        $rows = $stmt->rowCount();

        if ($rows === 0) {
            // Already reviewed or ID doesn't exist — not a failure, just skip
            continue;
        }

        $updated++;

    } catch (PDOException $e) {
        $failed++;
        error_log("[bulk_status] DB update failed — doctype={$doctype}, id={$id}: " . $e->getMessage());
        continue; // skip notification for this item
    }

    // ── Step 2: Notifications (only if update actually changed a row) ──
    if (!$notifEnabled) continue;

    try {
        // Fetch uploader info + resource code + title in one query
        if ($doctype === 'note') {
            $infoStmt = $conn->prepare("
                SELECT
                    n.user_id,
                    n.n_code        AS resource_code,
                    n.title         AS resource_title,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                        NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                        u.username
                    ) AS uploader_name
                FROM notes n
                JOIN users u    ON u.user_id = n.user_id
                LEFT JOIN students s ON s.user_id = n.user_id
                LEFT JOIN tutors   t ON t.user_id = n.user_id
                WHERE n.note_id = ?
                LIMIT 1
            ");
        } else {
            $infoStmt = $conn->prepare("
                SELECT
                    b.user_id,
                    b.b_code        AS resource_code,
                    b.title         AS resource_title,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                        NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                        u.username
                    ) AS uploader_name
                FROM books b
                JOIN users u    ON u.user_id = b.user_id
                LEFT JOIN students s ON s.user_id = b.user_id
                LEFT JOIN tutors   t ON t.user_id = b.user_id
                WHERE b.book_id = ?
                LIMIT 1
            ");
        }

        $infoStmt->execute([$id]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            error_log("[bulk_status] Could not fetch info for notification — doctype={$doctype}, id={$id}");
            continue;
        }

        $uploaderId    = (int)  $info['user_id'];
        $resourceCode  = (string)$info['resource_code'];
        $resourceTitle = (string)$info['resource_title'];
        $uploaderName  = (string)($info['uploader_name'] ?: 'A user');
        $resourceType  = $doctype; // 'note' or 'book'

        if ($action === 'approved') {

            // 1. Notify the uploader their content was approved
            notifyUploadApproved(
                userId:        $uploaderId,
                resourceType:  $resourceType,
                resourceId:    $resourceCode,
                resourceTitle: $resourceTitle
            );

            // 2. Notify all followers of the uploader about the new content
            //    Books AND notes both notify followers — content is now live either way
            notifyAllFollowers(
                uploaderId:    $uploaderId,
                uploaderName:  $uploaderName,
                resourceType:  $resourceType,
                resourceId:    $resourceCode,
                resourceTitle: $resourceTitle
            );

        } else {
            // Rejected — notify only the uploader, not followers
            // Default reason shown — extend this to accept a reason field if needed
            notifyUploadRejected(
                userId:        $uploaderId,
                resourceType:  $resourceType,
                resourceId:    $resourceCode,
                resourceTitle: $resourceTitle,
                reason:        'Your content did not meet our community guidelines. Please review and re-upload.'
            );
        }

    } catch (Throwable $e) {
        // Notification errors must NEVER affect the HTTP response —
        // the status update already succeeded
        error_log("[bulk_status] Notification error — doctype={$doctype}, id={$id}: " . $e->getMessage());
    }
}

// ── Response ──
$label = $action === 'approved' ? 'approved' : 'rejected';

if ($updated > 0) {
    echo json_encode([
        'success' => true,
        'message' => "{$updated} item(s) {$label} successfully."
            . ($failed > 0 ? " ({$failed} failed)" : ''),
        'updated' => $updated,
        'failed'  => $failed,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $failed > 0
            ? "All {$failed} item(s) failed to update."
            : 'No items were updated (they may have already been reviewed).',
        'updated' => 0,
        'failed'  => $failed,
    ]);
}