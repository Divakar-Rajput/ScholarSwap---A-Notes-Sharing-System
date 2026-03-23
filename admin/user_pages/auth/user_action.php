<?php

/**
 * user_action.php
 * Place at: admin/auth/user_action.php
 *
 * Handles single-user actions for students and tutors.
 *
 * POST JSON:
 *   { "id": 12, "action": "activate" }
 *   { "id": 12, "action": "deactivate" }
 *   { "id": 12, "action": "verify" }
 *   { "id": 12, "action": "ban" }
 *   { "id": 12, "action": "warn", "subject": "Content Violation", "message": "Your content violated our guidelines." }
 *
 * Returns: { success: bool, message: string }
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
$id     = (int)($body['id']     ?? 0);
$action = trim($body['action']  ?? '');
$warnMsg = trim($body['message'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

/* ── Verify user exists in users table ── */
$check = $conn->prepare("SELECT user_id, username, email FROM users WHERE user_id = ? LIMIT 1");
$check->execute([$id]);
$user  = $check->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

try {
    switch ($action) {

        /* ────────────────────────────────────── */
        case 'activate':
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User has been activated successfully.']);
            break;

        /* ────────────────────────────────────── */
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User has been deactivated.']);
            break;

        /* ────────────────────────────────────── */
        case 'verify':
            $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User has been marked as verified.']);
            break;

        /* ────────────────────────────────────── */
        case 'ban':
            /* Ban = deactivate + mark unverified so they cannot re-login */
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User has been banned and can no longer access the platform.']);
            break;

        /* ────────────────────────────────────── */
        case 'warn':
            $warnSubject = trim($body['subject'] ?? 'Policy Violation');
            if (empty($warnMsg)) {
                echo json_encode(['success' => false, 'message' => 'Warning message cannot be empty.']);
                exit;
            }
            if (empty($warnSubject)) $warnSubject = 'Policy Violation';

            $ins = $conn->prepare(
                "INSERT INTO user_warnings (user_id, admin_id, subject, message)
                  VALUES (:user_id, :admin_id, :subject, :message)"
            );
            $ins->execute([
                ':user_id'  => $id,
                ':admin_id' => (int)$_SESSION['admin_id'],
                ':subject'  => $warnSubject,
                ':message'  => $warnMsg,
            ]);
            $warnId = (int)$conn->lastInsertId();

            if ($warnId === 0) {
                echo json_encode(['success' => false, 'message' => 'Warning could not be saved. Check database.']);
                exit;
            }

            echo json_encode([
                'success'    => true,
                'warning_id' => $warnId,
                'message'    => 'Warning has been sent to ' . htmlspecialchars($user['username']) . '.',
            ]);
            break;

        /* ────────────────────────────────────── */
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
