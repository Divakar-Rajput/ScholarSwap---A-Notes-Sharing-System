<?php
/**
 * delete_note.php
 * Place at: admin/auth/delete_note.php
 *
 * POST JSON:
 *   Single:  { "id": 5 }
 *   Bulk:    { "ids": [5, 8, 12] }
 *
 * Returns: { success, message, deleted, failed }
 */

session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');

// ── Auth ──
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Load notification helper ──
// Wrapped so a missing file never crashes the delete operation
$notifEnabled = false;
try {
    require_once __DIR__ . '/notifications.php';
    $notifEnabled = true;
} catch (Throwable $e) {
    error_log('[delete_note] Could not load notifications.php: ' . $e->getMessage());
}

// ── Parse JSON body ──
$body = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

// ── Collect & sanitise IDs ──
$ids = [];
if (!empty($body['ids']) && is_array($body['ids'])) {
    $ids = array_values(
        array_filter(array_map('intval', $body['ids']), fn($v) => $v > 0)
    );
} elseif (!empty($body['id'])) {
    $ids = [(int)$body['id']];
}

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid IDs provided.']);
    exit;
}

// Hard cap — prevent accidental mass deletions
if (count($ids) > 100) {
    echo json_encode(['success' => false, 'message' => 'Too many IDs. Maximum 100 per request.']);
    exit;
}

// ── Process each note ──
$deleted = 0;
$failed  = 0;

foreach ($ids as $id) {
    try {
        // Fetch file path + uploader info + resource identifiers in ONE query
        // before deletion — after DELETE the row is gone and can't be retrieved.
        $q = $conn->prepare("
            SELECT
                n.file_path,
                n.user_id,
                n.n_code,
                n.title,
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
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Note doesn't exist — not a hard failure, just skip
            continue;
        }

        // ── Delete physical file ──
        if (!empty($row['file_path'])) {
            $absPath = __DIR__ . '/../../' . ltrim($row['file_path'], './');
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }

        // ── Delete DB row ──
        $stmt = $conn->prepare("DELETE FROM notes WHERE note_id = ?");
        $stmt->execute([$id]);
        $rows = $stmt->rowCount();

        if ($rows === 0) {
            // Already deleted between SELECT and DELETE (race condition)
            continue;
        }

        $deleted += $rows;

        // ── Notify uploader their note was removed ──
        // Fires only after DELETE succeeds so we never notify about a
        // deletion that didn't happen. Uses 'banned_content' — the correct
        // type for admin-forced removal of previously active content.
        if ($notifEnabled) {
            try {
                notifyBannedContent(
                    userId:        (int)   $row['user_id'],
                    resourceType:  'note',
                    resourceId:    (string)$row['n_code'],
                    resourceTitle: (string)$row['title'],
                    reason:        'Your note was removed by an administrator for violating our content policy.'
                );
            } catch (Throwable $e) {
                // Notification failure must never roll back a successful delete
                error_log("[delete_note] Notification error for note_id={$id}: " . $e->getMessage());
            }
        }

    } catch (PDOException $e) {
        $failed++;
        error_log("[delete_note] DB error for note_id={$id}: " . $e->getMessage());
    }
}

// ── Response ──
if ($deleted > 0) {
    echo json_encode([
        'success' => true,
        'message' => "{$deleted} note(s) deleted successfully." . ($failed > 0 ? " ({$failed} failed)" : ''),
        'deleted' => $deleted,
        'failed'  => $failed,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $failed > 0
            ? "All {$failed} note(s) failed to delete."
            : 'Note not found or already deleted.',
        'deleted' => 0,
        'failed'  => $failed,
    ]);
}