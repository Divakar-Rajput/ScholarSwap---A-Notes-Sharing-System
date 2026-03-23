<?php

/**
 * handle_request_admin.php
 * ─────────────────────────────────────────────────────────────
 * Admin-only POST handler for material requests management.
 *
 * Actions:
 *   update_request  — update status + admin_note + send notification
 *   quick_status    — update status + optional admin_note only
 *
 * Place at:  admin/auth/handle_request_admin.php
 * ─────────────────────────────────────────────────────────────
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorised.']);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

require_once '../config/connection.php';

$action = trim($_POST['action'] ?? '');

/* ── Helper: sanitise ── */
function p(string $key, int $max = 500): string
{
    return mb_substr(trim(strip_tags($_POST[$key] ?? '')), 0, $max);
}
function pint(string $key): int
{
    return (int)($_POST[$key] ?? 0);
}

/* ══════════════════════════════════════════════════════════════
   ACTION: update_request
   Full update — status + admin_note + optional in-app notification
══════════════════════════════════════════════════════════════ */
if ($action === 'update_request') {

    $requestId  = pint('request_id');
    $userId     = pint('user_id');
    $newStatus  = p('new_status', 40);
    $adminNote  = p('admin_note', 500);
    $notifType  = p('notif_type', 40) ?: 'admin_message';
    $notifTitle = p('notif_title', 150);
    $message    = p('message', 1000);
    $sendNotif  = ($_POST['send_notif'] ?? '') === '1';
    $refCode    = p('ref_code', 20);
    $trackingNum = p('tracking_num', 32);
    $matTitle   = p('mat_title', 300);
    $resUrl     = trim($_POST['res_url']  ?? '');          /* raw — no strip_tags on URLs */
    $resLabel   = mb_substr(trim(strip_tags($_POST['res_label'] ?? '')), 0, 80) ?: 'View Resource';

    /* Sanitise resource URL — only allow http/https */
    if ($resUrl && !preg_match('#^https?://#i', $resUrl)) {
        $resUrl = '';
    }

    if ($requestId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }

    $allowedStatuses = ['Pending', 'In Progress', 'Fulfilled', 'Cannot Fulfil', 'Cancelled'];

    try {
        /* Build UPDATE dynamically — only update what's provided */
        $setParts = ['updated_at = NOW()'];
        $params   = [];

        if ($newStatus && in_array($newStatus, $allowedStatuses, true)) {
            $setParts[] = 'status = ?';
            $params[]   = $newStatus;
            if ($newStatus === 'Fulfilled') {
                $setParts[] = 'fulfilled_by = ?';
                $setParts[] = 'fulfilled_at = NOW()';
                $params[]   = $adminId;
            }
        }

        if ($adminNote !== '') {
            $setParts[] = 'admin_note = ?';
            $params[]   = $adminNote;
        }

        if (count($setParts) > 1) {
            $params[] = $requestId;
            $sql = 'UPDATE material_requests SET ' . implode(', ', $setParts) . ' WHERE request_id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }

        /* Send in-app notification */
        if ($sendNotif && $userId > 0 && $message !== '' && $notifTitle !== '') {
            $notifMsg = $message;
            if ($refCode)     $notifMsg .= "\n\nRef: {$refCode}";
            if ($trackingNum) $notifMsg .= " | Tracking: {$trackingNum}";
            if ($resUrl)      $notifMsg .= "\n\n🔗 {$resLabel}: {$resUrl}";

            $ns = $conn->prepare("
                INSERT INTO notifications
                    (user_id, type, title, message, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $ns->execute([$userId, $notifType, $notifTitle, $notifMsg]);
        }

        $summary = [];
        if ($newStatus)              $summary[] = "Status → {$newStatus}";
        if ($adminNote)              $summary[] = "Admin note saved";
        if ($sendNotif && $message)  $summary[] = "Notification sent" . ($resUrl ? " with resource link" : "");

        echo json_encode([
            'success' => true,
            'message' => implode('. ', $summary) ?: 'Request updated.',
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: quick_status
   Status-only update (from modal footer buttons)
══════════════════════════════════════════════════════════════ */
if ($action === 'quick_status') {

    $requestId = pint('request_id');
    $newStatus = p('new_status', 40);
    $adminNote = p('admin_note', 500);

    $allowedStatuses = ['Pending', 'In Progress', 'Fulfilled', 'Cannot Fulfil', 'Cancelled'];

    if ($requestId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }
    if (!in_array($newStatus, $allowedStatuses, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }

    try {
        $setParts = ['status = ?', 'updated_at = NOW()'];
        $params   = [$newStatus];

        if ($newStatus === 'Fulfilled') {
            $setParts[] = 'fulfilled_by = ?';
            $setParts[] = 'fulfilled_at = NOW()';
            $params[]   = $adminId;
        }

        if ($adminNote !== '') {
            $setParts[] = 'admin_note = ?';
            $params[]   = $adminNote;
        }

        $params[] = $requestId;
        $sql = 'UPDATE material_requests SET ' . implode(', ', $setParts) . ' WHERE request_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        /* Auto-notify student when status changes to key states */
        $autoNotify = [
            'In Progress'    => ['admin_message', 'Your request is now In Progress 🔄', "Your material request is now being actively worked on by our team. We'll update you once we've found it!"],
            'Fulfilled'      => ['success',        'Your Request Has Been Fulfilled! ✅', "Great news! The material you requested is now available. Check ScholarSwap to access it."],
            'Cannot Fulfil'  => ['warning',        'Request Update — Cannot Fulfil',      "We were unfortunately unable to source the material you requested. Please contact us if you need further assistance."],
        ];

        if (isset($autoNotify[$newStatus])) {
            /* Fetch user_id for this request */
            $uq = $conn->prepare('SELECT user_id FROM material_requests WHERE request_id = ? LIMIT 1');
            $uq->execute([$requestId]);
            $uid = (int)$uq->fetchColumn();

            if ($uid > 0) {
                [$nType, $nTitle, $nMsg] = $autoNotify[$newStatus];
                if ($adminNote) $nMsg .= "\n\nNote: {$adminNote}";
                $ns = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $ns->execute([$uid, $nType, $nTitle, $nMsg]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Status updated to {$newStatus}." . ($adminNote ? ' Admin note saved.' : '') . ' Student notified.',
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
