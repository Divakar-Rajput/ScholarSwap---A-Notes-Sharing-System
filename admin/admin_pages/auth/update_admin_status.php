<?php
// auth/update_admin_status.php
session_start();
require_once "../config/connection.php";

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doerAdminId = (int)$_SESSION['admin_id'];
$meQ = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? LIMIT 1");
$meQ->execute([$doerAdminId]);
$me = $meQ->fetch(PDO::FETCH_ASSOC);
$myRole    = $me['role'] ?? 'admin';
$doerName  = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: 'Admin';
$isSuperAdmin = ($myRole === 'superadmin');

$action = trim($_POST['action'] ?? '');

// ── Role config (level map) ──────────────────────────────────
$roleLevels = [
    'superadmin' => 100,
    'admin'      => 50,
    'moderator'  => 25,
    'viewer'     => 10,
];
$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin'      => 'Admin',
    'moderator'  => 'Moderator',
    'viewer'     => 'Viewer',
];

// ── Notification insert helper ───────────────────────────────
function insertAdminNotif(
    $conn,
    int $adminId,
    string $type,
    string $title,
    string $message,
    ?string $fromName = null
): bool {
    $valid = ['warning', 'admin_message', 'upload_approved', 'upload_rejected', 'new_upload', 'banned_content'];
    if (!in_array($type, $valid, true)) $type = 'admin_message';
    if (trim($title) === '' || trim($message) === '') return false;
    $st = $conn->prepare("
        INSERT INTO notifications
            (admin_id, type, title, message, from_name, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    return $st->execute([$adminId, $type, $title, $message, $fromName]);
}

try {

    /* ══════════════════════════════════════════════════════════════
   ACTION: approve_admin
   Approve a pending admin registration + assign role + notify
══════════════════════════════════════════════════════════════ */
    if ($action === 'approve_admin') {
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admins can approve requests']);
            exit;
        }

        $targetId = (int)($_POST['admin_id'] ?? 0);
        $role     = trim($_POST['role']     ?? 'admin');
        $msg      = trim($_POST['message']  ?? '');

        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
            exit;
        }
        if (!array_key_exists($role, $roleLevels)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }

        // Fetch applicant
        $aq = $conn->prepare("SELECT first_name, last_name, email FROM admin_user WHERE admin_id=? AND status='pending' LIMIT 1");
        $aq->execute([$targetId]);
        $applicant = $aq->fetch(PDO::FETCH_ASSOC);
        if (!$applicant) {
            echo json_encode(['success' => false, 'message' => 'Applicant not found or already processed']);
            exit;
        }

        $targetName = trim($applicant['first_name'] . ' ' . $applicant['last_name']);
        $roleLabel  = $roleLabels[$role] ?? ucfirst($role);

        // Update status + role
        $upd = $conn->prepare("UPDATE admin_user SET status='approved', role=? WHERE admin_id=? AND status='pending'");
        $upd->execute([$role, $targetId]);

        if ($upd->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Update failed — already processed?']);
            exit;
        }

        // Build notification message
        $notifBody = "Your admin registration has been approved! You have been granted " . $roleLabel . " access to ScholarSwap.\n\n";
        if ($msg !== '') $notifBody .= $msg;
        else $notifBody .= "Welcome to the admin team. Please log in to access your dashboard.";

        insertAdminNotif(
            $conn,
            $targetId,
            'admin_message',
            '🎉 Your Admin Access Has Been Approved!',
            $notifBody,
            $doerName
        );

        echo json_encode([
            'success' => true,
            'message' => $targetName . ' has been approved as ' . $roleLabel . '.'
        ]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: reject_admin
══════════════════════════════════════════════════════════════ */
    if ($action === 'reject_admin') {
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admins can reject requests']);
            exit;
        }

        $targetId = (int)($_POST['admin_id'] ?? 0);
        $msg      = trim($_POST['message']   ?? '');
        $reason   = trim($_POST['reason']    ?? '');

        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
            exit;
        }

        $aq = $conn->prepare("SELECT first_name, last_name FROM admin_user WHERE admin_id=? AND status='pending' LIMIT 1");
        $aq->execute([$targetId]);
        $applicant = $aq->fetch(PDO::FETCH_ASSOC);
        if (!$applicant) {
            echo json_encode(['success' => false, 'message' => 'Applicant not found or already processed']);
            exit;
        }

        $targetName = trim($applicant['first_name'] . ' ' . $applicant['last_name']);

        $upd = $conn->prepare("UPDATE admin_user SET status='rejected' WHERE admin_id=? AND status='pending'");
        $upd->execute([$targetId]);

        if ($upd->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
            exit;
        }

        // Notification
        $reasonMap = [
            'does_not_meet_criteria' => 'the application does not meet our criteria',
            'incomplete_information' => 'the application had incomplete information',
            'duplicate_application'  => 'a duplicate application was found',
            'not_affiliated'         => 'the institution affiliation could not be verified',
            'other'                  => 'internal policy reasons',
        ];
        $reasonText = $reasonMap[$reason] ?? 'internal policy reasons';
        $notifBody  = "Unfortunately, your admin registration request has been declined because " . $reasonText . ".";
        if ($msg !== '') $notifBody .= "\n\n" . $msg;
        else $notifBody .= "\n\nYou may re-apply in the future if your situation changes.";

        insertAdminNotif(
            $conn,
            $targetId,
            'admin_message',
            'Admin Registration Request Declined',
            $notifBody,
            $doerName
        );

        echo json_encode([
            'success' => true,
            'message' => $targetName . "'s application has been rejected."
        ]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: change_role
   Promote or demote an admin's role
══════════════════════════════════════════════════════════════ */
    if ($action === 'change_role') {
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admins can change roles']);
            exit;
        }

        $targetId = (int)($_POST['admin_id'] ?? 0);
        $newRole  = trim($_POST['new_role']  ?? '');
        $oldRole  = trim($_POST['old_role']  ?? '');
        $msg      = trim($_POST['message']   ?? '');

        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
            exit;
        }
        if (!array_key_exists($newRole, $roleLevels)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role']);
            exit;
        }
        if ($targetId === $doerAdminId) {
            echo json_encode(['success' => false, 'message' => 'You cannot change your own role']);
            exit;
        }

        $aq = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? AND status='approved' LIMIT 1");
        $aq->execute([$targetId]);
        $target = $aq->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            exit;
        }

        $targetName   = trim($target['first_name'] . ' ' . $target['last_name']);
        $currentRole  = $target['role'];
        $newLabel     = $roleLabels[$newRole] ?? ucfirst($newRole);
        $oldLabel     = $roleLabels[$currentRole] ?? ucfirst($currentRole);

        if ($newRole === $currentRole) {
            echo json_encode(['success' => false, 'message' => 'Role is already ' . $newLabel]);
            exit;
        }

        // Determine promotion or demotion
        $isPromotion = ($roleLevels[$newRole] ?? 0) > ($roleLevels[$currentRole] ?? 0);

        $upd = $conn->prepare("UPDATE admin_user SET role=? WHERE admin_id=?");
        $upd->execute([$newRole, $targetId]);

        // Notification
        $notifTitle = $isPromotion
            ? '🎉 Role Promotion — You\'re now a ' . $newLabel . '!'
            : 'Role Change — Your role has been updated to ' . $newLabel;

        $notifBody = $isPromotion
            ? "Congratulations! Your admin role has been promoted from {$oldLabel} to {$newLabel}.\n\nYou now have " . ($roleLevels[$newRole] > 50 ? 'expanded' : 'updated') . " access permissions."
            : "Your admin role has been changed from {$oldLabel} to {$newLabel}.";

        if ($msg !== '') $notifBody .= "\n\n" . $msg;

        insertAdminNotif($conn, $targetId, 'admin_message', $notifTitle, $notifBody, $doerName);

        echo json_encode([
            'success' => true,
            'message' => $targetName . ' role changed from ' . $oldLabel . ' to ' . $newLabel . '.'
        ]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: revoke_access
══════════════════════════════════════════════════════════════ */
    if ($action === 'revoke_access') {
        if (!$isSuperAdmin) {
            echo json_encode(['success' => false, 'message' => 'Only Super Admins can revoke access']);
            exit;
        }

        $targetId = (int)($_POST['admin_id'] ?? 0);
        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        if ($targetId === $doerAdminId) {
            echo json_encode(['success' => false, 'message' => 'You cannot revoke your own access']);
            exit;
        }

        $aq = $conn->prepare("SELECT first_name, last_name, role FROM admin_user WHERE admin_id=? LIMIT 1");
        $aq->execute([$targetId]);
        $target = $aq->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            exit;
        }

        if (($target['role'] ?? '') === 'superadmin') {
            echo json_encode(['success' => false, 'message' => 'Super Admin accounts cannot be revoked this way']);
            exit;
        }

        $targetName = trim($target['first_name'] . ' ' . $target['last_name']);

        $upd = $conn->prepare("UPDATE admin_user SET status='rejected' WHERE admin_id=?");
        $upd->execute([$targetId]);

        insertAdminNotif(
            $conn,
            $targetId,
            'warning',
            'Admin Access Revoked',
            "Your admin access to ScholarSwap has been revoked by a Super Admin. If you believe this is a mistake, please contact the administration.",
            $doerName
        );

        echo json_encode(['success' => true, 'message' => $targetName . "'s admin access has been revoked."]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════════
   ACTION: send_admin_message
   Send a custom notification to any admin
══════════════════════════════════════════════════════════════ */
    if ($action === 'send_admin_message') {
        $targetId = (int)($_POST['admin_id']   ?? 0);
        $nType    = trim($_POST['notif_type']  ?? 'admin_message');
        $title    = trim($_POST['title']       ?? '');
        $message  = trim($_POST['message']     ?? '');

        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
            exit;
        }
        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit;
        }
        if ($message === '') {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }

        // Map custom types to valid DB enum
        $typeMap = [
            'admin_message' => 'admin_message',
            'warning'       => 'warning',
            'info'          => 'admin_message',   // info maps to admin_message type
            'promo'         => 'admin_message',   // promotion maps to admin_message type
        ];
        $dbType = $typeMap[$nType] ?? 'admin_message';

        $ok = insertAdminNotif($conn, $targetId, $dbType, $title, $message, $doerName);

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Failed to insert notification']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
} catch (PDOException $e) {
    error_log('[update_admin_status.php] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
