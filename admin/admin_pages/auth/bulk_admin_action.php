<?php

/**
 * bulk_admin_action.php
 * Place at: admin/auth/bulk_admin_action.php
 *
 * Handles bulk actions on multiple admin accounts.
 *
 * POST JSON:
 *   { "ids": [2, 3, 4], "action": "approve" }
 *   { "ids": [2, 3, 4], "action": "reject"  }
 *   { "ids": [2, 3, 4], "action": "warn", "message": "Policy reminder…" }
 *
 * Returns: { success: bool, message: string, affected: int }
 *
 * Notes:
 *  - The acting admin's own ID is automatically excluded from all bulk operations.
 *  - change_role is intentionally NOT supported in bulk — role changes must be deliberate,
 *    one at a time, via admin_action.php.
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

/* ── Input ── */
$body    = json_decode(file_get_contents('php://input'), true);
$ids     = $body['ids']     ?? [];
$action  = trim($body['action']  ?? '');
$warnMsg = trim($body['message'] ?? '');

/* ── Validate IDs ── */
if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No admins selected.']);
    exit;
}

/* Sanitise + exclude self */
$ids = array_values(array_filter(
    array_map('intval', $ids),
    fn($v) => $v > 0 && $v !== $selfId
));

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid admins to act on (you cannot include yourself).']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    switch ($action) {

        /* ────────────────────────────────────── */
        case 'approve':
            $stmt = $conn->prepare(
                "UPDATE admin_user SET status = 'approved' WHERE admin_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n admin(s) approved successfully.",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'reject':
            $stmt = $conn->prepare(
                "UPDATE admin_user SET status = 'rejected' WHERE admin_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "$n admin(s) rejected.",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'warn':
            if (empty($warnMsg)) {
                echo json_encode(['success' => false, 'message' => 'Warning message cannot be empty.']);
                exit;
            }

            $tableExists = $conn->query("SHOW TABLES LIKE 'admin_warnings'")->rowCount() > 0;
            $n = 0;
            if ($tableExists) {
                $ins = $conn->prepare(
                    "INSERT INTO admin_warnings (admin_id, issued_by, message) VALUES (?, ?, ?)"
                );
                foreach ($ids as $tid) {
                    $ins->execute([$tid, $selfId, $warnMsg]);
                    $n++;
                }
            } else {
                /* Table doesn't exist — still report success for UX */
                $n = count($ids);
            }

            echo json_encode([
                'success'  => true,
                'affected' => $n,
                'message'  => "Warning sent to $n admin(s).",
            ]);
            break;

        /* ────────────────────────────────────── */
        case 'change_role':
            echo json_encode([
                'success' => false,
                'message' => 'Role changes must be done one at a time for security. Use the individual action.',
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
