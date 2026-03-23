<?php
/** Place at: admin/auth/delete_book.php */

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
    error_log('[delete_book] Could not load notifications.php: ' . $e->getMessage());
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

// Hard cap — prevent accidental bulk deletions
if (count($ids) > 100) {
    echo json_encode(['success' => false, 'message' => 'Too many IDs. Maximum 100 per request.']);
    exit;
}

// ── Process each book ──
$deleted = 0;
$failed  = 0;

foreach ($ids as $id) {
    try {
        // Fetch file paths + uploader info + resource identifiers in ONE query
        // before deletion so we still have everything we need for notifications
        // and file cleanup even after the row is gone.
        $q = $conn->prepare("
            SELECT
                b.file_path,
                b.cover_image,
                b.user_id,
                b.b_code,
                b.title,
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
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Book doesn't exist — not a hard failure, just skip
            continue;
        }

        // ── Delete physical files ──
        foreach (['file_path', 'cover_image'] as $col) {
            if (!empty($row[$col])) {
                $absPath = __DIR__ . '/../../' . ltrim($row[$col], './');
                if (file_exists($absPath)) {
                    @unlink($absPath);
                }
            }
        }

        // ── Delete DB row ──
        $s = $conn->prepare("DELETE FROM books WHERE book_id = ?");
        $s->execute([$id]);
        $rows = $s->rowCount();

        if ($rows === 0) {
            // Already deleted between SELECT and DELETE (race condition)
            continue;
        }

        $deleted += $rows;

        // ── Notify uploader that their book was removed ──
        // Uses 'banned_content' type — the closest semantic match for
        // admin-initiated forced removal. Notification fires AFTER delete
        // succeeds so we never notify about a deletion that didn't happen.
        if ($notifEnabled) {
            try {
                notifyBannedContent(
                    userId:        (int)   $row['user_id'],
                    resourceType:  'book',
                    resourceId:    (string)$row['b_code'],
                    resourceTitle: (string)$row['title'],
                    reason:        'Your book was removed by an administrator for violating our content policy.'
                );
            } catch (Throwable $e) {
                // Notification failure must never roll back a successful delete
                error_log("[delete_book] Notification error for book_id={$id}: " . $e->getMessage());
            }
        }

    } catch (PDOException $e) {
        $failed++;
        error_log("[delete_book] DB error for book_id={$id}: " . $e->getMessage());
    }
}

// ── Response ──
if ($deleted > 0) {
    echo json_encode([
        'success' => true,
        'message' => "{$deleted} book(s) deleted." . ($failed > 0 ? " ({$failed} failed)" : ''),
        'deleted' => $deleted,
        'failed'  => $failed,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $failed > 0 ? "All {$failed} failed." : 'Book not found or already deleted.',
        'deleted' => 0,
        'failed'  => $failed,
    ]);
}