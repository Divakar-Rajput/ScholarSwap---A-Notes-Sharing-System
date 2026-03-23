<?php
session_start();
require_once "../config/connection.php";

// ── Auth check ──
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../admin_login.php");
    exit;
}

// ── Helper: redirect shortcuts ──
function redirectFail(string $msg): never
{
    header("Location: http://localhost/ScholarSwap/admin/admin_pages/dashboard.php?s=failed&msg=" . urlencode($msg));
    exit;
}
function redirectOk(string $msg): never
{
    header("Location: http://localhost/ScholarSwap/admin/admin_pages/dashboard.php?s=success&msg=" . urlencode($msg));
    exit;
}

// ── Input validation ──
$status     = trim($_GET['s'] ?? '');
$documentId = (int)($_GET['r'] ?? 0);
$docType    = strtolower(trim($_GET['t'] ?? ''));

$allowedStatuses = ['approved', 'rejected', 'pending'];
$allowedTypes    = ['note', 'notes', 'book', 'books'];

if (!in_array($status, $allowedStatuses, true)) {
    redirectFail('Invalid status.');
}
if ($documentId <= 0 || !in_array($docType, $allowedTypes, true)) {
    redirectFail('Invalid document.');
}

$adminId = (int)$_SESSION['admin_id'];
$date    = date('Y-m-d H:i:s');

// ── Normalise doctype to singular canonical form ──
// 'notes' → 'note',  'books' → 'book'
// This canonical form is what gets passed to notification helpers
// and stored as resource_type in the notifications table.
$canonicalType = in_array($docType, ['note', 'notes']) ? 'note' : 'book';
$table         = $canonicalType === 'note' ? 'notes' : 'books';
$idCol         = $canonicalType === 'note' ? 'note_id' : 'book_id';

// ── Update approval_status ──
$stmt = $conn->prepare("
    UPDATE `{$table}`
    SET    approval_status = :s,
           approved_by     = :a,
           approved_at     = :d
    WHERE  `{$idCol}`      = :id
");

$success = $stmt->execute([
    ':s'  => $status,
    ':a'  => $adminId,
    ':d'  => $date,
    ':id' => $documentId,
]);

// If the UPDATE itself failed or touched 0 rows, redirect immediately
if (!$success || $stmt->rowCount() === 0) {
    redirectFail('Update failed or record not found.');
}

// ── Notifications ──
// Only send when moving to approved or rejected — not when reverting to pending.
// Wrapped entirely in try/catch so a notification error never breaks the redirect.
if (in_array($status, ['approved', 'rejected'], true)) {
    try {
        require_once __DIR__ . '/notifications.php';

        // Fetch uploader info + resource code + title in a single query
        if ($canonicalType === 'note') {
            $infoStmt = $conn->prepare("
                SELECT
                    n.user_id,
                    n.n_code   AS resource_code,
                    n.title    AS resource_title,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                        NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                        u.username
                    ) AS uploader_name
                FROM notes n
                JOIN users u         ON u.user_id = n.user_id
                LEFT JOIN students s ON s.user_id = n.user_id
                LEFT JOIN tutors   t ON t.user_id = n.user_id
                WHERE n.note_id = ?
                LIMIT 1
            ");
        } else {
            $infoStmt = $conn->prepare("
                SELECT
                    b.user_id,
                    b.b_code   AS resource_code,
                    b.title    AS resource_title,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(s.first_name,' ',s.last_name)),''),
                        NULLIF(TRIM(CONCAT(t.first_name,' ',t.last_name)),''),
                        u.username
                    ) AS uploader_name
                FROM books b
                JOIN users u         ON u.user_id = b.user_id
                LEFT JOIN students s ON s.user_id = b.user_id
                LEFT JOIN tutors   t ON t.user_id = b.user_id
                WHERE b.book_id = ?
                LIMIT 1
            ");
        }

        $infoStmt->execute([$documentId]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            // Row exists (update succeeded) but JOIN returned nothing — log and move on
            error_log("[change_status] Could not fetch uploader info — {$canonicalType} id={$documentId}");
        } else {
            $uploaderId    = (int)   $info['user_id'];
            $resourceCode  = (string)$info['resource_code'];
            $resourceTitle = (string)$info['resource_title'];
            $uploaderName  = (string)($info['uploader_name'] ?: 'A user');

            if ($status === 'approved') {

                // 1. Tell the uploader their content is now live
                notifyUploadApproved(
                    userId:        $uploaderId,
                    resourceType:  $canonicalType,
                    resourceId:    $resourceCode,
                    resourceTitle: $resourceTitle
                );

                // 2. Tell all followers of the uploader about the new content
                notifyAllFollowers(
                    uploaderId:    $uploaderId,
                    uploaderName:  $uploaderName,
                    resourceType:  $canonicalType,
                    resourceId:    $resourceCode,
                    resourceTitle: $resourceTitle
                );

            } else {
                // status === 'rejected'
                // Only the uploader is notified — content is not live,
                // so followers have nothing to see.
                notifyUploadRejected(
                    userId:        $uploaderId,
                    resourceType:  $canonicalType,
                    resourceId:    $resourceCode,
                    resourceTitle: $resourceTitle,
                    reason:        'Your content did not meet our community guidelines. Please review and re-upload.'
                );
            }
        }

    } catch (Throwable $e) {
        // Log silently — the status update already succeeded, don't block the redirect
        error_log("[change_status] Notification error — {$canonicalType} id={$documentId}: " . $e->getMessage());
    }
}

// ── Redirect to dashboard ──
redirectOk(ucfirst($canonicalType) . ' marked as ' . $status . '.');