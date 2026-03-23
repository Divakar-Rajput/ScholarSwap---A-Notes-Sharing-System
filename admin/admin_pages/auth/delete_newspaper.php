<?php

/** Place at: admin/auth/delete_newspaper.php */
session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$ids = [];
if (!empty($body['ids']) && is_array($body['ids'])) $ids = array_filter(array_map('intval', $body['ids']), fn($v) => $v > 0);
elseif (!empty($body['id'])) $ids = [(int)$body['id']];
if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid IDs.']);
    exit;
}
$deleted = $failed = 0;
foreach ($ids as $id) {
    try {
        $q = $conn->prepare("SELECT file_path FROM newspapers WHERE newspaper_id=? LIMIT 1");
        $q->execute([$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['file_path'])) {
            $p = __DIR__ . '/../../' . ltrim($row['file_path'], './');
            if (file_exists($p)) @unlink($p);
        }
        $s = $conn->prepare("DELETE FROM newspapers WHERE newspaper_id=?");
        $s->execute([$id]);
        $deleted += $s->rowCount();
    } catch (PDOException $e) {
        $failed++;
    }
}
echo json_encode($deleted > 0 ? ['success' => true, 'message' => "$deleted newspaper(s) deleted." . ($failed ? " ($failed failed)" : ''), 'deleted' => $deleted] : ['success' => false, 'message' => $failed ? "All $failed failed." : 'Newspaper not found.', 'deleted' => 0]);
