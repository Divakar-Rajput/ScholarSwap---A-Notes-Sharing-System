<?php

/**
 * admin_action.php
 * Place at: admin/auth/admin_action.php
 *
 * Handles single-admin actions.
 *
 * POST JSON:
 *   { "id": 5, "action": "approve" }
 *   { "id": 5, "action": "reject" }
 *   { "id": 5, "action": "warn", "message": "Policy violation…" }
 *   { "id": 5, "action": "change_role", "role": "superadmin" }
 *   { "id": 5, "action": "change_role", "role": "admin" }
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

$selfId  = (int)$_SESSION['admin_id'];

/* Fetch current admin's own role */
$selfQ = $conn->prepare("SELECT role FROM admin_user WHERE admin_id = ? LIMIT 1");
$selfQ->execute([$selfId]);
$selfRow = $selfQ->fetch(PDO::FETCH_ASSOC);
$isSuperAdmin = strtolower($selfRow['role'] ?? '') === 'superadmin';

/* ── Input ── */
$body    = json_decode(file_get_contents('php://input'), true);
$id      = (int)($body['id']      ?? 0);
$action  = trim($body['action']   ?? '');
$role    = trim($body['role']     ?? '');
$warnMsg = trim($body['message']  ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID.']);
    exit;
}

/* ── Prevent acting on self (except warn) ── */
if ($id === $selfId && $action !== 'warn') {
    echo json_encode(['success' => false, 'message' => 'You cannot perform this action on your own account.']);
    exit;
}

/* ── Fetch target admin ── */
$tq = $conn->prepare("SELECT admin_id, username, status, role FROM admin_user WHERE admin_id = ? LIMIT 1");
$tq->execute([$id]);
$target = $tq->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Admin account not found.']);
    exit;
}

try {
    switch ($action) {

        /* ────────────────────────────────────── */
        case 'approve':
            $conn->prepare("UPDATE admin_user SET status = 'approved' WHERE admin_id = ?")
                ->execute([$id]);
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($target['username']) . ' has been approved and can now log in.',
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'reject':
            $conn->prepare("UPDATE admin_user SET status = 'rejected' WHERE admin_id = ?")
                ->execute([$id]);
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($target['username']) . ' has been rejected.',
            ]);
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
                "INSERT INTO admin_warnings (admin_id, issued_by, subject, message)
                 VALUES (:admin_id, :issued_by, :subject, :message)"
            );
            $ins->execute([
                ':admin_id'  => $id,
                ':issued_by' => $selfId,
                ':subject'   => $warnSubject,
                ':message'   => $warnMsg,
            ]);
            $warnId = (int)$conn->lastInsertId();

            if ($warnId === 0) {
                echo json_encode(['success' => false, 'message' => 'Warning could not be saved. Check database.']);
                exit;
            }

            echo json_encode([
                'success'    => true,
                'warning_id' => $warnId,
                'message'    => 'Warning has been sent to ' . htmlspecialchars($target['username']) . '.',
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'change_role':
            /* Only superadmins can change roles */
            if (!$isSuperAdmin) {
                echo json_encode(['success' => false, 'message' => 'Only Super Admins can change roles.']);
                exit;
            }

            $allowed = ['admin', 'superadmin'];
            if (!in_array($role, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
                exit;
            }

            $conn->prepare("UPDATE admin_user SET role = ? WHERE admin_id = ?")
                ->execute([$role, $id]);

            $label = $role === 'superadmin' ? 'Super Admin' : 'Admin';
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($target['username']) . ' has been set to ' . $label . '.',
            ]);
            break;

        /* ────────────────────────────────────── */
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
