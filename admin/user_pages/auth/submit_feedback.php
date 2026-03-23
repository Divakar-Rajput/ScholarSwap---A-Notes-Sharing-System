<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit feedback.']);
    exit;
}

include_once __DIR__ . '/../../config/connection.php';

$userId   = (int)$_SESSION['user_id'];
$category = trim($_POST['category'] ?? 'general');
$subject  = trim($_POST['subject']  ?? '');
$message  = trim($_POST['message']  ?? '');
$rating   = (int)($_POST['rating']  ?? 0);

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'Subject and message are required.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid rating.']);
    exit;
}

// Rate limit: 1 feedback per 24h per user
$check = $conn->prepare("SELECT COUNT(*) FROM feedback WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$check->execute([$userId]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You can only submit one feedback per 24 hours.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO feedback (user_id, category, subject, message, rating, page_context, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'homepage', 'pending', NOW())
    ");
    $stmt->execute([$userId, $category, $subject, $message, $rating]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}