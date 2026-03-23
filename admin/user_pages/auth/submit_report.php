<?php
session_start();
header('Content-Type: application/json');

/* ── Auth check ── */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to report content.']);
    exit();
}

$root = dirname(__DIR__, 3);

include_once $root . '/admin/config/connection.php';
require_once $root . '/admin/encryption.php';
require_once $root . '/admin/admin_pages/auth/notifications.php';

$reporterId = (int)$_SESSION['user_id'];

/* ── Parse JSON body ── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

/* ── Decrypt inputs ── */
$resourceId   = trim(decryptId($data['resource_id']   ?? ''));
$documentType = trim(decryptId($data['document_type'] ?? ''));
$reason       = isset($data['reason'])  ? trim($data['reason'])                   : '';
$details      = isset($data['details']) ? trim(substr($data['details'], 0, 1000)) : '';

/* ── Allowed values ── */
$allowedTypes = ['note', 'book', 'newspaper'];
$allowedReasons = [
    'spam',
    'inappropriate_content',
    'copyright_violation',
    'misleading_information',
    'wrong_category',
    'other'
];

if (empty($resourceId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid resource.']);
    exit();
}
if (!in_array($documentType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid content type.']);
    exit();
}
if (!in_array($reason, $allowedReasons)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid reason.']);
    exit();
}

/* ── Check reporter is not banned ── */
$banChk = $conn->prepare('SELECT is_active FROM users WHERE user_id = :id LIMIT 1');
$banChk->execute([':id' => $reporterId]);
$banRow = $banChk->fetch(PDO::FETCH_ASSOC);
if (!$banRow || (int)$banRow['is_active'] === 0) {
    echo json_encode(['success' => false, 'message' => 'Your account is restricted.']);
    exit();
}

/* ── Check resource exists + fetch owner user_id and title ── */
$ownerId       = null;
$resourceTitle = '';

try {
    if ($documentType === 'note') {
        $resChk = $conn->prepare('SELECT n_code, user_id, title FROM notes WHERE n_code = :id LIMIT 1');
    } elseif ($documentType === 'book') {
        $resChk = $conn->prepare('SELECT b_code, user_id, title FROM books WHERE b_code = :id LIMIT 1');
    } else {
        // newspapers are uploaded by admins — no user notification needed
        $resChk = $conn->prepare('SELECT n_code FROM newspapers WHERE n_code = :id LIMIT 1');
    }

    $resChk->execute([':id' => $resourceId]);
    $resRow = $resChk->fetch(PDO::FETCH_ASSOC);

    if (!$resRow) {
        echo json_encode(['success' => false, 'message' => 'Resource not found.']);
        exit();
    }

    $ownerId       = isset($resRow['user_id']) ? (int)$resRow['user_id'] : null;
    $resourceTitle = $resRow['title'] ?? '';

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Could not verify resource.']);
    exit();
}

/* ── Check for duplicate report ── */
try {
    $dupChk = $conn->prepare(
        'SELECT report_id FROM reports
         WHERE reporter_id = :rid AND resource_id = :resid AND document_type = :dtype
         LIMIT 1'
    );
    $dupChk->execute([
        ':rid'   => $reporterId,
        ':resid' => $resourceId,
        ':dtype' => $documentType,
    ]);
    if ($dupChk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this content.']);
        exit();
    }
} catch (PDOException $e) {
    /* reports table missing — fall through */
}

/* ── Insert report ── */
try {
    $ins = $conn->prepare(
        'INSERT INTO reports
            (reporter_id, resource_id, document_type, reason, details, status, created_at)
         VALUES
            (:reporter_id, :resource_id, :document_type, :reason, :details, \'pending\', NOW())'
    );
    $ins->execute([
        ':reporter_id'   => $reporterId,
        ':resource_id'   => $resourceId,
        ':document_type' => $documentType,
        ':reason'        => $reason,
        ':details'       => $details ?: null,
    ]);

    /* ── Notify content owner (notes and books only — newspapers are admin-owned) ── */
    if ($ownerId && $ownerId !== $reporterId) {

        // Human-readable reason label
        $reasonLabels = [
            'spam'                   => 'Spam',
            'inappropriate_content'  => 'Inappropriate Content',
            'copyright_violation'    => 'Copyright Violation',
            'misleading_information' => 'Misleading Information',
            'wrong_category'         => 'Wrong Category',
            'other'                  => 'Other',
        ];
        $reasonLabel = $reasonLabels[$reason] ?? ucfirst(str_replace('_', ' ', $reason));

        _insertNotification(
            userId:        $ownerId,
            type:          'admin_message',
            title:         'Your ' . ucfirst($documentType) . ' has been reported',
            message:       "Your {$documentType} \"{$resourceTitle}\" has been reported for: {$reasonLabel}. Our team will review it shortly.",
            resourceType:  $documentType,
            resourceId:    $resourceId,
            resourceTitle: $resourceTitle
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully. Our team will review it shortly.',
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'You have already reported this content.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not save report. Please try again.']);
    }
}