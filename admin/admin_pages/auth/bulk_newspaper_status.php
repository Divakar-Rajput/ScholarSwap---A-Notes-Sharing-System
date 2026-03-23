<?php

/** Place at: admin/auth/bulk_newspaper_status.php
 *  POST JSON: { ids: [1,2,3], action: "approved"|"rejected" }
 */
session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$ids = array_filter(array_map('intval', $body['ids'] ?? []), fn($v) => $v > 0);
$action = $body['action'] ?? '';
if (empty($ids) || !in_array($action, ['approved', 'rejected'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}
$updated = $failed = 0;
foreach ($ids as $id) {
    try {
        $s = $conn->prepare("UPDATE newspapers SET approval_status=? WHERE newspaper_id=?");
        $s->execute([$action, $id]);
        $updated += $s->rowCount();
    } catch (PDOException $e) {
        $failed++;
    }
}
echo json_encode($updated > 0 ? ['success' => true, 'message' => "$updated newspaper(s) $action." . ($failed ? " ($failed failed)" : ''), 'updated' => $updated] : ['success' => false, 'message' => 'No items updated.']);
