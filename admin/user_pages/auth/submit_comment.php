<?php
session_start();
require_once "../../../admin/config/connection.php";
require_once "../../../admin/encryption.php"; // FIX 1: needed for decryptId()
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to comment.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true);
$action = trim($body['action'] ?? '');

try {
    switch ($action) {

        /* ── Post top-level comment / reply ── */
        case 'post':
        case 'reply':
            /* FIX 2: decrypt resource_id and document_type —
               JS sends encrypted $enoteId and $etype, NOT plain integers/strings.
               decryptId() returns the string code e.g. "NT20260313061850433" */
            $resourceId   = trim(decryptId($body['resource_id']   ?? ''));
            $documentType = trim(decryptId($body['document_type'] ?? ''));
            $text         = trim($body['body']                    ?? '');
            $parentId     = $action === 'reply' ? (int)($body['parent_id'] ?? 0) : null;

            if (empty($resourceId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid resource.']);
                exit;
            }
            if (!in_array($documentType, ['note', 'book', 'newspaper'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
                exit;
            }
            if (empty($text)) {
                echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
                exit;
            }
            if (mb_strlen($text) > 2000) {
                echo json_encode(['success' => false, 'message' => 'Comment is too long (max 2000 characters).']);
                exit;
            }
            if ($parentId !== null && $parentId <= 0) $parentId = null;

            /* Verify parent exists if replying */
            if ($parentId !== null) {
                $pc = $conn->prepare("SELECT comment_id FROM comments WHERE comment_id=? AND is_deleted=0 LIMIT 1");
                $pc->execute([$parentId]);
                if (!$pc->fetchColumn()) {
                    echo json_encode(['success' => false, 'message' => 'Parent comment not found.']);
                    exit;
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO comments (resource_id, document_type, user_id, parent_id, body, created_at, updated_at)
                VALUES (:rid, :dt, :uid, :pid, :body, NOW(), NOW())
            ");
            $stmt->execute([
                ':rid'  => $resourceId,
                ':dt'   => $documentType,
                ':uid'  => $userId,
                ':pid'  => $parentId,
                ':body' => $text,
            ]);
            $commentId = (int)$conn->lastInsertId();

            /* Fetch comment with user info for instant UI render */
            $cq = $conn->prepare("
                SELECT c.*,
                       u.username,
                       u.profile_image,
                       u.role AS user_role,
                       COALESCE(
                           NULLIF(CONCAT(s.first_name,' ',s.last_name),' '),
                           NULLIF(CONCAT(t.first_name,' ',t.last_name),' '),
                           u.username
                       ) AS display_name
                FROM comments c
                JOIN users    u ON u.user_id = c.user_id
                LEFT JOIN students s ON s.user_id = c.user_id
                LEFT JOIN tutors   t ON t.user_id = c.user_id
                WHERE c.comment_id = :id
            ");
            $cq->execute([':id' => $commentId]);
            $comment = $cq->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => $action === 'reply' ? 'Reply posted!' : 'Comment posted!',
                'comment' => [
                    'comment_id'    => $comment['comment_id'],
                    'parent_id'     => $comment['parent_id'],
                    'body'          => htmlspecialchars($comment['body']),
                    'display_name'  => htmlspecialchars($comment['display_name']),
                    'username'      => htmlspecialchars($comment['username']),
                    'user_role'     => htmlspecialchars($comment['user_role']),
                    'profile_image' => $comment['profile_image'] ? htmlspecialchars($comment['profile_image']) : '',
                    'user_id'       => (int)$comment['user_id'],
                    'created_at'    => $comment['created_at'],
                    'time_ago'      => 'Just now',
                    'is_own'        => true,
                ],
            ]);
            break;

        /* ── Soft-delete ── */
        case 'delete':
            // FIX 3: comment_id is a plain auto-increment integer (not encrypted)
            // JS sends it directly from PHP's $cm['comment_id'] rendered in HTML,
            // so (int) cast is correct here — no decryption needed.
            $commentId = (int)($body['comment_id'] ?? 0);
            if ($commentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid comment ID.']);
                exit;
            }

            /* Only owner can delete */
            $own = $conn->prepare("SELECT user_id FROM comments WHERE comment_id=? AND is_deleted=0 LIMIT 1");
            $own->execute([$commentId]);
            $ownerRow = $own->fetch(PDO::FETCH_ASSOC);

            if (!$ownerRow) {
                echo json_encode(['success' => false, 'message' => 'Comment not found.']);
                exit;
            }
            if ((int)$ownerRow['user_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'You can only delete your own comments.']);
                exit;
            }

            $del = $conn->prepare("UPDATE comments SET is_deleted=1 WHERE comment_id=?");
            $del->execute([$commentId]);

            echo json_encode(['success' => true, 'message' => 'Comment deleted.', 'comment_id' => $commentId]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (PDOException $e) {
    error_log('[ScholarSwap] submit_comment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
