<?php

/**
 * feature_note.php
 * Place at: admin/auth/feature_note.php
 *
 * POST JSON: { "id": 5 }
 * Returns:   { success, message }
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
$notifEnabled = false;
try {
    require_once __DIR__ . '/notifications.php';
    $notifEnabled = true;
} catch (Throwable $e) {
    error_log('[feature_note] Could not load notifications.php: ' . $e->getMessage());
}

// ── Parse body ──
$body = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid note ID.']);
    exit;
}

try {
    // Fetch note + uploader info in one query
    $check = $conn->prepare("
        SELECT
            n.title,
            n.is_featured,
            n.approval_status,
            n.user_id,
            n.n_code,
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
    $check->execute([$id]);
    $note = $check->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Note not found.']);
        exit;
    }
    if ($note['approval_status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved notes can be featured.']);
        exit;
    }
    if ((int)$note['is_featured'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Note is already featured.']);
        exit;
    }

    // ── Mark as featured ──
    $stmt = $conn->prepare("UPDATE notes SET is_featured = 1 WHERE note_id = ?");
    $stmt->execute([$id]);

    // ── Fetch admin display name ──
    $adminId = (int)$_SESSION['admin_id'];
    $ast = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) FROM admin_user WHERE admin_id = ? LIMIT 1");
    $ast->execute([$adminId]);
    $adminName = trim($ast->fetchColumn() ?: 'Admin');

    // ── Notify uploader their note is now featured ──
    if ($notifEnabled) {
        try {
            notifyAdminMessage(
                userId: (int)$note['user_id'],
                type: 'admin_message',
                title: '🌟 Your note has been featured!',
                message: "Congratulations! Your note \"{$note['title']}\" has been selected as a featured resource and is now highlighted for all users on ScholarSwap.",
                adminId: $adminId,
                adminName: $adminName
            );
        } catch (Throwable $e) {
            error_log('[feature_note] Notification error for note_id=' . $id . ': ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '"' . htmlspecialchars($note['title']) . '" is now featured.',
    ]);
} catch (PDOException $e) {
    error_log('[feature_note] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
}
