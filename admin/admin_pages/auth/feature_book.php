<?php

/** Place at: admin/auth/feature_book.php */

session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');

// ── Auth ──
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Load notification helper ──
$notifEnabled = false;
try {
    require_once __DIR__ . '/notifications.php';
    $notifEnabled = true;
} catch (Throwable $e) {
    error_log('[feature_book] Could not load notifications.php: ' . $e->getMessage());
}

// ── Parse body ──
$body = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

try {
    // Fetch book + uploader info in one query
    $c = $conn->prepare("
        SELECT
            b.title,
            b.is_featured,
            b.approval_status,
            b.user_id,
            b.b_code,
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
    $c->execute([$id]);
    $row = $c->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Book not found.']);
        exit;
    }
    if ($row['approval_status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved books can be featured.']);
        exit;
    }
    if ((int)$row['is_featured'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Already featured.']);
        exit;
    }

    // ── Mark as featured ──
    $conn->prepare("UPDATE books SET is_featured = 1 WHERE book_id = ?")->execute([$id]);

    // ── Fetch admin display name ──
    $adminId = (int)$_SESSION['admin_id'];
    $ast = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) FROM admin_user WHERE admin_id = ? LIMIT 1");
    $ast->execute([$adminId]);
    $adminName = trim($ast->fetchColumn() ?: 'Admin');

    // ── Notify uploader their book is now featured ──
    if ($notifEnabled) {
        try {
            notifyAdminMessage(
                userId: (int)$row['user_id'],
                type: 'admin_message',
                title: '🌟 Your book has been featured!',
                message: "Congratulations! Your book \"{$row['title']}\" has been selected as a featured resource and is now highlighted for all users on ScholarSwap.",
                adminId: $adminId,
                adminName: $adminName
            );
        } catch (Throwable $e) {
            error_log('[feature_book] Notification error for book_id=' . $id . ': ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '"' . htmlspecialchars($row['title']) . '" is now featured.',
    ]);
} catch (PDOException $e) {
    error_log('[feature_book] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
