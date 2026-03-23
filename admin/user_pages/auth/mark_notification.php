<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include_once __DIR__ . '/../../config/connection.php';

$action  = $_POST['action'] ?? '';
$userId  = (int)$_SESSION['user_id'];

try {
    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        if (!$notifId) { echo json_encode(['success' => false]); exit; }
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notif_id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}