<?php

/**
 * cancel_material_request.php
 * ─────────────────────────────────────────────────────────────
 * POST — marks a Pending or In Progress request as Cancelled.
 * Only the owner can cancel their own request.
 *
 * Place at:  admin/user_pages/auth/cancel_material_request.php
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

$base = dirname(__DIR__, 3);
include_once $base . '/admin/config/connection.php';

$uid        = (int)$_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);

if ($request_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

try {
    /* Only allow cancellation if the request belongs to this user
       AND is still in a cancellable state */
    $check = $conn->prepare("
        SELECT request_id, status FROM material_requests
        WHERE request_id = ? AND user_id = ? LIMIT 1
    ");
    $check->execute([$request_id, $uid]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    if (!in_array($row['status'], ['Pending', 'In Progress'], true)) {
        echo json_encode(['success' => false, 'message' => 'This request can no longer be cancelled.']);
        exit;
    }

    $upd = $conn->prepare("
        UPDATE material_requests
        SET status = 'Cancelled', updated_at = NOW()
        WHERE request_id = ? AND user_id = ?
    ");
    $upd->execute([$request_id, $uid]);

    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
