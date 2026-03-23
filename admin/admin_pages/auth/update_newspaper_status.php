<?php

/** Place at: admin/auth/update_newspaper_status.php
 *  Single newspaper approve/reject
 *  POST JSON: { id, action: "approved"|"rejected" }
 */
session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$id = (int)($body['id'] ?? 0);
$action = $body['action'] ?? '';
if ($id <= 0 || !in_array($action, ['approved', 'rejected'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}
try {
    $s = $conn->prepare("UPDATE newspapers SET approval_status=? WHERE newspaper_id=?");
    $s->execute([$action, $id]);
    echo json_encode($s->rowCount() > 0 ? ['success' => true, 'message' => "Newspaper $action."] : ['success' => false, 'message' => 'Not found or already updated.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error.']);
}
