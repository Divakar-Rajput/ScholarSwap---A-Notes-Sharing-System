<?php
// auth/mark_notification.php — ADMIN VERSION
// notifications table has a separate admin_id column for admin recipients.

session_start();
require_once "../config/connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

if ($adminId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin session']);
    exit;
}

$action  = trim($_POST['action']   ?? '');
$notifId = (int)($_POST['notif_id'] ?? 0);

try {

    if ($action === 'mark_read') {
        if ($notifId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        $st = $conn->prepare("
            UPDATE notifications
            SET    is_read = 1, read_at = NOW()
            WHERE  notif_id  = ?
              AND  admin_id  = ?
        ");
        $st->execute([$notifId, $adminId]);
        echo json_encode(['success' => true, 'updated' => $st->rowCount()]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $st = $conn->prepare("
            UPDATE notifications
            SET    is_read = 1, read_at = NOW()
            WHERE  admin_id = ?
              AND  is_read  = 0
        ");
        $st->execute([$adminId]);
        echo json_encode(['success' => true, 'updated' => $st->rowCount()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
} catch (PDOException $e) {
    error_log('[mark_notification.php] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
