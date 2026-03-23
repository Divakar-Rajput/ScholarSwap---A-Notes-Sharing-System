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
 *   { "id": 12, "action": "warn", "message": "Your content violated our guidelines." }
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
            if (empty($warnMsg)) {
                echo json_encode(['success' => false, 'message' => 'Warning message cannot be empty.']);
                exit;
            }

            /*
             * Store the warning in a warnings table if it exists,
             * otherwise just acknowledge — swap the INSERT below for your
             * actual notification / warnings table.
             *
             * Expected table (create once):
             * CREATE TABLE IF NOT EXISTS user_warnings (
             *   warning_id  INT AUTO_INCREMENT PRIMARY KEY,
             *   user_id     INT NOT NULL,
             *   admin_id    INT NOT NULL,
             *   message     TEXT NOT NULL,
             *   created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
             * );
             */
            $tableCheck = $conn->query("SHOW TABLES LIKE 'user_warnings'")->rowCount();
            if ($tableCheck > 0) {
                $ins = $conn->prepare(
                    "INSERT INTO user_warnings (user_id, admin_id, message) VALUES (?, ?, ?)"
                );
                $ins->execute([$id, $_SESSION['admin_id'], $warnMsg]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Warning has been sent to ' . htmlspecialchars($user['username']) . '.',
            ]);
            break;

        /* ────────────────────────────────────── */
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
