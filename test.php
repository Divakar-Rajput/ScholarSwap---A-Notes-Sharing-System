<?php
function generateBookCode(): string
{
    return 'BK' . date('YmdHis') . random_int(100, 999);
}

$contentID = generateBookCode();
?>
require_once __DIR__ . '/../../admin_pages/auth/notifications.php';
        $nameStmt = $conn->prepare("
            SELECT COALESCE(
                NULLIF(TRIM(CONCAT(s.first_name, ' ', s.last_name)), ''),
                NULLIF(TRIM(CONCAT(t.first_name, ' ', t.last_name)), ''),
                u.username
            ) AS display_name
            FROM users u
            LEFT JOIN students s ON s.user_id = u.user_id
            LEFT JOIN tutors   t ON t.user_id = u.user_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $nameStmt->execute([$user_id]);
        $uploaderName = $nameStmt->fetchColumn() ?: 'A user';

        // $contentID IS the b_code — no lastInsertId() lookup needed
        notifyAllFollowers(
            uploaderId:    $user_id,
            uploaderName:  $uploaderName,
            resourceType:  'book',
            resourceId:    $contentID,    // b_code e.g. "BK202506141523004271"
            resourceTitle: $title
        );