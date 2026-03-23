<?php

/**
 * bulk_user_action.php
 * Place at: admin/auth/bulk_user_action.php
 *
 * Handles bulk actions on multiple users (students or tutors).
 *
 * POST JSON:
 *   { "ids": [1, 2, 3], "action": "activate" }
 *   { "ids": [1, 2, 3], "action": "deactivate" }
 *   { "ids": [1, 2, 3], "action": "verify" }
 *   { "ids": [1, 2, 3], "action": "ban" }
 *
 * Returns: { success: bool, message: string, affected: int }
 */

session_start();
require_once "../config/connection.php";
header('Content-Type: application/json');

/* ── Auth ── */
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$ids    = $body['ids']    ?? [];
$action = trim($body['action'] ?? '');

/* ── Validate ── */
if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No users selected.']);
    exit;
}

/* Sanitise: keep only positive integers */
$ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid user IDs provided.']);
    exit;
}

/* Build safe IN clause — never interpolate raw input */
$placeholders = implode(',', array_fill(0, count($ids), '?'));

/* ── Execute ── */
try {
    switch ($action) {

        /* ────────────────────────────────────── */
        case 'activate':
            $stmt = $conn->prepare(
                "UPDATE users SET is_active = 1 WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n user(s) activated successfully.",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'deactivate':
            $stmt = $conn->prepare(
                "UPDATE users SET is_active = 0 WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n user(s) deactivated.",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'verify':
            $stmt = $conn->prepare(
                "UPDATE users SET is_verified = 1 WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n user(s) verified successfully.",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'ban':
            $stmt = $conn->prepare(
                "UPDATE users SET is_active = 0 WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n user(s) banned successfully.",
            ]);
            break;

        /* ────────────────────────────────────── */
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action: ' . htmlspecialchars($action),
            ]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
