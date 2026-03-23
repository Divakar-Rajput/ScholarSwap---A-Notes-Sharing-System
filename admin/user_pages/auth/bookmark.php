<?php
session_start();
include_once('../../config/connection.php');
include_once('../../encryption.php');

$userID = $_SESSION['user_id'] ?? 0;
$type   = decryptId($_GET['t'] ?? '') ?? '';
$id     = decryptId($_GET['r'] ?? '') ?? '';

$eId     = $_GET['r'] ?? '';
$euserId = $_GET['u'] ?? '';
$etype   = $_GET['t'] ?? '';

/* Detect AJAX — notes_reader.php sends X-Requested-With: XMLHttpRequest */
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    header('Content-Type: application/json');
}

if (!$userID || !$type || !$id) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please log in.']);
    } else {
        header("Location: ../../../notes_reader?r=$eId&u=$euserId&t=$etype");
    }
    exit;
}

try {
    /* Check if bookmark already exists */
    $check = $conn->prepare("
        SELECT bookmark_id FROM bookmarks 
        WHERE user_id = :u AND document_type = :t AND document_id = :d
        LIMIT 1
    ");
    $check->execute([':u' => $userID, ':t' => $type, ':d' => $id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        /* Already bookmarked → remove it (toggle off) */
        $conn->prepare("DELETE FROM bookmarks WHERE bookmark_id = :bid")
            ->execute([':bid' => $existing['bookmark_id']]);

        if ($isAjax) {
            echo json_encode([
                'success' => true,
                'action'  => 'removed',
                'message' => 'Removed from bookmarks.',
            ]);
            exit;
        }
    } else {
        /* Not bookmarked → add it */
        $conn->prepare("
            INSERT INTO bookmarks (user_id, document_type, document_id)
            VALUES (:u, :t, :d)
        ")->execute([':u' => $userID, ':t' => $type, ':d' => $id]);

        if ($isAjax) {
            echo json_encode([
                'success' => true,
                'action'  => 'saved',
                'message' => 'Saved to your bookmarks!',
            ]);
            exit;
        }
    }
} catch (PDOException $e) {
    error_log('[ScholarSwap] bookmark error: ' . $e->getMessage());

    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit;
    }
}

/* Non-AJAX fallback — redirect as before */
header("Location: http://localhost/ScholarSwap/notes_reader?r=$eId&u=$euserId&t=$etype&s=bookmarked");
exit;
