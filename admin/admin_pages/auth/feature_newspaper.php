<?php

/** Place at: admin/auth/feature_newspaper.php */
session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}
try {
    $c = $conn->prepare("SELECT title,is_featured,approval_status FROM newspapers WHERE newspaper_id=? LIMIT 1");
    $c->execute([$id]);
    $row = $c->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Newspaper not found.']);
        exit;
    }
    if ($row['approval_status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved newspapers can be featured.']);
        exit;
    }
    if ($row['is_featured']) {
        echo json_encode(['success' => false, 'message' => 'Already featured.']);
        exit;
    }
    $conn->prepare("UPDATE newspapers SET is_featured=1 WHERE newspaper_id=?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => '"' . htmlspecialchars($row['title']) . '" is now featured.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error.']);
}
