<?php

/**
 * delete_admin.php
 * Place at: admin/auth/delete_admin.php
 *
 * POST JSON: { "id": 5 }
 * Returns:   { success, message }
 */
session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
    exit;
}

// Prevent self-deletion
if ($id === (int)$_SESSION['admin_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM admin_user WHERE admin_id=?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Admin deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Admin not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
